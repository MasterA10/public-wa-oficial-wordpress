<?php
require_once __DIR__ . '/../../../../wp-load.php';
require_once __DIR__ . '/../includes/Core/Autoloader.php';
\WAS\Core\Autoloader::register();

// Mock a request
$request = new WP_REST_Request('GET', '/was/v1/meta/config');
$controller = new \WAS\REST\MetaApiController();

// We need a tenant context
\WAS\Auth\TenantContext::set_tenant_id(1);

$response = $controller->get_config($request);
echo json_encode($response->get_data(), JSON_PRETTY_PRINT);
