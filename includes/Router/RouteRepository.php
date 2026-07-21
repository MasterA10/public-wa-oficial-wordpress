<?php

namespace WAS\Router;

use WAS\Core\TableNameResolver;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RouteRepository {

	public function create_or_update( array $data ) {
		global $wpdb;

		$table = TableNameResolver::getRoutesTable();
		$target_url = esc_url_raw( $data['target_url'] ?? '' );
		$hash = hash( 'sha256', $target_url );
		$tenant_id = (int) ( $data['tenant_id'] ?? 0 );
		$phone_number_id = (int) ( $data['phone_number_id'] ?? 0 );

		$existing = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $table WHERE tenant_id = %d AND phone_number_id = %d AND target_url_hash = %s AND is_active = 1 LIMIT 1",
				$tenant_id,
				$phone_number_id,
				$hash
			)
		);

		$prepared = [
			'tenant_id'          => $tenant_id,
			'phone_number_id'    => $phone_number_id,
			'name'               => sanitize_text_field( $data['name'] ?? 'Webhook route' ),
			'target_url'         => $target_url,
			'target_url_hash'    => $hash,
			'secret_encrypted'   => SecretVault::encrypt( $data['secret'] ?? null ),
			'event_filters_json' => empty( $data['event_filters'] ) ? null : wp_json_encode( $data['event_filters'] ),
			'is_active'          => empty( $data['is_active'] ) ? 0 : 1,
			'status'             => sanitize_text_field( $data['status'] ?? 'active' ),
			'timeout_ms'         => (int) ( $data['timeout_ms'] ?? 8000 ),
			'max_retries'        => (int) ( $data['max_retries'] ?? 3 ),
			'priority'           => (int) ( $data['priority'] ?? 100 ),
			'updated_at'         => current_time( 'mysql', true ),
		];

		if ( $existing ) {
			$wpdb->update( $table, $prepared, [ 'id' => (int) $existing->id ] );
			return (int) $existing->id;
		}

		$prepared['created_at'] = current_time( 'mysql', true );
		$wpdb->insert( $table, $prepared );
		return (int) $wpdb->insert_id;
	}

	public function create_strict( array $data ) {
		global $wpdb;

		$target_url = esc_url_raw( $data['target_url'] ?? '' );
		$tenant_id = (int) ( $data['tenant_id'] ?? 0 );
		$phone_number_id = (int) ( $data['phone_number_id'] ?? 0 );
		$is_active = array_key_exists( 'is_active', $data ) ? ! empty( $data['is_active'] ) : true;

		if ( $is_active && $this->find_active_duplicate( $tenant_id, $phone_number_id, $target_url ) ) {
			return $this->active_duplicate_error();
		}

		$table = TableNameResolver::getRoutesTable();
		$prepared = $this->prepare_route_data( $data, $target_url, $tenant_id, $phone_number_id );
		$prepared['created_at'] = current_time( 'mysql', true );
		$wpdb->insert( $table, $prepared );

		return $wpdb->insert_id ? $this->find( (int) $wpdb->insert_id ) : new WP_Error( 'route_create_failed', $wpdb->last_error ?: 'Falha ao criar rota.', [ 'status' => 500 ] );
	}

	public function find( $id ) {
		global $wpdb;
		$table = TableNameResolver::getRoutesTable();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", (int) $id ) );
	}

	public function update_status( $id, $is_active, $status = null ) {
		global $wpdb;
		$table = TableNameResolver::getRoutesTable();
		$route = $this->find( $id );
		if ( ! $route ) {
			return new WP_Error( 'route_not_found', 'Rota nao encontrada.', [ 'status' => 404 ] );
		}

		if ( $is_active && $this->find_active_duplicate( (int) $route->tenant_id, (int) $route->phone_number_id, $route->target_url, (int) $id ) ) {
			return $this->active_duplicate_error();
		}

		$wpdb->update(
			$table,
			[
				'is_active'  => $is_active ? 1 : 0,
				'status'     => $status ?: ( $is_active ? 'active' : 'disabled' ),
				'updated_at' => current_time( 'mysql', true ),
			],
			[ 'id' => (int) $id ]
		);

		return $this->find( $id );
	}

	public function update( $id, array $data ) {
		global $wpdb;

		$route = $this->find( $id );
		if ( ! $route ) {
			return new WP_Error( 'route_not_found', 'Rota nao encontrada.', [ 'status' => 404 ] );
		}

		$target_url = array_key_exists( 'target_url', $data ) ? esc_url_raw( $data['target_url'] ) : $route->target_url;
		$tenant_id = (int) ( $data['tenant_id'] ?? $route->tenant_id );
		$phone_number_id = (int) ( $data['phone_number_id'] ?? $route->phone_number_id );
		$is_active = array_key_exists( 'is_active', $data ) ? ! empty( $data['is_active'] ) : (bool) $route->is_active;

		if ( $is_active && $this->find_active_duplicate( $tenant_id, $phone_number_id, $target_url, (int) $id ) ) {
			return $this->active_duplicate_error();
		}

		$update = [
			'tenant_id'          => $tenant_id,
			'phone_number_id'    => $phone_number_id,
			'target_url'         => $target_url,
			'target_url_hash'    => hash( 'sha256', $target_url ),
			'is_active'          => $is_active ? 1 : 0,
			'status'             => sanitize_text_field( $data['status'] ?? $route->status ),
			'updated_at'         => current_time( 'mysql', true ),
		];

		foreach ( [ 'name', 'timeout_ms', 'max_retries', 'priority' ] as $field ) {
			if ( array_key_exists( $field, $data ) ) {
				$update[ $field ] = in_array( $field, [ 'timeout_ms', 'max_retries', 'priority' ], true ) ? (int) $data[ $field ] : sanitize_text_field( $data[ $field ] );
			}
		}

		if ( array_key_exists( 'secret', $data ) ) {
			$update['secret_encrypted'] = SecretVault::encrypt( $data['secret'] );
		}

		if ( array_key_exists( 'event_filters', $data ) || array_key_exists( 'event_filters_json', $data ) ) {
			$filters = $data['event_filters'] ?? $data['event_filters_json'];
			$update['event_filters_json'] = is_array( $filters ) ? wp_json_encode( $filters ) : (string) $filters;
		}

		$wpdb->update( TableNameResolver::getRoutesTable(), $update, [ 'id' => (int) $id ] );
		return $this->find( $id );
	}

	public function archive( $id ) {
		return $this->update_status( (int) $id, false, 'disabled' );
	}

	public function delete( $id ) {
		global $wpdb;

		$id = (int) $id;
		$route = $this->find( $id );
		if ( ! $route ) {
			return new WP_Error( 'route_not_found', 'Rota nao encontrada.', [ 'status' => 404 ] );
		}

		// A tabela de entregas não possui FK para permitir a remoção automática.
		// Remova primeiro as entregas para não deixar referências órfãs à rota.
		$wpdb->delete( TableNameResolver::getOutboxDeliveriesTable(), [ 'route_id' => $id ] );
		$deleted = $wpdb->delete( TableNameResolver::getRoutesTable(), [ 'id' => $id ] );

		if ( ! $deleted ) {
			return new WP_Error( 'route_delete_failed', 'Nao foi possivel apagar a rota.', [ 'status' => 500 ] );
		}

		return [
			'id'      => $id,
			'deleted' => true,
		];
	}

	public function duplicate( $id ) {
		global $wpdb;

		$route = $this->find( $id );
		if ( ! $route ) {
			return null;
		}

		$table = TableNameResolver::getRoutesTable();
		$wpdb->insert(
			$table,
			[
				'tenant_id'          => (int) $route->tenant_id,
				'phone_number_id'    => (int) $route->phone_number_id,
				'name'               => sanitize_text_field( $route->name . ' Copy' ),
				'target_url'         => $route->target_url,
				'target_url_hash'    => hash( 'sha256', $route->target_url . '#copy-' . wp_generate_password( 8, false ) ),
				'secret_encrypted'   => $route->secret_encrypted,
				'event_filters_json' => $route->event_filters_json,
				'is_active'          => 0,
				'status'             => 'disabled',
				'timeout_ms'         => (int) $route->timeout_ms,
				'max_retries'        => (int) $route->max_retries,
				'priority'           => (int) $route->priority,
				'created_at'         => current_time( 'mysql', true ),
				'updated_at'         => current_time( 'mysql', true ),
			]
		);

		return $wpdb->insert_id ? $this->find( (int) $wpdb->insert_id ) : null;
	}

	public function list( array $filters = [] ) {
		global $wpdb;
		$table = TableNameResolver::getRoutesTable();
		$where = 'WHERE 1=1';
		$params = [];

		if ( ! empty( $filters['tenant_id'] ) ) {
			$where .= ' AND tenant_id = %d';
			$params[] = (int) $filters['tenant_id'];
		}

		if ( ! empty( $filters['phone_number_id'] ) ) {
			$where .= ' AND phone_number_id = %d';
			$params[] = (int) $filters['phone_number_id'];
		}

		if ( array_key_exists( 'is_active', $filters ) && null !== $filters['is_active'] ) {
			$where .= ' AND is_active = %d';
			$params[] = empty( $filters['is_active'] ) ? 0 : 1;
		}

		$sql = "SELECT * FROM $table $where ORDER BY priority ASC, id ASC";
		if ( $params ) {
			$sql = $wpdb->prepare( $sql, ...$params );
		}

		return $wpdb->get_results( $sql );
	}

	public function active_for_phone( $phone_number_id, $tenant_id = null ) {
		$filters = [
			'phone_number_id' => (int) $phone_number_id,
			'is_active'       => 1,
		];

		if ( $tenant_id ) {
			$filters['tenant_id'] = (int) $tenant_id;
		}

		return $this->list( $filters );
	}

	public function find_active_duplicate( $tenant_id, $phone_number_id, $target_url, $exclude_id = 0 ) {
		global $wpdb;
		$table = TableNameResolver::getRoutesTable();
		$sql = $wpdb->prepare(
			"SELECT * FROM $table WHERE tenant_id = %d AND phone_number_id = %d AND target_url_hash = %s AND is_active = 1",
			(int) $tenant_id,
			(int) $phone_number_id,
			hash( 'sha256', esc_url_raw( $target_url ) )
		);
		$rows = $wpdb->get_results( $sql );
		foreach ( $rows as $row ) {
			if ( ! $exclude_id || (int) $row->id !== (int) $exclude_id ) {
				return $row;
			}
		}
		return null;
	}

	private function prepare_route_data( array $data, $target_url, $tenant_id, $phone_number_id ) {
		return [
			'tenant_id'          => (int) $tenant_id,
			'phone_number_id'    => (int) $phone_number_id,
			'name'               => sanitize_text_field( $data['name'] ?? 'Webhook route' ),
			'target_url'         => $target_url,
			'target_url_hash'    => hash( 'sha256', $target_url ),
			'secret_encrypted'   => SecretVault::encrypt( $data['secret'] ?? null ),
			'event_filters_json' => empty( $data['event_filters'] ) ? null : wp_json_encode( $data['event_filters'] ),
			'is_active'          => array_key_exists( 'is_active', $data ) && empty( $data['is_active'] ) ? 0 : 1,
			'status'             => sanitize_text_field( $data['status'] ?? 'active' ),
			'timeout_ms'         => (int) ( $data['timeout_ms'] ?? 8000 ),
			'max_retries'        => (int) ( $data['max_retries'] ?? 3 ),
			'priority'           => (int) ( $data['priority'] ?? 100 ),
			'updated_at'         => current_time( 'mysql', true ),
		];
	}

	private function active_duplicate_error() {
		return new WP_Error(
			'active_route_already_exists_for_phone',
			'Ja existe uma rota ativa com este tenant, numero e target_url. Desative a rota ativa atual antes de ativar outra igual.',
			[ 'status' => 409 ]
		);
	}
}
