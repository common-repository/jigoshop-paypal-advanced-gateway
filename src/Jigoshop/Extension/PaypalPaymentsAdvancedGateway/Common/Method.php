<?php

namespace Jigoshop\Extension\PaypalPaymentsAdvancedGateway\Common;


use Jigoshop\Core\Messages;
use Jigoshop\Core\Options;
use Jigoshop\Entity\Customer\CompanyAddress;
use Jigoshop\Entity\Order;
use Jigoshop\Exception;
use Jigoshop\Frontend\Pages;
use Jigoshop\Helper\Api;
use Jigoshop\Helper\Currency;
use Jigoshop\Helper\Options as OptionsHelper;
use Jigoshop\Integration\Helper\Render;
use Jigoshop\Service\CartServiceInterface;
use Jigoshop\Service\OrderServiceInterface;

class Method implements \Jigoshop\Payment\Method
{
    const ID = 'paypal_advanced';

    /** @var  Options */
    private $options;
    /** @var  OrderService */
    private $orderService;
    /** @var  Messages */
    private $messages;
    /**@var CardService */
    private $cartService;
    /** @var array */
    private $settings;
    /**@var $currency array */
    private static $currency;
    /**@var $currency array */
    private static $currencies = array('USD'); // allowed currencies

    private $secure_id;
    private $debug_log = array();
    private $liveurl = 'https://payflowpro.paypal.com/';
    private $testurl = 'https://pilot-payflowpro.paypal.com/';
    private $redirecturl = 'https://payflowlink.paypal.com/';

    /**
     * Method constructor.
     * @param Options $options
     * @param CartServiceInterface $cartService
     * @param OrderServiceInterface $orderService
     * @param Messages $messages
     */
    public function __construct(Options $options, CartServiceInterface $cartService, OrderServiceInterface $orderService, Messages $messages)
    {
        $this->options = $options;
        $this->messages = $messages;
        $this->cartService = $cartService;
        $this->orderService = $orderService;
        self::$currency = Currency::code();

        OptionsHelper::setDefaults('payment.' . self::ID, array(
            'enabled' => false,
            'title' => __('Paypal Advanced', 'jigoshop_paypal_advanced'),
            'description' => __('Paypal Advanced', 'jigoshop_paypal_advanced'),
            'user_id' => '',
            'vendor_id' => '',
            'partner_id' => '',
            'password' => '',
            'transaction_type' => '',
            'template' => '',
            'iframe_width' => '650',
            'iframe_height' => '550',
            'debug' => false,
            'debug_email' => '',
            'test_mode' => '',
        ));

        $this->settings = OptionsHelper::getOptions('payment.' . self::ID);

        add_action('init', array($this, 'setMessage'), 10);
        add_filter('init', array($this, 'checkStatusResponse'));
        add_filter('jigoshop\pay\render', array($this, 'renderPay'), 10, 2);

    }

    /**
     * @return string ID of payment method.
     */
    public function getId()
    {
        return self::ID;
    }

    /**
     * @return string Human readable name of method.
     */
    public function getName()
    {
        return is_admin() ? $this->getLogoImage() . ' ' . __('Paypal Advanced', 'jigoshop_eway_payment_gateway') : $this->settings['title'];
    }

    private function getLogoImage()
    {
        return '<img src="' . JIGOSHOP_PAYPAL_ADVANCED_URL . '/assets/images/PayPal_mark_37x23.gif' . '" alt="paypal" width="" height="" style="border:none !important;" class="shipping-logo" />';
    }

    /**
     * @return bool Whether current method is enabled and able to work.
     */
    public function isEnabled()
    {
        return $this->settings['enabled'];
    }

