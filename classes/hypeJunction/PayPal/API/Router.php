<?php

namespace hypeJunction\PayPal\API;

use hypeJunction\Payments\Transaction;

class Router {

	/**
	 * Route payment pages
	 *
	 * @param string $hook   "route"
	 * @param string $type   "payments"
	 * @param mixed  $return New route
	 * @param array  $params Hook params
	 * @return array
	 */
	public static function controller($hook, $type, $return, $params) {

		if (!is_array($return)) {
			return;
		}

		$segments = (array) elgg_extract('segments', $return);

		if ($segments[0] !== 'paypal') {
			return;
		}

		if ($segments[1] !== 'api') {
			return;
		}

		$forward_url = false;

		$adapter = new Adapter();
		$transaction_id = get_input('transaction_id');
		$ia = elgg_set_ignore_access(true);
		$transaction = Transaction::getFromID($transaction_id);

		$forward_reason = null;
		if ($transaction) {
			switch ($segments[2]) {
				case 'success' :
					$adapter->executePayment($transaction);
					system_message(elgg_echo('payments:paypal:api:transaction:successful'));
					$forward_url = get_input('forward_url');
					if (!$forward_url) {
						$forward_url = "payments/transaction/$transaction_id";
					}
					break;

				case 'cancel' :
					$adapter->cancelPayment($transaction);
					register_error(elgg_echo('payments:paypal:api:transaction:cancelled'));
					$forward_url = get_input('forward_url');
					if (!$forward_url) {
						$forward_url = "payments/transaction/$transaction_id";
					}
					break;

				case 'webhook' :
					if ($adapter->digestWebhook()) {
						echo 'Webhook digested';
						return false;
					}
					$forward_url = '';
					$forward_reason = '400';
					break;
			}
		}

		elgg_set_ignore_access($ia);

		if ($forward_url) {
			forward($forward_url, $forward_reason);
		}
	}

	/**
	 * Add IPN processor to public pages
	 *
	 * @param string $hook   "public_pages"
	 * @param string $type   "walled_garden"
	 * @param array  $return Public pages
	 * @param array  $params Hook params
	 * @return array
	 */
	public static function setPublicPages($hook, $type, $return, $params) {
		$return[] = 'payments/paypal/api';
		$return[] = 'payments/paypal/api/.*';
		return $return;
	}

}
