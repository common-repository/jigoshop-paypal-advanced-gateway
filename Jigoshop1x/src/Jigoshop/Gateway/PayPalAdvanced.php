<?php

namespace Jigoshop\Gateway;

/**
 * PayPal Advanced class
 */
class PayPalAdvanced extends \jigoshop_payment_gateway
{
    public $secure_id;
    private static $pluginName = 'jigoshop_paypal_advanced';
    private $debug_log = array();
    private $liveurl = 'https://payflowpro.paypal.com/';
    private $testurl = 'https://pilot-payflowpro.paypal.com/';
    private $redirecturl = 'https://payflowlink.paypal.com/';

    private $vendor;
    private $user;
    private $partner;
    private $password;
    private $trxtype;
    private $template;
    private $iframe_width;
    private $iframe_height;
    private $testmode;
    private $debug;
    private $debug_email;
    private $posturl;

    public function __construct()
    {
        parent::__construct();

        // Parent class properties
        $this->id = 'paypal_advanced';
        $this->icon = JIGOSHOP_PAYPAL_ADVANCED_URL . '/images/PayPal_mark_37x23.gif';
        $this->has_fields = false;
        $this->enabled = \Jigoshop_Base::get_options()->get_option('jigoshop_paypal_advanced_enabled');
        $this->title = \Jigoshop_Base::get_options()->get_option('jigoshop_paypal_advanced_title');
        $this->description = \Jigoshop_Base::get_options()->get_option('jigoshop_paypal_advanced_description');

        // Class properties
        $this->vendor = \Jigoshop_Base::get_options()->get_option('jigoshop_paypal_advanced_vendor');
        $this->user = \Jigoshop_Base::get_options()->get_option('jigoshop_paypal_advanced_user');
        $this->partner = \Jigoshop_Base::get_options()->get_option('jigoshop_paypal_advanced_partner');
        $this->password = \Jigoshop_Base::get_options()->get_option('jigoshop_paypal_advanced_password');
        $this->trxtype = \Jigoshop_Base::get_options()->get_option('jigoshop_paypal_advanced_trxtype');
        $this->template = \Jigoshop_Base::get_options()->get_option('jigoshop_paypal_advanced_template');
        $this->iframe_width = \Jigoshop_Base::get_options()->get_option('jigoshop_paypal_advanced_iframe_width');
        $this->iframe_height = \Jigoshop_Base::get_options()->get_option('jigoshop_paypal_advanced_iframe_height');
        $this->testmode = \Jigoshop_Base::get_options()->get_option('jigoshop_paypal_advanced_testmode');
        $this->debug = \Jigoshop_Base::get_options()->get_option('jigoshop_paypal_advanced_debug');
        $this->debug_email = \Jigoshop_Base::get_options()->get_option('jigoshop_paypal_advanced_debug_email');
        $this->posturl = ($this->testmode == 'TEST') ? $this->testurl : $this->liveurl;

        add_filter('init', array($this, 'check_status_response'));
        add_filter('receipt_paypal_advanced', array($this, 'process_receipt'));
        add_filter('admin_notices', array($this, 'usage_notification'));
        add_filter('jigoshop_thankyou_message', array($this, 'set_thankyou_message'));
        add_filter('init', array($this, 'set_text_localization'));
    }

    /*
     * Build a string from array in PayPal way
     * @param	array	$params The prepared request parameters
     * @return	string	String in a format "key[value_lenght]=value&..."
     */
    private function array2string($params)
    {
        $str = '';

        foreach ($params as $key => $val) {
            $str .= $key . '[' . strlen($val) . ']=' . $val . '&';
        }

        return substr($str, 0, -1);
    }

