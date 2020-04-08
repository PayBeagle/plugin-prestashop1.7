<?php

include(dirname(__FILE__). '/../../../config/config.inc.php');
include(dirname(__FILE__). '/../../../init.php');
include(dirname(__FILE__). '/../PayBeagle.php');
global $smarty;
global $cart;

if(!isset($_POST['customOrderRef'])){
	sendToHome();
}
/* customOrderRef = cart id - without this, its not a valid return */
$cart = new Cart($_POST['customOrderRef']);
if (!Validate::isLoadedObject($cart)){
	sendToHome();
}

$customer = new Customer($cart->id_customer);
$paybeagle = new PayBeagle();

/* set order status to error */
$paybeagle->validateOrder((int)$cart->id, _PS_OS_ERROR_, 0, $paybeagle->displayName, null, array(), NULL, false, $cart->secure_key);

/* send customer to order confirmation page */
Tools::redirect('order-confirmation.php?id_module='.(int)$paybeagle->id.'&id_cart='.(int)$cart->id.'&key='.$customer->secure_key);

function sendToHome(){
	header('location: ' . Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__ );
	exit();
}