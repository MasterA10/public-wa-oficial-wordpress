<?php

namespace WAS\Compliance;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Catálogo e estado dos checklists disponíveis na plataforma.
 */
class ChecklistService {
	public function get_catalog() {
		return [
			'app-review' => [
				'slug'        => 'app-review',
				'title'       => 'App Review / Compliance',
				'description' => 'Verifique se a integração está pronta para a revisão da Meta.',
				'items'       => [
					'business_portfolio_created'       => 'Portfolio de Negócios Criado',
					'meta_app_created'                 => 'Meta App Criado',
					'embedded_signup_configured'       => 'Embedded Signup Configurado',
					'privacy_policy_url_added'         => 'URL de Privacidade Adicionada',
					'template_creation_video_recorded' => 'Vídeo: Criação de Template',
					'message_sending_video_recorded'   => 'Vídeo: Envio de Mensagem',
				],
			],
			'template-creation' => [
				'slug'        => 'template-creation',
				'title'       => 'Criação de Template',
				'description' => 'Confira os requisitos antes de enviar um template para aprovação.',
				'items'       => [
					'name_valid'             => 'Nome válido (minúsculas, números e underscore)',
					'body_filled'            => 'Mensagem principal preenchida',
					'variables_have_examples' => 'Todas as variáveis possuem exemplos',
					'header_valid'           => 'Cabeçalho válido',
					'buttons_valid'          => 'Botões válidos',
				],
			],
			'operation' => [
				'slug'        => 'operation',
				'title'       => 'Operação WhatsApp',
				'description' => 'Valide o fluxo básico de operação da conta WhatsApp.',
				'items'       => [
					'number_connected' => 'Número WhatsApp conectado',
					'template_approved' => 'Template aprovado pela Meta',
					'test_message_sent' => 'Mensagem de teste enviada',
					'inbound_received'  => 'Mensagem recebida no Inbox',
					'webhook_checked'   => 'Webhook verificado nos Logs',
				],
			],
		];
	}

	public function get( $slug ) {
		$catalog = $this->get_catalog();
		return isset( $catalog[ $slug ] ) ? $catalog[ $slug ] : null;
	}

	public function get_items( $slug ) {
		$checklist = $this->get( $slug );
		if ( ! $checklist ) {
			return null;
		}

		$statuses = [];
		if ( 'app-review' === $slug ) {
			global $wpdb;
			$table = \WAS\Core\TableNameResolver::get_table_name( 'app_review_checklist' );
			$rows  = $wpdb->get_results( "SELECT item_key, status FROM $table" );
			foreach ( $rows as $row ) {
				$statuses[ $row->item_key ] = $row->status;
			}
		} else {
			$statuses = (array) get_option( 'was_checklist_status_' . $slug, [] );
		}

		$items = [];
		foreach ( $checklist['items'] as $key => $label ) {
			$items[] = [
				'item_key' => $key,
				'label'    => $label,
				'status'   => ( isset( $statuses[ $key ] ) && 'done' === $statuses[ $key ] ) ? 'done' : 'pending',
			];
		}
		return $items;
	}

	public function update_item( $slug, $item_key, $status ) {
		$checklist = $this->get( $slug );
		if ( ! $checklist || ! isset( $checklist['items'][ $item_key ] ) ) {
			return false;
		}
		$status = 'done' === $status ? 'done' : 'pending';

		if ( 'app-review' === $slug ) {
			global $wpdb;
			$table = \WAS\Core\TableNameResolver::get_table_name( 'app_review_checklist' );
			$wpdb->replace( $table, [
				'meta_app_id' => 1,
				'item_key'   => $item_key,
				'label'      => $checklist['items'][ $item_key ],
				'status'     => $status,
				'updated_at' => current_time( 'mysql', true ),
				'created_at' => current_time( 'mysql', true ),
			] );
			return true;
		}

		$statuses            = (array) get_option( 'was_checklist_status_' . $slug, [] );
		$statuses[ $item_key ] = $status;
		update_option( 'was_checklist_status_' . $slug, $statuses, false );
		return true;
	}
}