    /*
     * Check for PayPal Advanced Payment Response.
     * Process Payment based on the Response.
     */
    public function check_status_response()
    {
        if (isset($_GET['paypalAdvancedListener']) && $_GET['paypalAdvancedListener'] == 'paypalAdvancedResponse') {
            $_POST = stripslashes_deep($_POST);

            //Debug log
            if ($this->debug == 'yes') {
                $this->debug_entry('Paypal Advanced: Payment response received.');
                $this->debug_entry('Response is: ' . print_r($_POST, true));
            }

            // Retrive order ID
            if ($this->getter_post('INVNUM') !== '' ) {
                $invoice_details = explode('-', $this->getter_post('INVNUM'));
                $order_id = (int)$invoice_details[0];

                if ($this->getter_post('RESULT') != 0 && $this->getter_get('error-payment')) {
                    // Clean
                    @ob_clean();
                    // Header
                    header('HTTP/1.1 200 OK');

                    if ($this->getter_post('RESULT') != 12) {
                        $redirect_url = add_query_arg('paypal-error', 'true', get_permalink(jigoshop_get_page_id('thanks')));
                    } else {
                        // Create order object
                        $order = new \jigoshop_order($order_id);
                        $order->update_status('on-hold', __('Order payment was declined. Message: ' . $this->getter_post('RESPMSG'), self::$pluginName));

                        //Debug log
                        if ($this->debug == 'yes') {
                            $this->debug_entry(__('Order payment was declined. Message: ' . $this->getter_post('RESPMSG'), self::$pluginName));
                        }

                        $url = add_query_arg('order', $order->id, add_query_arg('key', $order->order_key, get_permalink(jigoshop_get_page_id('thanks'))));
                        $redirect_url = add_query_arg('paypal-declined', 'true', $url);
                    }

                    //Debug log
                    if ($this->debug == 'yes') {
                        $this->debug_report($this->debug_log);
                    }

                    if ($this->template == 'C') {
                        echo "<script>window.parent.location.href='" . $redirect_url . "';</script>";
                    } else {
                        $this->pp_redirect($redirect_url);
                    }

                    exit;
                } elseif ($this->getter_get('cancel-payment')) {
                    //Debug log
                    if ($this->debug == 'yes') {
                        $this->debug_entry('Cancel order message. Redirect to cancel URL.');
                        $this->debug_report($this->debug_log);
                    }

                    // Create order object
                    $order = new \jigoshop_order($order_id);
                    wp_redirect($order->get_cancel_order_url());
                    exit;
                } else {
                    $order = new \jigoshop_order($order_id);
                    $inquiry = $this->send_post(array(
                        'ORIGID' => $this->getter_post('PNREF'),
                        'TRXTYPE' => 'I',
                        'VERBOSITY' => 'HIGH'
                    ));

                    if (is_wp_error($inquiry)) {
                        $order->add_order_note(__('Error: payment verifiation failed.', self::$pluginName));

                        //Debug log
                        if ($this->debug == 'yes') {
                            $this->debug_entry('Error: payment verifiation failed. Error message: ' . $inquiry->get_error_message());
                            $this->debug_report($this->debug_log);
                        }

                        $redirect_url = add_query_arg('paypal-error', 'true', get_permalink(jigoshop_get_page_id('thanks')));

                        if ($this->template == 'C') {
                            echo "<script>window.parent.location.href='" . $redirect_url . "';</script>";
                        } else {
                            $this->pp_redirect($redirect_url);
                        }
                        exit;
                    } else {
                        $response = $this->string2array($inquiry['body']);
                        $result = $response['RESULT'];
                        $ref_number = $response['PNREF'];
                        $err_message = $response['RESPMSG'];

                        //check if order was processed already
                        if ($order->status == 'complete' || $order->status == 'processing') {
                            $order->add_order_note(__('Response received, but order was already paid for.', self::$pluginName));
                            //Debug log
                            if ($this->debug == 'yes') {
                                $this->debug_entry('Error: Order was already paid for. Aborting.');
                                $this->debug_report($this->debug_log);
                            }

                            $url = add_query_arg('order', $order->id, add_query_arg('key', $order->order_key, get_permalink(jigoshop_get_page_id('thanks'))));

                            if ($this->template == 'C') {
                                echo "<script>window.parent.location.href='" . $url . "';</script>";
                            } else {
                                $this->pp_redirect($url);
                            }
                            exit;
                        }

                        //Debug log
                        if ($this->debug == 'yes') {
                            $this->debug_entry('Inquiry order: ' . print_r($response, true));
                        }

                        switch ($result) {
                            case '0':
                                $order->add_order_note(__('PayPal Advanced Payment Completed. Reference number: ' . $ref_number, self::$pluginName));

                                if ($this->debug == 'yes') {
                                    $this->debug_entry('Payment completed.');
                                }

                                $order->payment_complete();
                                \jigoshop_cart::empty_cart();
                                break;
                            default:
                                $order->add_order_note(__('PayPal Advanced Payment Failed. Error message: ' . $err_message, self::$pluginName));
                                $order->update_status('on-hold', __('Order payment was declined. Message: ' . $this->getter_post('RESPMSG'), self::$pluginName));

                                //Debug log
                                if ($this->debug == 'yes') {
                                    $this->debug_entry('Payment failed.');
                                }

                                break;
                        }

                        $url = add_query_arg('order', $order->id, add_query_arg('key', $order->order_key, get_permalink(jigoshop_get_page_id('thanks'))));
                        $redirect_url = apply_filters('paypal_advanced_ipn_redirect_url', $url);

                        //Debug log
                        if ($this->debug == 'yes') {
                            $this->debug_report($this->debug_log);
                        }
                    }
                }

                @ob_clean();
                // Header
                header('HTTP/1.1 200 OK');

                if ($this->template == 'C') {
                    echo "<script>window.parent.location.href='" . $redirect_url . "';</script>";
                } else {
                    $this->pp_redirect($redirect_url);
                }
                exit;
            }
        }
    }

