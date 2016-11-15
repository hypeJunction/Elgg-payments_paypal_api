<?php

/**
 * PayPal API Payments
 *
 * @author Ismayil Khayredinov <info@hypejunction.com>
 * @copyright Copyright (c) 2016, Ismayil Khayredinov
 * @copyright Copyright (c) 2016, Social Business World
 */
require_once __DIR__ . '/autoloader.php';

use hypeJunction\PayPal\API\Payments;
use hypeJunction\PayPal\API\Router;

elgg_register_event_handler('init', 'system', function() {
	
	elgg_register_plugin_hook_handler('route', 'payments', [Router::class, 'controller'], 100);
	elgg_register_plugin_hook_handler('public_pages', 'walled_garden', [Router::class, 'setPublicPages']);

	elgg_register_plugin_hook_handler('refund', 'payments', [Payments::class, 'refundTransaction']);

	elgg_register_action('payments/checkout/paypal', __DIR__ . '/actions/payments/checkout/paypal.php', 'public');
	elgg_register_action('paypal_api/setup_webhooks', __DIR__ . '/actions/paypal_api/setup_webhooks.php', 'admin');
});

