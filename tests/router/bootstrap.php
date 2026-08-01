<?php

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/../../' );
}

$GLOBALS['was_test_options'] = [];
$GLOBALS['was_test_user_meta'] = [];
$GLOBALS['was_test_capabilities'] = [];
$GLOBALS['was_test_logged_in'] = true;
$GLOBALS['was_test_http_posts'] = [];
$GLOBALS['was_test_http_gets'] = [];
$GLOBALS['was_test_http_response'] = [
	'code' => 200,
	'body' => [ 'success' => true ],
];
$GLOBALS['was_test_http_response_queue'] = [];
$GLOBALS['was_test_uploads'] = [];
$GLOBALS['was_test_upload_base'] = sys_get_temp_dir() . '/was-router-uploads';

class WP_Error {
	private $code;
	private $message;
	private $data;

	public function __construct( $code = '', $message = '', $data = [] ) {
		$this->code = $code;
		$this->message = $message;
		$this->data = $data;
	}

	public function get_error_code() {
		return $this->code;
	}

	public function get_error_message() {
		return $this->message;
	}

	public function get_error_data() {
		return $this->data;
	}
}

class WP_REST_Response {
	private $data;
	private $status;

	public function __construct( $data = null, $status = 200 ) {
		$this->data = $data;
		$this->status = (int) $status;
	}

	public function get_data() {
		return $this->data;
	}

	public function get_status() {
		return $this->status;
	}
}

class WP_REST_Request implements ArrayAccess {
	private $method;
	private $route;
	private $params = [];
	private $query_params = [];
	private $body_params = [];
	private $headers = [];
	private $body = '';

	public function __construct( $method = 'GET', $route = '' ) {
		$this->method = strtoupper( (string) $method );
		$this->route = (string) $route;
	}

	public function get_method() {
		return $this->method;
	}

	public function get_route() {
		return $this->route;
	}

	public function set_param( $key, $value ) {
		$this->params[ $key ] = $value;
	}

	public function get_param( $key ) {
		return $this->params[ $key ] ?? $this->body_params[ $key ] ?? $this->query_params[ $key ] ?? null;
	}

	public function set_query_params( array $params ) {
		$this->query_params = $params;
	}

	public function get_query_params() {
		return $this->query_params;
	}

	public function set_body_params( array $params ) {
		$this->body_params = $params;
	}

	public function get_body_params() {
		return $this->body_params;
	}

	public function get_json_params() {
		$json = json_decode( $this->body, true );
		return is_array( $json ) ? $json : [];
	}

	public function get_params() {
		return array_merge( $this->query_params, $this->body_params, $this->params );
	}

	public function set_body( $body ) {
		$this->body = (string) $body;
	}

	public function get_body() {
		return $this->body;
	}

	public function set_header( $key, $value ) {
		$this->headers[ strtolower( (string) $key ) ] = $value;
	}

	public function get_header( $key ) {
		return $this->headers[ strtolower( (string) $key ) ] ?? '';
	}

	public function offsetExists( $offset ): bool {
		return null !== $this->get_param( $offset );
	}

	public function offsetGet( $offset ): mixed {
		return $this->get_param( $offset );
	}

	public function offsetSet( $offset, $value ): void {
		$this->set_param( $offset, $value );
	}

	public function offsetUnset( $offset ): void {}
}

class WAS_Test_User {
	public $ID = 1;
	public $display_name = 'Test User';
	public $user_email = 'test@example.test';
	public $user_pass = 'secret';
	public $allcaps = [ 'was_view_inbox' => true, 'was_send_messages' => true ];
	public $data;

	public function __construct() {
		$this->data = (object) [ 'user_pass' => $this->user_pass ];
	}

	public function exists() {
		return true;
	}
}

function is_wp_error( $value ) {
	return $value instanceof WP_Error;
}

function get_option( $key, $default = false ) {
	return array_key_exists( $key, $GLOBALS['was_test_options'] ) ? $GLOBALS['was_test_options'][ $key ] : $default;
}

function plugin_dir_path( $file ) {
	return trailingslashit( dirname( $file ) );
}

