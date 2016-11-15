<?php

$namespace = get_input('namespace', 'sandbox');

$adapter = new hypeJunction\PayPal\API\Adapter();
if ($webhook_id = $adapter->setupWebhook($namespace)) {
	$message = elgg_echo('payments:paypal:api:setup_webhooks_success', [$webhook_id]);
	return elgg_ok_response([
		'webhook_id' => $webhook_id,
	], $message);
}

$error = elgg_echo('payments:paypal:api:setup_webhooks_error');
return elgg_error_response($error);