    /**
     * @return array List of options to display on Payment settings page.
     */
    public function getOptions()
    {
        return array(
            array(
                'name' => sprintf('[%s][enabled]', self::ID),
                'title' => __('Enable PayPal Advanced', 'jigoshop_paypal_advanced'),
                'id' => 'jigoshop_paypal_advanced_enabled',
                'type' => 'checkbox',
                'checked' => $this->settings['enabled'],
                'classes' => array('switch-medium'),
            ),
            array(
                'name' => sprintf('[%s][title]', self::ID),
                'title' => __('Method Title', 'jigoshop_paypal_advanced'),
                'tip' => __('This controls the title which the user sees during checkout.', 'jigoshop_paypal_advanced'),
                'type' => 'text',
                'value' => $this->settings['title'],
            ),
            array(
                'name' => sprintf('[%s][description]', self::ID),
                'title' => __('Description', 'jigoshop_paypal_advanced'),
                'tip' => __('This controls the description which the user sees during checkout.', 'jigoshop_paypal_advanced'),
                'type' => 'text',
                'value' => $this->settings['description'],
            ),
            array(
                'name' => sprintf('[%s][user_id]', self::ID),
                'title' => __('User ID', 'jigoshop_paypal_advanced'),
                'tip' => __('If you set up one or more additional users on the PayPal Advanced account, this value is the ID of the user authorized to process transactions. Leave blank, if you don\'t have additional accounts.', 'jigoshop_paypal_advanced'),
                'type' => 'text',
                'value' => $this->settings['user_id'],
            ),
            array(
                'name' => sprintf('[%s][vendor_id]', self::ID),
                'title' => __('Vendor ID', 'jigoshop_paypal_advanced'),
                'tip' => __('Your merchant login ID that you created when you registered for the PayPal Advanced account.', 'jigoshop_paypal_advanced'),
                'type' => 'text',
                'value' => $this->settings['vendor_id'],
            ),
            array(
                'name' => sprintf('[%s][partner_id]', self::ID),
                'title' => __('Partner ID', 'jigoshop_paypal_advanced'),
                'tip' => __("The ID provided to you by the authorized PayPal Reseller who registered you for the Gateway gateway. If you purchased your account directly from PayPal, use PayPal.", 'jigoshop_paypal_advanced'),
                'type' => 'text',
                'value' => $this->settings['partner_id'],
            ),
            array(
                'name' => sprintf('[%s][password]', self::ID),
                'title' => __('Password', 'jigoshop_paypal_advanced'),
                'tip' => __("The password that you defined while registering for the PayPal Advanced account.", 'jigoshop_paypal_advanced'),
                'type' => 'text',
                'value' => $this->settings['password'],
            ),
            array(
                'name' => sprintf('[%s][transaction_type]', self::ID),
                'title' => __('Transaction Type', 'jigoshop_paypal_advanced'),
                'tip' => __('Choose the transaction type you want to process.<br/> <strong>Sale</strong> - for instant capture.<br/> <strong>Authorization</strong> - to authorize and capture later.', 'jigoshop_paypal_advanced'),
                'type' => 'select',
                'options' => array(
                    'A' => __('Authorization', 'jigoshop_paypal_advanced'),
                    'S' => __('Sale', 'jigoshop_paypal_advanced')
                ),
                'value' => $this->settings['transaction_type']
            ),
            array(
                'name' => sprintf('[%s][template]', self::ID),
                'title' => __('Payment Page Template', 'jigoshop_paypal_advanced'),
                'tip' => __('Choose the payment page template you want to use.', 'jigoshop_paypal_advanced'),
                'type' => 'select',
                'options' => array(
                    'A' => __('Layout - A', 'jigoshop_paypal_advanced'),
                    'B' => __('Layout - B', 'jigoshop_paypal_advanced'),
                ),
                'value' => $this->settings['template']
            ),
            array(
                'name' => sprintf('[%s][iframe_width]', self::ID),
                'title' => __('Iframe Width', 'jigoshop_paypal_advanced'),
                'desc' => __("Width of the iframe window, if Layout - C is your chosen template. Enter only numbers in pixels (i.e. 500)", 'jigoshop_paypal_advanced'),
                'type' => 'text',
                'value' => $this->settings['iframe_width'],
            ),
            array(
                'name' => sprintf('[%s][iframe_height]', self::ID),
                'title' => __('Iframe Height', 'jigoshop_paypal_advanced'),
                'desc' => __("Height of the iframe window, if Layout - C is your chosen template. Enter only numbers in pixels (i.e. 565)", 'jigoshop_paypal_advanced'),
                'type' => 'text',
                'value' => $this->settings['iframe_height'],
            ),
            array(
                'name' => sprintf('[%s][test_mode]', self::ID),
                'title' => __('Sandbox/Testmode', 'jigoshop_paypal_advanced'),
                'tip' => __('Enable PayPal Payments Advanced sandbox for test payments.', 'jigoshop_paypal_advanced'),
                'type' => 'select',
                'options' => array(
                    'LIVE' => __('No', 'jigoshop_paypal_advanced'),
                    'TEST' => __('Yes', 'jigoshop_paypal_advanced'),
                ),
                'value' => $this->settings['test_mode'],
            ),
            array(
                'name' => sprintf('[%s][debug]', self::ID),
                'title' => __('Enable Debug', 'jigoshop_paypal_advanced'),
                'tip' => __('Debug log will provide you with most of the data and events generated by the payment process.', 'jigoshop_paypal_advanced'),
                'type' => 'checkbox',
                'checked' => $this->settings['debug'],
                'classes' => array('switch-medium'),
            ),
            array(
                'name' => sprintf('[%s][debug_email]', self::ID),
                'title' => __('Debug Email', 'jigoshop_paypal_advanced'),
                'tip' => __('Email you want to receive all logs to.<br/> If email field is empty, the admin email will be used.', 'jigoshop_paypal_advanced'),
                'type' => 'text',
                'value' => $this->settings['debug_email'],
            ),
        );
    }