function plugin_dir_url( $file ) {
	return 'http://example.test/wp-content/plugins/' . basename( dirname( $file ) ) . '/';
}

function rest_ensure_response( $value ) {
	return $value instanceof WP_REST_Response ? $value : new WP_REST_Response( $value, 200 );
}

function is_user_logged_in() {
	return (bool) $GLOBALS['was_test_logged_in'];
}

function current_user_can( $capability ) {
	return user_can( get_current_user_id(), $capability );
}

function wp_get_current_user() {
	return new WAS_Test_User();
}

function wp_check_password( $password, $hash, $user_id = 0 ) {
	return (string) $password === (string) $hash;
}

function wp_generate_uuid4() {
	return '00000000-0000-4000-8000-' . str_pad( (string) mt_rand( 1, 999999999999 ), 12, '0', STR_PAD_LEFT );
}

function home_url( $path = '' ) {
	return 'https://example.test' . (string) $path;
}

function rest_url( $path = '' ) {
	return home_url( '/wp-json/' . ltrim( (string) $path, '/' ) );
}

function admin_url( $path = '' ) {
	return home_url( '/wp-admin/' . ltrim( (string) $path, '/' ) );
}

function is_admin() {
	return false;
}

function register_rest_route( $namespace, $route, $args ) {
	$GLOBALS['was_test_rest_routes'][ $namespace . $route ] = $args;
	return true;
}

function trailingslashit( $path ) {
	return rtrim( $path, '/\\' ) . '/';
}

function update_option( $key, $value ) {
	$GLOBALS['was_test_options'][ $key ] = $value;
	return true;
}

function current_time( $type, $gmt = false ) {
	if ( 'timestamp' === $type ) {
		return time();
	}
	return gmdate( 'Y-m-d H:i:s' );
}

function sanitize_text_field( $value ) {
	if ( is_array( $value ) || is_object( $value ) ) {
		return '';
	}
	return trim( preg_replace( '/[\r\n\t]+/', ' ', strip_tags( (string) $value ) ) );
}

function sanitize_key( $key ) {
	$key = strtolower( (string) $key );
	return preg_replace( '/[^a-z0-9_\-]/', '', $key );
}

function sanitize_title( $value ) {
	$value = strtolower( sanitize_text_field( $value ) );
	$value = preg_replace( '/[^a-z0-9]+/', '-', $value );
	return trim( $value, '-' );
}

if ( ! function_exists( 'remove_accents' ) ) {
	function remove_accents( $text ) {
		return strtr(
			(string) $text,
			[
				'á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a', 'ä' => 'a',
				'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
				'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
				'ó' => 'o', 'ò' => 'o', 'õ' => 'o', 'ô' => 'o', 'ö' => 'o',
				'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ç' => 'c',
				'Á' => 'A', 'À' => 'A', 'Ã' => 'A', 'Â' => 'A', 'Ä' => 'A',
				'É' => 'E', 'È' => 'E', 'Ê' => 'E', 'Ë' => 'E',
				'Í' => 'I', 'Ì' => 'I', 'Î' => 'I', 'Ï' => 'I',
				'Ó' => 'O', 'Ò' => 'O', 'Õ' => 'O', 'Ô' => 'O', 'Ö' => 'O',
				'Ú' => 'U', 'Ù' => 'U', 'Û' => 'U', 'Ü' => 'U', 'Ç' => 'C',
			]
		);
	}
}

function esc_url_raw( $value ) {
	return trim( (string) $value );
}

function esc_sql( $value ) {
	return str_replace( "'", "''", (string) $value );
}

function wp_json_encode( $value, $flags = 0, $depth = 512 ) {
	return json_encode( $value, $flags, $depth );
}

function wp_generate_password( $length = 12, $special_chars = true ) {
	return substr( str_repeat( 'testpass', 10 ), 0, $length );
}

function sanitize_file_name( $filename ) {
	return preg_replace( '/[^A-Za-z0-9._-]+/', '-', basename( (string) $filename ) );
}

function wp_parse_url( $url, $component = -1 ) {
	return parse_url( $url, $component );
}

