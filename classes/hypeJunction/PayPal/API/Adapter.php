<?php

namespace hypeJunction\PayPal\API;

use Exception;
use hypeJunction\Payments\Amount;
use hypeJunction\Payments\ChargeInterface;
use hypeJunction\Payments\CreditCard;
use hypeJunction\Payments\GatewayInterface;
use hypeJunction\Payments\OrderItemInterface;
use hypeJunction\Payments\Payment;
use hypeJunction\Payments\Refund;
use hypeJunction\Payments\ShippingFee;
use hypeJunction\Payments\Tax;
use hypeJunction\Payments\Transaction;
use hypeJunction\Payments\TransactionInterface;
use PayPal\Api\Amount as PayPalAmount;
use PayPal\Api\Details;
use PayPal\Api\FundingInstrument;
use PayPal\Api\Item;
use PayPal\Api\ItemList;
use PayPal\Api\Payee;
use PayPal\Api\Payer;
use PayPal\Api\Payment as PayPalPayment;
use PayPal\Api\PaymentExecution;
use PayPal\Api\RedirectUrls;
use PayPal\Api\Refund as PayPalRefund;
use PayPal\Api\Sale;
use PayPal\Api\ShippingAddress;
use PayPal\Api\Transaction as PayPalTransaction;
use PayPal\Api\VerifyWebhookSignature;
use PayPal\Api\Webhook;
use PayPal\Api\WebhookEvent;
use PayPal\Api\WebhookEventType;
use PayPal\Auth\OAuthTokenCredential;
use PayPal\Exception\PayPalConfigurationException;
use PayPal\Exception\PayPalConnectionException;
use PayPal\Exception\PayPalInvalidCredentialException;
use PayPal\Exception\PayPalMissingCredentialException;
use PayPal\Rest\ApiContext;

class Adapter implements GatewayInterface {

	/**
	 * @var string
	 */
	protected $payee_email;

	/**
	 * Set payee email
	 * 
	 * @param string $email Payee email
	 */
	public function setPayeeEmail($email) {
		$this->payee_email = $email;
	}

	/**
	 * {@inheritdoc}
	 */
	public function pay(TransactionInterface $transaction) {
		$forward_url = $this->getPaymentUrl($transaction);

		if (!$forward_url) {
			$transaction->setStatus(Transaction::STATUS_FAILED);
			$error = elgg_echo('payments:paypal:api:connection_error');
			$status_code = ELGG_HTTP_INTERNAL_SERVER_ERROR;
			$forward_url = $transaction->getURL();
			return elgg_error_response($error, $forward_url, $status_code);
		}

		return elgg_redirect_response($forward_url);
	}