    /**
     * Validates and returns properly sanitized options.
     *
     * @param $settings array Input options.
     *
     * @return array Sanitized result.
     */
    public function validateOptions($settings)
    {
        $disabled = null;
        $settings['enabled'] = $settings['enabled'] == 'on';
        $settings['debug'] = $settings['debug'] == 'on';
        $settings['title'] = trim(htmlspecialchars(strip_tags($settings['title'])));
        $settings['description'] = trim(htmlspecialchars(strip_tags($settings['description'],
            '<p><a><strong><em><b><i>')));

        $settings['test_mode'] = $this->validateArData($settings['test_mode']);
        $settings['transaction_type'] = $this->validateArData($settings['transaction_type']);
        $settings['template'] = $this->validateArData($settings['template']);

        $settings['debug_email'] = trim(strip_tags($settings['debug_email']));
        $settings['partner_id'] = trim(strip_tags(esc_attr($settings['partner_id'])));
        $settings['vendor_id'] = trim(strip_tags(esc_attr($settings['vendor_id'])));
        $settings['user_id'] = trim(strip_tags(esc_attr($settings['user_id'])));

        if ($settings['enabled'] == 'on') {
            if (!$this->isEmail($settings['debug_email']) && !empty($settings['debug_email'])) {
                $this->messages->addError('Please enter a valid debug email. <strong>Gateway is disabled</strong>', 'jigoshop_paypal_advanced');
                $settings['enabled'] = $disabled;
            }
        }

        $current_currency = self::$currency;
        if (!in_array($current_currency, self::$currencies)) {
            $message = sprintf(__('Paypal Advanced Gateway for Jigoshop accepts payments in USD. Your current currency is %s. Paypal Advanced Gateway won\'t work until you change the Jigoshop currency to one accepted.', 'jigoshop_paypal_advanced'), self::$currency);
            $this->messages->addError($message);
            $settings['enabled'] = $disabled;
        }

        return $settings;
    }