function add_query_arg( $key, $value = null, $url = null ) {
	if ( is_array( $key ) ) {
		$args = $key;
		$base = (string) $value;
	} else {
		$args = [ (string) $key => $value ];
		$base = (string) $url;
	}
	$separator = strpos( $base, '?' ) === false ? '?' : '&';
	return $base . $separator . http_build_query( $args );
}

function wp_remote_post( $url, $args = [] ) {
	$GLOBALS['was_test_http_posts'][] = [
		'url'  => $url,
		'args' => $args,
	];
	$response = was_test_next_http_response();
	return [
		'response' => [ 'code' => $response['code'] ],
		'body'     => is_string( $response['body'] ) ? $response['body'] : json_encode( $response['body'] ),
	];
}

function wp_remote_get( $url, $args = [] ) {
	$GLOBALS['was_test_http_gets'][] = [
		'url'  => $url,
		'args' => $args,
	];
	$response = was_test_next_http_response();
	return [
		'response' => [ 'code' => $response['code'] ],
		'body'     => is_string( $response['body'] ) ? $response['body'] : json_encode( $response['body'] ),
	];
}

function was_test_next_http_response() {
	if ( ! empty( $GLOBALS['was_test_http_response_queue'] ) ) {
		return array_shift( $GLOBALS['was_test_http_response_queue'] );
	}
	return $GLOBALS['was_test_http_response'];
}

function wp_remote_request( $url, $args = [] ) {
	return wp_remote_post( $url, $args );
}

function wp_remote_retrieve_response_code( $response ) {
	return (int) ( $response['response']['code'] ?? 0 );
}

function wp_remote_retrieve_body( $response ) {
	return (string) ( $response['body'] ?? '' );
}

function wp_upload_bits( $name, $deprecated, $bits ) {
	$file = sys_get_temp_dir() . '/' . sanitize_file_name( $name );
	file_put_contents( $file, (string) $bits );
	$result = [
		'file'  => $file,
		'url'   => 'https://wordpress.test/uploads/' . sanitize_file_name( $name ),
		'error' => false,
	];
	$GLOBALS['was_test_uploads'][] = [
		'name' => $name,
		'bits' => $bits,
		'result' => $result,
	];
	return $result;
}

function wp_upload_dir() {
	$base = $GLOBALS['was_test_upload_base'] ?? ( sys_get_temp_dir() . '/was-router-uploads' );
	if ( ! is_dir( $base ) ) {
		mkdir( $base, 0777, true );
	}
	return [
		'basedir' => $base,
		'baseurl' => 'https://wordpress.test/uploads',
		'error'   => false,
	];
}

function wp_mkdir_p( $target ) {
	return is_dir( $target ) || mkdir( $target, 0777, true );
}

function wp_unique_filename( $dir, $filename ) {
	$filename = sanitize_file_name( $filename );
	$path = rtrim( $dir, '/\\' ) . '/' . $filename;
	if ( ! file_exists( $path ) ) {
		return $filename;
	}

	$info = pathinfo( $filename );
	$stem = $info['filename'] ?? 'file';
	$extension = isset( $info['extension'] ) ? '.' . $info['extension'] : '';
	$index = 1;
	while ( file_exists( rtrim( $dir, '/\\' ) . '/' . $stem . '-' . $index . $extension ) ) {
		$index++;
	}
	return $stem . '-' . $index . $extension;
}

function get_user_by( $field, $value ) {
	return (object) [ 'ID' => (int) $value ];
}

function user_can( $user, $capability ) {
	$user_id = is_object( $user ) ? (int) ( $user->ID ?? 0 ) : (int) $user;
	if ( array_key_exists( $capability, $GLOBALS['was_test_capabilities'][ $user_id ] ?? [] ) ) {
		return (bool) $GLOBALS['was_test_capabilities'][ $user_id ][ $capability ];
	}
	return true;
}

function wp_authenticate( $username, $password ) {
	if ( 'admin' === $username && 'secret' === $password ) {
		return (object) [ 'ID' => 1, 'user_login' => 'admin' ];
	}
	return new WP_Error( 'invalid_credentials', 'Invalid credentials.' );
}

