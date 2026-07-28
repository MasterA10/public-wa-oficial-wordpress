<?php

use WAS\Auth\TenantContext;
use WAS\Core\TableNameResolver;
use WAS\Inbox\ConversationRepository;
use WAS\Inbox\MediaRepository;
use WAS\Inbox\MessageRepository;
use WAS\Meta\MetaApiClient;
use WAS\Meta\TokenVault;
use WAS\WhatsApp\InboundMediaService;
use WAS\WhatsApp\PhoneNumberDiagnosticsService;

class PlatformServiceContractsTest extends WAS_Router_TestCase {

	protected function set_up() {
		global $wpdb;
		TenantContext::set_tenant_id( 1 );
		$wpdb->insert(
			TableNameResolver::get_table_name( 'tenants' ),
			[
				'id'         => 1,
				'name'       => 'Agenda',
				'slug'       => 'agenda',
				'status'     => 'active',
				'created_at' => current_time( 'mysql', true ),
			]
		);
		$wpdb->insert(
			TableNameResolver::get_table_name( 'whatsapp_accounts' ),
			[
				'id'         => 7,
				'tenant_id'  => 1,
				'waba_id'    => 'waba-1',
				'status'     => 'active',
				'created_at' => current_time( 'mysql', true ),
			]
		);
		$wpdb->insert(
			TableNameResolver::get_table_name( 'whatsapp_phone_numbers' ),
			[
				'id'                  => 10,
				'tenant_id'           => 1,
				'whatsapp_account_id' => 7,
				'phone_number_id'     => 'phone-1',
				'status'              => 'active',
				'created_at'          => current_time( 'mysql', true ),
			]
		);
		$wpdb->insert(
			TableNameResolver::get_table_name( 'meta_tokens' ),
			[
				'id'                    => 20,
				'tenant_id'             => 1,
				'whatsapp_account_id'   => 7,
				'access_token_encrypted' => TokenVault::encrypt( 'waba-token' ),
				'status'                => 'active',
				'created_at'            => current_time( 'mysql', true ),
			]
		);
	}

	public function test_media_repository_preserves_downloaded_public_copy_and_tenant_scope() {
		$repository = new MediaRepository();
		$media_id = $repository->create(
			[
				'meta_media_id' => 'meta-image-1',
				'message_id'    => 20,
				'mime_type'     => 'image/jpeg',
				'filename'      => 'foto.jpg',
				'public_url'    => 'https://wordpress.test/uploads/foto.jpg',
				'status'        => 'downloaded',
			]
		);

		$found = $repository->find_downloaded_by_meta_id( 'meta-image-1' );
		$wrong_tenant = $repository->find_downloaded_by_meta_id( 'meta-image-1', 2 );

		$this->assert_true( $media_id > 0 );
		$this->assert_same( $media_id, (int) $found->id );
		$this->assert_same( 'https://wordpress.test/uploads/foto.jpg', $found->public_url );
		$this->assert_null( $wrong_tenant );
	}

	public function test_media_repository_marks_meta_upload_and_attaches_message() {
		$repository = new MediaRepository();
		$media_id = $repository->create( [ 'filename' => 'audio.ogg', 'status' => 'downloaded' ] );

		$this->assert_true( $repository->mark_uploaded( $media_id, 'meta-audio-1' ) );
		$this->assert_true( $repository->attach_message( $media_id, 42 ) );

		$media = $repository->get_by_id( $media_id );
		$this->assert_same( 'uploaded_to_meta', $media->status );
		$this->assert_same( 'meta-audio-1', $media->meta_media_id );
		$this->assert_same( 42, (int) $media->message_id );
	}

	public function test_message_repository_persists_inbound_outbound_and_status_updates() {
		$repository = new MessageRepository();
		$inbound_id = $repository->create_inbound(
			[
				'conversation_id' => 5,
				'wa_message_id'   => 'wamid-inbound-1',
				'message_type'    => 'text',
				'text_body'       => 'Recebida',
			]
		);
		$outbound_id = $repository->create_outbound(
			[
				'conversation_id' => 5,
				'wa_message_id'   => 'wamid-outbound-1',
				'message_type'    => 'audio',
			]
		);

		$inbound = $repository->find_by_wa_message_id( 'wamid-inbound-1' );
		$outbound = $repository->find_by_id( $outbound_id );
		$updated = $repository->update_status( 'wamid-outbound-1', 'delivered' );
		$updated_outbound = $repository->find_by_wa_message_id( 'wamid-outbound-1' );

		$this->assert_true( $inbound_id > 0 );
		$this->assert_true( $outbound_id > 0 );
		$this->assert_same( 'inbound', $inbound->direction );
		$this->assert_same( 'Recebida', $inbound->text_body );
		$this->assert_same( 'outbound', $outbound->direction );
		$this->assert_same( 1, $updated );
		$this->assert_same( 'delivered', $updated_outbound->status );
	}