    private function validateArData($data)
    {
        if (is_array($data)) {
            filter_var_array($data, FILTER_SANITIZE_STRING);
        }

        return $data;
    }

    private function isEmail($email)
    {
        if (!empty($email)) {
            return preg_match('/^[A-Za-z0-9!#$%&\'*+-\/=?^_`{|}~]+@[A-Za-z0-9-]+(\.[AZa-z0-9-]+)+[A-Za-z]$/', $email);
        }

        return true;
    }

    /**
     * Renders method fields and data in Checkout page.
     */
    public function render()
    {
        if ($this->settings['description']) {
            echo wpautop($this->settings['description']);
        }
    }

    /**
     * Get a PayPal Advanced Secure Token
     * @param    int $order_id The Order ID
     * @return    string    The generated token
     * @throws    Exception
     */
    protected function getPaypalToken($order)
    {
        /**@var Order $order */
        $params = $this->generateRequestDetails($order);

        //Debug log
        if ($this->settings['debug'] == true) {
            $this->debugEntry('Order form parameters: ' . print_r($params, true));
        }

        $request = $this->sendPost($params);

        //Debug log
        if ($this->settings['debug'] == true) {
            $this->debugEntry('Set token response:' . print_r($request, true));
            $this->debugReport($this->debug_log);
        }

        if (!is_wp_error($request)) {
            $response = $this->string2array($request['body']);

            if ($response['RESULT'] != 0) {
                $this->messages->addError(__('Error: There was an error while processing your request. Error message: ' . $response['RESPMSG'], 'jigoshop_paypal_advanced'));
            } elseif (empty($response['SECURETOKEN'])) {
                $this->messages->addError(__('Error: There was an error while processing your request. Secure token was empty.', 'jigoshop_paypal_advanced'));
            } else {
                return $response['SECURETOKEN'];
            }
        } else {
            $this->messages->addError(__('Error: There was an error while processing your request. Error message: ' . $request->get_error_message(), 'jigoshop_paypal_advanced'));
        }
    }

    /**
     * Send the request and return the response
     * @param    array $params The build request parameters
     * @return    mixed    The received post response
     */
    private function sendPost($params)
    {
        $params['USER'] = (!empty($this->user)) ? $this->user : $this->settings['vendor_id'];
        $params['VENDOR'] = $this->settings['vendor_id'];
        $params['PARTNER'] = $this->settings['partner_id'];
        $params['PWD'] = $this->settings['password'];

        $str_params = $this->array2string($params);

        if ($this->settings['debug'] == true) {
            $this->debugEntry('Request string is: ' . $str_params);
        }

        $url = $this->settings['test_mode'] == 'TEST' ? $this->testurl : $this->liveurl;

        $sslMode = ($this->settings['test_mode'] == 'TEST' ? false : true);

        $response = wp_remote_post($url, array(
            'method' => 'POST',
            'body' => $str_params,
            'timeout' => 60,
            'sslverify' => apply_filters('https_local_ssl_verify', $sslMode),
        ));

        return $response;
    }

    /**
     * Build a string from array in PayPal way
     * @param    array $params The prepared request parameters
     * @return    string    String in a format "key[value_lenght]=value&..."
     */
    private function array2string($params)
    {
        $str = '';

        foreach ($params as $key => $val) {
            $str .= $key . '[' . strlen($val) . ']=' . $val . '&';
        }

        return substr($str, 0, -1);
    }

    /**
     * Sort all variables returned by the post response
     * and sort them in an array
     * @param    string $response_string PayPal string response
     * @return    array    The sorted response in a NVP format
     */
    private function string2array($response_string)
    {
        $response_args = explode('&', $response_string);
        $response_array = array();

        foreach ($response_args as $value) {
            $args_pair = explode('=', $value);
            $response_array[$args_pair[0]] = urldecode($args_pair[1]);
        }

        return $response_array;
    }

