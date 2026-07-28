<?php

use WAS\Meta\TokenVault;
use WAS\Meta\MetaApiResponse;
use WAS\Meta\MetaEndpointRegistry;
use WAS\Router\SecretVault;
use WAS\Router\RouterSettings;
use WAS\Router\TemplateResponseFormatter;
use WAS\Core\URLService;
use WAS\Templates\AuthenticationTemplatePayloadBuilder;
use WAS\Templates\AuthenticationTemplateSendPayloadBuilder;
use WAS\Templates\TemplatePayloadBuilder;
use WAS\Templates\TemplatePayloadFactory;
use WAS\Templates\TemplatePolicyService;
use WAS\Templates\TemplateVariableParser;
use WAS\WhatsApp\MediaValidationService;
use WAS\WhatsApp\PhoneNormalizerService;
use WAS\WhatsApp\WebhookReplyContextParser;
use WAS\WhatsApp\WebhookSignatureValidator;
use WAS\WhatsApp\WhatsAppInboundMessageNormalizer;

class CoreContractsTest extends WAS_Router_TestCase {

	public function test_phone_normalizer_generates_brazilian_ninth_digit_candidates() {
		$result = ( new PhoneNormalizerService() )->normalize( '(31) 99999-1234' );

		$this->assert_true( $result['success'] );
		$this->assert_same( '5531999991234', $result['normalized'] );
		$this->assert_same( [ '5531999991234', '553199991234' ], $result['candidates'] );
	}

	public function test_phone_normalizer_adds_country_code_and_handles_empty_input() {
		$national = ( new PhoneNormalizerService() )->normalize( '31 3333-4444' );
		$empty = ( new PhoneNormalizerService() )->normalize( '---' );

		$this->assert_same( '553133334444', $national['normalized'] );
		$this->assert_same( '5531933334444', $national['candidates'][1] );
		$this->assert_false( $empty['success'] );
		$this->assert_same( 'Telefone vazio.', $empty['message'] );
		$this->assert_count( 0, $empty['candidates'] );
	}

	public function test_media_validation_accepts_allowed_file_and_rejects_type_size_and_missing_file() {
		$validator = new MediaValidationService();
		$file = tempnam( sys_get_temp_dir(), 'was-media-test-' );
		file_put_contents( $file, 'valid media fixture' );

		$this->assert_true( $validator->validate( $file, 'image/jpeg', 'image' ) );
		$this->assert_throws( fn() => $validator->validate( $file, 'image/gif', 'image' ) );
		$this->assert_throws( fn() => $validator->validate( $file . '-missing', 'image/jpeg', 'image' ) );
		$this->assert_throws( fn() => $validator->validate( $file, 'image/jpeg', 'unsupported' ) );

		file_put_contents( $file, str_repeat( 'x', 5 * 1024 * 1024 + 1 ) );
		$this->assert_throws( fn() => $validator->validate( $file, 'image/jpeg', 'image' ) );
		unlink( $file );
	}

	public function test_secret_vault_round_trip_never_changes_secret_and_masks_it() {
		$secret = 'route-secret-for-contract-test';
		$encrypted = SecretVault::encrypt( $secret );

		$this->assert_true( $encrypted !== $secret );
		$this->assert_same( $secret, SecretVault::decrypt( $encrypted ) );
		$this->assert_same( 'rout...test', TokenVault::mask( $secret ) );
		$this->assert_null( SecretVault::encrypt( null ) );
		$this->assert_null( SecretVault::decrypt( null ) );
	}