	/**
	 * Get payment URL
	 *
	 * @param TransactionInterface $transaction Transaction object
	 * @return string
	 */
	public function getPaymentUrl(TransactionInterface $transaction) {

		$transaction->setStatus(TransactionInterface::STATUS_PAYMENT_PENDING);

		$merchant = $transaction->getMerchant();

		$payee = new Payee();
		$payee->setEmail($this->payee_email);

		$amount = $transaction->getAmount();
		$total = $amount->getConvertedAmount();
		$currency = $amount->getCurrency();

		$description = $transaction->getDisplayName();
		if (!$description) {
			$description = "Payment to {$merchant->getDisplayName()}";
		}

		$paypal_transaction = new PayPalTransaction();
		$paypal_transaction->setPayee($payee)
				->setInvoiceNumber($transaction->transaction_id)
				->setDescription($description);

		$item_list = new ItemList();

		$order = $transaction->getOrder();
		if ($order) {
			$items = [];
			$order_items = $order->all();
			foreach ($order_items as $order_item) {
				/* @var $order_item OrderItemInterface */
				if ($order_item->sku) {
					$sku = $order_item->sku;
				} else {
					$mid = (int) $merchant->guid;
					$iid = (int) $order_item->getId();
					$sku = "$mid-$iid";
				}
				$item = new Item();
				$item->setName($order_item->getTitle() . " ($sku)")
						->setCurrency($currency)
						->setQuantity($order_item->getQuantity())
						->setSku($sku)
						->setPrice($order_item->getPrice()->getConvertedAmount());
				$items[] = $item;
			}

			$subtotal = $order->getSubtotalAmount()->getAmount();
			$shipping = 0;
			$tax = 0;
			$charges_amount = $order->getChargesAmount();
			$order_charges = $order->getCharges();
			foreach ($order_charges as $order_charge) {
				/* @var $order_charge ChargeInterface */
				if ($order_charge instanceof ShippingFee) {
					$shipping += $order_charge->getTotalAmount()->getConvertedAmount();
				} else if ($order_charge instanceof Tax) {
					$tax += $order_charge->getTotalAmount()->getConvertedAmount();
				} else {
					$item = new Item();
					$item->setName(elgg_echo("payments:charge:{$order_charge->getId()}"))
							->setCurrency($currency)
							->setQuantity(1)
							->setSku($order_charge->getId())
							->setPrice($order_charge->getTotalAmount()->getConvertedAmount());

					$subtotal += $order_charge->getTotalAmount()->getAmount();
					$items[] = $item;
				}
			}

			$subtotal = (new Amount($subtotal, $currency))->getConvertedAmount();
			$shipping = (new Amount($shipping, $currency))->getConvertedamount();
			$tax = (new Amount($tax, $currency))->getConvertedAmount();

			$details = new Details();
			$details->setSubtotal($subtotal);

			if ($shipping) {
				$details->setShipping($shipping);
			}

			if ($tax) {
				$details->setTax($tax);
			}

			$item_list->setItems($items);

			$amount = new PayPalAmount();
			$amount->setCurrency($currency)
					->setTotal($total)
					->setDetails($details);

			$paypal_transaction->setAmount($amount);

			$order_shipping_address = $order->getShippingAddress();
			if ($order_shipping_address) {
				$shipping_address = new ShippingAddress();

				$shipping_address->setCity($order_shipping_address->locality);
				$shipping_address->setCountryCode($order_shipping_address->country_code);
				$shipping_address->setPostalCode($order_shipping_address->postal_code);
				$shipping_address->setLine1($order_shipping_address->street_address);
				$shipping_address->setLine2($order_shipping_address->extended_address);
				$shipping_address->setState($order_shipping_address->region);
				$shipping_address->setRecipientName($order->getCustomer()->name);

				$item_list->setShippingAddress($shipping_address);
			}
			$paypal_transaction->setItemList($item_list);
		} else {
			$amount = new PayPalAmount();
			$amount->setCurrency($currency)
					->setTotal($total);
		}

		$paypal_transaction->setAmount($amount);

		$success = elgg_normalize_url(elgg_http_add_url_query_elements('payments/paypal/api/success', [
			'transaction_id' => $transaction->transaction_id,
			'forward_url' => $merchant->getURL(),
		]));

		$cancel = elgg_normalize_url(elgg_http_add_url_query_elements('payments/paypal/api/cancel', [
			'transaction_id' => $transaction->transaction_id,
			'forward_url' => $merchant->getURL(),
		]));

		$redirectUrls = new RedirectUrls();
		$redirectUrls->setReturnUrl($success)
				->setCancelUrl($cancel);

		$payer = new Payer();
		$payer->setPaymentMethod("paypal");

		$payment = new PayPalPayment();
		$payment->setIntent("sale")
				->setPayer($payer)
				->setRedirectUrls($redirectUrls)
				->setTransactions([$paypal_transaction]);

		try {
			$payment->create($this->getApiContext());
		} catch (PayPalConnectionException $ex) {
			register_error(elgg_echo('payments:paypal:api:connection_error'));
			elgg_log($ex->getMessage() . ': ' . print_r(json_decode($ex->getData()), true), 'ERROR');
			return false;
		} catch (PayPalConfigurationException $ex) {
			register_error(elgg_echo('payments:paypal:api:configuration_error'));
			return false;
		} catch (PayPalInvalidCredentialException $ex) {
			register_error(elgg_echo('payments:paypal:api:invalid_credentials_error'));
			return false;
		} catch (PayPalMissingCredentialException $ex) {
			register_error(elgg_echo('payments:paypal:api:missing_credentials_error'));
			return false;
		} catch (Exception $ex) {
			register_error($ex->getMessage());
			return false;
		}

		return $payment->getApprovalLink();
	}

