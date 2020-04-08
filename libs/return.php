<?php

include(dirname(__FILE__). '/../../../config/config.inc.php');
include(dirname(__FILE__). '/../../../init.php');
include(dirname(__FILE__). '/../PayBeagle.php');
global $smarty;
global $cart;


/* customOrderRef = cart id - without this, its not a valid return */
$cart = new Cart($_POST['customOrderRef']);
if (!Validate::isLoadedObject($cart)){
	sendToHome();
}

$customer = new Customer($cart->id_customer);

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
	!isset($_POST['sandbox'])){
		sendToHome();
}

$paybeagle = new PayBeagle();
        
if(!defined('_PAYBEAGLE_PS_OS_PAYMENT_')){
    define('_PAYBEAGLE_PS_OS_PAYMENT_', Configuration::get('PAYBEAGLE_PS_OS_PAYMENT'));
}
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
	$success = true;
	$amountPennies = number_format($_POST['amount'], 2, '', '');
	$cartPennies = number_format($cart->getOrderTotal(true, Cart::BOTH), 2, '', '');

	/* compare cart value to payment value */
	if($amountPennies == $cartPennies){
		/* cart value matches payment value */

		if($currentOrderState == 0){
			/* order has not got any status - still in cart */
			/* MARK AS _PAYBEAGLE_PS_OS_PAYMENT_ */
			//$paybeagle->validateOrder((int)$cart->id, 2, $cartPennies, $paybeagle->displayName, null, array(), NULL, false, $cart->secure_key);
			$paybeagle->validateOrder((int)$cart->id, _PAYBEAGLE_PS_OS_PAYMENT_, $cartPennies, $paybeagle->displayName, null, array(), NULL, false, $cart->secure_key);

		} elseif($currentOrderState == _PS_OS_ERROR_){
			/* order has error status - payment declined / payment process failed */
			/* DO NOTHING */

		} elseif($currentOrderState == _PAYBEAGLE_PS_OS_PAYMENT_){
			/* order has waiting status - IPN will mark as complete */
			/* DO NOTHING */

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
		$success = false;
		if($currentOrderState == 0){
			$paybeagle->validateOrder((int)$cart->id, _PS_OS_ERROR_, 0, $paybeagle->displayName, $message);
		}
	}

}else{

	/* payment declined */
	/* mark order as failed if not already got status */
	if($currentOrderState == 0){
		$success = false;
		$paybeagle->validateOrder((int)$cart->id, _PS_OS_ERROR_, 0, $paybeagle->displayName, $message);
	}
}

if($success == true){
	/* send customer to order confirmation page */
	Tools::redirect('order-confirmation.php?id_module='.(int)$paybeagle->id.'&id_cart='.(int)$cart->id.'&key='.$customer->secure_key);
}else{
	sendToHome();
}


function sendToHome(){
	header('location: ' . Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__ );
	exit();
}