function get_current_user_id() {
	return 1;
}

function get_user_meta( $user_id, $key, $single = false ) {
	return $GLOBALS['was_test_user_meta'][ $user_id ][ $key ] ?? '';
}

function update_user_meta( $user_id, $key, $value ) {
	$GLOBALS['was_test_user_meta'][ $user_id ][ $key ] = $value;
	return true;
}

class WAS_Test_WPDB {
	public $prefix = 'wp_';
	public $users = 'wp_users';
	public $insert_id = 0;
	public $last_error = '';
	public $tables = [];
	private $auto_ids = [];

	public function reset() {
		$this->insert_id = 0;
		$this->last_error = '';
		$this->tables = [];
		$this->auto_ids = [];
	}

	public function insert( $table, $data ) {
		if ( ! isset( $this->tables[ $table ] ) ) {
			$this->tables[ $table ] = [];
		}
		if ( ! isset( $data['id'] ) ) {
			$this->auto_ids[ $table ] = ( $this->auto_ids[ $table ] ?? 0 ) + 1;
			$data['id'] = $this->auto_ids[ $table ];
		} else {
			$this->auto_ids[ $table ] = max( $this->auto_ids[ $table ] ?? 0, (int) $data['id'] );
		}
		$this->tables[ $table ][] = $data;
		$this->insert_id = (int) $data['id'];
		return true;
	}

	public function update( $table, $data, $where ) {
		if ( ! isset( $this->tables[ $table ] ) ) {
			return 0;
		}
		$count = 0;
		foreach ( $this->tables[ $table ] as &$row ) {
			if ( $this->row_matches_where_array( $row, $where ) ) {
				$row = array_merge( $row, $data );
				$count++;
			}
		}
		unset( $row );
		return $count;
	}

	public function delete( $table, $where ) {
		$before = count( $this->tables[ $table ] ?? [] );
		$this->tables[ $table ] = array_values(
			array_filter(
				$this->tables[ $table ] ?? [],
				fn( $row ) => ! $this->row_matches_where_array( $row, $where )
			)
		);
		return $before - count( $this->tables[ $table ] );
	}

	public function replace( $table, $data ) {
		$identity = [];
		foreach ( [ 'id', 'tenant_id', 'user_id', 'phone_number_id', 'waba_id', 'meta_template_id', 'session_uuid' ] as $field ) {
			if ( array_key_exists( $field, $data ) ) {
				$identity[ $field ] = $data[ $field ];
			}
		}

		if ( $identity ) {
			foreach ( $this->tables[ $table ] ?? [] as $index => $row ) {
				$matches = true;
				foreach ( $identity as $field => $value ) {
					if ( (string) ( $row[ $field ] ?? '' ) !== (string) $value ) {
						$matches = false;
						break;
					}
				}
				if ( $matches ) {
					$data['id'] = $row['id'] ?? null;
					$this->tables[ $table ][ $index ] = array_merge( $row, $data );
					$this->insert_id = (int) ( $data['id'] ?? 0 );
					return 1;
				}
			}
		}

		return $this->insert( $table, $data );
	}

	public function prepare( $query, ...$params ) {
		if ( 1 === count( $params ) && is_array( $params[0] ) ) {
			$params = $params[0];
		}
		$index = 0;
		return preg_replace_callback(
			'/%[ds]/',
			function ( $matches ) use ( $params, &$index ) {
				$param = $params[ $index++ ] ?? null;
				if ( '%d' === $matches[0] ) {
					return (string) (int) $param;
				}
				return "'" . esc_sql( $param ) . "'";
			},
			$query
		);
	}