    /**
     * Safely get GET variables
     * @var        string    Post variable name
     * @return    string    The variable value
     */
    private function getterGet($name)
    {
        if (isset($_GET[$name])) {
            return sanitize_text_field($_GET[$name]);
        } else {
            return '';
        }
    }

    /**
     * Safely get POST variables
     * @var        string    Post variable name
     * @return    string    The variable value
     */
    private function getterPost($name)
    {
        if (isset($_POST[$name]) && !empty($_POST[$name])) {
            return sanitize_text_field($_POST[$name]);
        } else {
            return '';
        }
    }


    /**
     * @param Order $order Order to process payment for.
     *
     * @return string URL to redirect to.
     * @throws Exception On any payment error.
     */
    public function process($order)
    {
        try {
            // Unset before obtaining the new token
            $_SESSION['paypal_advanced_secure_token'] = '';
            $_SESSION['paypal_advanced_secure_id'] = '';

            // Set the token and id to the customer session
            $_SESSION['paypal_advanced_secure_token'] = $this->getPaypalToken($order);
            $_SESSION['paypal_advanced_secure_id'] = esc_attr($this->secure_id);

            //Debug log
            if ($this->settings['debug'] == true) {
                $this->debugEntry('PayPal Advanced: Secure Token is:');
                $this->debugEntry($_SESSION['paypal_advanced_secure_token']);
            }

        } catch (\Exception $e) {
            $this->messages->addError($e->getMessage());
        }

        return \Jigoshop\Helper\Order::getPayLink($order);
    }

    /**
     * Generate and pass the request details, such as order, customer, transaction details
     * @param    Order $order The new order object
     * @return    array    The request details
     */
    private function generateRequestDetails($order)
    {
        if ($this->settings['debug'] == true) {
            $this->debugEntry('PayPal Advanced: Generating payment form for order #' . $order->getId());
        }

        $currency = Currency::code();
        $url_success = add_query_arg('paypalAdvancedListener', 'paypalAdvancedResponse', home_url('/'));
        $url_cancel = add_query_arg('paypalAdvancedListener', 'paypalAdvancedResponse', add_query_arg('order', $order->getId(), add_query_arg('cancel-payment', 'true', home_url('/'))));
        $url_error = add_query_arg('paypalAdvancedListener', 'paypalAdvancedResponse', add_query_arg('error-payment', 'true', home_url('/')));

        $this->secure_id = uniqid('', true);

        $billing = $order->getCustomer()->getBillingAddress();

        $companyName = 'N\A';

        if ($billing instanceof CompanyAddress) {
            $companyName = $billing->getCompany();
        }

        $shipping = $order->getCustomer()->getShippingAddress();
        $paypal_adv_arguments = [
            'SECURETOKENID' => $this->secure_id,
            'TRXTYPE' => $this->settings['transaction_type'],
            'AMT' => $order->getTotal(),
            'COMPANYNAME' => $companyName,
            'CURRENCY' => $currency,
            'INVNUM' => $order->getId() . '-' . $order->getKey(),
            'EMAIL' => $billing->getEmail(),
            'CREATESECURETOKEN' => 'Y',
            'BUTTONSOURCE' => 'Jigoshop_SP',
            // Checkout page settings
            'RETURNURL' => $url_success,
            'ERRORURL' => $url_error,
            'URLMETHOD' => 'POST',
            'CANCELURL' => $url_cancel,
            'VERBOSITY' => 'HIGH',
            'BILLTOFIRSTNAME' => $billing->getFirstName(),
            'BILLTOLASTNAME' => $billing->getLastName(),
            'BILLTOSTREET' => $billing->getAddress(),
            'BILLTOCITY' => $billing->getCity(),
            'BILLTOSTATE' => $billing->getState(),
            'BILLTOZIP' => $billing->getPostcode(),
            'BILLTOCOUNTRY' => $billing->getCountry(),
            'BILLTOEMAIL' => $billing->getEmail(),
            'BILLTOPHONENUM' => $billing->getPhone(),
            'SHIPTOFIRSTNAME' => $shipping->getFirstName(),
            'SHIPTOLASTNAME' => $shipping->getLastName(),
            'SHIPTOSTREET' => $shipping->getAddress(),
            'SHIPTOCITY' => $shipping->getCity(),
            'SHIPTOSTATE' => $shipping->getState(),
            'SHIPTOZIP' => $shipping->getPostcode(),
            'SHIPTTOCOUNTRY' => $shipping->getCountry(),
        ];

        $item_counter = 1;
        $description = '';
        if (sizeof($order->getItems()) > 0) {
            foreach ($order->getItems() as $item) {
                if ($item->getQuantity()) {
                    $title = $item->getName();
                    $prod_id = $item->getId();

                    // Set items
                    $paypal_adv_arguments['L_NAME' . $item_counter] = $title;
                    $paypal_adv_arguments['L_QTY' . $item_counter] = $item->getQuantity();
                    $paypal_adv_arguments['L_COST' . $item_counter] = number_format($item->getPrice(), 2);
                    $paypal_adv_arguments['L_SKU' . $item_counter] = $prod_id;

                    $item_counter++;
                }
            }


            // Set order discount coupons
            if ($order->getDiscount() != 0) {
                $paypal_adv_arguments['L_NAME' . $item_counter] = __('Discount', 'jigoshop_paypal_advanced');
                $paypal_adv_arguments['L_QTY' . $item_counter] = 1;
                $paypal_adv_arguments['L_COST' . $item_counter] = number_format($order->getDiscount(), 2) * (-1);
                $item_counter++;
            }

            // Shipping amount
            if ($order->getShippingPrice() > 0) {
                $paypal_adv_arguments['L_NAME' . $item_counter] = __('Shipping', 'jigoshop_paypal_advanced');
                $paypal_adv_arguments['L_QTY' . $item_counter] = 1;
                $paypal_adv_arguments['L_COST' . $item_counter] = number_format($order->getShippingPrice(), 2);
            }

            // Tax
            $paypal_adv_arguments['TAXAMT'] = number_format($order->getTotalTax(), 2);

        }
        // Filter the order details
        return apply_filters('jigoshop_paypal_advanced_arguments', $paypal_adv_arguments);
    }

