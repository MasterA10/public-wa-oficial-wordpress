<?php

use WAS\Router\RawRequestDispatcher;
use WAS\Router\RouterApiController;

class RouterHealthTest extends WAS_Router_TestCase {

	public function test_health_endpoints_return_ok_payload() {
		$controller = new RouterApiController();

		foreach ( [ '/health', '/healthz', '/healthcheck', '/internal/health' ] as $route ) {
			$response = $controller->health( new WP_REST_Request( 'GET', $route ) );

			$this->assert_true( $response instanceof WP_REST_Response );
			$this->assert_same( 200, $response->get_status() );
			$this->assert_same( [ 'status' => 'ok' ], $response->get_data() );
		}
	}

	public function test_raw_dispatcher_accepts_health_aliases() {
		$dispatcher = new RawRequestDispatcher();
		$method = new ReflectionMethod( RawRequestDispatcher::class, 'dispatch' );
		$method->setAccessible( true );
		$_SERVER['REQUEST_METHOD'] = 'GET';

		foreach ( [ 'health', 'healthz', 'healthcheck', 'internal/health' ] as $path ) {
			$response = $method->invoke( $dispatcher, $path, new WP_REST_Request( 'GET', '/' . $path ) );

			$this->assert_true( $response instanceof WP_REST_Response );
			$this->assert_same( 200, $response->get_status() );
			$this->assert_same( [ 'status' => 'ok' ], $response->get_data() );
		}
	}
}
