=== Jigoshop PayPal Payments Advanced Gateway ===
Contributors: jigoshop
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=7F5FZ5NGJ3XTL
Tags: ecommerce, wordpress ecommerce, gateway, shop, shopping, cart, checkout, paypal, reports, tax, paypal, jigowatt, online, sell, sales, configurable, variable, downloadable, external, affiliate, download, virtual, physical, payment, pro, payments, advanced
Requires at least: 4.0
Tested up to: 4.8.2
Requires PHP: 5.6
Stable tag: 3.3

This plugin allows you to use PayPal Payments Advanced gateway within your Jigoshop store.

== Description ==

Take your PayPal payments to the next level with the PayPal Advanced Gateway Extension for Jigoshop. PayPal Standard is included in the core Jigoshop download, but when you want to seamlessly integrate credit card payments into your eCommerce shop, then this plugin will solve your problem. This gateway allows you to take credit card payments via PayPal without needing an SSL certificate, but still integrating the process seamlessly into your store. By combining a PayPal Merchant Account and Payment Gateway into one solution you have everything you need. It also accepts normal PayPal and Bill Me Later options. With PayPal Advanced  you can also use PayPal Here to take payments via a mobile phone, and email PayPal generated invoices within the same Merchant Account. If you want customers to stay onsite throughout the whole process, they may receive ‘Mixed Content’ warnings if you do not have an SSL Certificate in place. You can learn more about PayPal Advanced on the PayPal website, and see how it simplifies PCI compliance. US Dollars Only.
PayPal Payments Advanced lets merchants:
= Focus on their business =
* Use this all-in-one solution to have all customer payments conveniently deposited into their PayPal account.
= Accept credit and debit cards, PayPal, and PayPal Credit® =
* Accept all major debit and credit cards, including Visa, MasterCard® , American Express, Discover, JCB, and Diners Club.
* Offer Express Checkout and PayPal Credit as additional payment options.
= Customize the checkout experience — Merchants can host checkout pages on their website using PayPal’s embedded template, or let PayPal host the checkout on their secure servers. =
= Get paid quickly — After a payment is processed, the money usually shows up in a merchant’s PayPal business account within minutes. =
= Simplify PCI compliance — With PayPal Payments Advanced, PayPal handles data security for the merchant, helping to reduce their workload for proving PCI compliance. =
= Stay informed with reporting =
* Choose from various reports, including transaction status and settlement.
* Customize reporting with APIs that integrate into merchants’ back-office applications.
* Use post-authorization APIs to send transaction data to their backend solution.
= Add levels of fraud protection — Add Basic or Advanced Fraud Protection Services to help protect against fraud threats.  Available for an additional fee. =
= Set up recurring payments — Add the Recurring Billing Service to automatically debit customers’ debit or credit cards. Available for an additional fee. =
Fees and pricing:
* $5 a month + standard transaction fees,
* No startup costs, no termination fee,

== Installation ==

This section describes how to install the plugin and get it working.

1. Upload the plugin folder to the '/wp-content/plugins/' directory
1. Activate plugin through the 'Plugins' menu in WordPress
1. Go to 'Jigoshop/Manage Licenses' to enter license key for your plugin
1. Navigate to Jigoshop > Settings > Payment to configure the Payments Advanced gateway settings.
= Steps for configuration of your PayPal Advanced installation. =
1. Login to your Jigoshop store 
1. Enable PayPal Advanced.
1. Set your "Method Title" and "Description". These options are seen on the checkout page. 
1. Enter your PayPal Advanced User ID, if applicable. 
1. Enter your PayPal Advanced Vendor ID.Parter ID 
1. Enter your PayPal Advanced Parter ID. 
1. Enter your PayPal Advanced Password. 
1. Choose "Transaction Type" you want to process. 
1. Choose the payment page template. The option has to be the same as the one in your manager.paypal account 1. Set the "Iframe Width/Height", if "Layout - C" is chosen. 
1. Choose if the gateway will process Test or Live transactions. 
1. Enable "Debug Log", if you want to get a log of the request and response steps and parameters. 
1. Set the email you want to receive debug emails to. 
1. Save Changes.
= Options =
Enable PayPal Advanced: Enable PayPal Advanced.
Method Title: This controls the title which the user sees during checkout.
Description: This controls the description which the user sees during checkout.
User ID: If you set up one or more additional users on the PayPal Advanced account, this value is the ID of the user authorized to process transactions. Leave blank, if you don't have additional accounts.
Vendor ID: Your merchant login ID that you created when you registered for the PayPal Advanced account.
Parter ID: The ID provided to you by the authorized PayPal Reseller who registered you for the Gateway gateway. If you purchased your account directly from PayPal, use PayPal.
Password: The password that you defined while registering for the PayPal Advanced account.
Transaction Type: Choose the transaction type you want to process. Sale - for instant capture. Authorization - to authorize and capture later.
Payment Page Template: Choose the payment page template you want to use. A and B will redirect the user to PayPal for payment. C will show a iframe payment page and the customer will stay on your site.
Iframe Width: Width of the iframe window, if Layout - C is your choosen template. Enter only numbers in pixels (i.e. 500)
Iframe Height: Height of the iframe window, if Layout - C is your choosen template. Enter only numbers in pixels (i.e. 565)
Sandbox/Testmode: Enable PayPal Payments Advanced sandbox for test payments.
Debug Log: Recommended: Test Mode only Debug log will provide you with most of the data and events generated by the payment process.
Debug Email: Email you want to receive all logs to. If email field is empty, the admin email will be used.

== Changelog ==

= 3.4 =
    * New version released with small fixes
= 3.3 =
    * Small fix with order processing
= 3.2 =
    * New version release
= 3.1 =
    * Fixed issue with Company name
= 3.0 =
    * Plugin Redeveloped to Jigoshop 2.0 compatible
= 2.2.3 =
    * Added: actions links
= 2.2.2 =
    * Fixed: headers issues
= 2.2.1 =
    * Added BN code
= 2.2 =
    * Improved: Required Jigoshop version checking.
    * Improved: Match Jigoshop Guidelines for code formatting.
= 2.1 =
    * Improved: Support for Jigoshop 1.9.3 which is minimum version now.
    * Minor bug fixed
= 2.0 =
    * Initail release 