	/**
	 * Execute approved payment
	 *
	 * @param Transaction $transaction Transaction object
	 * @return bool
	 */
	public function executePayment(TransactionInterface $transaction) {

		$payment_id = get_input('paymentId');
		$payment = PayPalPayment::get($payment_id, $this->getApiContext());

		$payer_id = get_input('PayerID');
		$execution = new PaymentExecution();
		$execution->setPayerId($payer_id);


		try {
			$transaction->paypal_payment_id = $payment->getId();
			$payment->execute($execution, $this->getApiContext());
		} catch (PayPalConnectionException $ex) {
			register_error(elgg_echo('payments:paypal:api:connection_error'));
			elgg_log($ex->getMessage() . ': ' . print_r(json_decode($ex->getData()), true), 'ERROR');
			return false;
		} catch (PayPalConfigurationException $ex) {
			register_error(elgg_echo('payments:paypal:api:configuration_error'));
			return false;
		} catch (PayPalInvalidCredentialException $ex) {
			register_error(elgg_echo('payments:paypal:api:invalid_credentials_error'));
			return false;
		} catch (PayPalMissingCredentialException $ex) {
			register_error(elgg_echo('payments:paypal:api:missing_credentials_error'));
			return false;
		} catch (Exception $ex) {
			register_error($ex->getMessage());
			return false;
		}

		if ($payment->getState() == 'failed') {
			$transaction->setStatus(TransactionInterface::STATUS_FAILED);
			return false;
		} else {
			// We can't say for sure what the status of the payment is
			// If funded by e-check, this API endpoint tells us that the payment is complete
			// whereas in fact the payment is pending
			// So, we are going to wait for a webhook to let us know for use
			$this->updateTransactionStatus($transaction);
		}

		return true;
	}

	/**
	 * Cancel payment
	 * @return bool
	 */
	public function cancelPayment(TransactionInterface $transaction) {
		$transaction->setStatus(TransactionInterface::STATUS_FAILED);
		return true;
	}

	/**
	 * Process a webhook
	 * @return bool
	 */
	public function digestWebhook() {

		try {
			$request_content = _elgg_services()->request->getContent();
			$headers = _elgg_services()->request->headers;

			$verification = new VerifyWebhookSignature();
			$verification->setAuthAlgo($headers->get('PAYPAL-AUTH-ALGO'));
			$verification->setTransmissionId($headers->get('PAYPAL-TRANSMISSION-ID'));
			$verification->setCertUrl($headers->get('PAYPAL-CERT-URL'));
			$verification->setWebhookId($this->setupWebhook());
			$verification->setTransmissionSig($headers->get('PAYPAL-TRANSMISSION-SIG'));
			$verification->setTransmissionTime($headers->get('PAYPAL-TRANSMISSION-TIME'));

			$webhook_event = new WebhookEvent();
			$webhook_event->fromJson($request_content);

			$verification->setWebhookEvent($webhook_event);

			$response = $verification->post($this->getApiContext());
			if ($response->getVerificationStatus() !== 'SUCCESS') {
				return false;
			}
		} catch (PayPalConnectionException $ex) {
			elgg_log($ex->getMessage() . ': ' . print_r(json_decode($ex->getData()), true), 'ERROR');
			return false;
		} catch (PayPalConfigurationException $ex) {
			return false;
		} catch (PayPalInvalidCredentialException $ex) {
			return false;
		} catch (PayPalMissingCredentialException $ex) {
			return false;
		} catch (Exception $ex) {
			return false;
		}

		$data = json_decode($request_content, true);

		$transaction_id = $data['resource']['invoice_number'];
		$transaction = Transaction::getFromId($transaction_id);
		if (!$transaction) {
			return false;
		}

		switch ($data['event_type']) {
			case 'PAYMENT.SALE.PENDING' :
			case 'PAYMENT.SALE.COMPLETED' :
			case 'PAYMENT.SALE.REFUNDED' :
			case 'PAYMENT.SALE.DENIED' :
			case 'PAYMENT.SALE.REVERSED' :
				$this->updateTransactionStatus($transaction);
				break;
		}

		$params = [
			'webhook_event' => $webhook_event,
			'webhook_data' => $data,
			'transaction' => $transaction,
		];

		if (elgg_trigger_plugin_hook('digest:webhook', 'paypal_api', $params, true)) {
			return true;
		}
	}

