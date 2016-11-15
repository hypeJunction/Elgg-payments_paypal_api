<?php

namespace hypeJunction\PayPal\API;

use hypeJunction\Payments\Transaction;


class Payments {


	/**
	 * Initiate a refund
	 *
	 * @param string $hook   "refund"
	 * @param string $type   "payments"
	 * @param bool   $return Success
	 * @param array  $params Hook params
	 * @return bool
	 */
	public static function refundTransaction($hook, $type, $return, $params) {
		if ($return) {
			return;
		}
		
		$transaction = elgg_extract('entity', $params);
		if (!$transaction instanceof Transaction) {
			return;
		}

		if ($transaction->payment_method == 'paypal') {
			$adapter = new Adapter();
			return $adapter->refund($transaction);
		}
	}

}
