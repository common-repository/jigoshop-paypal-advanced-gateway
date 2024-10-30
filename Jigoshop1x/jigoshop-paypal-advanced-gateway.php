<?php

add_action('plugins_loaded', 'add_jigoshop_paypal_advanced_gateway', 0);

function add_jigoshop_paypal_advanced_gateway()
{
    require_once(JIGOSHOP_PAYPAL_ADVANCED_DIR . '/src/Jigoshop/Gateway/PayPalAdvanced.php');
    add_filter('jigoshop_payment_gateways', function ($methods) {
        $methods[] = '\\Jigoshop\\Gateway\\PayPalAdvanced';

        return $methods;
    });

}

if (is_admin()) {
    add_filter('plugin_action_links_' . plugin_basename(dirname(JIGOSHOP_PAYPAL_ADVANCED_DIR) . '/bootstrap.php'),
        function ($links) {
            $links[] = '<a href="https://www.jigoshop.com/documentation/paypal-advanced/" target="_blank">Documentation</a>';
            $links[] = '<a href="https://www.jigoshop.com/support/" target="_blank">Support</a>';
            $links[] = '<a href="https://wordpress.org/support/view/plugin-reviews/jigoshop#postform" target="_blank">Rate Us</a>';
            $links[] = '<a href="https://www.jigoshop.com/product-category/extensions/" target="_blank">More plugins for Jigoshop</a>';
            return $links;
        });
}