	public function get_var( $sql ) {
		if ( preg_match( '/SHOW TABLES LIKE \'([^\']+)\'/i', $sql, $matches ) ) {
			return isset( $this->tables[ $matches[1] ] ) ? $matches[1] : null;
		}
		if ( preg_match( '/SELECT COUNT\(\*\) FROM ([`\w]+)/i', $sql, $matches ) ) {
			return count( $this->filter_sql_rows( trim( $matches[1], '`' ), $sql ) );
		}
		$rows = $this->get_results( $sql );
		if ( empty( $rows ) ) {
			return null;
		}
		if ( preg_match( '/SELECT\s+1\s+FROM/i', $sql ) ) {
			return 1;
		}
		if ( preg_match( '/SELECT\s+([`\w]+)\s+FROM/i', $sql, $matches ) ) {
			$field = trim( $matches[1], '`' );
			return $rows[0]->{$field} ?? null;
		}
		return reset( get_object_vars( $rows[0] ) );
	}

	public function get_row( $sql ) {
		$rows = $this->get_results( $sql );
		return $rows[0] ?? null;
	}

	public function get_results( $sql ) {
		if ( preg_match( '/SHOW COLUMNS FROM `([^`]+)` LIKE \'([^\']+)\'/i', $sql, $matches ) ) {
			$table = $matches[1];
			$column = $matches[2];
			foreach ( $this->tables[ $table ] ?? [] as $row ) {
				if ( array_key_exists( $column, $row ) ) {
					return [ (object) [ 'Field' => $column ] ];
				}
			}
			return [];
		}

		if ( preg_match( '/SELECT ma\.\* FROM ([`\w]+) ma INNER JOIN ([`\w]+) wa/i', $sql, $matches ) ) {
			$apps_table = trim( $matches[1], '`' );
			$accounts_table = trim( $matches[2], '`' );
			preg_match( "/wa\.waba_id = '([^']+)'/i", $sql, $waba_match );
			$waba_id = $waba_match[1] ?? null;
			foreach ( $this->tables[ $accounts_table ] ?? [] as $account ) {
				if ( (string) ( $account['waba_id'] ?? '' ) === (string) $waba_id ) {
					foreach ( $this->tables[ $apps_table ] ?? [] as $app ) {
						if ( (int) ( $app['id'] ?? 0 ) === (int) ( $account['meta_app_id'] ?? 0 ) ) {
							return [ (object) $app ];
						}
					}
				}
			}
			return [];
		}

		if ( ! preg_match( '/FROM\s+([`\w]+)/i', $sql, $matches ) ) {
			return [];
		}
		$table = trim( $matches[1], '`' );
		$rows = $this->filter_sql_rows( $table, $sql );
		$rows = $this->sort_sql_rows( $rows, $sql );
		if ( preg_match( '/LIMIT\s+(\d+)/i', $sql, $limit_match ) ) {
			$rows = array_slice( $rows, 0, (int) $limit_match[1] );
		}
		return array_map( fn( $row ) => (object) $row, $rows );
	}

	public function query( $sql ) {
		return true;
	}

	private function filter_sql_rows( $table, $sql ) {
		$rows = $this->tables[ $table ] ?? [];
		if ( ! preg_match( '/WHERE\s+(.+?)(ORDER BY|LIMIT|$)/is', $sql, $matches ) ) {
			return $rows;
		}
		$where = trim( $matches[1] );
		$conditions = preg_split( '/\s+AND\s+/i', $where );
		return array_values(
			array_filter(
				$rows,
				function ( $row ) use ( $conditions ) {
					foreach ( $conditions as $condition ) {
						$condition = trim( $condition );
						if ( '' === $condition || '1=1' === $condition ) {
							continue;
						}
						if ( ! $this->row_matches_sql_condition( $row, $condition ) ) {
							return false;
						}
					}
					return true;
				}
			)
		);
	}

	private function row_matches_sql_condition( $row, $condition ) {
		if ( preg_match( '/^`?(\w+)`?\s+IS\s+NULL$/i', $condition, $matches ) ) {
			return ! isset( $row[ $matches[1] ] ) || null === $row[ $matches[1] ];
		}
		if ( preg_match( '/^`?(\w+)`?\s+IN\s+\(([^)]+)\)$/i', $condition, $matches ) ) {
			$field = $matches[1];
			$values = array_map( [ $this, 'unquote' ], array_map( 'trim', explode( ',', $matches[2] ) ) );
			return in_array( (string) ( $row[ $field ] ?? '' ), array_map( 'strval', $values ), true );
		}
		if ( preg_match( "/^(?:\w+\.)?`?(\w+)`?\s*=\s*('([^']*)'|[0-9]+)$/i", $condition, $matches ) ) {
			$field = $matches[1];
			$value = $this->unquote( $matches[2] );
			return (string) ( $row[ $field ] ?? '' ) === (string) $value;
		}
		return true;
	}