	public function test_conversation_repository_reuses_open_conversation_and_updates_state() {
		$repository = new ConversationRepository();
		$first = $repository->find_or_create_open_conversation( 30, 'meta-phone-1' );
		$second = $repository->find_or_create_open_conversation( 30, 'meta-phone-1' );

		$repository->update_last_inbound_wa_message_id( $first->id, 'wamid-inbound-2' );
		$repository->mark_typing_sent( $first->id );
		$repository->mark_outbound_sent( $first->id );
		$repository->assign( $first->id, 7 );
		$conversation = $repository->get_by_id( $first->id );

		$this->assert_same( (int) $first->id, (int) $second->id );
		$this->assert_same( 'wamid-inbound-2', $conversation->last_inbound_wa_message_id );
		$this->assert_not_null( $conversation->last_typing_sent_at );
		$this->assert_not_null( $conversation->last_outbound_sent_at );
		$this->assert_same( 7, (int) $conversation->assigned_user_id );
	}

	public function test_meta_api_client_builds_registered_paths_and_parses_success_and_error() {
		$GLOBALS['was_test_http_response_queue'] = [
			[
				'code' => 200,
				'body' => [ 'messages' => [ [ 'id' => 'wamid-meta-client' ] ] ],
			],
			[
				'code' => 400,
				'body' => [ 'error' => [ 'message' => 'Unsupported post request', 'code' => 100 ] ],
			],
			[
				'code' => 200,
				'body' => [ 'data' => [ [ 'id' => 'waba-1' ] ] ],
			],
		];
		$client = new MetaApiClient();

		$success = $client->postJson( 'messages.send', [ 'phone_number_id' => 'phone-1' ], [ 'type' => 'text' ], 'meta-token' );
		$error = $client->postJson( 'messages.send', [ 'phone_number_id' => 'phone-1' ], [ 'type' => 'text' ], 'meta-token' );
		$listed = $client->get( 'waba.get_subscribed_apps', [ 'waba_id' => 'waba-1' ], [ 'fields' => 'id' ], 'meta-token' );

		$this->assert_true( $success['success'] );
		$this->assert_same( 'wamid-meta-client', $success['messages'][0]['id'] );
		$this->assert_false( $error['success'] );
		$this->assert_same( 100, $error['code'] );
		$this->assert_same( 'Unsupported post request', $error['error'] );
		$this->assert_true( $listed['success'] );
		$this->assert_true( str_contains( $GLOBALS['was_test_http_posts'][0]['url'], '/v25.0/phone-1/messages' ) );
		$this->assert_same( 'Bearer meta-token', $GLOBALS['was_test_http_posts'][0]['args']['headers']['Authorization'] );
		$this->assert_true( str_contains( $GLOBALS['was_test_http_gets'][0]['url'], '/v25.0/waba-1/subscribed_apps' ) );
	}

	public function test_inbound_media_failure_is_saved_with_meta_download_error() {
		$GLOBALS['was_test_http_response'] = [
			'code' => 400,
			'body' => [
				'error' => [
					'code'    => 100,
					'message' => "Unsupported get request. Object with ID 'media-bad' does not exist.",
				],
			],
		];

		$result = ( new InboundMediaService() )->handle_inbound_media(
			1,
			5,
			20,
			'media-bad',
			'image',
			'image/jpeg',
			'inbound',
			'phone-1'
		);
		$media = $GLOBALS['wpdb']->tables[ TableNameResolver::get_table_name( 'media' ) ][0];

		$this->assert_false( $result );
		$this->assert_same( 'failed', $media['status'] );
		$this->assert_true( str_contains( $media['error_message'], 'Unsupported get request' ) );
		$this->assert_count( 1, $GLOBALS['was_test_http_gets'] );
		$this->assert_true( str_contains( $GLOBALS['was_test_http_gets'][0]['url'], '/v25.0/media-bad' ) );
	}

	public function test_phone_diagnostics_reads_meta_details_and_updates_local_number() {
		$GLOBALS['was_test_http_response_queue'] = [
			[
				'code' => 200,
				'body' => [
					'data' => [
						[ 'id' => 'phone-1', 'display_phone_number' => '+55 31 99999-0000', 'verified_name' => 'Agenda', 'quality_rating' => 'GREEN' ],
					],
				],
			],
			[
				'code' => 200,
				'body' => [ 'id' => 'phone-1', 'status' => 'CONNECTED', 'name_status' => 'APPROVED', 'health_status' => 'GREEN' ],
			],
			[
				'code' => 200,
				'body' => [ 'data' => [ 'about' => 'Agenda oficial' ] ],
			],
		];

		$result = ( new PhoneNumberDiagnosticsService() )->run( 1, 'waba-1', 'waba-token' );
		$phone = $GLOBALS['wpdb']->get_row( "SELECT * FROM " . TableNameResolver::get_table_name( 'whatsapp_phone_numbers' ) . " WHERE phone_number_id = 'phone-1'" );

		$this->assert_same( 'success', $result['status'] );
		$this->assert_same( 1, count( $result['numbers'] ) );
		$this->assert_same( 'CONNECTED', $result['numbers'][0]['status'] );
		$this->assert_same( 'APPROVED', $result['numbers'][0]['name_status'] );
		$this->assert_same( 'Agenda', $phone->verified_name );
		$this->assert_same( 'GREEN', $phone->quality_rating );
		$this->assert_count( 3, $GLOBALS['was_test_http_gets'] );
	}
}
