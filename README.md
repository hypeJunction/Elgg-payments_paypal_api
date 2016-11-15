PayPal API Payments for Elgg
============================
![Elgg 2.3](https://img.shields.io/badge/Elgg-2.3-orange.svg?style=flat-square)

## Features

 * API for handling payments via PayPal API

## Acknowledgements

 * Plugin has been sponsored by [Social Business World] (https://socialbusinessworld.org "Social Business World")

## Notes

### Example

See actions/payments/checkout/paypal.php for usage example.


### Payment Status

You can use 'transaction:<status>', 'payments' hooks to apply additional logic upon payment.
Note that not all payment are synchronous.

### Web hook events

Make sure to setup webhook via plugin settings. Web hook event data signature is validated for all requests to `paymens/paypal/api/webhook`
Web hook event data can be digested with `'digest:webhook', 'paypal_api'` plugin hook that receives an instance of `\PayPal\API\WebhookEvent` as `$params['webhook_event']`

### SSL

 * Your site must be served over HTTPS for the API requests and webhooks to work as expected

### App Credentials

 * Login to https://developer.paypal.com
 * Create a new REST API app
 * Enter Sandbox and Live Credentials in Plugin Settings
 * You can switch to Live (production) mode in `payments` plugin settings
 * Once you have configured your credentials, setup Webhooks using the buttons in the plugin settings

### Logs

 * Logs are enabled and located in the root of the data directory
