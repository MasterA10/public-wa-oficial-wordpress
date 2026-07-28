<?php

use WAS\Auth\TenantContext;
use WAS\Core\TableNameResolver;
use WAS\Meta\TokenVault;
use WAS\WhatsApp\OutboundMediaService;

class OutboundMediaServiceTest extends WAS_Router_TestCase {

	protected function set_up() {
		global $wpdb;

		TenantContext::set_tenant_id( 1 );
		$now = current_time( 'mysql', true );

		$wpdb->insert( TableNameResolver::get_table_name( 'tenants' ), [
			'id'         => 1,
			'name'       => 'Agenda',
			'slug'       => 'agenda',
			'status'     => 'active',
			'created_at' => $now,
		] );
		$wpdb->insert( TableNameResolver::get_table_name( 'whatsapp_accounts' ), [
			'id'         => 7,
			'tenant_id'  => 1,
			'waba_id'    => 'waba-media',
			'status'     => 'active',
			'created_at' => $now,
		] );
		$wpdb->insert( TableNameResolver::get_table_name( 'whatsapp_phone_numbers' ), [
			'id'                 => 10,
			'tenant_id'          => 1,
			'whatsapp_account_id' => 7,
			'phone_number_id'    => 'meta-phone-media',
			'display_phone_number'=> '553171183457',
			'status'             => 'active',
			'created_at'         => $now,
		] );
		$wpdb->insert( TableNameResolver::get_table_name( 'meta_tokens' ), [
			'id'                    => 20,
			'tenant_id'             => 1,
			'whatsapp_account_id'   => 7,
			'access_token_encrypted' => TokenVault::encrypt( 'media-token' ),
			'status'                => 'active',
			'created_at'            => $now,
		] );
		$wpdb->insert( TableNameResolver::get_table_name( 'contacts' ), [
			'id'               => 30,
			'tenant_id'        => 1,
			'wa_id'            => '553199919648',
			'phone'            => '553199919648',
			'normalized_phone' => '553199919648',
			'phone_status'     => 'confirmed_by_wa_id',
			'created_at'       => $now,
			'updated_at'       => $now,
		] );
		$wpdb->insert( TableNameResolver::get_table_name( 'conversations' ), [
			'id'                              => 40,
			'tenant_id'                       => 1,
			'contact_id'                      => 30,
			'phone_number_id'                 => 'meta-phone-media',
			'last_inbound_wa_message_id'      => 'wamid.inbound-media',
			'customer_service_window_expires_at' => gmdate( 'Y-m-d H:i:s', time() + 3600 ),
			'status'                          => 'open',
			'created_at'                      => $now,
			'updated_at'                      => $now,
		] );
	}

	public function test_success_uploads_media_saves_public_copy_and_sends_meta_media_id() {
		global $wpdb;

		$file = $this->temporary_file( 'foto-original.jpg', 'image-bytes' );
		$service = new OutboundMediaService();
		$fake_api = $this->replace_api_client( $service, [
			'upload' => [ 'success' => true, 'id' => 'meta-media-1' ],
			'send'   => [ 'success' => true, 'messages' => [ [ 'id' => 'wamid.media-1' ] ] ],
		] );

		$result = $service->send_media( 40, $file, 'image/jpeg', 'image', 'Legenda da foto', 'foto.jpg' );

		$this->assert_true( $result['success'] );
		$this->assert_same( 'wamid.media-1', $result['wa_message_id'] );
		$this->assert_same( 1, count( $fake_api->uploads ) );
		$this->assert_same( 'meta-phone-media', $fake_api->uploads[0]['phone_number_id'] );
		$this->assert_same( 'media-token', $fake_api->uploads[0]['token'] );
		$this->assert_same( 1, count( $fake_api->posts ) );
		$this->assert_same( 'meta-phone-media', $fake_api->posts[0]['path']['phone_number_id'] );
		$this->assert_same( '553199919648', $fake_api->posts[0]['body']['to'] );
		$this->assert_same( 'meta-media-1', $fake_api->posts[0]['body']['image']['id'] );
		$this->assert_same( 'Legenda da foto', $fake_api->posts[0]['body']['image']['caption'] );
		$this->assert_false( array_key_exists( 'link', $fake_api->posts[0]['body']['image'] ) );

		$media = $wpdb->tables[ TableNameResolver::get_table_name( 'media' ) ][0];
		$message = $wpdb->tables[ TableNameResolver::get_table_name( 'messages' ) ][0];
		$this->assert_same( 'sent', $media['status'] );
		$this->assert_same( 'meta-media-1', $media['meta_media_id'] );
		$this->assert_same( 'outbound', $media['direction'] );
		$this->assert_true( str_starts_with( $media['public_url'], 'https://wordpress.test/uploads/was-media/1/' ) );
		$this->assert_true( file_exists( $media['storage_path'] ) );
		$this->assert_same( 'outbound', $message['direction'] );
		$this->assert_same( 'image', $message['message_type'] );
		$this->assert_same( 'wamid.media-1', $message['wa_message_id'] );
		$this->assert_same( 1, count( $GLOBALS['was_test_http_posts'] ) );
		$typing = json_decode( $GLOBALS['was_test_http_posts'][0]['args']['body'], true );
		$this->assert_same( 'read', $typing['status'] );
		$this->assert_same( 'wamid.inbound-media', $typing['message_id'] );
	}

