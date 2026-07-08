<?php

use WAS\Core\TableNameResolver;
use WAS\Router\RouteRepository;
use WAS\Router\RouterApiController;
use WAS\Router\TemplateRouterService;

class TemplateRouterServiceTest extends WAS_Router_TestCase {

	protected function set_up() {
		global $wpdb;
		$wpdb->insert( TableNameResolver::get_table_name( 'tenants' ), [ 'id' => 1, 'name' => 'Agenda', 'slug' => 'agenda', 'created_at' => current_time( 'mysql', true ) ] );
		$wpdb->insert( TableNameResolver::get_table_name( 'whatsapp_accounts' ), [ 'id' => 7, 'tenant_id' => 1, 'meta_app_id' => 1, 'waba_id' => 'meta-waba-1', 'name' => 'WABA', 'created_at' => current_time( 'mysql', true ) ] );
		$wpdb->insert( TableNameResolver::get_table_name( 'meta_tokens' ), [ 'id' => 20, 'tenant_id' => 1, 'whatsapp_account_id' => 7, 'access_token_encrypted' => \WAS\Meta\TokenVault::encrypt( 'waba-token' ), 'status' => 'active', 'created_at' => current_time( 'mysql', true ) ] );
		$GLOBALS['was_test_http_response'] = [
			'code' => 200,
			'body' => [ 'id' => 'template-id', 'status' => 'PENDING' ],
		];
	}

	public function test_submits_template_for_approval_using_internal_waba_id() {
		$result = ( new TemplateRouterService() )->submit_template(
			[
				'waba_id'         => 7,
				'phone_number_id' => 10,
				'name'            => 'pedido_confirmado',
				'language'        => 'pt_BR',
				'category'        => 'UTILITY',
				'components'      => [
					[ 'type' => 'BODY', 'text' => 'Pedido {{1}} confirmado.' ],
				],
			]
		);

		$this->assert_false( is_wp_error( $result ) );
		$this->assert_same( 7, $result['waba_id'] );
		$this->assert_same( 'template-id', $result['id'] );
		$this->assert_same( 'template-id', $result['provider_template_id'] );
		$this->assert_same( 'template-id', $result['meta_response']['id'] );
		$this->assert_same( 'PENDING', $result['template_status'] );
		$this->assert_same( $result['template_id'], $result['router_template_id'] );
		$this->assert_count( 1, $GLOBALS['was_test_http_posts'] );
		$call = $GLOBALS['was_test_http_posts'][0];
		$this->assert_true( str_contains( $call['url'], '/v25.0/meta-waba-1/message_templates' ) );
		$this->assert_same( 'Bearer waba-token', $call['args']['headers']['Authorization'] );

		$templates = $GLOBALS['wpdb']->tables[ TableNameResolver::getTemplatesTable() ];
		$this->assert_count( 1, $templates );
		$this->assert_same( 7, (int) $templates[0]['whatsapp_account_id'] );
		$this->assert_same( 10, (int) $templates[0]['router_phone_number_id'] );
		$this->assert_same( 'UTILITY', $templates[0]['category'] );
		$this->assert_same( 'PENDING', $templates[0]['status'] );
		$this->assert_same( 'template-id', $templates[0]['meta_template_id'] );
	}

	public function test_submits_template_from_nested_official_payload() {
		$result = ( new TemplateRouterService() )->submit_template(
			[
				'waba_id'  => 7,
				'template' => [
					'name'       => 'lembrete_agendamento',
					'language'   => 'pt_BR',
					'category'   => 'UTILITY',
					'components' => [
						[ 'type' => 'BODY', 'text' => 'Seu horario esta confirmado.' ],
					],
				],
			]
		);
		$body = json_decode( $GLOBALS['was_test_http_posts'][0]['args']['body'], true );

		$this->assert_false( is_wp_error( $result ) );
		$this->assert_same( 'lembrete_agendamento', $body['name'] );
		$this->assert_same( 'Seu horario esta confirmado.', $body['components'][0]['text'] );
	}