	public function test_token_vault_prefers_waba_token_and_falls_back_only_to_generic_tenant_token() {
		global $wpdb;

		$wpdb->insert( was_router_table( 'meta_tokens' ), [
			'id'                    => 1,
			'tenant_id'             => 1,
			'whatsapp_account_id'   => null,
			'access_token_encrypted'=> TokenVault::encrypt( 'generic-token' ),
			'status'                => 'active',
		] );
		$wpdb->insert( was_router_table( 'meta_tokens' ), [
			'id'                    => 2,
			'tenant_id'             => 1,
			'whatsapp_account_id'   => 9,
			'access_token_encrypted'=> TokenVault::encrypt( 'waba-token' ),
			'status'                => 'active',
		] );
		$wpdb->insert( was_router_table( 'meta_tokens' ), [
			'id'                    => 3,
			'tenant_id'             => 1,
			'whatsapp_account_id'   => 9,
			'access_token_encrypted'=> TokenVault::encrypt( 'inactive-token' ),
			'status'                => 'inactive',
		] );

		$vault = new TokenVault();
		$this->assert_same( 'waba-token', $vault->get_valid_token( 1, 9 ) );
		$this->assert_same( 'generic-token', $vault->get_valid_token( 1, 10 ) );
		$this->assert_null( $vault->get_valid_token( 2, 9 ) );
	}

	public function test_router_settings_reads_external_and_route_secrets_from_options() {
		$GLOBALS['was_test_options']['was_router_service_secret'] = 'service-secret';
		$GLOBALS['was_test_options']['was_router_route_secret'] = 'route-secret';
		$GLOBALS['was_test_options']['was_external_send_webhook_secret'] = 'external-secret';
		$GLOBALS['was_test_options']['was_router_route_target_url'] = 'https://app.test/webhook';

		$this->assert_same( 'service-secret', RouterSettings::get_service_secret() );
		$this->assert_same( 'route-secret', RouterSettings::get_route_secret() );
		$this->assert_same( 'external-secret', RouterSettings::get_external_send_webhook_secret() );
		$this->assert_same( 'https://app.test/webhook', RouterSettings::get_default_route_target_url() );
	}

	public function test_url_service_maps_shell_pages_and_meta_helpers() {
		$this->assert_same( 'https://example.test/app/dashboard', URLService::get_page_url( 'was-dashboard' ) );
		$this->assert_same( 'https://example.test/app/settings/meta', URLService::get_meta_settings_url() );
		$this->assert_same( 'https://example.test/app/settings/whatsapp', URLService::get_whatsapp_settings_url() );
	}

	public function test_template_variable_parser_preserves_order_and_falls_back_for_missing_examples() {
		$result = ( new TemplateVariableParser() )->parse(
			'Olá {{ nome }}, seu pedido {{pedido}} está pronto, {{nome}}.',
			[ 'nome' => 'Ana' ]
		);

		$this->assert_same( 'Olá {{1}}, seu pedido {{2}} está pronto, {{1}}.', $result['meta_text'] );
		$this->assert_same( [ '1' => 'nome', '2' => 'pedido' ], $result['variable_map'] );
		$this->assert_same( [ 'Ana', 'Exemplo' ], $result['example_values'] );
	}

	public function test_authentication_template_builder_builds_copy_code_and_rejects_invalid_one_tap() {
		$builder = new AuthenticationTemplatePayloadBuilder();
		$copy_code = $builder->build(
			[
				'name'           => 'Código de Login',
				'language'       => 'pt_BR',
				'authentication' => [ 'type' => 'COPY_CODE', 'code_expiration_minutes' => 10 ],
			]
		);

		$this->assert_same( 'codigo_de_login', $copy_code['name'] );
		$this->assert_same( 'AUTHENTICATION', $copy_code['category'] );
		$this->assert_same( 'FOOTER', $copy_code['components'][1]['type'] );
		$this->assert_same( 10, $copy_code['components'][1]['code_expiration_minutes'] );
		$this->assert_throws(
			fn() => $builder->build(
				[
					'name'           => 'Login',
					'authentication' => [ 'type' => 'ONE_TAP' ],
				]
			)
		);
	}

