<?php

include(dirname(__FILE__). '/../../../config/config.inc.php');
include(dirname(__FILE__). '/../../../init.php');
include(dirname(__FILE__). '/../PayBeagle.php');
global $smarty;
global $cart;
PrestaShopLogger::addLog("IPN Recieved", 1);


/* customOrderRef = cart id - without this, its not a valid return */
$cart = new Cart($_POST['customOrderRef']);
if (!Validate::isLoadedObject($cart)){
	exit;
}
PrestaShopLogger::addLog("load Cart", 1);

$customer = new Customer($cart->id_customer);
PrestaShopLogger::addLog("load Customer", 1);

/* make sure all variables are here, if not, someone is trying to hack an order */
if(	!isset($_POST['amount']) ||
	!isset($_POST['currency']) ||
	!isset($_POST['cardName']) ||
	!isset($_POST['cardNumber']) ||
	!isset($_POST['transactionTime']) ||
	!isset($_POST['transactionResult']) ||
	!isset($_POST['transactionAuthCode']) ||
	!isset($_POST['acquirerRef']) ||
	!isset($_POST['acquirerOrderNumber']) ||
	!isset($_POST['acquirerResponseCode']) ||
	!isset($_POST['payBeagleTransactionRef']) ||
	!isset($_POST['customOrderRef']) ||
	!isset($_POST['customOrderDescription']) ||
	!isset($_POST['merchantName']) ||
	!isset($_POST['merchantUserID']) ||
	!isset($_POST['merchantContinueUrl']) ||
	!isset($_POST['merchantErrorUrl']) ||
	!isset($_POST['merchantIpnUrl']) ||
	!isset($_POST['sandbox']) ||
	!isset($_POST['validationToken'])){
	exit;
}
PrestaShopLogger::addLog("All IPN values present", 1);

$paybeagle = new PayBeagle();

/* set IPN URL */
$IPN = $_POST['validationToken'];
$url = 'https://'.$paybeagle->paybeagle_domain.$paybeagle->ipn_validate_url.$IPN;
PrestaShopLogger::addLog($url, 1);
/* set curl params, we just want headers for status code */
PrestaShopLogger::addLog("before curl init", 1);
$ch = curl_init($url);
PrestaShopLogger::addLog("after curl init", 1);
try{
	curl_setopt($ch, CURLOPT_HEADER, true); // we want headers
	curl_setopt($ch, CURLOPT_NOBODY, true);    // we don't need body
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_TIMEOUT,10);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, true);
	curl_setopt($ch, CURLOPT_POST, true);
	curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($_POST));
}catch(Exception $e){
	PrestaShopLogger::addLog("exception occured", 3);
	PrestaShopLogger::addLog($e->getMessage(), 3);
}
//validation needs looking at
// /* execute curl and cast status code to variable */
// PrestaShopLogger::addLog("validating IPN", 1);

// try{
// 	$output = curl_exec($ch);
// 	$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
// 	curl_close($ch);
// 	PrestaShopLogger::addLog("$httpcode", 1);
// }catch(Exception $e){
// 	PrestaShopLogger::addLog("exception occured", 3);
// 	PrestaShopLogger::addLog($e->getMessage(), 3);
// }
// /* check this was a valid IPN - only status code 200 will match */
// if($httpcode !== 200){
// 	$paybeagle->validateOrder((int)$cart->id, _PS_OS_ERROR_, 0, $paybeagle->displayName, $message);
// 	PrestaShopLogger::addLog("IPN Not Valid", 3);

// 	exit;
// }
// PrestaShopLogger::addLog("IPN Valid", 1);


// /* check if prestashop paybeagle sandbox setting matches the sandbox value returned from paybeagle IPN system */
// if(($paybeagle->sandbox && $_POST['sandbox'] !== "TRUE") || (!$paybeagle->sandbox && $_POST['sandbox'] !== "FALSE")){
// 	$paybeagle->validateOrder((int)$cart->id, _PS_OS_ERROR_, 0, $paybeagle->displayName, $message);
// 	exit;
// }

/* check if order is already marked as complete by IPN */
$cartId = (int)$cart->id;
$currentOrderState = 0;
$currentOrderStateQuery = "select current_state from "._DB_PREFIX_."orders where id_cart = ".$cartId;
if($row = Db::getInstance()->getRow($currentOrderStateQuery)){
	$currentOrderState = (int) $row['current_state'];
}	

/* check if payment was successful */
if ((string) $_POST['acquirerResponseCode'] == "0"){
	/* payment processed */

	$amountPennies = number_format($_POST['amount'], 2, '', '');
	$cartPennies = number_format($cart->getOrderTotal(true, Cart::BOTH), 2, '', '');

	/* compare cart value to payment value */
	if($amountPennies == $cartPennies){
		/* cart value matches payment value */

		if($currentOrderState == 0){
			/* order has not got any status - still in cart */
			/* MARK AS _PAYBEAGLE_PS_OS_PAYMENT_ */

			/* why is amount required in pennies here, but pounds and pence in return.php? (using the same function, same params, except payment status int) */
			//$paybeagle->validateOrder((int)$cart->id, _PAYBEAGLE_PS_OS_PAYMENT_, $cart->getOrderTotal(true, Cart::BOTH), $paybeagle->displayName, null, array(), NULL, false, $cart->secure_key);

		} elseif($currentOrderState == _PS_OS_ERROR_){
			/* order has error status - payment declined / payment process failed */
			/* DO NOTHING */

		} elseif($currentOrderState == _PAYBEAGLE_PS_OS_PAYMENT_){
			/* order has waiting status - IPN will mark as complete */
			/* MARK AS COMPLETE */

			/* change order status to paid */
			$OrderID = Order::getOrderByCartId($cart->id);
			$history = new OrderHistory();
			$history->id_order = $OrderID;
			$history->changeIdOrderState(_PS_OS_PAYMENT_, $OrderID);

			/* add history record so it shows up in order history */
			$updateOrderHistory = "insert into "._DB_PREFIX_."order_history ( id_employee, id_order, id_order_state, date_add ) values ( 0, $OrderID, "._PS_OS_PAYMENT_.", now() )";
			$updateOrderHistoryResult = Db::getInstance()->execute($updateOrderHistory);

		} elseif($currentOrderState == _PS_OS_PAYMENT_){
			/* order is marked as complete - IPN has already processed it */
			/* DO NOTHING */

		} else {
			/* order has an unknown status - so we leave as it is */
			/* this else block is staying for possible future use */
			/* DO NOTHING */
		}

	} else {
		/* cart value does not match - hacking attempt? */
		/* set order status to error */
		if($currentOrderState == 0){
			$paybeagle->validateOrder((int)$cart->id, _PS_OS_ERROR_, 0, $paybeagle->displayName, $message);
		}
	}

}else{
	/* payment declined */
	/* mark order as failed */
	if($currentOrderState == 0){
		$paybeagle->validateOrder((int)$cart->id, _PS_OS_ERROR_, 0, $paybeagle->displayName, $message);
	}
}
