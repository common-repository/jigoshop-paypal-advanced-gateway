<?php
/**
 * Plugin Name: Jigoshop PayPal Advanced Gateway
 * Plugin URI: https://wordpress.org/plugins/jigoshop-paypal-advanced-gateway/
 * Description: Allows you to use <a href="https://www.paypal.com/webapps/mpp/paypal-payments-advanced">PayPal Payments Advanced</a> with the Jigoshop plugin.
 * Version: 3.3
 * Author: Jigoshop
 * Author URI: https://www.jigoshop.com
 */
// Define plugin name
define('JIGOSHOP_PAYPAL_ADVANCED_NAME', 'Jigoshop PayPal Advanced Gateway');
add_action('plugins_loaded', function () {
    load_plugin_textdomain('jigoshop_paypal_advanced_gateway', false, dirname(plugin_basename(__FILE__)) . '/languages/');
    if (class_exists('\Jigoshop\Core')) {
        //Check version.
        if (\Jigoshop\addRequiredVersionNotice(JIGOSHOP_PAYPAL_ADVANCED_NAME, '2.0')) {
           return;
        }
        // Define plugin directory for inclusions
        define('JIGOSHOP_PAYPAL_ADVANCED_DIR', dirname(__FILE__));
        // Define plugin URL for assets
        define('JIGOSHOP_PAYPAL_ADVANCED_URL', plugins_url('', __FILE__));
        //Init components.
        require_once(JIGOSHOP_PAYPAL_ADVANCED_DIR . '/src/Jigoshop/Extension/PaypalPaymentsAdvancedGateway/Common.php');
        if (is_admin()) {
            require_once(JIGOSHOP_PAYPAL_ADVANCED_DIR . '/src/Jigoshop/Extension/PaypalPaymentsAdvancedGateway/Admin.php');
        }
    } elseif (class_exists('jigoshop')) {
        //Check version.
        if (jigoshop_add_required_version_notice(JIGOSHOP_PAYPAL_ADVANCED_NAME, '1.17')) {
            return;
        }

        // Define plugin directory for inclusions
        define('JIGOSHOP_PAYPAL_ADVANCED_DIR', dirname(__FILE__) . '/Jigoshop1x');
        // Define plugin URL for assets
        define('JIGOSHOP_PAYPAL_ADVANCED_URL', plugins_url('', __FILE__) . '/Jigoshop1x');
        //Init components.
        require_once(JIGOSHOP_PAYPAL_ADVANCED_DIR . '/jigoshop-paypal-advanced-gateway.php');
    } else {
        add_action('admin_notices', function () {
            echo '<div class="error"><p>';
            printf(__('%s requires Jigoshop plugin to be active. Code for plugin %s was not loaded.',
                'jigoshop_paypal_advanced_gateway'), JIGOSHOP_PAYPAL_ADVANCED_NAME, JIGOSHOP_PAYPAL_ADVANCED_NAME);
            echo '</p></div>';
        });
    }
});
