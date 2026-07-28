<?php

use WAS\Auth\TenantContext;
use WAS\Core\TableNameResolver;
use WAS\Meta\TokenVault;
use WAS\Templates\TemplateMetaService;
use WAS\Templates\TemplateRepository;

class TemplatePersistenceContractsTest extends WAS_Router_TestCase {

	protected function set_up() {
		global $wpdb;
		TenantContext::set_tenant_id( 1 );
		$wpdb->insert( TableNameResolver::get_table_name( 'tenants' ), [ 'id' => 1, 'name' => 'Agenda', 'slug' => 'agenda', 'status' => 'active', 'created_at' => current_time( 'mysql', true ) ] );
		$wpdb->insert( TableNameResolver::get_table_name( 'whatsapp_accounts' ), [ 'id' => 7, 'tenant_id' => 1, 'waba_id' => 'waba-1', 'status' => 'active', 'created_at' => current_time( 'mysql', true ) ] );
		$wpdb->insert( TableNameResolver::get_table_name( 'meta_tokens' ), [ 'id' => 20, 'tenant_id' => 1, 'whatsapp_account_id' => 7, 'access_token_encrypted' => TokenVault::encrypt( 'waba-token' ), 'status' => 'active', 'created_at' => current_time( 'mysql', true ) ] );
	}

	public function test_template_repository_covers_create_lookup_update_list_and_soft_delete() {
		$repository = new TemplateRepository();
		$id = $repository->create(
			[
				'name'             => 'boas_vindas',
				'language'         => 'pt_BR',
				'waba_id'          => 'waba-1',
				'category'         => 'UTILITY',
				'body_text'        => 'Olá {{1}}',
				'status'           => 'APPROVED',
				'meta_template_id' => 'meta-template-1',
			]
		);

		$by_id = $repository->get_by_id( $id );
		$by_name = $repository->get_by_name_lang( 'boas_vindas', 'pt_BR' );
		$any = $repository->find_any_by_name_lang( 1, 'waba-1', 'boas_vindas', 'pt_BR' );
		$tenant_template = $repository->findForTenant( $id, 1 );
		$updated = $repository->update( $id, [ 'status' => 'PENDING', 'body_text' => 'Atualizado' ] );
		$list = $repository->list_templates();

		$this->assert_true( $id > 0 );
		$this->assert_same( $id, (int) $by_id->id );
		$this->assert_same( 7, (int) $by_id->whatsapp_account_id );
		$this->assert_same( $id, (int) $by_name->id );
		$this->assert_same( $id, (int) $any->id );
		$this->assert_same( $id, (int) $tenant_template->id );
		$this->assert_same( 1, $updated );
		$this->assert_count( 1, $list );

		$this->assert_same( 1, $repository->softDelete( $id ) );
		$this->assert_null( $repository->findForTenant( $id, 1 ) );
		$this->assert_same( $id, (int) $repository->find_any_by_name_lang( 1, 'waba-1', 'boas_vindas', 'pt_BR' )->id );
	}

	public function test_template_meta_service_maps_all_graph_operations_and_query_parameters() {
		$GLOBALS['was_test_http_response_queue'] = [
			[ 'code' => 200, 'body' => [ 'data' => [ [ 'id' => 'meta-1' ] ] ] ],
			[ 'code' => 200, 'body' => [ 'id' => 'meta-1', 'status' => 'APPROVED' ] ],
			[ 'code' => 200, 'body' => [ 'id' => 'meta-2', 'status' => 'PENDING' ] ],
			[ 'code' => 200, 'body' => [ 'success' => true ] ],
			[ 'code' => 200, 'body' => [ 'success' => true ] ],
		];
		$service = new TemplateMetaService();
		$list = $service->list( 'waba-1', 'waba-token' );
		$get = $service->get( 'meta-1', 'waba-token' );
		$created = $service->create( 'waba-1', [ 'name' => 'novo' ], 'waba-token' );
		$updated = $service->update( 'meta-1', [ 'category' => 'UTILITY' ], 'waba-token' );
		$deleted_by_name = $service->deleteByName( 'waba-1', 'novo', 'waba-token' );
		$deleted_by_id = $service->deleteById( 'waba-1', 'novo', 'meta-2', 'waba-token' );

		$this->assert_true( $list['success'] );
		$this->assert_true( $get['success'] );
		$this->assert_true( $created['success'] );
		$this->assert_true( $updated['success'] );
		$this->assert_true( $deleted_by_name['success'] );
		$this->assert_true( $deleted_by_id['success'] );
		$this->assert_count( 4, $GLOBALS['was_test_http_posts'] );
		$this->assert_count( 2, $GLOBALS['was_test_http_gets'] );
		$this->assert_true( str_contains( $GLOBALS['was_test_http_gets'][0]['url'], '/v25.0/waba-1/message_templates' ) );
		$this->assert_true( str_contains( $GLOBALS['was_test_http_gets'][0]['url'], 'fields=' ) );
		$this->assert_true( str_contains( $GLOBALS['was_test_http_gets'][1]['url'], '/v25.0/meta-1' ) );
		$this->assert_same( 'DELETE', $GLOBALS['was_test_http_posts'][2]['args']['method'] );
		$this->assert_same( 'DELETE', $GLOBALS['was_test_http_posts'][3]['args']['method'] );
		$this->assert_true( str_contains( $GLOBALS['was_test_http_posts'][2]['url'], '/v25.0/waba-1/message_templates' ) );
		$this->assert_same( 'Bearer waba-token', $GLOBALS['was_test_http_posts'][2]['args']['headers']['Authorization'] );
	}
}
