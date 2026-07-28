<?php

use WAS\Auth\TenantContext;
use WAS\Core\TableNameResolver;
use WAS\Inbox\ContactRepository;
use WAS\Tenants\TenantRepository;
use WAS\WhatsApp\PhoneNumberRepository;
use WAS\WhatsApp\WhatsAppAccountRepository;

class RepositoryContractsTest extends WAS_Router_TestCase {

	protected function set_up() {
		TenantContext::set_tenant_id( 1 );
	}

	public function test_tenant_repository_creates_finds_and_updates_status() {
		$repository = new TenantRepository();
		$id = $repository->create( [ 'name' => 'Empresa', 'slug' => 'empresa', 'status' => 'active' ] );

		$this->assert_true( $id > 0 );
		$this->assert_same( 'empresa', $repository->find( $id )->slug );
		$this->assert_same( $id, $repository->find_by_slug( 'empresa' )->id );
		$this->assert_true( $repository->update_status( $id, 'inactive' ) );
		$this->assert_same( 'inactive', $repository->find( $id )->status );
	}

	public function test_waba_and_phone_repositories_keep_tenant_and_default_number_contract() {
		$accounts = new WhatsAppAccountRepository();
		$account_id = $accounts->createOrUpdate( [
			'tenant_id' => 1,
			'waba_id'   => 'waba-contract',
			'name'      => 'WABA Contract',
		] );
		$phones = new PhoneNumberRepository();
		$first_id = $phones->createOrUpdate( [
			'tenant_id'           => 1,
			'whatsapp_account_id' => $account_id,
			'phone_number_id'     => 'meta-phone-first',
			'display_phone_number' => '+55 31 90000-0001',
		] );
		$second_id = $phones->createOrUpdate( [
			'tenant_id'           => 1,
			'whatsapp_account_id' => $account_id,
			'phone_number_id'     => 'meta-phone-second',
			'display_phone_number' => '+55 31 90000-0002',
			'is_default'          => true,
		] );

		$this->assert_same( $account_id, $accounts->findByWabaId( 'waba-contract' )->id );
		$this->assert_same( $account_id, $accounts->findForTenant( 1, 'waba-contract' )->id );
		$this->assert_true( $first_id > 0 );
		$this->assert_true( $second_id > 0 );
		$this->assert_same( 'meta-phone-second', $phones->getDefaultByTenant( 1 )->phone_number_id );
		$this->assert_same( $first_id, $phones->findByPhoneNumberId( 'meta-phone-first' )->id );
		$this->assert_same( 1, count( $accounts->getByTenant( 1 ) ) );
	}

	public function test_contact_repository_normalizes_phone_confirms_identity_and_manages_tags() {
		$repository = new ContactRepository();
		$contact = $repository->find_or_create_by_wa_id( '5531999000001', 'Cliente', '(31) 99999-0001' );

		$this->assert_not_null( $contact );
		$this->assert_same( '5531999000001', $contact->wa_id );
		$this->assert_same( '5531999990001', $contact->normalized_phone );
		$this->assert_same( 'confirmed_by_wa_id', $contact->phone_status );
		$this->assert_true( $repository->add_tag( $contact->id, 'vip' ) );
		$this->assert_true( $repository->add_tag( $contact->id, 'vip' ) );
		$this->assert_true( $repository->remove_tag( $contact->id, 'vip' ) );
		$this->assert_same( [], json_decode( $repository->get_by_id( $contact->id )->tags, true ) );
		$this->assert_same( $contact->id, $repository->find_by_normalized_phone( '55 31 99999-0001' )->id );
		$this->assert_count( 1, $repository->list_contacts() );
	}
}