    /**
     * Check for PayPal Advanced Payment Response.
     * Process Payment based on the Response.
     */
    public function checkStatusResponse()
    {
        if (isset($_GET['paypalAdvancedListener']) && $_GET['paypalAdvancedListener'] == 'paypalAdvancedResponse') {
            $_POST = stripslashes_deep($_POST);

            //Debug log
            if ($this->settings['debug'] == true) {
                $this->debugEntry('Paypal Advanced: Payment response received.');
                $this->debugEntry('Response is: ' . print_r($_POST, true));
            }

            // Retrieve order ID
            if ($this->getterPost('INVNUM') !== '') {

                $invoice_details = explode('-', $this->getterPost('INVNUM'));
                $order_id = (int)$invoice_details[0];

                if ($this->getterPost('RESULT') != 0 && $this->getterGet('error-payment')) {
                    // Clean
                    @ob_clean();
                    // Header
                    header('HTTP/1.1 200 OK');

                    if ($this->getterPost('RESULT') != 12) {
                        $redirect_url = add_query_arg('paypal-error', 'true', get_permalink($this->options->getPageId(Pages::THANK_YOU)));
                    } else {
                        // Create order object
                        /**@var Order $order */
                        $order = $this->orderService->find($order_id);
                        $order->setStatus('on-hold', __('Order payment was declined. Message: ' . $this->getterPost('RESPMSG'), 'jigoshop_paypal_advanced'));

                        //Debug log
                        if ($this->settings['debug'] == true) {
                            $this->debugEntry(__('Order payment was declined. Message: ' . $this->getterPost('RESPMSG'), 'jigoshop_paypal_advanced'));
                        }

                        $url = add_query_arg('order', $order->getId(), add_query_arg('key', $order->getKey(), get_permalink($this->options->getPageId(Pages::THANK_YOU))));
                        $redirect_url = add_query_arg('paypal-declined', 'true', $url);
                    }

                    //Debug log
                    if ($this->settings['debug'] == true) {
                        $this->debugReport($this->debug_log);
                    }

                    if ($this->settings['template'] == 'C') {
                        echo "<script>window.parent.location.href='" . $redirect_url . "';</script>";
                    } else {
                        $this->ppRedirect($redirect_url);
                    }

                    exit;
                } elseif ($this->getterGet('cancel-payment')) {
                    //Debug log
                    if ($this->settings['debug'] == true) {
                        $this->debugEntry('Cancel order message. Redirect to cancel URL.');
                        $this->debugReport($this->debug_log);
                    }
                    // Create order object
                    $cancelOrderID = $_GET['order'];
                    $order = $this->orderService->find($cancelOrderID);
                    $redirect = \Jigoshop\Helper\Order::getCancelLink($order);
                    wp_safe_redirect($redirect);
                    exit;
                } else {
                    /**@var Order $order */
                    $order = $this->orderService->find($order_id);
                    $inquiry = $this->sendPost(array(
                        'ORIGID' => $this->getterPost('PNREF'),
                        'TRXTYPE' => 'I',
                        'VERBOSITY' => 'HIGH'
                    ));

                    if (is_wp_error($inquiry)) {
                        $order->setStatus(__('Error: payment verification failed.', 'jigoshop_paypal_advanced'));

                        //Debug log
                        if ($this->settings['debug'] == true) {
                            $this->debugEntry('Error: payment verification failed. Error message: ' . $inquiry->get_error_message());
                            $this->debugReport($this->debug_log);
                        }

                        $redirect_url = add_query_arg('paypal-error', 'true', get_permalink($this->options->getPageId(Pages::THANK_YOU)));

                        if ($this->settings['template'] == 'C') {
                            echo "<script>window.parent.location.href='" . $redirect_url . "';</script>";
                        } else {
                            $this->ppRedirect($redirect_url);
                        }
                        exit;
                    } else {
                        $response = $this->string2array($inquiry['body']);
                        $result = $response['RESULT'];
                        $ref_number = $response['PNREF'];
                        $err_message = $response['RESPMSG'];

                        //check if order was processed already
                        if ($order->getStatus() == 'jigoshop-complete' || $order->getStatus() == 'jigoshop-processing') {
                            $order->setStatus(__('Response received, but order was already paid for.', 'jigoshop_paypal_advanced'));
                            //Debug log
                            if ($this->settings['debug'] == true) {
                                $this->debugEntry('Error: Order was already paid for. Aborting.');
                                $this->debugReport($this->debug_log);
                            }

                            $url = \Jigoshop\Helper\Order::getThankYouLink($order);

                            if ($this->settings['template'] == 'C') {
                                echo "<script>window.parent.location.href='" . $url . "';</script>";
                            } else {
                                $this->ppRedirect($url);
                            }
                            exit;
                        }

                        //Debug log
                        if ($this->settings['debug'] == true) {
                            $this->debugEntry('Inquiry order: ' . print_r($response, true));
                        }

                        switch ($result) {
                            case '0':
                                $status = \Jigoshop\Helper\Order::getStatusAfterCompletePayment($order);
                                /**@var Order $order */
                                $order->setStatus($status, __('PayPal Advanced Payment Completed. Reference number: ' . $ref_number . ' ', 'jigoshop_paypal_advanced'));
                                if ($this->settings['debug'] == true) {
                                    $this->debugEntry('Payment completed.');
                                }

                                $this->orderService->save($order);
                                break;
                            default:
                                $order->setStatus(__('PayPal Advanced Payment Failed. Error message: ' . $err_message, 'jigoshop_paypal_advanced'));
                                $order->setStatus('on-hold', __('Order payment was declined. Message: ' . $this->getterPost('RESPMSG'), 'jigoshop_paypal_advanced'));

                                //Debug log
                                if ($this->settings['debug'] == true) {
                                    $this->debugEntry('Payment failed.');
                                }

                                break;
                        }

                        $url = $url = \Jigoshop\Helper\Order::getThankYouLink($order);
                        $redirect_url = apply_filters('paypal_advanced_ipn_redirect_url', $url);

                        //Debug log
                        if ($this->settings['debug'] == true) {
                            $this->debugReport($this->debug_log);
                        }
                    }
                }

                @ob_clean();
                // Header
                header('HTTP/1.1 200 OK');

                if ($this->settings['template'] == 'C') {
                    echo "<script>window.parent.location.href='" . $redirect_url . "';</script>";
                } else {
                    $this->ppRedirect($redirect_url);
                }
                exit;
            }
        }
    }