	public function test_meta_webhook_signature_accepts_only_the_expected_sha256_hmac() {
		$body = '{"object":"whatsapp_business_account"}';
		$secret = 'meta-app-secret';
		$signature = 'sha256=' . hash_hmac( 'sha256', $body, $secret );

		$this->assert_true( WebhookSignatureValidator::is_valid( $body, $signature, $secret ) );
		$this->assert_false( WebhookSignatureValidator::is_valid( $body . 'x', $signature, $secret ) );
		$this->assert_false( WebhookSignatureValidator::is_valid( $body, 'sha1=' . substr( $signature, 7 ), $secret ) );
		$this->assert_false( WebhookSignatureValidator::is_valid( $body, 'sha256=invalid', $secret ) );
	}

	public function test_inbound_normalizer_preserves_meta_fields_for_text_media_interactive_and_location() {
		$normalizer = new WhatsAppInboundMessageNormalizer();
		$text = $normalizer->normalize(
			[
				'id'        => 'wamid-text',
				'from'      => '5531999000001',
				'timestamp' => '1710000000',
				'type'      => 'text',
				'text'      => [ 'body' => 'Olá' ],
			]
		);
		$media = $normalizer->normalize(
			[
				'id'    => 'wamid-image',
				'type'  => 'image',
				'image' => [ 'id' => 'media-1', 'mime_type' => 'image/jpeg', 'sha256' => 'hash', 'caption' => 'Foto' ],
			]
		);
		$interactive = $normalizer->normalize(
			[
				'id'          => 'wamid-interactive',
				'type'        => 'interactive',
				'interactive' => [ 'type' => 'button_reply', 'button_reply' => [ 'id' => 'yes', 'title' => 'Sim' ] ],
			]
		);
		$location = $normalizer->normalize(
			[
				'id'       => 'wamid-location',
				'type'     => 'location',
				'location' => [ 'latitude' => -20.1, 'longitude' => -44.2, 'name' => 'Praça' ],
			]
		);

		$this->assert_same( 'Olá', $text['text_body'] );
		$this->assert_same( 'text', $text['message_type'] );
		$this->assert_same( 'media-1', $media['meta_media_id'] );
		$this->assert_same( 'image/jpeg', $media['mime_type'] );
		$this->assert_same( 'Foto', $media['text_body'] );
		$this->assert_same( 'yes', $interactive['interactive_id'] );
		$this->assert_same( 'Sim', $interactive['text_body'] );
		$this->assert_same( -20.1, $location['latitude'] );
		$this->assert_same( '📍 Localização: Praça', $location['text_body'] );
	}

	public function test_inbound_normalizer_covers_button_reaction_contacts_order_and_all_media_types() {
		$normalizer = new WhatsAppInboundMessageNormalizer();
		$button = $normalizer->normalize( [ 'type' => 'button', 'button' => [ 'text' => 'Sim', 'payload' => 'yes' ] ] );
		$reaction = $normalizer->normalize( [ 'type' => 'reaction', 'reaction' => [ 'emoji' => '👍', 'message_id' => 'wamid-original' ] ] );
		$contacts = $normalizer->normalize( [ 'type' => 'contacts', 'contacts' => [ [ 'name' => [ 'formatted_name' => 'Ana' ] ] ] ] );
		$order = $normalizer->normalize( [ 'type' => 'order', 'order' => [ 'catalog_id' => 'catalog-1', 'product_items' => [] ] ] );
		$sticker = $normalizer->normalize( [ 'type' => 'sticker', 'sticker' => [ 'id' => 'sticker-1', 'mime_type' => 'image/webp' ] ] );
		$unsupported = $normalizer->normalize( [ 'type' => 'unsupported' ] );

		$this->assert_same( 'Sim', $button['text_body'] );
		$this->assert_same( 'yes', $button['button_payload'] );
		$this->assert_same( '👍', $reaction['text_body'] );
		$this->assert_same( 'wamid-original', $reaction['reply_to_wa_message_id'] );
		$this->assert_same( '👤 Contato(s): Ana', $contacts['text_body'] );
		$this->assert_same( '🛒 Novo Pedido (Catálogo)', $order['text_body'] );
		$this->assert_same( 'sticker-1', $sticker['meta_media_id'] );
		$this->assert_same( 'image/webp', $sticker['mime_type'] );
		$this->assert_same( 'Tipo de mensagem ainda não suportado: unsupported', $unsupported['text_body'] );
	}