	public function test_returns_controlled_error_when_waba_has_no_token() {
		global $wpdb;
		$wpdb->tables[ TableNameResolver::get_table_name( 'meta_tokens' ) ] = [];

		$result = ( new TemplateRouterService() )->submit_template(
			[
				'waba_id'    => 7,
				'name'       => 'sem_token',
				'language'   => 'pt_BR',
				'category'   => 'UTILITY',
				'components' => [ [ 'type' => 'BODY', 'text' => 'Teste' ] ],
			]
		);

		$this->assert_true( is_wp_error( $result ) );
		$this->assert_same( 'missing_waba_access_token', $result->get_error_code() );
	}

	public function test_returns_meta_rejection_without_stack_trace() {
		$GLOBALS['was_test_http_response'] = [
			'code' => 400,
			'body' => [ 'error' => [ 'message' => 'Invalid template', 'code' => 100 ] ],
		];

		$result = ( new TemplateRouterService() )->submit_template(
			[
				'waba_id'    => 7,
				'name'       => 'template_invalido',
				'language'   => 'pt_BR',
				'category'   => 'UTILITY',
				'components' => [ [ 'type' => 'BODY', 'text' => '' ] ],
			]
		);

		$this->assert_true( is_wp_error( $result ) );
		$this->assert_same( 'meta_template_submission_rejected', $result->get_error_code() );
		$this->assert_same( 'Invalid template', $result->get_error_data()['meta_response']['error'] );
		$this->assert_same( 400, $result->get_error_data()['status'] );
	}

	public function test_template_rejection_response_keeps_legacy_detail_shape() {
		$GLOBALS['was_test_options']['was_router_service_secret'] = 'service-secret';
		$GLOBALS['was_test_http_response'] = [
			'code' => 400,
			'body' => [
				'error' => [
					'message'          => 'Ja existe conteudo nesse idioma',
					'code'             => 100,
					'error_subcode'    => 2388024,
					'error_user_title' => 'Ja existe conteudo nesse idioma',
					'error_user_msg'   => 'Ja existe conteudo em Portuguese (BR) para esse modelo.',
				],
			],
		];

		$request = new WP_REST_Request( 'POST', '/v1/whatsapp/templates' );
		$request->set_header( 'Authorization', 'Bearer service-secret' );
		$request->set_body_params(
			[
				'waba_id'  => 7,
				'template' => [
					'name'       => 'template_duplicado',
					'language'   => 'pt_BR',
					'category'   => 'UTILITY',
					'components' => [ [ 'type' => 'BODY', 'text' => 'Duplicado' ] ],
				],
			]
		);

		$response = ( new RouterApiController() )->submit_template( $request );
		$data = $response->get_data();

		$this->assert_same( 400, $response->get_status() );
		$this->assert_same( 'meta_template_submission_rejected', $data['error'] );
		$this->assert_same( 'meta_template_submission_rejected', $data['detail']['error'] );
		$this->assert_same( 2388024, $data['detail']['meta_response']['error']['error_subcode'] );
		$this->assert_same( 'Ja existe conteudo nesse idioma', $data['detail']['meta_response']['error']['message'] );
	}

	public function test_submits_template_preserving_parameter_format_and_variables() {
		$result = ( new TemplateRouterService() )->submit_template(
			[
				'waba_id'               => 7,
				'phone_number_id'       => 10,
				'name'                  => 'pedido_confirmado',
				'language'              => 'pt_BR',
				'category'              => 'UTILITY',
				'parameter_format'      => 'named',
				'allow_category_change' => true,
				'components'            => [
					[
						'type'    => 'BODY',
						'text'    => 'Ola {{first_name}}, pedido {{order_number}} confirmado.',
						'example' => [
							'body_text_named_params' => [
								[ 'param_name' => 'first_name', 'example' => 'Ana' ],
								[ 'param_name' => 'order_number', 'example' => '860198' ],
							],
						],
					],
				],
			]
		);

		$body = json_decode( $GLOBALS['was_test_http_posts'][0]['args']['body'], true );
		$this->assert_false( is_wp_error( $result ) );
		$this->assert_same( 'submitted', $result['status'] );
		$this->assert_same( 'named', $body['parameter_format'] );
		$this->assert_true( $body['allow_category_change'] );
		$this->assert_same( 'first_name', $result['variables'][0]['name'] );
		$this->assert_same( 'Ana', $result['variables'][0]['example'] );
		$this->assert_same( 'named', $result['parameter_format'] );
	}