    /**
     * @param string $render
     * @param Order $order
     *
     * @return string
     */
    public function renderPay($render, $order)
    {
        if ($order->getPaymentMethod()->getId() != self::ID) {
            return $render;
        }
        // Build an URL for customer to open or be redirected to
        $location = add_query_arg('MODE', $this->settings['test_mode'], add_query_arg('SECURETOKEN', $_SESSION['paypal_advanced_secure_token'], add_query_arg('SECURETOKENID', $_SESSION['paypal_advanced_secure_id'], $this->redirecturl)));

        if ($this->settings['debug'] == true) {
            $this->debugEntry('PayPal Advanced: Redirect URl: ' . $location);
            $this->debugReport($this->debug_log);
        }
        if ($this->settings['template'] == 'C') {
            $width = $this->settings['iframe_width'];
            $height = $this->settings['iframe_height'];
            $render = Render::get('paypal_advanced', 'iframe', array(
                'location' => $location,
                'width' => $width,
                'height' => $height,
            ));
        } else {
            $this->ppRedirect($location);
            exit;
        }

        return $render;
    }

    /*
     * Receive and generate an array from the generated debug steps
     * @param	string	$message A descriptive message of the step
     */
    private function debugEntry($message)
    {
        if ($this->settings['debug'] == true) {
            $this->debug_log[] = date('Y-m-d H:i:s') . ' : ' . $message;
        }
    }

