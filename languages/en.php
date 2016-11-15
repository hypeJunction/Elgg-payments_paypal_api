<?php

return [

	'payments:paypal' => 'PayPal',
	'payments:method:paypal' => 'PayPal',
	'payments:charges:paypal_fee' => 'PayPal Fee',
	
	'payments:paypal:api:paypal_email' => 'PayPal Email to receive payments',

	'payments:paypal:api:sandbox' => 'Sandbox API credentials',
	'payments:paypal:api:sandbox_client_id' => 'Sandbox Client ID',
	'payments:paypal:api:sandbox_client_secret' => 'Sandbox Client Secret',

	'payments:paypal:api:live' => 'Live API credentials',
	'payments:paypal:api:live_client_id' => 'Live Client ID',
	'payments:paypal:api:live_client_secret' => 'Live Client Secret',

	'payments:paypal:api:transaction:successful' => 'PayPal payment successfully completed',
	'payments:paypal:api:transaction:cancelled' => 'PayPal payment was not completed',

	'payments:paypal:api:connection_error' => 'There was an error contacting PayPal',
	'payments:paypal:api:configuration_error' => 'PayPal client is not configured correctly',
	'payments:paypal:api:invalid_credentials_error' => 'PayPal credentials are invalid',
	'payments:paypal:api:missing_credentials_error' => 'PayPal credentials are missing',

	'payments:paypal:api:setup_sandbox_webhooks'=> 'Setup sandbox webhooks',
	'payments:paypal:api:setup_live_webhooks'=> 'Setup live webhooks',
	'payments:paypal:api:setup_webhooks_success' => 'Webhook [id: %s] has been configured',
	'payments:paypal:api:setup_webhooks_error' => 'There was a problem with webhook configuration',

	'payments:paypal:paypal_balance' => 'PayPal Balance',
];