	public function test_template_list_extracts_positional_and_named_variables() {
		global $wpdb;
		$wpdb->insert(
			TableNameResolver::getTemplatesTable(),
			[
				'id'                    => 90,
				'tenant_id'             => 1,
				'whatsapp_account_id'   => 7,
				'router_phone_number_id'=> 10,
				'meta_template_id'      => 'meta-variables',
				'waba_id'               => 'meta-waba-1',
				'name'                  => 'variaveis',
				'language'              => 'pt_BR',
				'category'              => 'UTILITY',
				'status'                => 'APPROVED',
				'friendly_payload'      => wp_json_encode( [ 'parameter_format' => 'named' ] ),
				'meta_response_json'    => wp_json_encode( [ 'quality_score' => [ 'score' => 'GREEN' ] ] ),
				'components_json'       => wp_json_encode(
					[
						[
							'type'    => 'HEADER',
							'format'  => 'TEXT',
							'text'    => 'Pedido {{1}}',
							'example' => [ 'header_text' => [ '860198' ] ],
						],
						[
							'type'    => 'BODY',
							'text'    => 'Ola {{first_name}}, entrega em {{delivery_date}}.',
							'example' => [
								'body_text_named_params' => [
									[ 'param_name' => 'first_name', 'example' => 'Ana' ],
									[ 'param_name' => 'delivery_date', 'example' => '10/06' ],
								],
							],
						],
					]
				),
				'body_text'             => 'Ola {{first_name}}',
				'created_at'            => current_time( 'mysql', true ),
				'updated_at'            => current_time( 'mysql', true ),
			]
		);

		$result = ( new TemplateRouterService() )->list_templates(
			[
				'waba_id'         => 7,
				'phone_number_id' => 10,
			]
		);

		$variables = [];
		foreach ( $result['templates'][0]['variables'] as $variable ) {
			$variables[ $variable['name'] ] = $variable;
		}
		$this->assert_same( '860198', $variables['1']['example'] );
		$this->assert_same( 'HEADER', $variables['1']['component_type'] );
		$this->assert_same( 'Ana', $variables['first_name']['example'] );
		$this->assert_same( 'named', $result['templates'][0]['parameter_format'] );
		$this->assert_same( [ 'score' => 'GREEN' ], $result['templates'][0]['quality_score'] );
	}

	public function test_template_sync_uses_official_meta_filters_and_summary() {
		$GLOBALS['was_test_http_response'] = [
			'code' => 200,
			'body' => [
				'data' => [
					[
						'id'            => 'meta-filtered-1',
						'name'          => 'pedido_confirmado',
						'language'      => 'pt_BR',
						'category'      => 'UTILITY',
						'status'        => 'APPROVED',
						'quality_score' => [ 'score' => 'GREEN' ],
						'components'    => [ [ 'type' => 'BODY', 'text' => 'Pedido {{order_number}} confirmado' ] ],
					],
				],
				'summary' => [
					'message_template_limit' => 250,
				],
			],
		];

		$result = ( new TemplateRouterService() )->list_templates(
			[
				'waba_id'         => 7,
				'sync'            => true,
				'category'        => 'UTILITY',
				'language'        => 'pt_BR',
				'name_or_content' => 'pedido',
				'quality_score'   => 'GREEN',
				'status'          => 'APPROVED',
				'include_summary' => true,
			]
		);

		$url = $GLOBALS['was_test_http_gets'][0]['url'];
		$this->assert_true( $result['synced'] );
		$this->assert_same( 1, $result['sync_count'] );
		$this->assert_same( 250, $result['meta_summary']['message_template_limit'] );
		$this->assert_true( str_contains( $url, 'category=UTILITY' ) );
		$this->assert_true( str_contains( $url, 'language=pt_BR' ) );
		$this->assert_true( str_contains( $url, 'name_or_content=pedido' ) );
		$this->assert_true( str_contains( $url, 'quality_score=GREEN' ) );
		$this->assert_true( str_contains( $url, 'status=APPROVED' ) );
		$this->assert_true( str_contains( $url, 'summary=total_count%2Cmessage_template_count%2Cmessage_template_limit%2Care_translations_complete' ) );
	}