    /*
     * Check if store supports currency
     * @return	boolean
     */
    protected function check_supported_currency()
    {
        $currency = apply_filters('paypal_advanced_accepted_currency', array('USD'));
        $jigoshop_set_currency = \Jigoshop_Base::get_options()->get_option('jigoshop_currency');

        if (in_array($jigoshop_set_currency, $currency)) {
            return true;
        } else {
            return false;
        }
    }

    /*
     * Receive and generate an array from the generated debug steps
     * @param	string	$message A descriptive message of the step
     */
    private function debug_entry($message)
    {
        if ($this->debug == 'yes') {
            $this->debug_log[] = date('Y-m-d H:i:s') . ' : ' . $message;
        }
    }

    /*
     * Email the generated debug array
     * @param	array	$debug_log Generated debug report
     */
    private function debug_report($debug_log)
    {
        if ($this->debug == 'yes') {
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

    /**
     * Generate and pass the request details, such as order, customer, transaction details
     * @param    \jigoshop_order $order The new order object
     * @return    array    The request details
     */
    private function generate_request_details($order)
    {
        if ($this->debug == 'yes') {
            $this->debug_entry('PayPal Advanced: Generating payment form for order #' . $order->id);
        }

        $currency = \Jigoshop_Base::get_options()->get_option('jigoshop_currency');
        $url_success = add_query_arg('paypalAdvancedListener', 'paypalAdvancedResponse', home_url('/'));
        $url_cancel = add_query_arg('paypalAdvancedListener', 'paypalAdvancedResponse', add_query_arg('cancel-payment', 'true', home_url('/')));
        $url_error = add_query_arg('paypalAdvancedListener', 'paypalAdvancedResponse', add_query_arg('error-payment', 'true', home_url('/')));

        $this->secure_id = uniqid('', true);

        $paypal_adv_arguments = array(
            'SECURETOKENID' => $this->secure_id,
            'TRXTYPE' => $this->trxtype,
            'AMT' => $order->order_total,
            'COMPANYNAME' => $order->billing_company,
            'CURRENCY' => $currency,
            'INVNUM' => $order->id . '-' . $order->order_key,
            'EMAIL' => $order->billing_email,
            'CREATESECURETOKEN' => 'Y',
            'BUTTONSOURCE' => 'Jigoshop_SP',
            // Checkout page settings
            'RETURNURL' => $url_success,
            'ERRORURL' => $url_error,
            'URLMETHOD' => 'POST',
            'CANCELURL' => $url_cancel,
            'VERBOSITY' => 'HIGH',
            'BILLTOFIRSTNAME' => $order->billing_first_name,
            'BILLTOLASTNAME' => $order->billing_last_name,
            'BILLTOSTREET' => $order->billing_address_1 . ' ' . $order->billing_address_2,
            'BILLTOCITY' => $order->billing_city,
            'BILLTOSTATE' => $order->billing_state,
            'BILLTOZIP' => $order->billing_postcode,
            'BILLTOCOUNTRY' => $order->billing_country,
            'BILLTOEMAIL' => $order->billing_email,
            'BILLTOPHONENUM' => $order->billing_phone,
            'SHIPTOFIRSTNAME' => $order->shipping_first_name,
            'SHIPTOLASTNAME' => $order->shipping_last_name,
            'SHIPTOSTREET' => $order->shipping_address_1 . ' ' . $order->shipping_address_2,
            'SHIPTOCITY' => $order->shipping_city,
            'SHIPTOSTATE' => $order->shipping_state,
            'SHIPTOZIP' => $order->shipping_postcode,
            'SHIPTTOCOUNTRY' => $order->shipping_country,
        );

        $item_counter = 1;
        $description = '';
        // Because of rounding errors when the order items include tax
        // the Items of the cart/order will be send as one Item with price equal to the order total.
        // Cart Contents - Generate cart description
        if (\Jigoshop_Base::get_options()->get_option('jigoshop_prices_include_tax') == 'yes') {
            if (sizeof($order->items) > 0) {
                foreach ($order->items as $item) {
                    if (!empty($item['variation_id'])) {
                        $prod = new \jigoshop_product_variation($item['variation_id']);

                        if (gettype($item['variation']) == 'array') {
                            $prod->set_variation_attributes($item['variation']);
                        }
                    } else {
                        $prod = new \jigoshop_product($item['id']);
                    }

                    if ($prod->exists() && $item['qty']) {
                        $title = $prod->get_title();

                        // If variation, insert variation details into product title
                        if ($prod instanceof \jigoshop_product_variation) {
                            $variation_details = array();

                            foreach ($prod->get_variation_attributes() as $name => $value) {
                                $variation_details[] = ucfirst(str_replace('tax_', '', $name)) . ': ' . ucfirst($value);
                            }

                            if (!empty($variation_details)) {
                                $variations = jigoshop_get_formatted_variation($prod, $prod->variation_data, true);
                                $title .= ' (' . $variations . ')';
                            }
                        }

                        $description .= $title . ' x ' . $item['qty'] . ', ';
                    }
                }
            }

            // Set the description
            $description = substr($description, 0, -2);
            $paypal_adv_arguments['L_NAME' . $item_counter] = $description;
            $paypal_adv_arguments['L_QTY' . $item_counter] = '1';
            $paypal_adv_arguments['L_COST' . $item_counter] = number_format($order->order_total, 2);
        } else {
            if (sizeof($order->items) > 0) {
                foreach ($order->items as $item) {
                    if (!empty($item['variation_id'])) {
                        $prod = new \jigoshop_product_variation($item['variation_id']);
                        if (gettype($item['variation']) == 'array') {
                            $prod->set_variation_attributes($item['variation']);
                        }
                    } else {
                        $prod = new \jigoshop_product($item['id']);
                    }

                    if ($prod->exists() && $item['qty']) {
                        $title = $prod->get_title();
                        $prod_id = $item['id'];

                        // If variation, insert variation details into product title
                        if ($prod instanceof \jigoshop_product_variation) {
                            $variation_details = array();

                            foreach ($prod->get_variation_attributes() as $name => $value) {
                                $variation_details[] = ucfirst(str_replace('tax_', '', $name)) . ': ' . ucfirst($value);
                            }

                            if (!empty($variation_details)) {
                                $variations = jigoshop_get_formatted_variation($prod, $prod->variation_data, true);
                                $title .= ' (' . $variations . ')';
                                $prod_id = $prod->get_variation_id();
                            }
                        }

                        // Set items
                        $paypal_adv_arguments['L_NAME' . $item_counter] = $title;
                        $paypal_adv_arguments['L_QTY' . $item_counter] = $item['qty'];
                        $paypal_adv_arguments['L_COST' . $item_counter] = number_format($prod->get_price_excluding_tax(), 2);
                        $paypal_adv_arguments['L_SKU' . $item_counter] = $prod_id;

                        $item_counter++;
                    }
                }
            }

            // Set order discount coupons
            if ($order->order_discount != 0) {
                $paypal_adv_arguments['L_NAME' . $item_counter] = __('Discount', self::$pluginName);
                $paypal_adv_arguments['L_QTY' . $item_counter] = 1;
                $paypal_adv_arguments['L_COST' . $item_counter] = number_format($order->order_discount, 2) * (-1);
                $item_counter++;
            }

            // Shipping amount
            if ($order->order_shipping > 0) {
                $paypal_adv_arguments['L_NAME' . $item_counter] = __('Shipping', self::$pluginName);
                $paypal_adv_arguments['L_QTY' . $item_counter] = 1;
                $paypal_adv_arguments['L_COST' . $item_counter] = number_format($order->order_shipping, 2);
            }

            // Tax
            $paypal_adv_arguments['TAXAMT'] = number_format($order->get_total_tax(), 2);

        }

        // Filter the order details
        return apply_filters('jigoshop_paypal_advanced_arguments', $paypal_adv_arguments);
    }

    protected function get_default_options()
    {
        $defaults = array();

        /*
         * Jigoshop_Options section
         */
        $defaults[] = array(
            'name' => __('PayPal Payments Advanced', self::$pluginName),
            'type' => 'title',
            'desc' => __('PayPal Advanced offers you the ability to accept credit cards, PayPal, and Bill Me Later straight from your website.', self::$pluginName)
        );

        /*
         * Jigoshop_Options list
         */
        $defaults[] = array(
            'name' => __('Enable PayPal Advanced', self::$pluginName),
            'desc' => '',
            'tip' => '',
            'id' => 'jigoshop_paypal_advanced_enabled',
            'std' => 'yes',
            'type' => 'checkbox',
            'choices' => array(
                'no' => __('No', self::$pluginName),
                'yes' => __('Yes', self::$pluginName)
            )
        );

        $defaults[] = array(
            'name' => __('Method Title', self::$pluginName),
            'desc' => '',
            'tip' => __('This controls the title which the user sees during checkout.', self::$pluginName),
            'id' => 'jigoshop_paypal_advanced_title',
            'std' => __('PayPal Payments Advanced', self::$pluginName),
            'type' => 'midtext'
        );

        $defaults[] = array(
            'name' => __('Description', self::$pluginName),
            'desc' => '',
            'tip' => __('This controls the description which the user sees during checkout.', self::$pluginName),
            'id' => 'jigoshop_paypal_advanced_description',
            'std' => __("Make a payment using your credit/debit card or PayPal account.", self::$pluginName),
            'type' => 'textarea'
        );

        $defaults[] = array(
            'name' => __('User ID', self::$pluginName),
            'desc' => '',
            'tip' => __('If you set up one or more additional users on the PayPal Advanced account, this value is the ID of the user authorized to process transactions. Leave blank, if you don\'t have additional accounts.', self::$pluginName),
            'id' => 'jigoshop_paypal_advanced_user',
            'std' => '',
            'type' => 'midtext'
        );

        $defaults[] = array(
            'name' => __('Vendor ID', self::$pluginName),
            'desc' => '',
            'tip' => __('Your merchant login ID that you created when you registered for the PayPal Advanced account.', self::$pluginName),
            'id' => 'jigoshop_paypal_advanced_vendor',
            'std' => '',
            'type' => 'midtext'
        );

        $defaults[] = array(
            'name' => __('Partner ID', self::$pluginName),
            'desc' => '',
            'tip' => __("The ID provided to you by the authorized PayPal Reseller who registered you for the Gateway gateway. If you purchased your account directly from PayPal, use PayPal.", self::$pluginName),
            'id' => 'jigoshop_paypal_advanced_partner',
            'std' => '',
            'type' => 'midtext'
        );

        $defaults[] = array(
            'name' => __('Password', self::$pluginName),
            'desc' => '',
            'tip' => __("The password that you defined while registering for the PayPal Advanced account.", self::$pluginName),
            'id' => 'jigoshop_paypal_advanced_password',
            'std' => '',
            'type' => 'midtext'
        );

        $defaults[] = array(
            'name' => __('Transaction Type', self::$pluginName),
            'desc' => '',
            'tip' => __('Choose the transaction type you want to process.<br/> <strong>Sale</strong> - for instant capture.<br/> <strong>Authorization</strong> - to authorize and capture later.', self::$pluginName),
            'id' => 'jigoshop_paypal_advanced_trxtype',
            'std' => 'S',
            'type' => 'select',
            'choices' => array(
                'A' => __('Authorization', self::$pluginName),
                'S' => __('Sale', self::$pluginName)
            )
        );

        $defaults[] = array(
            'name' => __('Payment Page Template', self::$pluginName),
            'desc' => '',
            'tip' => __('Choose the payment page template you want to use. <strong>A</strong> and <strong>B</strong> will redirect the user to PayPal for payment. <strong>C</strong> will show a iframe payment page and the customer will stay on your site.', self::$pluginName),
            'id' => 'jigoshop_paypal_advanced_template',
            'std' => 'C',
            'type' => 'select',
            'choices' => array(
                'A' => __('Layout - A', self::$pluginName),
                'B' => __('Layout - B', self::$pluginName),
                'C' => __('Layout - C', self::$pluginName)
            )
        );

        $defaults[] = array(
            'name' => __('Iframe Width', self::$pluginName),
            'desc' => __("Width of the iframe window, if Layout - C is your choosen template. Enter only numbers in pixels (i.e. 500)", self::$pluginName),
            'tip' => '',
            'id' => 'jigoshop_paypal_advanced_iframe_width',
            'std' => '500',
            'type' => 'midtext'
        );

        $defaults[] = array(
            'name' => __('Iframe Height', self::$pluginName),
            'desc' => __("Height of the iframe window, if Layout - C is your choosen template. Enter only numbers in pixels (i.e. 565)", self::$pluginName),
            'tip' => '',
            'id' => 'jigoshop_paypal_advanced_iframe_height',
            'std' => '565',
            'type' => 'midtext'
        );

        $defaults[] = array(
            'name' => __('Sandbox/Testmode', self::$pluginName),
            'desc' => '',
            'tip' => __('Enable PayPal Payments Advanced sandbox for test payments.', self::$pluginName),
            'id' => 'jigoshop_paypal_advanced_testmode',
            'std' => 'TEST',
            'type' => 'select',
            'choices' => array(
                'TEST' => __('Yes', self::$pluginName),
                'LIVE' => __('No', self::$pluginName),
            )
        );

        $defaults[] = array(
            'name' => __('Enable Debug', self::$pluginName),
            'desc' => '',
            'tip' => __('Debug log will provide you with most of the data and events generated by the payment process.', self::$pluginName),
            'id' => 'jigoshop_paypal_advanced_debug',
            'std' => 'yes',
            'type' => 'select',
            'choices' => array(
                'no' => __('No', self::$pluginName),
                'yes' => __('Yes', self::$pluginName)
            )
        );

        $defaults[] = array(
            'name' => __('Debug Email', self::$pluginName),
            'desc' => '',
            'tip' => __('Email you want to receive all logs to.<br/> If email field is empty, the admin email will be used.', self::$pluginName),
            'id' => 'jigoshop_paypal_advanced_debug_email',
            'std' => '',
            'type' => 'midtext'
        );

        return $defaults;
    }

    /*
     * Get a PayPal Advanced Secure Token
     * @param	int		$order_id The Order ID
     * @return	string	The generated token
     * @throws	Exception
     */
    protected function get_paypal_token($order_id)
    {
        $order = new \jigoshop_order($order_id);
        $params = $this->generate_request_details($order);

        //Debug log
        if ($this->debug == 'yes') {
            $this->debug_entry('Order form parameters: ' . print_r($params, true));
        }

        $request = $this->send_post($params);

        //Debug log
        if ($this->debug == 'yes') {
            $this->debug_entry('Set token response:' . print_r($request, true));
            $this->debug_report($this->debug_log);
        }

        if (!is_wp_error($request)) {
            $response = $this->string2array($request['body']);

            if ($response['RESULT'] != 0) {
                throw new \Exception(__('Error: There was an error while processing your request. Error message: ' . $response['RESPMSG'], self::$pluginName));
            } elseif (empty($response['SECURETOKEN'])) {
                throw new \Exception(__('Error: There was an error while processing your request. Secure token was empty.', self::$pluginName));
            } else {
                return $response['SECURETOKEN'];
            }
        } else {
            throw new \Exception(__('Error: There was an error while processing your request. Error message: ' . $request->get_error_message(), self::$pluginName));
        }
    }

    /**
     * Safely get GET variables
     * @var        string    Post variable name
     * @return    string    The variable value
     */
    private function getter_get($name)
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
    private function getter_post($name)
    {
        if (isset($_POST[$name]) && !empty($_POST[$name])) {
            return sanitize_text_field($_POST[$name]);
        } else {
            return '';
        }
    }

    public function payment_fields()
    {
        if ($this->description) {
            echo wpautop(wptexturize($this->description));
        }
    }

    /**
     * Process the payment and return the result
     */
    public function process_payment($order_id)
    {
        $order = new \jigoshop_order($order_id);

        try {
            // Unset before obtaining the new token
            $_SESSION['paypal_advanced_secure_token'] = '';
            $_SESSION['paypal_advanced_secure_id'] = '';

            // Set the token and id to the customer session
            $_SESSION['paypal_advanced_secure_token'] = $this->get_paypal_token($order_id);
            $_SESSION['paypal_advanced_secure_id'] = $this->secure_id;

            //Debug log
            if ($this->debug == 'yes') {
                $this->debug_entry('PayPal Advanced: Secure Token is:');
                $this->debug_entry($_SESSION['paypal_advanced_secure_token']);
            }

            return array(
                'result' => 'success',
                'redirect' => add_query_arg('order', $order->id, add_query_arg('key', $order->order_key, get_permalink(jigoshop_get_page_id('pay'))))
            );

        } catch (\Exception $e) {
            \jigoshop::add_error($e->getMessage());
        }

        return array();
    }

    /**
     * Sets a receipt page or redirects to one
     */
    public function process_receipt()
    {
        // Build an URL for customer to open or be redirected to
        $location = add_query_arg('MODE', $this->testmode, add_query_arg('SECURETOKEN', $_SESSION['paypal_advanced_secure_token'], add_query_arg('SECURETOKENID', $_SESSION['paypal_advanced_secure_id'], $this->redirecturl)));

        if ($this->debug == 'yes') {
            $this->debug_entry('PayPal Advanced: Redirect URl: ' . $location);
            $this->debug_report($this->debug_log);
        }

        if ($this->template == 'C') {
            echo '<iframe src="' . $location . '" width="' . $this->iframe_width . '" height="' . $this->iframe_height . '" border="0" frameborder="0" scrolling="no" allowtransparency="true">\n</iframe>';
        } else {
            $this->pp_redirect($location);
            exit;
        }
    }

    /**
     * Send the request and return the response
     * @param    array $params The build request parameters
     * @return    mixed    The received post response
     */
    private function send_post($params)
    {
        $params['USER'] = (!empty($this->user)) ? $this->user : $this->vendor;
        $params['VENDOR'] = $this->vendor;
        $params['PARTNER'] = $this->partner;
        $params['PWD'] = $this->password;

        $str_params = $this->array2string($params);

        if ($this->debug == 'yes') {
            $this->debug_entry('Request string is: ' . $str_params);
        }

        $response = wp_remote_post($this->posturl, array(
            'method' => 'POST',
            'body' => $str_params,
            'timeout' => 60,
            'sslverify' => apply_filters('https_local_ssl_verify', false),
        ));

        return $response;
    }

    public function set_text_localization()
    {
        load_textdomain(self::$pluginName, WP_LANG_DIR . '/my-plugin/' . self::$pluginName . '-' . get_locale() . '.mo');
        load_plugin_textdomain(self::$pluginName, false, dirname(plugin_basename(__FILE__)) . '/languages/');
    }

    public function set_thankyou_message($message)
    {
        if (isset($_GET['paypal-error'])) {
            $message = apply_filters('paypal-advanced-error-message', "<p>" . __('We are sorry, but your payment did not go through. Please try again or contact us for help.', self::$pluginName) . "</p>");
        } elseif (isset($_GET['paypal-declined'])) {
            $message = apply_filters('paypal-advanced-declined-message', "<p>" . __('We are sorry, but your payment was declined. Please try again or contact us for help.', self::$pluginName) . "</p>");
        }

        return $message;
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

    public function usage_notification()
    {
        if ($this->enabled == 'no') {
            return;
        }

        $options = \Jigoshop_Base::get_options();

        if (!$this->check_supported_currency() && \Jigoshop_Base::get_options()->get_option('jigoshop_paypal_advanced_enabled') == 'yes') {
            echo '<div class="error"><p>' . __('Attention: Your store currency is not supported by PayPal Advanced Gateway. PayPal Advanced is disabled. Supported currency is US Dollar (USD).', self::$pluginName) . '</p></div>';
            $options->set_option('jigoshop_paypal_advanced_enabled', 'no');
        }
    }

    protected function pp_redirect($url)
    {
        $string = '<script type="text/javascript">';
        $string .= 'window.location = "' . $url . '"';
        $string .= '</script>';

        echo $string;
    }
}
