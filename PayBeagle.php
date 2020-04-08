<?php
/*
* 2007-2015 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author PrestaShop SA <contact@prestashop.com>
*  @copyright  2007-2015 PrestaShop SA
*  @license    http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

use PrestaShop\PrestaShop\Core\Payment\PaymentOption;

if (!defined('_PS_VERSION_')) {
    exit;
}

class PayBeagle extends PaymentModule
{
    protected $_html = '';
    protected $_postErrors = array();

    public $details;
    public $owner;
    public $address;
    public $extra_mail_vars;

    public function __construct()
    {
        $this->name = 'PayBeagle';
        $this->tab = 'payments_gateways';
        $this->version = '0.0.1';
        $this->ps_versions_compliancy = array('min' => '1.7', 'max' => _PS_VERSION_);
        $this->author = 'PayBeagle';
        $this->controllers = array('validation');
        $this->is_eu_compatible = 1;

        $this->currencies = true;
        $this->currencies_mode = 'checkbox';

        $this->bootstrap = true;
        parent::__construct();

        $this->displayName = $this->l('PayBeagle Hosted');
        $this->description = $this->l('Payment Via the PayBeagle hosted platform');

        $this->js_include_url = '/static/hosted.js';
        $this->ipn_validate_url = '/ipn/';

        // define constant for sandbox bool
        $sandbox = true;
        $sandbox_value = Configuration::get('PAYBEAGLE_SANDBOX');       
        if($sandbox_value == 'N') $sandbox = false;
        $this->sandbox = $sandbox;

        // define constants based on sandbox bool (urls mainly)
        if($this->sandbox){
            $this->paybeagle_domain = 'sandboxx.paybeagle.com';
        } else {
            $this->paybeagle_domain = 'secure.paybeagle.com';
        }



        // define constant for custom state id
        if(!defined('_PAYBEAGLE_PS_OS_PAYMENT_')){
            define('_PAYBEAGLE_PS_OS_PAYMENT_', Configuration::get('PAYBEAGLE_PS_OS_PAYMENT'));
        }

        if (!count(Currency::checkPaymentCurrencies($this->id))) {
            $this->warning = $this->l('No currency has been set for this module.');
        }
    }

    public function install()
    {
        //temp removed to stop duplicated statuses
        // PrestaShopLogger::addLog("TEST LOG 2", 1);
        // $id = 0;
        // /* add custom order state (awaiting payment - marked paid by IPN) */
        // $OrderState = new OrderState();
        // $OrderState->name = array_fill(0,10,"Payment Made Via PayBeagle");
        // // $OrderState->name = array_fill(0,10,"Awaiting Payment Notification");
        // $OrderState->add();
        // $OrderState->template = array_fill(0,10,"paybeagle");
        // $OrderState->module_name = "PayBeagle";
        // $OrderState->send_email = 0;
        // $OrderState->invoice = 0;
        // $OrderState->color = "#ff7109";
        // $OrderState->unremovable = 0;
        // $OrderState->logable = 0;



        // PrestaShopLogger::addLog("TEST LOG 3", 1);
        // if($OrderState->add()){
        //     // PrestaShopLogger::addLog("TEST LOG 4", 1);

        //     $id = (int)$OrderState->id;
        // }
        // PrestaShopLogger::addLog("TEST LOG 5", 1);      
        // PrestaShopLogger::addLog("TEST LOG 6", 1);

        $state_exist = false;
        $states = OrderState::getOrderStates((int)$this->context->language->id);
 
        // check if order state exist
        foreach ($states as $state) {
            if (in_array("Payment Made Via PayBeagle", $state)) {
                $state_exist = true;
                break;
            }
        }
 
        // If the state does not exist, we create it.
            if (!$state_exist) {
            // create new order state
            // /* add custom order state (Payment Made - PayBeagle) */
            $OrderState = new OrderState();
            $OrderState->name = array_fill(0,10,"Payment Made Via PayBeagle");
            // $OrderState->name = array_fill(0,10,"Awaiting Payment Notification");
            $OrderState->add();
            $OrderState->template = array_fill(0,10,"payment");
            $OrderState->module_name = "PayBeagle";
            $OrderState->send_email = 1;
            $OrderState->invoice = 0;
            $OrderState->color = "#72E20F";
            $OrderState->unremovable = 0;
            $OrderState->logable = 0;

            if($OrderState->add()){
                // PrestaShopLogger::addLog("TEST LOG 4", 1);
                $id = (int)$OrderState->id;
                Configuration::updateValue('PAYBEAGLE_PS_OS_PAYMENT', $id);
            }
        }

        
        Configuration::updateValue('PAYBEAGLE_PLATFORM', 'sandbox');
        Configuration::updateValue('PAYBEAGLE_USER_ID', 'prestatest');
        Configuration::updateValue('PAYBEAGLE_USER_PASSWORD', 'Pa55W0rd*2020');
        // PrestaShopLogger::addLog("TEST LOG 7", 1);

        try{
            if (!parent::install() || !$this->registerHook('paymentOptions') || !$this->registerHook('paymentReturn')) {
                return false;
            }
        }catch(Exception $e){
            PrestaShopLogger::addLog("exception occured", 3);
            PrestaShopLogger::addLog($e->getMessage(), 3);
        }
        return true;
    }

    public function hookPaymentOptions($params)
    {

        if (!$this->active) {
            return;
        }

        if (!$this->checkCurrency($params['cart'])) {
            return;
        }

        $payment_options = [
            $this->getExternalPaymentOption($params),
        ];

        return $payment_options;
    }

    public function checkCurrency($cart)
    {
        $currency_order = new Currency($cart->id_currency);
        $currencies_module = $this->getCurrency($cart->id_currency);

        if (is_array($currencies_module)) {
            foreach ($currencies_module as $currency_module) {
                if ($currency_order->id == $currency_module['id_currency']) {
                    return true;
                }
            }
        }
        return false;
    }

    public function getExternalPaymentOption($params)
    {
        try{
            $externalOption = new PaymentOption();
            $cart = $params['cart'];
            if(Configuration::get('PAYBEAGLE_PLATFORM') == "live"){
                $paybeagle_platform_url = "secure.paybeagle.com";
            }else{
                $paybeagle_platform_url = "sandboxx.paybeagle.com";
            }
            //echo var_dump($cart);
            $externalOption->setCallToActionText($this->l('Pay By Card'))
                           ->setAction($this->context->link->getModuleLink($this->name, 'validation', array(), true))
                           ->setInputs([
                                'pb_platform_url' => [
                                    'name' =>'pb_platform_url',
                                    'type' =>'hidden',
                                    'value' =>$paybeagle_platform_url,
                                ],
                                'pb_user' => [
                                    'name' =>'pb_user',
                                    'type' =>'hidden',
                                    'value' =>Configuration::get('PAYBEAGLE_MERCHANT_USERNAME'),
                                ],
                                'pb_pass' => [
                                    'name' =>'pb_pass',
                                    'type' =>'hidden',
                                    'value' =>Configuration::get('PAYBEAGLE_MERCHANT_PASSWORD'),
                                ],
                                'total' => [
                                   'name' =>'total',
                                   'type' =>'hidden',
                                   'value' => $cart->getOrderTotal(),  
                                ],
                                 'cart_id' => [
                                     'name' =>'cart_id',
                                     'type' =>'hidden',
                                     'value' => $cart->id,  
                                ],
                            ])
                           ->setAdditionalInformation($this->context->smarty->fetch('module:PayBeagle/views/templates/front/paybeagle_info.tpl'));
                           //->setLogo(Media::getMediaPath(_PS_MODULE_DIR_.$this->name.'/payment.jpg'));
        }catch(Exception $e){
            PrestaShopLogger::addLog("exception occured", 3);
            PrestaShopLogger::addLog($e->getMessage(), 3);
        }
        return $externalOption;
    }    
    
    /**
    * Update configuration for any changes made in the module admin section
    */
    public function getContent() {
        //$this->log('Updating module configuration');
        $output = null;

        if (Tools::isSubmit('submit' . $this->name)) {
            Configuration::updateValue('PAYBEAGLE_MERCHANT_USERNAME', Tools::getvalue('paybeagle_merchant_username'));
            Configuration::updateValue('PAYBEAGLE_MERCHANT_PASSWORD', Tools::getvalue('paybeagle_merchant_password'));
            Configuration::updateValue('PAYBEAGLE_PLATFORM', Tools::getvalue('paybeagle_platform'));
            $output .= $this->displayConfirmation($this->l('Settings updated'));

        }

        return $output . $this->displayForm();
    }

    /**
    * Display the modules configuration settings using a HelperForm
    */
    public function displayForm() {
        //$this->log('Displaying module settings');
        // Get default language
        $default_lang = (int)Configuration::get('PS_LANG_DEFAULT');

        $fields_form  = array();
        // Init Fields form array
        $fields_form[0]['form'] = array(
            'legend' => array(
                'title' => $this->l('PayBeagle Gateway Config'),
                ),
            'input'  => array(
                array(
                    'type'     => 'text',
                    'label'    => $this->l('PayBeagle Merchant ID'),
                    'name'     => 'paybeagle_merchant_username',
                    'class'    => 'fixed-width-md',
                    'required' => true,
                    ),
                array(
                    'type'     => 'text',
                    'label'    => $this->l('PayBeagle Password'),
                    'name'     => 'paybeagle_merchant_password',
                    'class'    => 'fixed-width-xl',
                    'required' => true,
                    ),
                array(
                    'type' => 'radio',
                    'label' => $this->l('Platform'),
                    'name' => 'paybeagle_platform',
                    'class' => 't',
                    'required'  => true,
                    //'is_bool' => true, 
                    'values' => array(
                        array(
                            'id' => 'Live',
                            'value' => 'live',
                            'label' => $this->l('Live (https://secure.paybeagle.com)')
                        ),
                        array(
                            'id' => 'Sandbox',
                            'value' => 'sandbox',
                            'label' => $this->l('Sandboxx (https://sandboxx.paybeagle.com)')
                        ),
                    ),
                ),
            ),

            'submit' => array(
                'title' => $this->l('Save'),
                'class' => 'button'
            ),

        );


        $helper = new HelperFormCore();

        // Module, token and currentIndex
        $helper->module          = $this;
        $helper->name_controller = $this->name;
        $helper->token           = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex    = AdminController::$currentIndex . '&configure=' . $this->name;

        // Language
        $helper->default_form_language    = $default_lang;
        $helper->allow_employee_form_lang = $default_lang;

        // Title and toolbar
        $helper->title          = $this->displayName;
        $helper->show_toolbar   = true; // false -> remove toolbar
        $helper->toolbar_scroll = true; // yes - > Toolbar is always visible on the top of the screen.
        $helper->submit_action  = 'submit' . $this->name;
        $helper->toolbar_btn    = array(
            'save' =>
                array(
                    'desc' => $this->l('Save'),
                    'href' => AdminController::$currentIndex . '&configure=' . $this->name . '&save' . $this->name .
                              '&token=' . Tools::getAdminTokenLite('AdminModules'),
                ),
            'back' => array(
                'href' => AdminController::$currentIndex . '&token=' . Tools::getAdminTokenLite('AdminModules'),
                'desc' => $this->l('Back to list')
            )
        );

        // Load current values
        $helper->fields_value['paybeagle_merchant_username']  = Configuration::get('PAYBEAGLE_MERCHANT_USERNAME');
        $helper->fields_value['paybeagle_merchant_password']  = Configuration::get('PAYBEAGLE_MERCHANT_PASSWORD');
        $helper->fields_value['paybeagle_platform']  = Configuration::get('PAYBEAGLE_PLATFORM');

        return $helper->generateForm($fields_form);
    }

    /**
     * Log out a message to the logger and error log
     * for debugging and error purposes
     */
    public function log($msg) {
       
        // Severity level (3 for error, 1 for information)
        //$msg = sprintf('', $this->name, $this->version, $msg);
        echo print_r($msg);
        PrestaShopLogger::addLog($msg, 1);
        error_log($msg);
    }
}
