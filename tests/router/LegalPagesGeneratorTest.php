<?php

use WAS\Compliance\LegalPagesGenerator;

class LegalPagesGeneratorTest extends WAS_Router_TestCase {

	public function test_company_data_is_saved_and_exposed_to_legal_templates() {
		LegalPagesGenerator::save_company_data( [
			'company_name' => 'Acme Tecnologia',
			'legal_name'   => 'Acme Tecnologia LTDA',
			'cnpj'         => '12.345.678/0001-90',
			'email'        => 'privacidade@acme.test',
			'contact_url'  => 'https://acme.test/contato',
		] );

		$data = LegalPagesGenerator::get_company_data();

		$this->assert_same( 'Acme Tecnologia', $data['company_name'] );
		$this->assert_same( 'Acme Tecnologia LTDA', $data['legal_name'] );
		$this->assert_same( '12.345.678/0001-90', LegalPagesGenerator::get_placeholder( 'cnpj' ) );
		$this->assert_same( 'privacidade@acme.test', LegalPagesGenerator::get_placeholder( 'email' ) );
		$this->assert_same( 'https://acme.test/contato', LegalPagesGenerator::get_placeholder( 'contact_url' ) );
	}
}
