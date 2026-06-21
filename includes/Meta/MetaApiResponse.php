<?php
/**
 * MetaApiResponse class.
 *
 * @package WAS\Meta
 */

namespace WAS\Meta;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Standardized Meta API response.
 */
class MetaApiResponse {

	public $success;
	public $status_code;
	public $operation;
	public $data;
	public $error;
	public $meta_request_id;

	/**
	 * Constructor.
	 */
	public function __construct( $success, $status_code, $operation, $data = [], $error = [], $request_id = null ) {
		$this->success         = $success;
		$this->status_code     = $status_code;
		$this->operation       = $operation;
		$this->data            = $data;
		$this->error           = $error;
		$this->meta_request_id = $request_id;
	}

	/**
	 * Success factory.
	 */
	public static function success( $operation, $data, $status_code = 200, $request_id = null ) {
		return new self( true, $status_code, $operation, $data, [], $request_id );
	}

	/**
	 * Error factory.
	 */
	public static function error( $operation, $error, $status_code = 400, $request_id = null ) {
		return new self( false, $status_code, $operation, [], $error, $request_id );
	}
}