	/**
	 * Update transaction status via API call
	 * 
	 * @param TransactionInterface $transaction Transaction
	 * @return TransactionInterface
	 */
	public function updateTransactionStatus(TransactionInterface $transaction) {

		if (!$transaction->paypal_payment_id) {
			return $transaction;
		}

		try {
			$payment = PayPalPayment::get($transaction->paypal_payment_id, $this->getApiContext());
			$paypal_transaction = array_shift($payment->getTransactions());
			/* @var $paypal_transaction PayPalTransaction */

			foreach ($paypal_transaction->getRelatedResources() as $related) {
				if ($related->getSale()) {
					$sale = $related->getSale();
					break;
				}
			}

			if (!$sale) {
				return $transaction;
			}

			if (!$transaction->paypal_sale_id) {
				$transaction->paypal_sale_id = $sale->getId();
				switch ($payment->getPayer()->getPaymentMethod()) {
					case 'paypal' :
						$transaction->setFundingSource(new PaypalBalance());
						break;

					case 'credit_card' :
						$instruments = $payment->getPayer()->getFundingInstruments();
						if ($instruments) {
							$instrument = array_shift($instruments);
							if ($instrument) {
								/* @var $instrument FundingInstrument */
								$credit_card = $instrument->getCreditCard();
								if ($credit_card) {
									$cc = new CreditCard();
									$cc->id = $credit_card->getId();
									$cc->last4 = substr($credit_card->getNumber(), -4);
									$cc->brand = $credit_card->getType();
									$cc->exp_month = $credit_card->getExpireMonth();
									$cc->exp_year = $credit_card->getExpireYear();
									$transaction->setFundingSource($cc);
								}
							}
						}
						break;
				}
			}
		} catch (PayPalConnectionException $ex) {
			register_error(elgg_echo('payments:paypal:api:connection_error'));
			elgg_log($ex->getMessage() . ': ' . print_r(json_decode($ex->getData()), true), 'ERROR');
			return $transaction;
		} catch (PayPalConfigurationException $ex) {
			register_error(elgg_echo('payments:paypal:api:configuration_error'));
			return $transaction;
		} catch (PayPalInvalidCredentialException $ex) {
			register_error(elgg_echo('payments:paypal:api:invalid_credentials_error'));
			return $transaction;
		} catch (PayPalMissingCredentialException $ex) {
			register_error(elgg_echo('payments:paypal:api:missing_credentials_error'));
			return $transaction;
		} catch (Exception $ex) {
			register_error($ex->getMessage());
			return $transaction;
		}

		switch ($sale->getState()) {
			case 'created' :
			case 'pending' :
			case 'processed' :
				if ($transaction->status != TransactionInterface::STATUS_PAYMENT_PENDING) {
					$transaction->setStatus(TransactionInterface::STATUS_PAYMENT_PENDING);
				}
				break;

			case 'completed' :
				if ($transaction->status != TransactionInterface::STATUS_PAID) {
					$payment = new Payment();
					$payment->setTimeCreated(time())
							->setAmount(Amount::fromString($sale->getAmount()->getTotal(), $sale->getAmount()->getCurrency()))
							->setPaymentMethod('paypal')
							->setDescription(elgg_echo('payments:payment'));
					$transaction->addPayment($payment);
					$transaction->setStatus(TransactionInterface::STATUS_PAID);

					$processor_fee = Amount::fromString((string) $sale->getTransactionFee()->getValue(), $sale->getTransactionFee()->getCurrency());
					$transaction->setProcessorFee($processor_fee);
				}
				break;

			case 'refunded' :
			case 'partially_refunded' :

				if ($sale->getState() == 'refunded') {
					if ($transaction->status != TransactionInterface::STATUS_REFUNDED) {
						$transaction->setStatus(TransactionInterface::STATUS_REFUNDED);
					}
				} else {
					if ($transaction->status != TransactionInterface::STATUS_PARTIALLY_REFUNDED) {
						$transaction->setStatus(TransactionInterface::STATUS_PARTIALLY_REFUNDED);
					}
				}


				$payments = $transaction->getPayments();
				$payment_ids = array_map(function($payment) {
					return $payment->paypal_refund_id;
				}, $payments);

				foreach ($paypal_transaction->getRelatedResources() as $related) {
					if (!$related->getRefund()) {
						continue;
					}

					$paypal_refund = $related->getRefund();

					if (in_array($paypal_refund->getId(), $payment_ids)) {
						continue;
					}

					/**
					 * @todo: deduct refunded paypal fee from processor fee amount
					 * Currently, not possible because PP API is dumb
					 * https://github.com/paypal/PayPal-Ruby-SDK/issues/106#issuecomment-262592048
					 */

					$refund = new Refund();
					$refund->setTimeCreated(strtotime($paypal_refund->getCreateTime()))
							->setAmount(Amount::fromString((string) -$paypal_refund->getAmount()->getTotal(), $paypal_refund->getAmount()->getCurrency()))
							->setPaymentMethod('paypal')
							->setDescription(elgg_echo('payments:refund'));
					$refund->paypal_refund_id = $paypal_refund->getId();
					$transaction->addPayment($refund);
				}

				break;
		}

		return $transaction;
	}

