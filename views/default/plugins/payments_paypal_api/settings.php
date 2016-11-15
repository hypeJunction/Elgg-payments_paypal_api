<?php

$entity = elgg_extract('entity', $vars);

echo elgg_view_field([
	'#type' => 'text',
	'#label' => elgg_echo('payments:paypal:api:paypal_email'),
	'name' => 'params[paypal_email]',
	'value' => $entity->paypal_email,
]);

echo elgg_view_field([
	'#type' => 'fieldset',
	'legend' => elgg_echo('payments:paypal:api:sandbox'),
	'fields' => [
			[
			'#type' => 'text',
			'#label' => elgg_echo('payments:paypal:api:sandbox_client_id'),
			'name' => 'params[sandbox_client_id]',
			'value' => $entity->sandbox_client_id,
		],
			[
			'#type' => 'text',
			'#label' => elgg_echo('payments:paypal:api:sandbox_client_secret'),
			'name' => 'params[sandbox_client_secret]',
			'value' => $entity->sandbox_client_secret,
		]
	],
]);

if ($entity->sandbox_client_id) {
	elgg_register_menu_item('title', [
		'name' => 'webhooks:sandbox',
		'text' => elgg_echo('payments:paypal:api:setup_sandbox_webhooks'),
		'href' => 'action/paypal_api/setup_webhooks?namespace=sandbox',
		'link_class' => 'elgg-button elgg-button-action',
		'is_action' => true,
	]);
}

echo elgg_view_field([
	'#type' => 'fieldset',
	'legend' => elgg_echo('payments:paypal:api:live'),
	'fields' => [
			[
			'#type' => 'text',
			'#label' => elgg_echo('payments:paypal:api:live_client_id'),
			'name' => 'params[live_client_id]',
			'value' => $entity->live_client_id,
		],
			[
			'#type' => 'text',
			'#label' => elgg_echo('payments:paypal:api:live_client_secret'),
			'name' => 'params[live_client_secret]',
			'value' => $entity->live_client_secret,
		]
	],
]);

if ($entity->live_client_id) {
	elgg_register_menu_item('title', [
		'name' => 'webhooks:live',
		'text' => elgg_echo('payments:paypal:api:setup_live_webhooks'),
		'href' => 'action/paypal_api/setup_webhooks?namespace=live',
		'link_class' => 'elgg-button elgg-button-action',
		'is_action' => true,
	]);
}