	public function test_webhook_reply_context_supports_both_meta_context_id_aliases() {
		$parser = new WebhookReplyContextParser();
		$with_id = $parser->extract( [ 'context' => [ 'id' => 'wamid-original', 'from' => '5531999000001' ] ] );
		$with_alias = $parser->extract( [ 'context' => [ 'message_id' => 'wamid-alias' ] ] );
		$without_context = $parser->extract( [] );

		$this->assert_true( $with_id['has_context'] );
		$this->assert_same( 'wamid-original', $with_id['reply_to_wa_message_id'] );
		$this->assert_same( '5531999000001', $with_id['context_from'] );
		$this->assert_same( 'wamid-alias', $with_alias['reply_to_wa_message_id'] );
		$this->assert_false( $without_context['has_context'] );
		$this->assert_null( $without_context['context_payload'] );
	}

	public function test_meta_endpoint_registry_resolves_official_graph_paths_and_rejects_unknown_operations() {
		$this->assert_same( '/123/messages', MetaEndpointRegistry::resolve( 'messages.send', [ 'phone_number_id' => 123 ] ) );
		$this->assert_same( '/456/message_templates', MetaEndpointRegistry::resolve( 'templates.list', [ 'waba_id' => 456 ] ) );
		$this->assert_same( '/media-1', MetaEndpointRegistry::resolve( 'media.get', [ 'media_id' => 'media-1' ] ) );
		$this->assert_same( '', MetaEndpointRegistry::resolve( 'unknown.operation', [] ) );
	}

	public function test_meta_api_response_factories_keep_status_operation_data_and_request_id() {
		$success = MetaApiResponse::success( 'messages.send', [ 'messages' => [ [ 'id' => 'wamid-1' ] ] ], 200, 'req-1' );
		$error = MetaApiResponse::error( 'messages.send', [ 'message' => 'Bad token' ], 401, 'req-2' );

		$this->assert_true( $success->success );
		$this->assert_same( 200, $success->status_code );
		$this->assert_same( 'messages.send', $success->operation );
		$this->assert_same( 'req-1', $success->meta_request_id );
		$this->assert_false( $error->success );
		$this->assert_same( 401, $error->status_code );
		$this->assert_same( 'Bad token', $error->error['message'] );
	}

	public function test_template_response_formatter_extracts_variables_examples_and_parameter_format() {
		$template = (object) [
			'id'                    => 7,
			'tenant_id'             => 1,
			'whatsapp_account_id'   => 5,
			'router_phone_number_id'=> 10,
			'name'                  => 'hello',
			'language'              => 'pt_BR',
			'category'              => 'UTILITY',
			'status'                => 'APPROVED',
			'components_json'       => wp_json_encode( [ [ 'type' => 'BODY', 'text' => 'Olá {{nome}} {{1}}', 'example' => [ 'body_text' => [ [ 'Pedido 10' ] ], 'body_text_named_params' => [ [ 'param_name' => 'nome', 'example' => 'Ana' ] ] ] ] ] ),
			'friendly_payload'      => wp_json_encode( [ 'parameter_format' => 'NAMED' ] ),
			'meta_response_json'    => wp_json_encode( [ 'quality_score' => 'GREEN' ] ),
		];

		$result = ( new TemplateResponseFormatter() )->format( $template );

		$this->assert_same( 7, $result['id'] );
		$this->assert_same( 10, $result['phone_number_id'] );
		$this->assert_count( 2, $result['variables'] );
		$this->assert_same( 'nome', $result['variables'][0]['name'] );
		$this->assert_same( 'Ana', $result['variables'][0]['example'] );
		$this->assert_same( '1', $result['variables'][1]['name'] );
		$this->assert_same( 'Pedido 10', $result['variables'][1]['example'] );
		$this->assert_same( 'named', $result['parameter_format'] );
		$this->assert_same( 'GREEN', $result['quality_score'] );
	}

