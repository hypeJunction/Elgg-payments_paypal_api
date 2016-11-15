<?php

echo elgg_view('output/img', array(
	'src' => elgg_get_simplecache_url('payments/method/pp-logo-100px.png'),
	'alt' => elgg_echo('payments:method:paypal'),
));
