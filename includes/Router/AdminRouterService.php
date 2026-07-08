<?php

namespace WAS\Router;

use WAS\Core\TableNameResolver;
use WAS\Meta\MetaApiClient;
use WAS\Meta\TokenService;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AdminRouterService {

	public function dashboard() {
		global $wpdb;

		return [
			'tenants'           => (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . TableNameResolver::get_table_name( 'tenants' ) ),
			'meta_apps'         => (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . TableNameResolver::get_table_name( 'meta_apps' ) ),
			'wabas'             => (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . TableNameResolver::get_table_name( 'whatsapp_accounts' ) ),
			'phone_numbers'     => (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . TableNameResolver::get_table_name( 'whatsapp_phone_numbers' ) ),
			'routes'            => (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . TableNameResolver::getRoutesTable() ),
			'pending_deliveries'=> (int) $wpdb->get_var( "SELECT COUNT(*) FROM " . TableNameResolver::getOutboxDeliveriesTable() . " WHERE status = 'pending'" ),
			'dead_letters'      => (int) $wpdb->get_var( "SELECT COUNT(*) FROM " . TableNameResolver::getOutboxDeliveriesTable() . " WHERE status = 'dead_letter'" ),
		];
	}

	public function list_tenants() {
		global $wpdb;
		return $wpdb->get_results( 'SELECT * FROM ' . TableNameResolver::get_table_name( 'tenants' ) . ' ORDER BY id DESC LIMIT 500' );
	}

	public function create_tenant( array $params ) {
		global $wpdb;
		$table = TableNameResolver::get_table_name( 'tenants' );
		$name = sanitize_text_field( $params['name'] ?? '' );
		$slug = sanitize_title( $params['slug'] ?? $name );

		if ( ! $name || ! $slug ) {
			return new WP_Error( 'invalid_tenant_payload', 'name e slug sao obrigatorios.', [ 'status' => 400 ] );
		}

		$wpdb->insert(
			$table,
			[
				'name'       => $name,
				'slug'       => $slug,
				'status'     => sanitize_text_field( $params['status'] ?? 'active' ),
				'plan'       => sanitize_text_field( $params['plan'] ?? 'free' ),
				'created_at' => current_time( 'mysql', true ),
			]
		);

		if ( ! $wpdb->insert_id ) {
			return new WP_Error( 'tenant_create_failed', $wpdb->last_error ?: 'Falha ao criar tenant.', [ 'status' => 500 ] );
		}

		return $this->get_row( $table, (int) $wpdb->insert_id );
	}

	public function set_tenant_status( $tenant_id, array $params ) {
		global $wpdb;
		$status = sanitize_text_field( $params['status'] ?? '' );
		if ( ! $status ) {
			return new WP_Error( 'invalid_tenant_status', 'status e obrigatorio.', [ 'status' => 400 ] );
		}

		$table = TableNameResolver::get_table_name( 'tenants' );
		$row = $this->get_row( $table, (int) $tenant_id );
		if ( ! $row ) {
			return new WP_Error( 'tenant_not_found', 'Tenant nao encontrado.', [ 'status' => 404 ] );
		}
		$wpdb->update( $table, [ 'status' => $status ], [ 'id' => (int) $tenant_id ] );
		return $this->get_row( $table, (int) $tenant_id );
	}

	public function delete_resource( $resource, $id ) {
		switch ( $resource ) {
			case 'tenants':
				return $this->soft_disable_row( TableNameResolver::get_table_name( 'tenants' ), (int) $id );
			case 'meta-apps':
				return $this->soft_disable_row( TableNameResolver::get_table_name( 'meta_apps' ), (int) $id );
			case 'wabas':
				return $this->disable_waba_tree( (int) $id );
			case 'phone-numbers':
				return $this->disable_phone_number_tree( (int) $id );
		}

		return new WP_Error( 'unknown_admin_resource', 'Recurso admin desconhecido.', [ 'status' => 404 ] );
	}

	public function list_meta_apps() {
		global $wpdb;
		$table = TableNameResolver::get_table_name( 'meta_apps' );
		$rows = $wpdb->get_results( "SELECT * FROM $table ORDER BY id DESC LIMIT 500" );
		foreach ( $rows as $row ) {
			unset( $row->app_secret, $row->app_secret_encrypted );
		}
		return $rows;
	}

	public function create_meta_app( array $params ) {
		global $wpdb;
		$table = TableNameResolver::get_table_name( 'meta_apps' );

		if ( empty( $params['app_id'] ) || empty( $params['app_secret'] ) ) {
			return new WP_Error( 'invalid_meta_app_payload', 'app_id e app_secret sao obrigatorios.', [ 'status' => 400 ] );
		}

		$data = [
			'name'                       => sanitize_text_field( $params['name'] ?? 'Meta App' ),
			'app_id'                     => sanitize_text_field( $params['app_id'] ),
			'app_secret'                 => SecretVault::encrypt( $params['app_secret'] ),
			'app_secret_encrypted'       => SecretVault::encrypt( $params['app_secret'] ),
			'config_id'                  => sanitize_text_field( $params['config_id'] ?? '' ),
			'graph_version'              => sanitize_text_field( $params['graph_version'] ?? WAS_META_GRAPH_DEFAULT_VERSION ),
			'embedded_signup_config_id'  => sanitize_text_field( $params['embedded_signup_config_id'] ?? $params['config_id'] ?? '' ),
			'verify_token'               => sanitize_text_field( $params['verify_token'] ?? wp_generate_password( 32, false ) ),
			'webhook_verify_token'       => sanitize_text_field( $params['verify_token'] ?? '' ),
			'is_default'                 => empty( $params['is_default'] ) ? 0 : 1,
			'status'                     => sanitize_text_field( $params['status'] ?? 'active' ),
			'created_at'                 => current_time( 'mysql', true ),
			'updated_at'                 => current_time( 'mysql', true ),
		];

		$wpdb->insert( $table, $data );
		if ( ! $wpdb->insert_id ) {
			return new WP_Error( 'meta_app_create_failed', $wpdb->last_error ?: 'Falha ao criar Meta App.', [ 'status' => 500 ] );
		}

		$row = $this->get_row( $table, (int) $wpdb->insert_id );
		unset( $row->app_secret, $row->app_secret_encrypted );
		return $row;
	}

	public function rotate_meta_app_secret( $meta_app_id, array $params ) {
		global $wpdb;
		$table = TableNameResolver::get_table_name( 'meta_apps' );
		$row = $this->get_row( $table, (int) $meta_app_id );
		if ( ! $row ) {
			return new WP_Error( 'meta_app_not_found', 'Meta App nao encontrado.', [ 'status' => 404 ] );
		}

		$data = [ 'updated_at' => current_time( 'mysql', true ) ];
		if ( array_key_exists( 'app_secret', $params ) ) {
			$data['app_secret'] = SecretVault::encrypt( $params['app_secret'] );
			$data['app_secret_encrypted'] = SecretVault::encrypt( $params['app_secret'] );
		}
		if ( array_key_exists( 'verify_token', $params ) ) {
			$data['verify_token'] = sanitize_text_field( $params['verify_token'] );
			$data['webhook_verify_token'] = sanitize_text_field( $params['verify_token'] );
		}
		if ( count( $data ) < 2 ) {
			return new WP_Error( 'invalid_meta_app_rotation', 'Informe app_secret e/ou verify_token.', [ 'status' => 400 ] );
		}

		$wpdb->update( $table, $data, [ 'id' => (int) $meta_app_id ] );
		$row = $this->get_row( $table, (int) $meta_app_id );
		unset( $row->app_secret, $row->app_secret_encrypted );
		return $row;
	}

	public function list_wabas( array $params = [] ) {
		global $wpdb;
		$table = TableNameResolver::get_table_name( 'whatsapp_accounts' );
		$where = 'WHERE 1=1';
		$args = [];
		if ( ! empty( $params['tenant_id'] ) ) {
			$where .= ' AND tenant_id = %d';
			$args[] = (int) $params['tenant_id'];
		}
		$sql = "SELECT * FROM $table $where ORDER BY id DESC LIMIT 500";
		return $args ? $wpdb->get_results( $wpdb->prepare( $sql, ...$args ) ) : $wpdb->get_results( $sql );
	}

	public function create_waba( array $params ) {
		global $wpdb;
		$table = TableNameResolver::get_table_name( 'whatsapp_accounts' );
		$tenant_id = (int) ( $params['tenant_id'] ?? 0 );
		$meta_app_id = (int) ( $params['meta_app_id'] ?? 0 );
		$meta_waba_id = sanitize_text_field( $params['waba_id'] ?? $params['meta_waba_id'] ?? '' );

		if ( ! $tenant_id || ! $meta_app_id || ! $meta_waba_id ) {
			return new WP_Error( 'invalid_waba_payload', 'tenant_id, meta_app_id e waba_id sao obrigatorios.', [ 'status' => 400 ] );
		}

		$existing = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE waba_id = %s LIMIT 1", $meta_waba_id ) );
		$data = [
			'tenant_id'        => $tenant_id,
			'meta_app_id'      => $meta_app_id,
			'meta_business_id' => sanitize_text_field( $params['business_id'] ?? $params['meta_business_id'] ?? '' ),
			'waba_id'          => $meta_waba_id,
			'name'             => sanitize_text_field( $params['name'] ?? $meta_waba_id ),
			'status'           => sanitize_text_field( $params['status'] ?? 'active' ),
			'connected_at'     => current_time( 'mysql', true ),
			'updated_at'       => current_time( 'mysql', true ),
		];

		if ( $existing ) {
			$wpdb->update( $table, $data, [ 'id' => (int) $existing->id ] );
			$waba_id = (int) $existing->id;
		} else {
			$data['created_at'] = current_time( 'mysql', true );
			$wpdb->insert( $table, $data );
			$waba_id = (int) $wpdb->insert_id;
		}

		if ( ! empty( $params['access_token'] ) ) {
			( new TokenService() )->store_encrypted_token( $tenant_id, $waba_id, (string) $params['access_token'] );
		}

		return $this->get_row( $table, $waba_id );
	}

	public function rotate_waba_token( $waba_id, array $params ) {
		$table = TableNameResolver::get_table_name( 'whatsapp_accounts' );
		$waba = $this->get_row( $table, (int) $waba_id );
		if ( ! $waba ) {
			return new WP_Error( 'waba_not_found', 'WABA nao encontrada.', [ 'status' => 404 ] );
		}
		if ( empty( $params['access_token'] ) ) {
			return new WP_Error( 'invalid_waba_token_rotation', 'access_token e obrigatorio.', [ 'status' => 400 ] );
		}

		$token_id = ( new TokenService() )->store_encrypted_token( (int) $waba->tenant_id, (int) $waba->id, (string) $params['access_token'] );
		return [
			'id'            => (int) $waba->id,
			'waba_id'       => $waba->waba_id,
			'tenant_id'     => (int) $waba->tenant_id,
			'token_rotated' => (bool) $token_id,
			'token_id'      => $token_id ? (int) $token_id : null,
		];
	}

	public function list_phone_numbers( array $params = [] ) {
		global $wpdb;
		$table = TableNameResolver::get_table_name( 'whatsapp_phone_numbers' );
		$where = 'WHERE 1=1';
		$args = [];
		if ( ! empty( $params['tenant_id'] ) ) {
			$where .= ' AND tenant_id = %d';
			$args[] = (int) $params['tenant_id'];
		}
		if ( ! empty( $params['waba_id'] ) ) {
			$where .= ' AND whatsapp_account_id = %d';
			$args[] = (int) $params['waba_id'];
		}
		$sql = "SELECT * FROM $table $where ORDER BY id DESC LIMIT 500";
		return $args ? $wpdb->get_results( $wpdb->prepare( $sql, ...$args ) ) : $wpdb->get_results( $sql );
	}

	public function create_phone_number( array $params ) {
		global $wpdb;
		$table = TableNameResolver::get_table_name( 'whatsapp_phone_numbers' );
		$tenant_id = (int) ( $params['tenant_id'] ?? 0 );
		$waba_id = (int) ( $params['waba_id'] ?? $params['whatsapp_account_id'] ?? 0 );
		$meta_phone_id = sanitize_text_field( $params['phone_number_id'] ?? $params['meta_phone_number_id'] ?? '' );

		if ( ! $tenant_id || ! $waba_id || ! $meta_phone_id ) {
			return new WP_Error( 'invalid_phone_payload', 'tenant_id, waba_id e phone_number_id sao obrigatorios.', [ 'status' => 400 ] );
		}

		$existing = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE phone_number_id = %s LIMIT 1", $meta_phone_id ) );
		$data = [
			'tenant_id'            => $tenant_id,
			'whatsapp_account_id'  => $waba_id,
			'phone_number_id'      => $meta_phone_id,
			'display_phone_number' => sanitize_text_field( $params['display_phone_number'] ?? $meta_phone_id ),
			'verified_name'        => sanitize_text_field( $params['verified_name'] ?? '' ),
			'quality_rating'       => sanitize_text_field( $params['quality_rating'] ?? 'UNKNOWN' ),
			'messaging_limit_tier' => sanitize_text_field( $params['messaging_limit_tier'] ?? '' ),
			'meta_status'          => sanitize_text_field( $params['meta_status'] ?? '' ),
			'status'               => sanitize_text_field( $params['status'] ?? 'active' ),
			'is_default'           => empty( $params['is_default'] ) ? 0 : 1,
			'updated_at'           => current_time( 'mysql', true ),
		];

		if ( $existing ) {
			$wpdb->update( $table, $data, [ 'id' => (int) $existing->id ] );
			return $this->get_row( $table, (int) $existing->id );
		}

		$data['created_at'] = current_time( 'mysql', true );
		$wpdb->insert( $table, $data );
		return $this->get_row( $table, (int) $wpdb->insert_id );
	}

	public function pause_phone_number( $phone_number_id ) {
		return $this->set_phone_number_status( $phone_number_id, 'paused' );
	}

	public function resume_phone_number( $phone_number_id ) {
		return $this->set_phone_number_status( $phone_number_id, 'active' );
	}

	public function sync_phone_number_status( $phone_number_id ) {
		return ( new WhatsAppService() )->get_phone_status( (int) $phone_number_id, true );
	}

	public function create_route( array $params ) {
		if ( empty( $params['tenant_id'] ) || empty( $params['phone_number_id'] ) || empty( $params['target_url'] ) ) {
			return new WP_Error( 'invalid_route_payload', 'tenant_id, phone_number_id e target_url sao obrigatorios.', [ 'status' => 400 ] );
		}

		$route = ( new RouteRepository() )->create_strict(
			[
				'tenant_id'       => (int) $params['tenant_id'],
				'phone_number_id' => (int) $params['phone_number_id'],
				'name'            => $params['name'] ?? 'Webhook route',
				'target_url'      => $params['target_url'],
				'secret'          => $params['secret'] ?? RouterSettings::get_route_secret(),
				'event_filters'   => $params['event_filters'] ?? $params['event_filters_json'] ?? [],
				'is_active'       => array_key_exists( 'is_active', $params ) ? ! empty( $params['is_active'] ) : true,
				'status'          => $params['status'] ?? 'active',
				'timeout_ms'      => $params['timeout_ms'] ?? 8000,
				'max_retries'     => $params['max_retries'] ?? 3,
				'priority'        => $params['priority'] ?? 100,
			]
		);

		return $route;
	}

	public function update_route( $route_id, array $params ) {
		return ( new RouteRepository() )->update( (int) $route_id, $params );
	}

	public function delete_route( $route_id ) {
		return ( new RouteRepository() )->archive( (int) $route_id );
	}

	public function list_deliveries( array $params = [] ) {
		global $wpdb;
		$table = TableNameResolver::getOutboxDeliveriesTable();
		$where = 'WHERE 1=1';
		$args = [];
		if ( ! empty( $params['status'] ) ) {
			$where .= ' AND status = %s';
			$args[] = sanitize_text_field( $params['status'] );
		}
		$sql = "SELECT * FROM $table $where ORDER BY id DESC LIMIT 500";
		return $args ? $wpdb->get_results( $wpdb->prepare( $sql, ...$args ) ) : $wpdb->get_results( $sql );
	}

	public function list_events( array $params = [] ) {
		global $wpdb;
		$table = TableNameResolver::getWebhookEventsTable();
		$where = 'WHERE 1=1';
		$args = [];
		foreach ( [ 'tenant_id', 'whatsapp_account_id', 'whatsapp_phone_number_row_id' ] as $field ) {
			if ( ! empty( $params[ $field ] ) ) {
				$where .= " AND $field = %d";
				$args[] = (int) $params[ $field ];
			}
		}
		foreach ( [ 'event_type', 'message_type', 'routing_status', 'processing_status' ] as $field ) {
			if ( ! empty( $params[ $field ] ) ) {
				$where .= " AND $field = %s";
				$args[] = sanitize_text_field( $params[ $field ] );
			}
		}
		$sql = "SELECT * FROM $table $where ORDER BY id DESC LIMIT 500";
		return $args ? $wpdb->get_results( $wpdb->prepare( $sql, ...$args ) ) : $wpdb->get_results( $sql );
	}

	public function replay_event( $event_id ) {
		global $wpdb;
		$events_table = TableNameResolver::getWebhookEventsTable();
		$event = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $events_table WHERE id = %d LIMIT 1", (int) $event_id ) );
		if ( ! $event ) {
			return new WP_Error( 'event_not_found', 'Evento nao encontrado.', [ 'status' => 404 ] );
		}

		if ( empty( $event->whatsapp_phone_number_row_id ) ) {
			return new WP_Error( 'event_without_phone_route', 'Evento sem numero interno para roteamento.', [ 'status' => 409 ] );
		}

		$routes = ( new RouteRepository() )->active_for_phone( (int) $event->whatsapp_phone_number_row_id, (int) $event->tenant_id );
		if ( ! $routes ) {
			return new WP_Error( 'event_without_active_routes', 'Nenhuma rota ativa para reenfileirar o evento.', [ 'status' => 409 ] );
		}

		$deliveries = ( new OutboxService() )->enqueue_for_event( (int) $event->id, $routes );
		( new OutboxService() )->process_due( count( $deliveries ) ?: 10 );

		return [
			'event_id'   => (int) $event->id,
			'deliveries' => $deliveries,
		];
	}

	public function retry_delivery( $delivery_id ) {
		global $wpdb;
		$table = TableNameResolver::getOutboxDeliveriesTable();
		$wpdb->update(
			$table,
			[
				'status'          => 'pending',
				'next_attempt_at' => current_time( 'mysql', true ),
				'last_error'      => null,
			],
			[ 'id' => (int) $delivery_id ]
		);

		( new OutboxService() )->process_delivery( (int) $delivery_id );
		return $this->get_row( $table, (int) $delivery_id );
	}

	public function cancel_delivery( $delivery_id ) {
		global $wpdb;
		$table = TableNameResolver::getOutboxDeliveriesTable();
		$wpdb->update(
			$table,
			[
				'status'          => 'cancelled',
				'next_attempt_at' => null,
				'last_error'      => 'cancelled_by_api',
			],
			[ 'id' => (int) $delivery_id ]
		);

		return $this->get_row( $table, (int) $delivery_id );
	}

	public function list_audit_logs( array $params = [] ) {
		global $wpdb;
		$table = TableNameResolver::getAuditLogsTable();
		$where = 'WHERE 1=1';
		$args = [];
		if ( ! empty( $params['tenant_id'] ) ) {
			$where .= ' AND tenant_id = %d';
			$args[] = (int) $params['tenant_id'];
		}
		if ( ! empty( $params['action'] ) ) {
			$where .= ' AND action = %s';
			$args[] = sanitize_text_field( $params['action'] );
		}
		$sql = "SELECT * FROM $table $where ORDER BY id DESC LIMIT 500";
		return $args ? $wpdb->get_results( $wpdb->prepare( $sql, ...$args ) ) : $wpdb->get_results( $sql );
	}

	public function list_outbound_messages( array $params = [] ) {
		global $wpdb;
		$table = TableNameResolver::getOutboundMessagesTable();
		$where = 'WHERE 1=1';
		$args = [];
		foreach ( [ 'tenant_id', 'phone_number_id' ] as $field ) {
			if ( ! empty( $params[ $field ] ) ) {
				$where .= " AND $field = %d";
				$args[] = (int) $params[ $field ];
			}
		}
		if ( ! empty( $params['status'] ) ) {
			$where .= ' AND status = %s';
			$args[] = sanitize_text_field( $params['status'] );
		}
		$sql = "SELECT * FROM $table $where ORDER BY id DESC LIMIT 500";
		return $args ? $wpdb->get_results( $wpdb->prepare( $sql, ...$args ) ) : $wpdb->get_results( $sql );
	}

	public function list_templates( array $params = [] ) {
		global $wpdb;
		$table = TableNameResolver::getTemplatesTable();
		$where = 'WHERE deleted_at IS NULL';
		$args = [];
		foreach ( [ 'tenant_id', 'whatsapp_account_id', 'router_phone_number_id' ] as $field ) {
			if ( ! empty( $params[ $field ] ) ) {
				$where .= " AND $field = %d";
				$args[] = (int) $params[ $field ];
			}
		}
		foreach ( [ 'waba_id', 'name', 'language', 'category', 'status' ] as $field ) {
			if ( ! empty( $params[ $field ] ) ) {
				$where .= " AND $field = %s";
				$args[] = sanitize_text_field( $params[ $field ] );
			}
		}
		$sql = "SELECT * FROM $table $where ORDER BY updated_at DESC LIMIT 500";
		return $args ? $wpdb->get_results( $wpdb->prepare( $sql, ...$args ) ) : $wpdb->get_results( $sql );
	}

	public function get_template( $template_id ) {
		$template = $this->get_row( TableNameResolver::getTemplatesTable(), (int) $template_id );
		if ( ! $template || ! empty( $template->deleted_at ) ) {
			return new WP_Error( 'template_not_found', 'Template nao encontrado.', [ 'status' => 404 ] );
		}
		return $this->template_response( $template );
	}

	public function list_phone_number_templates( $phone_number_id ) {
		global $wpdb;
		$phones_table = TableNameResolver::get_table_name( 'whatsapp_phone_numbers' );
		$templates_table = TableNameResolver::getTemplatesTable();
		$phone = $this->get_row( $phones_table, (int) $phone_number_id );
		if ( ! $phone ) {
			return new WP_Error( 'phone_number_not_found', 'Numero nao encontrado.', [ 'status' => 404 ] );
		}

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM $templates_table WHERE whatsapp_account_id = %d AND deleted_at IS NULL ORDER BY category ASC, name ASC, language ASC LIMIT 500",
				(int) $phone->whatsapp_account_id
			)
		);

		return array_values(
			array_map(
				[ $this, 'template_response' ],
				array_filter(
					$rows,
					function ( $template ) use ( $phone_number_id ) {
						return empty( $template->router_phone_number_id ) || (int) $template->router_phone_number_id === (int) $phone_number_id;
					}
				)
			)
		);
	}

	public function sync_phone_number_templates( $phone_number_id, array $params = [] ) {
		$phone = $this->get_row( TableNameResolver::get_table_name( 'whatsapp_phone_numbers' ), (int) $phone_number_id );
		if ( ! $phone ) {
			return new WP_Error( 'phone_number_not_found', 'Numero nao encontrado.', [ 'status' => 404 ] );
		}

		$result = $this->sync_waba_templates( (int) $phone->whatsapp_account_id, $params );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$result['phone_number_id'] = (int) $phone->id;
		$result['message'] = sprintf(
			'Numero #%d: %d templates importados/atualizados da WABA #%d.',
			(int) $phone->id,
			(int) $result['imported_count'],
			(int) $phone->whatsapp_account_id
		);

		return $result;
	}

	public function sync_waba_templates( $waba_id, array $params = [] ) {
		global $wpdb;

		$waba = $this->get_row( TableNameResolver::get_table_name( 'whatsapp_accounts' ), (int) $waba_id );
		if ( ! $waba ) {
			return new WP_Error( 'waba_not_found', 'WABA nao encontrada.', [ 'status' => 404 ] );
		}

		$token = ( new TokenService() )->get_active_token( (int) $waba->tenant_id, (int) $waba->id );
		if ( ! $token ) {
			return new WP_Error( 'missing_waba_access_token', 'Token Meta ativo nao encontrado para a WABA.', [ 'status' => 409 ] );
		}

		$sync_params = $params;
		unset( $sync_params['phone_number_id'] );
		$sync_params['waba_id'] = (int) $waba->id;
		$sync_params['sync'] = true;

		$result = ( new TemplateRouterService() )->list_templates( $sync_params );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$wpdb->update(
			TableNameResolver::get_table_name( 'whatsapp_accounts' ),
			[
				'last_template_sync_at' => current_time( 'mysql', true ),
				'last_sync_error'       => null,
				'updated_at'            => current_time( 'mysql', true ),
			],
			[ 'id' => (int) $waba->id ]
		);

		return [
			'message'        => sprintf( 'WABA #%d: %d templates importados/atualizados.', (int) $waba->id, (int) $result['sync_count'] ),
			'waba_id'        => (int) $waba->id,
			'meta_waba_id'   => (string) $waba->waba_id,
			'imported_count' => (int) $result['sync_count'],
			'meta_summary'   => $result['meta_summary'] ?? null,
			'templates'      => $result['templates'] ?? [],
		];
	}

	public function sync_all_templates( array $params = [] ) {
		global $wpdb;

		$table = TableNameResolver::get_table_name( 'whatsapp_accounts' );
		$wabas = $wpdb->get_results( "SELECT * FROM $table WHERE status IN ('active','connected') ORDER BY id ASC LIMIT 500" );
		$result = [
			'wabas_checked'  => count( $wabas ),
			'imported_count' => 0,
			'errors'         => [],
			'results'        => [],
		];

		foreach ( $wabas as $waba ) {
			$sync = $this->sync_waba_templates( (int) $waba->id, $params );
			if ( is_wp_error( $sync ) ) {
				$result['errors'][] = [
					'waba_id'      => (int) $waba->id,
					'meta_waba_id' => (string) $waba->waba_id,
					'error'        => $sync->get_error_code(),
					'message'      => $sync->get_error_message(),
				];
				continue;
			}

			$result['imported_count'] += (int) $sync['imported_count'];
			$result['results'][] = $sync;
		}

		$result['message'] = sprintf(
			'Sincronizacao global de templates concluida. WABAs verificadas: %d. Templates importados/atualizados: %d. Erros: %d.',
			(int) $result['wabas_checked'],
			(int) $result['imported_count'],
			count( $result['errors'] )
		);

		return $result;
	}

	public function update_template( $template_id, array $params ) {
		global $wpdb;
		$table = TableNameResolver::getTemplatesTable();
		$template = $this->get_row( $table, (int) $template_id );
		if ( ! $template ) {
			return new WP_Error( 'template_not_found', 'Template nao encontrado.', [ 'status' => 404 ] );
		}

		$allowed = [
			'category',
			'status',
			'language',
			'rejection_reason',
			'last_meta_error',
			'requested_callback_url',
			'last_status_error',
		];
		$data = [];
		foreach ( $allowed as $field ) {
			if ( array_key_exists( $field, $params ) ) {
				$data[ $field ] = is_string( $params[ $field ] ) ? sanitize_text_field( $params[ $field ] ) : $params[ $field ];
			}
		}
		if ( array_key_exists( 'phone_number_id', $params ) ) {
			$data['router_phone_number_id'] = null === $params['phone_number_id'] || '' === $params['phone_number_id'] ? null : (int) $params['phone_number_id'];
		}
		if ( array_key_exists( 'components', $params ) ) {
			$data['components_json'] = wp_json_encode( $params['components'] );
			$data['friendly_payload'] = wp_json_encode( array_merge( json_decode( $template->friendly_payload ?? '{}', true ) ?: [], [ 'components' => $params['components'] ] ) );
			$data['meta_payload'] = wp_json_encode( array_merge( json_decode( $template->meta_payload ?? '{}', true ) ?: [], [ 'components' => $params['components'] ] ) );
		}
		if ( empty( $data ) ) {
			return new WP_Error( 'invalid_template_patch', 'Nenhum campo permitido informado.', [ 'status' => 400 ] );
		}
		$data['updated_at'] = current_time( 'mysql', true );
		$wpdb->update( $table, $data, [ 'id' => (int) $template_id ] );
		return $this->get_row( $table, (int) $template_id );
	}

	public function delete_template( $template_id, array $params = [] ) {
		global $wpdb;
		$table = TableNameResolver::getTemplatesTable();
		$template = $this->get_row( $table, (int) $template_id );
		if ( ! $template ) {
			return new WP_Error( 'template_not_found', 'Template nao encontrado.', [ 'status' => 404 ] );
		}

		$meta_deleted = null;
		if ( ! empty( $params['delete_from_meta'] ) && filter_var( $params['delete_from_meta'], FILTER_VALIDATE_BOOLEAN ) ) {
			$meta_deleted = $this->delete_template_from_meta( $template );
			if ( is_wp_error( $meta_deleted ) ) {
				return $meta_deleted;
			}
		}

		$wpdb->update(
			$table,
			[
				'status'     => 'DELETED',
				'deleted_at' => current_time( 'mysql', true ),
				'updated_at' => current_time( 'mysql', true ),
			],
			[ 'id' => (int) $template_id ]
		);
		$response = $this->template_response( $this->get_row( $table, (int) $template_id ) );
		if ( null !== $meta_deleted ) {
			$response['meta_deleted'] = true;
			$response['meta_response'] = $meta_deleted;
		}
		return $response;
	}

	public function activate_route( $route_id ) {
		return ( new RouteRepository() )->update_status( (int) $route_id, true, 'active' );
	}

	public function deactivate_route( $route_id ) {
		return ( new RouteRepository() )->update_status( (int) $route_id, false, 'disabled' );
	}

	public function duplicate_route( $route_id ) {
		$route = ( new RouteRepository() )->duplicate( (int) $route_id );
		if ( ! $route ) {
			return new WP_Error( 'route_not_found', 'Rota nao encontrada.', [ 'status' => 404 ] );
		}

		return $route;
	}

	public function test_route( $route_id ) {
		$route = ( new RouteRepository() )->find( (int) $route_id );
		if ( ! $route ) {
			return new WP_Error( 'route_not_found', 'Rota nao encontrada.', [ 'status' => 404 ] );
		}

		$secret = SecretVault::decrypt( $route->secret_encrypted );
		$headers = [ 'Content-Type' => 'application/json' ];
		if ( $secret ) {
			$headers['x-waba-router-secret'] = $secret;
		}

		$response = wp_remote_post(
			$route->target_url,
			[
				'headers' => $headers,
				'timeout' => max( 1, (int) ceil( (int) $route->timeout_ms / 1000 ) ),
				'body'    => wp_json_encode(
					[
						'event_type'      => 'route_test',
						'route_id'        => (int) $route->id,
						'phone_number_id' => (int) $route->phone_number_id,
						'payload'         => [ 'message' => 'WAS Router route test' ],
					]
				),
			]
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'route_test_failed', $response->get_error_message(), [ 'status' => 502 ] );
		}

		return [
			'route_id'        => (int) $route->id,
			'response_status' => (int) wp_remote_retrieve_response_code( $response ),
			'response_body'   => wp_remote_retrieve_body( $response ),
		];
	}

	private function get_row( $table, $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d LIMIT 1", (int) $id ) );
	}

	private function soft_disable_row( $table, $id ) {
		global $wpdb;
		$row = $this->get_row( $table, (int) $id );
		if ( ! $row ) {
			return new WP_Error( 'resource_not_found', 'Recurso nao encontrado.', [ 'status' => 404 ] );
		}

		$data = [ 'status' => 'disabled' ];
		if ( isset( $row->updated_at ) ) {
			$data['updated_at'] = current_time( 'mysql', true );
		}
		$wpdb->update( $table, $data, [ 'id' => (int) $id ] );
		return $this->get_row( $table, (int) $id );
	}

	private function disable_phone_number_tree( $phone_number_id ) {
		global $wpdb;
		$phone = $this->soft_disable_row( TableNameResolver::get_table_name( 'whatsapp_phone_numbers' ), (int) $phone_number_id );
		if ( is_wp_error( $phone ) ) {
			return $phone;
		}

		$routes = ( new RouteRepository() )->active_for_phone( (int) $phone_number_id );
		foreach ( $routes as $route ) {
			( new RouteRepository() )->archive( (int) $route->id );
		}

		$wpdb->update(
			TableNameResolver::get_table_name( 'whatsapp_phone_numbers' ),
			[ 'default_route_id' => null, 'updated_at' => current_time( 'mysql', true ) ],
			[ 'id' => (int) $phone_number_id ]
		);

		return $this->get_row( TableNameResolver::get_table_name( 'whatsapp_phone_numbers' ), (int) $phone_number_id );
	}

	private function disable_waba_tree( $waba_id ) {
		global $wpdb;
		$waba = $this->soft_disable_row( TableNameResolver::get_table_name( 'whatsapp_accounts' ), (int) $waba_id );
		if ( is_wp_error( $waba ) ) {
			return $waba;
		}

		$phones = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM " . TableNameResolver::get_table_name( 'whatsapp_phone_numbers' ) . " WHERE whatsapp_account_id = %d",
				(int) $waba_id
			)
		);
		foreach ( $phones as $phone ) {
			$this->disable_phone_number_tree( (int) $phone->id );
		}

		return $this->get_row( TableNameResolver::get_table_name( 'whatsapp_accounts' ), (int) $waba_id );
	}

	private function template_response( $template ) {
		return ( new TemplateResponseFormatter() )->format( $template );
	}

	private function delete_template_from_meta( $template ) {
		$waba = $this->get_row( TableNameResolver::get_table_name( 'whatsapp_accounts' ), (int) $template->whatsapp_account_id );
		if ( ! $waba ) {
			return new WP_Error( 'waba_not_found', 'WABA nao encontrada para o template.', [ 'status' => 404 ] );
		}

		$token = ( new TokenService() )->get_active_token( (int) $waba->tenant_id, (int) $waba->id );
		if ( ! $token ) {
			return new WP_Error( 'missing_waba_access_token', 'Token Meta ativo nao encontrado para a WABA.', [ 'status' => 409 ] );
		}

		$query = [ 'name' => (string) $template->name ];
		if ( ! empty( $template->meta_template_id ) ) {
			$query['hsm_id'] = (string) $template->meta_template_id;
		}

		$result = ( new MetaApiClient() )->delete(
			'templates.delete',
			[ 'waba_id' => $waba->waba_id ],
			$query,
			$token
		);

		if ( empty( $result['success'] ) ) {
			return new WP_Error(
				'meta_template_delete_failed',
				$result['error'] ?? 'Meta rejeitou a exclusao do template.',
				[
					'status'        => 400,
					'meta_response' => $result,
				]
			);
		}

		return $result;
	}

	private function set_phone_number_status( $phone_number_id, $status ) {
		global $wpdb;
		$table = TableNameResolver::get_table_name( 'whatsapp_phone_numbers' );
		$wpdb->update(
			$table,
			[
				'status'     => sanitize_text_field( $status ),
				'updated_at' => current_time( 'mysql', true ),
			],
			[ 'id' => (int) $phone_number_id ]
		);

		return $this->get_row( $table, (int) $phone_number_id );
	}
}
