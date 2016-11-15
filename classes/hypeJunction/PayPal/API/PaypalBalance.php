<?php

namespace hypeJunction\PayPal\API;

use hypeJunction\Payments\FundingSourceInterface;

class PaypalBalance implements FundingSourceInterface {

	public function serialize() {
		return serialize(get_object_vars($this));
	}

	public function unserialize($serialized) {
		$data = unserialize($serialized);
		foreach ($data as $key => $value) {
			$this->$key = $value;
		}
	}

	public function format() {
		return elgg_echo('payments:paypal:paypal_balance');
	}

}
