<?php

use WAS\Auth\TenantContext;
use WAS\Auth\TenantGuard;
use WAS\Core\TableNameResolver;
use WAS\Tenants\TenantUserRepository;

class TenantSecurityContractsTest extends WAS_Router_TestCase {

	public function test_tenant_user_repository_attaches_replaces_and_detaches_membership() {
		global $wpdb;
		$table = TableNameResolver::get_table_name( 'tenant_users' );
		$repository = new TenantUserRepository();

		$this->assert_true( $repository->attach_user( 10, 7, 'agent' ) );
		$this->assert_true( $repository->user_belongs_to_tenant( 7, 10 ) );
		$this->assert_true( $repository->attach_user( 10, 7, 'manager' ) );
		$this->assert_count( 1, $wpdb->tables[ $table ] );
		$this->assert_same( 'manager', $wpdb->tables[ $table ][0]['role'] );
		$this->assert_true( $repository->detach_user( 10, 7 ) );
		$this->assert_false( $repository->user_belongs_to_tenant( 7, 10 ) );
	}

	public function test_tenant_guard_rejects_inactive_membership_and_accepts_active_membership() {
		global $wpdb;
		$GLOBALS['was_test_capabilities'][7]['platform_owner'] = false;
		$wpdb->insert(
			TableNameResolver::get_table_name( 'tenant_users' ),
			[ 'tenant_id' => 10, 'user_id' => 7, 'role' => 'agent', 'status' => 'inactive' ]
		);

		$this->assert_false( TenantGuard::user_belongs_to_tenant( 10, 7 ) );
		$wpdb->update(
			TableNameResolver::get_table_name( 'tenant_users' ),
			[ 'status' => 'active' ],
			[ 'tenant_id' => 10, 'user_id' => 7 ]
		);
		$this->assert_true( TenantGuard::user_belongs_to_tenant( 10, 7 ) );
	}

	public function test_platform_owner_bypasses_membership_and_runtime_context_does_not_change_saved_context() {
		TenantContext::set_tenant_id( 1 );
		TenantContext::set_runtime_tenant_id( 99 );

		$this->assert_same( 99, TenantContext::get_current_tenant_id() );
		$this->assert_same( 99, TenantContext::getTenantId() );
		$this->assert_same( 99, TenantContext::get_tenant_id() );
		$this->assert_true( TenantGuard::user_belongs_to_tenant( 99, 7 ) );

		TenantContext::set_runtime_tenant_id( null );
		$this->assert_same( 1, TenantContext::get_current_tenant_id() );
	}

	public function test_context_falls_back_to_first_tenant_membership() {
		global $wpdb;
		TenantContext::set_runtime_tenant_id( null );
		$property = ( new ReflectionClass( TenantContext::class ) )->getProperty( 'current_tenant_id' );
		$property->setAccessible( true );
		$property->setValue( null, null );
		$GLOBALS['was_test_user_meta'][1] = [];
		$wpdb->insert( TableNameResolver::get_table_name( 'tenant_users' ), [ 'tenant_id' => 42, 'user_id' => 1, 'status' => 'active' ] );

		$this->assert_same( 42, TenantContext::get_current_tenant_id() );
		$this->assert_same( 42, $GLOBALS['was_test_user_meta'][1]['_was_current_tenant_id'] );
	}
}
