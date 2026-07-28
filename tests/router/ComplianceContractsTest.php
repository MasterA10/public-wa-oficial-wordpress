<?php

use WAS\Auth\TenantContext;
use WAS\Compliance\DataDeletionRepository;
use WAS\Compliance\DataPrivacyService;
use WAS\Compliance\OptInService;
use WAS\Compliance\ReviewChecklistService;
use WAS\Core\TableNameResolver;

class ComplianceContractsTest extends WAS_Router_TestCase {

	protected function set_up() {
		global $wpdb;
		TenantContext::set_tenant_id( 1 );
		$wpdb->insert( TableNameResolver::get_table_name( 'contacts' ), [ 'id' => 30, 'tenant_id' => 1, 'wa_id' => '5531999000001', 'phone' => '5531999000001', 'profile_name' => 'Ana', 'opt_in_status' => 'unknown' ] );
	}

	public function test_privacy_service_exports_only_current_tenant_and_anonymizes_with_audit() {
		$service = new DataPrivacyService();
		$export = $service->exportContactData( 30 );
		$updated = $service->deleteContactData( 30 );
		$contact = $GLOBALS['wpdb']->get_row( 'SELECT * FROM ' . TableNameResolver::get_table_name( 'contacts' ) . ' WHERE id = 30' );
		$logs = $GLOBALS['wpdb']->tables[ TableNameResolver::getAuditLogsTable() ] ?? [];

		$this->assert_same( 30, (int) $export['contact']->id );
		$this->assert_not_null( $export['exported_at'] );
		$this->assert_same( 1, $updated );
		$this->assert_same( 'ANONYMIZED', $contact->phone );
		$this->assert_same( 'DELETED', $contact->profile_name );
		$this->assert_same( 'revoked', $contact->opt_in_status );
		$this->assert_same( 'delete_contact_data', $logs[0]['action'] );
	}

	public function test_opt_in_persists_consent_updates_contact_and_audits_source() {
		( new OptInService() )->registerOptIn( 30, 'web_form', 'Aceito receber mensagens.' );
		$optins = $GLOBALS['wpdb']->tables[ TableNameResolver::get_table_name( 'contact_optins' ) ];
		$contact = $GLOBALS['wpdb']->get_row( 'SELECT * FROM ' . TableNameResolver::get_table_name( 'contacts' ) . ' WHERE id = 30' );
		$logs = $GLOBALS['wpdb']->tables[ TableNameResolver::getAuditLogsTable() ] ?? [];
		$metadata = json_decode( $logs[0]['metadata'], true );

		$this->assert_count( 1, $optins );
		$this->assert_same( 1, (int) $optins[0]['tenant_id'] );
		$this->assert_same( 'web_form', $optins[0]['source'] );
		$this->assert_same( 'Aceito receber mensagens.', $optins[0]['consent_text'] );
		$this->assert_same( 'opt_in', $contact->opt_in_status );
		$this->assert_same( 'register_opt_in', $logs[0]['action'] );
		$this->assert_same( 'web_form', $metadata['source'] );
	}

	public function test_data_deletion_repository_round_trip_and_review_checklist_contract() {
		$uuid = 'deletion-request-1';
		$this->assert_true( DataDeletionRepository::insert( [ 'request_uuid' => $uuid, 'status' => 'pending', 'email' => 'ana@example.test' ] ) );
		$request = DataDeletionRepository::find_by_uuid( $uuid );
		$checklist = new ReviewChecklistService();

		$this->assert_same( $uuid, $request->request_uuid );
		$this->assert_same( 'pending', $request->status );
		$this->assert_array_has_key( 'whatsapp_business_messaging', $checklist->getJustifications() );
		$this->assert_array_has_key( 'step_5', $checklist->getTechnicalWalkthrough() );
	}

	public function test_privacy_service_rejects_contact_from_another_tenant() {
		global $wpdb;
		$wpdb->insert( TableNameResolver::get_table_name( 'contacts' ), [ 'id' => 31, 'tenant_id' => 2, 'wa_id' => '5531999000002' ] );

		$exception = $this->assert_throws( fn() => ( new DataPrivacyService() )->exportContactData( 31 ) );
		$this->assert_same( 'Contato não encontrado.', $exception->getMessage() );
	}
}