	/**
	 * {@inheritdoc}
	 */
	public function refund(TransactionInterface $transaction) {

		$this->updateTransactionStatus($transaction);
		if (!$transaction->paypal_sale_id) {
			return false;
		}

		$transaction->setStatus(TransactionInterface::STATUS_REFUND_PENDING);

		try {
			$sale = Sale::get($transaction->paypal_sale_id, $this->getApiContext());
			$amount = $sale->getAmount();
			// Sale amount includes details, which apparently PayPal API doesn't like for refunds
			$refund_amount = new PayPalAmount();
			$refund_amount->setTotal($amount->getTotal())
					->setCurrency($amount->getCurrency());

			$refund = new PayPalRefund();
			$refund->setAmount($refund_amount)
					->setInvoiceNumber($transaction->transaction_id);

			$sale->refund($refund, $this->getApiContext());
		} catch (PayPalConnectionException $ex) {
			register_error(elgg_echo('payments:paypal:api:connection_error'));
			elgg_log($ex->getMessage() . ': ' . print_r(json_decode($ex->getData()), true), 'ERROR');
			return false;
		} catch (PayPalConfigurationException $ex) {
			register_error(elgg_echo('payments:paypal:api:configuration_error'));
			return false;
		} catch (PayPalInvalidCredentialException $ex) {
			register_error(elgg_echo('payments:paypal:api:invalid_credentials_error'));
			return false;
		} catch (PayPalMissingCredentialException $ex) {
			register_error(elgg_echo('payments:paypal:api:missing_credentials_error'));
			return false;
		} catch (Exception $ex) {
			register_error($ex->getMessage());
			return false;
		}

		$this->updateTransactionStatus($transaction);
		return true;
	}

	/**
	 * Setup webhook
	 * @return string Webhook id
	 */
	public function setupWebhook() {

		$environment = elgg_get_plugin_setting('environment', 'payments', 'sandbox');
		try {
			$webhook_id = elgg_get_plugin_setting("webhook:$environment", 'payments_paypal_api');
			if ($webhook_id) {
				$webhook = Webhook::get($webhook_id, $this->getApiContext());
				$webhook->delete($this->getApiContext());
			}

			$webhook = new Webhook();
			$url = elgg_normalize_url("payments/paypal/api/webhook");
			$webhook->setUrl($url);

			$event_types = WebhookEventType::availableEventTypes($this->getApiContext())->getEventTypes();
			$webhook->setEventTypes($event_types);

			$response = $webhook->create($this->getApiContext());
			$webhook_id = $response->getId();

			elgg_set_plugin_setting("webhook:$environment", $webhook_id, 'payments_paypal_api');
			return $webhook_id;
		} catch (PayPalConnectionException $ex) {
			register_error(elgg_echo('payments:paypal:api:connection_error'));
			elgg_log($ex->getMessage() . ': ' . print_r(json_decode($ex->getData()), true), 'ERROR');
			return false;
		} catch (PayPalConfigurationException $ex) {
			register_error(elgg_echo('payments:paypal:api:configuration_error'));
			return false;
		} catch (PayPalInvalidCredentialException $ex) {
			register_error(elgg_echo('payments:paypal:api:invalid_credentials_error'));
			return false;
		} catch (PayPalMissingCredentialException $ex) {
			register_error(elgg_echo('payments:paypal:api:missing_credentials_error'));
			return false;
		} catch (Exception $ex) {
			register_error($ex->getMessage());
			return false;
		}
	}

	/**
	 * Returns PP API context
	 * @return ApiContext
	 */
	public function getApiContext() {
		$plugin = elgg_get_plugin_from_id('payments_paypal_api');
		$settings = $plugin->getAllSettings();

		$mode = elgg_get_plugin_setting('environment', 'payments', 'sandbox');

		if ($mode == 'production') {
			$client_id = elgg_extract('live_client_id', $settings);
			$client_secret = elgg_extract('live_client_secret', $settings);
			$api_context = new ApiContext(new OAuthTokenCredential($client_id, $client_secret));

			$api_context->setConfig([
				'mode' => 'live',
				'log.LogEnabled' => true,
				'log.FileName' => elgg_get_config('dataroot') . 'PayPal.live.log',
				'log.LogLevel' => 'INFO',
				'cache.enabled' => true,
				'cache.FileName' => elgg_get_config('dataroot') . 'paypal/auth.live.cache',
			]);
		} else {
			$client_id = elgg_extract('sandbox_client_id', $settings);
			$client_secret = elgg_extract('sandbox_client_secret', $settings);
			$api_context = new ApiContext(new OAuthTokenCredential($client_id, $client_secret));

			$api_context->setConfig([
				'mode' => 'sandbox',
				'log.LogEnabled' => true,
				'log.FileName' => elgg_get_config('dataroot') . 'PayPal.sandbox.log',
				'log.LogLevel' => 'DEBUG',
				'cache.enabled' => true,
				'cache.FileName' => elgg_get_config('dataroot') . 'paypal/auth.test.cache',
			]);
		}

		return $api_context;
	}

}
