<?php

use hypeJunction\Payments\Transaction;
use hypeJunction\PayPal\API\Adapter;

$ia = elgg_set_ignore_access(true);

$transaction_id = get_input('transaction_id');
$transaction = Transaction::getFromId($transaction_id);

$error = false;
if ($transaction) {
	$merchant = $transaction->getMerchant();
	if ($merchant->paypal_email) {
		$payee_email = $merchant->paypal_email;
	} else {
		$payee_email = elgg_get_plugin_setting('paypal_email', 'payments_paypal_api');
	}
	$payee_email = elgg_trigger_plugin_hook('payee_email', 'paypal', [
		'transction' => $transaction,
	], $payee_email);

	$paypal_adapter = new Adapter();
	$paypal_adapter->setPayeeEmail($payee_email);
	$response = $paypal_adapter->pay($transaction);
} else {
	$error = elgg_echo('payments:error:not_found');
	$status_code = ELGG_HTTP_NOT_FOUND;
	$forward_url = REFERRER;
}

elgg_set_ignore_access($ia);

if ($error) {
	return elgg_error_response($error, $forward_url, $status_code);
}

return $response;

