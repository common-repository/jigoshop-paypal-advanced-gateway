<?php

namespace Jigoshop\Extension\PaypalPaymentsAdvancedGateway;


use Jigoshop\Integration;
use Jigoshop\Container;

class Common
{
	public function __construct()
	{
		Integration::addPsr4Autoload(__NAMESPACE__ . '\\', __DIR__);
		Integration\Helper\Render::addLocation('paypal_advanced', JIGOSHOP_PAYPAL_ADVANCED_DIR);
		/**@var Container $di*/
		$di = Integration::getService('di');
		$di->services->setDetails('jigoshop.payment.paypal_advanced', __NAMESPACE__ . '\\Common\\Method', array(
			'jigoshop.options',
			'jigoshop.service.cart',
			'jigoshop.service.order',
			'jigoshop.messages',
		));

		$di->triggers->add('jigoshop.service.payment', 'addMethod', array('jigoshop.payment.paypal_advanced'));
	}
}
new Common();