	public function test_template_payload_builder_maps_variables_headers_and_buttons_to_meta_contract() {
		$result = ( new TemplatePayloadBuilder() )->build(
			[
				'name'   => 'Oferta Especial',
				'category' => 'marketing',
				'header' => [ 'type' => 'TEXT', 'text' => 'Olá' ],
				'body'   => [
					'text'      => 'Olá {{nome}}, pedido {{pedido}}.',
					'variables' => [
						[ 'key' => 'nome', 'example' => 'Ana' ],
						[ 'key' => 'pedido', 'example' => '123' ],
					],
				],
				'footer'  => [ 'text' => 'Obrigado' ],
				'buttons' => [
					[ 'type' => 'QUICK_REPLY', 'text' => 'Confirmar' ],
					[ 'type' => 'URL', 'text' => 'Ver pedido', 'url' => 'https://app.test/{{pedido}}', 'example' => '123' ],
				],
			]
		);

		$payload = $result['meta_payload'];
		$this->assert_same( 'oferta_especial', $payload['name'] );
		$this->assert_same( 'MARKETING', $payload['category'] );
		$this->assert_same( [ '1' => 'nome', '2' => 'pedido' ], $result['variable_map'] );
		$this->assert_same( 'HEADER', $payload['components'][0]['type'] );
		$this->assert_same( 'Olá {{1}}, pedido {{2}}.', $payload['components'][1]['text'] );
		$this->assert_same( [ 'Ana', '123' ], $payload['components'][1]['example']['body_text'][0] );
		$this->assert_same( 'https://app.test/{{2}}', $payload['components'][3]['buttons'][1]['url'] );
		$this->assert_same( [ '123' ], $payload['components'][3]['buttons'][1]['example'] );
		$this->assert_throws( fn() => ( new TemplatePayloadBuilder() )->build( [ 'name' => 'invalid', 'body' => [ 'text' => '{{nome}}', 'variables' => [ [ 'key' => 'nome', 'example' => 'Ana' ] ] ] ] ) );
	}

	public function test_authentication_send_payload_contains_copy_code_body_and_button_parameters() {
		$payload = ( new AuthenticationTemplateSendPayloadBuilder() )->build_copy_code_payload( '5531999000001', 'login_code', 'pt_BR', ' 123456 ' );

		$this->assert_same( 'template', $payload['type'] );
		$this->assert_same( '5531999000001', $payload['to'] );
		$this->assert_same( 'login_code', $payload['template']['name'] );
		$this->assert_same( '123456', $payload['template']['components'][0]['parameters'][0]['text'] );
		$this->assert_same( '123456', $payload['template']['components'][1]['parameters'][0]['text'] );
		$this->assert_throws( fn() => ( new AuthenticationTemplateSendPayloadBuilder() )->build_copy_code_payload( '5531999000001', 'login_code', 'pt_BR', ' ' ) );
	}

	public function test_template_payload_factory_and_policy_keep_meta_status_rules() {
		$payload = TemplatePayloadFactory::createMetaPayload(
			[
				'name'      => 'boas_vindas',
				'language'  => 'pt_BR',
				'category'  => 'UTILITY',
				'body_text' => 'Bem-vindo',
			]
		);
		$policy = new TemplatePolicyService();

		$this->assert_same( 'boas_vindas', $payload['name'] );
		$this->assert_same( 'Bem-vindo', $payload['components'][0]['text'] );
		$this->assert_true( $policy->canEdit( (object) [ 'status' => 'APPROVED' ] ) );
		$this->assert_true( $policy->shouldBlockEdit( (object) [ 'status' => 'PENDING' ] ) );
		$this->assert_true( $policy->shouldRecommendDuplicate( (object) [ 'status' => 'DISABLED' ] ) );
		$this->assert_false( $policy->canEdit( (object) [ 'status' => 'DELETED' ] ) );
	}
}