	private function row_matches_where_array( $row, $where ) {
		foreach ( $where as $key => $value ) {
			if ( ! array_key_exists( $key, $row ) || (string) $row[ $key ] !== (string) $value ) {
				return false;
			}
		}
		return true;
	}

	private function sort_sql_rows( $rows, $sql ) {
		if ( stripos( $sql, 'ORDER BY' ) === false ) {
			return $rows;
		}
		if ( preg_match( '/ORDER BY\s+id\s+DESC/i', $sql ) ) {
			usort( $rows, fn( $a, $b ) => ( $b['id'] ?? 0 ) <=> ( $a['id'] ?? 0 ) );
		} elseif ( preg_match( '/ORDER BY\s+is_default\s+DESC,\s*created_at\s+DESC/i', $sql ) ) {
			usort(
				$rows,
				fn( $a, $b ) => [ (int) ( $b['is_default'] ?? 0 ), (string) ( $b['created_at'] ?? '' ) ] <=> [ (int) ( $a['is_default'] ?? 0 ), (string) ( $a['created_at'] ?? '' ) ]
			);
		} elseif ( preg_match( '/ORDER BY\s+is_default\s+DESC,\s*id\s+ASC/i', $sql ) ) {
			usort(
				$rows,
				fn( $a, $b ) => [ (int) ( $b['is_default'] ?? 0 ), (int) ( $a['id'] ?? 0 ) ] <=> [ (int) ( $a['is_default'] ?? 0 ), (int) ( $b['id'] ?? 0 ) ]
			);
		} elseif ( preg_match( '/ORDER BY\s+priority\s+ASC,\s*id\s+ASC/i', $sql ) ) {
			usort( $rows, fn( $a, $b ) => [ $a['priority'] ?? 100, $a['id'] ?? 0 ] <=> [ $b['priority'] ?? 100, $b['id'] ?? 0 ] );
		} elseif ( preg_match( '/ORDER BY\s+created_at\s+DESC/i', $sql ) ) {
			usort( $rows, fn( $a, $b ) => strcmp( $b['created_at'] ?? '', $a['created_at'] ?? '' ) );
		}
		return $rows;
	}

	private function unquote( $value ) {
		$value = trim( $value );
		if ( preg_match( "/^'(.*)'$/s", $value, $matches ) ) {
			return str_replace( "''", "'", $matches[1] );
		}
		return is_numeric( $value ) ? (int) $value : $value;
	}
}

$GLOBALS['wpdb'] = new WAS_Test_WPDB();

require_once __DIR__ . '/../../includes/Core/Constants.php';
require_once __DIR__ . '/../../includes/Core/Autoloader.php';
\WAS\Core\Autoloader::register();

function was_router_tests_reset() {
	$GLOBALS['wpdb']->reset();
	$GLOBALS['was_test_options'] = [];
	$GLOBALS['was_test_user_meta'] = [];
	$GLOBALS['was_test_capabilities'] = [];
	$GLOBALS['was_test_logged_in'] = true;
	$GLOBALS['was_test_rest_routes'] = [];
	$GLOBALS['was_test_http_posts'] = [];
	$GLOBALS['was_test_http_gets'] = [];
	$GLOBALS['was_test_http_response'] = [
		'code' => 200,
		'body' => [ 'success' => true ],
	];
	$GLOBALS['was_test_http_response_queue'] = [];
	$GLOBALS['was_test_uploads'] = [];
	$GLOBALS['was_test_upload_base'] = sys_get_temp_dir() . '/was-router-uploads';
}

function was_router_table( $name ) {
	return \WAS\Core\TableNameResolver::get_table_name( $name );
}
