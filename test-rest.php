<?php
require_once dirname(__FILE__, 4) . '/wp-load.php';
wp_set_current_user(1);
$request = new WP_REST_Request('GET', '/was/v1/templates');
$response = rest_do_request($request);
var_dump($response->get_status());
var_dump(count($response->get_data()));