	public function test_sync_imports_preexisting_meta_templates_without_phone_binding() {
		$GLOBALS['was_test_http_response'] = [
			'code' => 200,
			'body' => [
				'data' => [
					[
						'id'         => 'meta-existing-1',
						'name'       => 'template_antigo',
						'language'   => 'pt_BR',
						'category'   => 'UTILITY',
						'status'     => 'APPROVED',
						'components' => [ [ 'type' => 'BODY', 'text' => 'Template antigo' ] ],
					],
					[
						'id'         => 'meta-existing-2',
						'name'       => 'template_pendente',
						'language'   => 'pt_BR',
						'category'   => 'MARKETING',
						'status'     => 'PENDING',
						'components' => [ [ 'type' => 'BODY', 'text' => 'Template pendente' ] ],
					],
				],
			],
		];

		$result = ( new TemplateRouterService() )->list_templates(
			[
				'waba_id' => 7,
				'sync'    => true,
			]
		);

		$this->assert_true( $result['synced'] );
		$this->assert_same( 2, $result['sync_count'] );
		$this->assert_count( 2, $result['templates'] );
		$this->assert_true( str_contains( $GLOBALS['was_test_http_gets'][0]['url'], '/v25.0/meta-waba-1/message_templates' ) );
		$this->assert_true( str_contains( $GLOBALS['was_test_http_gets'][0]['url'], 'fields=id%2Cname%2Cstatus%2Ccategory%2Clanguage%2Ccomponents%2Crejected_reason%2Cquality_score%2Cparameter_format' ) );

		$templates = $GLOBALS['wpdb']->tables[ TableNameResolver::getTemplatesTable() ];
		$by_name = [];
		foreach ( $templates as $template ) {
			$by_name[ $template['name'] ] = $template;
		}

		$this->assert_array_has_key( 'template_antigo', $by_name );
		$this->assert_array_has_key( 'template_pendente', $by_name );
		$this->assert_same( 'APPROVED', $by_name['template_antigo']['status'] );
		$this->assert_same( 'meta-existing-1', $by_name['template_antigo']['meta_template_id'] );
		$this->assert_same( 7, (int) $by_name['template_antigo']['whatsapp_account_id'] );
		$this->assert_null( $by_name['template_antigo']['router_phone_number_id'] );
	}

	public function test_pending_template_status_is_checked_against_meta() {
		$this->insert_pending_template( 91, 'PENDING' );
		$GLOBALS['was_test_http_response'] = [
			'code' => 200,
			'body' => [
				'data' => [
					[
						'id'               => 'meta-template-91',
						'name'             => 'lembrete_agendamento',
						'language'         => 'pt_BR',
						'category'         => 'UTILITY',
						'status'           => 'IN_REVIEW',
						'parameter_format' => 'positional',
						'components'       => [
							[ 'type' => 'BODY', 'text' => 'Ola {{1}}, seu horario esta em analise.' ],
						],
					],
				],
			],
		];

		$result = ( new TemplateRouterService() )->sync_pending_template_statuses();
		$template = $GLOBALS['wpdb']->tables[ TableNameResolver::getTemplatesTable() ][0];
		$url = $GLOBALS['was_test_http_gets'][0]['url'];

		$this->assert_same( 1, $result['checked'] );
		$this->assert_same( 1, $result['updated'] );
		$this->assert_same( 0, $result['notified'] );
		$this->assert_same( 0, $result['errors'] );
		$this->assert_true( str_contains( $url, 'name=lembrete_agendamento' ) );
		$this->assert_true( str_contains( $url, 'language=pt_BR' ) );
		$this->assert_same( 'IN_REVIEW', $template['status'] );
		$this->assert_same( 'meta-template-91', $template['meta_template_id'] );
		$this->assert_not_null( $template['last_status_check_at'] );
		$this->assert_null( $template['last_status_error'] );
		$this->assert_count( 0, $GLOBALS['wpdb']->tables[ TableNameResolver::getWebhookEventsTable() ] ?? [] );
	}