    /*
     * Email the generated debug array
     * @param	array	$debug_log Generated debug report
     */
    private function debugReport($debug_log)
    {
        if ($this->settings['debug'] == true) {
            $subject = __('[' . get_bloginfo('name') . '] ' . $debug_log[0]);
            $message = '/*****************************************/' . PHP_EOL;

            foreach ($debug_log as $entry) {
                $message .= $entry . PHP_EOL;
            }

            $email = (!empty($this->debug_email)) ? $this->debug_email : get_bloginfo('admin_email');
            wp_mail($email, $subject, $message);

            unset($this->debug_log);
        }
    }

    protected function ppRedirect($url)
    {
        $string = '<script type="text/javascript">';
        $string .= 'window.location = "' . $url . '"';
        $string .= '</script>';

        echo $string;
    }

    public function setMessage($message)
    {
        if (isset($_GET['paypal-error'])) {
            $message = apply_filters('paypal-advanced-error-message', "<p>" . __('We are sorry, but your payment did not go through. Please try again or contact us for help.', 'jigoshop_paypal_advanced') . "</p>");
        } elseif (isset($_GET['paypal-declined'])) {
            $message = apply_filters('paypal-advanced-declined-message', "<p>" . __('We are sorry, but your payment was declined. Please try again or contact us for help.', 'jigoshop_paypal_advanced') . "</p>");
        }

        return $message;
    }
}