	public function test_meta_upload_failure_marks_local_media_failed_and_does_not_create_message() {
		global $wpdb;

		$file = $this->temporary_file( 'falha.jpg', 'image-bytes' );
		$service = new OutboundMediaService();
		$this->replace_api_client( $service, [
			'upload' => [ 'success' => false, 'error' => 'Media upload denied' ],
		] );

		$result = $service->send_media( 40, $file, 'image/jpeg', 'image', '', 'falha.jpg' );

		$this->assert_false( $result['success'] );
		$this->assert_same( 'Media upload denied', $result['error'] );
		$media = $wpdb->tables[ TableNameResolver::get_table_name( 'media' ) ][0];
		$this->assert_same( 'failed', $media['status'] );
		$this->assert_same( 'Media upload denied', $media['error_message'] );
		$this->assert_count( 0, $wpdb->tables[ TableNameResolver::get_table_name( 'messages' ) ] ?? [] );
	}

	public function test_meta_message_failure_keeps_media_as_uploaded_and_exposes_failure() {
		global $wpdb;

		$file = $this->temporary_file( 'envio.jpg', 'image-bytes' );
		$service = new OutboundMediaService();
		$this->replace_api_client( $service, [
			'upload' => [ 'success' => true, 'id' => 'meta-media-2' ],
			'send'   => [ 'success' => false, 'error' => 'Unsupported post request' ],
		] );

		$result = $service->send_media( 40, $file, 'image/jpeg', 'image', '', 'envio.jpg' );

		$this->assert_false( $result['success'] );
		$this->assert_same( 'Unsupported post request', $result['error'] );
		$media = $wpdb->tables[ TableNameResolver::get_table_name( 'media' ) ][0];
		$this->assert_same( 'uploaded_to_meta', $media['status'] );
		$this->assert_same( 'meta-media-2', $media['meta_media_id'] );
		$this->assert_count( 0, $wpdb->tables[ TableNameResolver::get_table_name( 'messages' ) ] ?? [] );
	}

	public function test_closed_customer_service_window_returns_interpretable_error_without_uploading() {
		global $wpdb;

		$wpdb->update(
			TableNameResolver::get_table_name( 'conversations' ),
			[ 'customer_service_window_expires_at' => gmdate( 'Y-m-d H:i:s', time() - 60 ) ],
			[ 'id' => 40 ]
		);
		$file = $this->temporary_file( 'janela.jpg', 'image-bytes' );
		$service = new OutboundMediaService();
		$fake_api = $this->replace_api_client( $service, [
			'upload' => [ 'success' => true, 'id' => 'never-used' ],
		] );

		$result = $service->send_media( 40, $file, 'image/jpeg', 'image', '', 'janela.jpg' );

		$this->assert_false( $result['success'] );
		$this->assert_same( 'CUSTOMER_SERVICE_WINDOW_CLOSED', $result['error_code'] );
		$this->assert_true( str_contains( $result['error'], 'janela de atendimento' ) );
		$this->assert_count( 0, $fake_api->uploads );
		$this->assert_count( 0, $wpdb->tables[ TableNameResolver::get_table_name( 'media' ) ] ?? [] );
	}

	private function replace_api_client( OutboundMediaService $service, array $responses ) {
		$fake = new class( $responses ) {
			public $responses;
			public $uploads = [];
			public $posts = [];

			public function __construct( $responses ) {
				$this->responses = $responses;
			}

			public function uploadMedia( $phone_number_id, $file_path, $mime_type, $token ) {
				$this->uploads[] = compact( 'phone_number_id', 'file_path', 'mime_type', 'token' );
				return $this->responses['upload'] ?? [ 'success' => false, 'error' => 'missing upload fixture' ];
			}

			public function postJson( $operation, $path, $body, $token ) {
				$this->posts[] = compact( 'operation', 'path', 'body', 'token' );
				return $this->responses['send'] ?? [ 'success' => false, 'error' => 'missing send fixture' ];
			}
		};

		$reflection = new ReflectionClass( $service );
		$property = $reflection->getProperty( 'api_client' );
		$property->setAccessible( true );
		$property->setValue( $service, $fake );
		return $fake;
	}

	private function temporary_file( $filename, $contents ) {
		$path = sys_get_temp_dir() . '/' . uniqid( 'was-media-', true ) . '-' . $filename;
		file_put_contents( $path, $contents );
		return $path;
	}
}