	public function test_approved_template_status_sync_queues_destination_webhook_once() {
		$this->seed_phone_route();
		$this->insert_pending_template( 92, 'PENDING' );
		$GLOBALS['was_test_http_response_queue'] = [
			[
				'code' => 200,
				'body' => [
					'data' => [
						[
							'id'               => 'meta-template-92',
							'name'             => 'lembrete_agendamento',
							'language'         => 'pt_BR',
							'category'         => 'UTILITY',
							'status'           => 'APPROVED',
							'quality_score'    => [ 'score' => 'GREEN' ],
							'parameter_format' => 'positional',
							'components'       => [
								[ 'type' => 'BODY', 'text' => 'Ola {{1}}, seu horario foi aprovado.' ],
							],
						],
					],
				],
			],
			[
				'code' => 200,
				'body' => [ 'ok' => true ],
			],
		];

		$result = ( new TemplateRouterService() )->sync_pending_template_statuses();
		$template = $GLOBALS['wpdb']->tables[ TableNameResolver::getTemplatesTable() ][0];
		$events = $GLOBALS['wpdb']->tables[ TableNameResolver::getWebhookEventsTable() ] ?? [];
		$delivered = json_decode( $GLOBALS['was_test_http_posts'][0]['args']['body'], true );

		$this->assert_same( 1, $result['checked'] );
		$this->assert_same( 1, $result['updated'] );
		$this->assert_same( 1, $result['notified'] );
		$this->assert_same( 'APPROVED', $template['status'] );
		$this->assert_not_null( $template['approved_notified_at'] );
		$this->assert_count( 1, $events );
		$this->assert_same( 'template_status_updated', $events[0]['event_type'] );
		$this->assert_same( 'queued', $events[0]['routing_status'] );
		$this->assert_same( 'https://agenda-master.test/webhook', $GLOBALS['was_test_http_posts'][0]['url'] );
		$this->assert_same( 'router-secret', $GLOBALS['was_test_http_posts'][0]['args']['headers']['x-waba-router-secret'] );
		$this->assert_same( 'template_status_updated', $delivered['event_type'] );
		$this->assert_same( 92, $delivered['payload']['template_id'] );
		$this->assert_same( 'meta-template-92', $delivered['payload']['meta_template_id'] );
		$this->assert_same( 'APPROVED', $delivered['payload']['status'] );

		$repeat = ( new TemplateRouterService() )->sync_pending_template_statuses();
		$this->assert_same( 0, $repeat['checked'] );
		$this->assert_count( 1, $GLOBALS['was_test_http_gets'] );
		$this->assert_count( 1, $GLOBALS['was_test_http_posts'] );
		$this->assert_count( 1, $GLOBALS['wpdb']->tables[ TableNameResolver::getWebhookEventsTable() ] );
	}

	private function insert_pending_template( $id, $status ) {
		global $wpdb;

		$wpdb->insert(
			TableNameResolver::getTemplatesTable(),
			[
				'id'                    => (int) $id,
				'tenant_id'             => 1,
				'whatsapp_account_id'   => 7,
				'router_phone_number_id'=> 10,
				'meta_template_id'      => null,
				'waba_id'               => 'meta-waba-1',
				'name'                  => 'lembrete_agendamento',
				'language'              => 'pt_BR',
				'category'              => 'UTILITY',
				'status'                => $status,
				'components_json'       => wp_json_encode( [ [ 'type' => 'BODY', 'text' => 'Ola {{1}}' ] ] ),
				'body_text'             => 'Ola {{1}}',
				'created_at'            => current_time( 'mysql', true ),
				'updated_at'            => current_time( 'mysql', true ),
			]
		);
	}

	private function seed_phone_route() {
		global $wpdb;

		$wpdb->insert(
			TableNameResolver::get_table_name( 'whatsapp_phone_numbers' ),
			[
				'id'                  => 10,
				'tenant_id'           => 1,
				'whatsapp_account_id' => 7,
				'phone_number_id'     => 'meta-phone-10',
				'display_phone_number'=> '+5511999999999',
				'status'              => 'active',
				'created_at'          => current_time( 'mysql', true ),
				'updated_at'          => current_time( 'mysql', true ),
			]
		);

		( new RouteRepository() )->create_or_update(
			[
				'tenant_id'       => 1,
				'phone_number_id' => 10,
				'name'            => 'Agenda Master',
				'target_url'      => 'https://agenda-master.test/webhook',
				'secret'          => 'router-secret',
				'event_filters'   => [ 'event_types' => [ 'template_status_updated' ] ],
				'is_active'       => 1,
			]
		);
	}
}
