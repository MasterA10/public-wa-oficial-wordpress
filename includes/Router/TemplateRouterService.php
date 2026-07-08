<?php

namespace WAS\Router;

use WAS\Core\TableNameResolver;
use WAS\Meta\MetaApiClient;
use WAS\Meta\TokenService;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TemplateRouterService {

	public function submit_template( array $params ) {
		$waba = $this->find_waba( (int) ( $params['waba_id'] ?? 0 ) );
		if ( ! $waba ) {
			return new WP_Error( 'waba_not_found', 'WABA not found.', [ 'status' => 404 ] );
		}

		$template_payload = $this->template_payload( $params );
		$missing = [];
		foreach ( [ 'name', 'language', 'category', 'components' ] as $field ) {
			if ( empty( $template_payload[ $field ] ) ) {
				$missing[] = $field;
			}
		}

		if ( $missing ) {
			return new WP_Error( 'missing_template_fields', 'Campos obrigatorios ausentes no template.', [ 'status' => 422, 'fields' => $missing ] );
		}

		$token = ( new TokenService() )->get_active_token( (int) $waba->tenant_id, (int) $waba->id );
		if ( ! $token ) {
			return new WP_Error( 'missing_waba_access_token', 'Token Meta ativo nao encontrado para a WABA.', [ 'status' => 409 ] );
		}

		$meta_response = ( new MetaApiClient() )->postJson(
			'templates.create',
			[ 'waba_id' => $waba->waba_id ],
			$template_payload,
			$token
		);

		if ( empty( $meta_response['success'] ) ) {
			return new WP_Error(
				'meta_template_submission_rejected',
				$meta_response['error'] ?? 'Meta rejeitou o template.',
				[
					'status'        => 400,
					'meta_response' => $meta_response,
				]
			);
		}

		$template_id = $this->upsert_template( $waba, $template_payload, $meta_response, $params );
		$template = $this->find_template_by_id( $template_id );
		$formatted = $template ? $this->template_response( $template ) : [];

		return [
			'status'               => 'submitted',
			'id'                   => $meta_response['id'] ?? null,
			'template_id'          => $template_id,
			'router_template_id'   => $template_id,
			'provider_template_id' => $meta_response['id'] ?? null,
			'template_status'      => strtoupper( $meta_response['status'] ?? 'SUBMITTED' ),
			'waba_id'              => (int) $waba->id,
			'meta_waba_id'         => $waba->waba_id,
			'phone_number_id'      => isset( $params['phone_number_id'] ) ? (int) $params['phone_number_id'] : null,
			'template_name'        => $template_payload['name'],
			'template_language'    => $template_payload['language'],
			'template_category'    => $template_payload['category'],
			'variables'            => $formatted['variables'] ?? [],
			'parameter_format'     => $formatted['parameter_format'] ?? null,
			'quality_score'        => $formatted['quality_score'] ?? null,
			'meta_response'        => $meta_response,
		];
	}

	public function list_templates( array $params ) {
		$waba = $this->find_waba( (int) ( $params['waba_id'] ?? 0 ) );
		if ( ! $waba ) {
			return new WP_Error( 'waba_not_found', 'WABA not found.', [ 'status' => 404 ] );
		}

		$synced = false;
		$sync_result = [ 'count' => 0, 'meta_summary' => null ];
		if ( ! empty( $params['sync'] ) && filter_var( $params['sync'], FILTER_VALIDATE_BOOLEAN ) ) {
			$sync_result = $this->sync_from_meta( $waba, $params );
			$synced = true;
		}

		$response = [
			'waba_id'         => (int) $waba->id,
			'meta_waba_id'    => $waba->waba_id,
			'phone_number_id' => isset( $params['phone_number_id'] ) ? (int) $params['phone_number_id'] : null,
			'synced'          => $synced,
			'sync_count'      => (int) $sync_result['count'],
			'meta_summary'    => $sync_result['meta_summary'],
			'templates'       => array_map( [ $this, 'template_response' ], $this->query_templates( $waba, $params ) ),
		];
		return $response;
	}

	public function sync_pending_template_statuses( $limit = 50 ) {
		global $wpdb;

		$table = TableNameResolver::getTemplatesTable();
		$limit = max( 1, min( 200, (int) $limit ) );
		$templates = $wpdb->get_results(
			"SELECT * FROM $table WHERE deleted_at IS NULL AND status IN ('PENDING','IN_REVIEW','SUBMITTED') ORDER BY updated_at ASC LIMIT $limit"
		);

		$summary = [
			'checked'  => 0,
			'updated'  => 0,
			'notified' => 0,
			'errors'   => 0,
		];

		foreach ( $templates as $template ) {
			$summary['checked']++;
			$result = $this->sync_pending_template( $template );

			if ( ! empty( $result['updated'] ) ) {
				$summary['updated']++;
			}
			if ( ! empty( $result['notified'] ) ) {
				$summary['notified']++;
			}
			if ( ! empty( $result['error'] ) ) {
				$summary['errors']++;
			}
		}

		return $summary;
	}

	private function sync_from_meta( $waba, array $params ) {
		$token = ( new TokenService() )->get_active_token( (int) $waba->tenant_id, (int) $waba->id );
		if ( ! $token ) {
			return [ 'count' => 0, 'meta_summary' => null ];
		}

		$query = [
			'fields' => 'id,name,status,category,language,components,rejected_reason,quality_score,parameter_format',
		];
		foreach ( [ 'category', 'language', 'name_or_content', 'quality_score', 'status' ] as $filter ) {
			if ( ! empty( $params[ $filter ] ) ) {
				$query[ $filter ] = sanitize_text_field( $params[ $filter ] );
			}
		}
		if ( ! empty( $params['include_summary'] ) && filter_var( $params['include_summary'], FILTER_VALIDATE_BOOLEAN ) ) {
			$query['summary'] = 'total_count,message_template_count,message_template_limit,are_translations_complete';
		}

		$result = ( new MetaApiClient() )->get(
			'templates.list',
			[ 'waba_id' => $waba->waba_id ],
			$query,
			$token
		);

		if ( empty( $result['success'] ) || empty( $result['data'] ) || ! is_array( $result['data'] ) ) {
			return [ 'count' => 0, 'meta_summary' => null ];
		}

		foreach ( $result['data'] as $template ) {
			$this->upsert_template(
				$waba,
				[
					'name'       => $template['name'] ?? '',
					'language'   => $template['language'] ?? '',
					'category'   => $template['category'] ?? '',
					'components' => $template['components'] ?? [],
					'parameter_format' => $template['parameter_format'] ?? null,
				],
				[
					'success' => true,
					'id'      => $template['id'] ?? null,
					'status'  => $template['status'] ?? 'UNKNOWN',
					'quality_score' => $template['quality_score'] ?? null,
					'raw'     => $template,
				],
				$params,
				true
			);
		}

		return [
			'count'        => count( $result['data'] ),
			'meta_summary' => $result['summary'] ?? null,
		];
	}

	private function upsert_template( $waba, array $template_payload, array $meta_response, array $params, $from_sync = false ) {
		global $wpdb;
		$table = TableNameResolver::getTemplatesTable();
		$name = sanitize_text_field( $template_payload['name'] ?? '' );
		$language = sanitize_text_field( $template_payload['language'] ?? '' );
		$existing = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $table WHERE tenant_id = %d AND waba_id = %s AND name = %s AND language = %s LIMIT 1",
				(int) $waba->tenant_id,
				$waba->waba_id,
				$name,
				$language
			)
		);

		$status = strtoupper( $meta_response['status'] ?? $meta_response['raw']['status'] ?? 'SUBMITTED' );
		$body_text = $this->extract_body_text( $template_payload['components'] ?? [] );
		$data = [
			'tenant_id'               => (int) $waba->tenant_id,
			'whatsapp_account_id'     => (int) $waba->id,
			'router_phone_number_id'  => isset( $params['phone_number_id'] ) ? (int) $params['phone_number_id'] : null,
			'meta_template_id'        => isset( $meta_response['id'] ) ? (string) $meta_response['id'] : null,
			'waba_id'                 => $waba->waba_id,
			'name'                    => $name,
			'category'                => sanitize_text_field( $template_payload['category'] ?? '' ),
			'language'                => $language,
			'status'                  => $status,
			'friendly_payload'        => wp_json_encode( $template_payload ),
			'meta_payload'            => wp_json_encode( $template_payload ),
			'meta_response_json'      => wp_json_encode( $meta_response ),
			'variable_map'            => wp_json_encode( ( new TemplateResponseFormatter() )->extract_variables( $template_payload['components'] ?? [] ) ),
			'body_text'               => $body_text,
			'components_json'         => wp_json_encode( $template_payload['components'] ?? [] ),
			'rejection_reason'        => $meta_response['raw']['rejected_reason'] ?? $meta_response['rejected_reason'] ?? null,
			'requested_callback_url'  => esc_url_raw( $params['requested_callback_url'] ?? '' ),
			'synced_at'               => $from_sync ? current_time( 'mysql', true ) : null,
			'submitted_at'            => $from_sync ? null : current_time( 'mysql', true ),
			'approved_at'             => 'APPROVED' === $status ? current_time( 'mysql', true ) : null,
			'updated_at'              => current_time( 'mysql', true ),
		];

		if ( $existing ) {
			$wpdb->update( $table, $data, [ 'id' => (int) $existing->id ] );
			return (int) $existing->id;
		}

		$data['created_at'] = current_time( 'mysql', true );
		$wpdb->insert( $table, $data );
		return (int) $wpdb->insert_id;
	}

	private function query_templates( $waba, array $params ) {
		global $wpdb;
		$table = TableNameResolver::getTemplatesTable();
		$where = 'WHERE tenant_id = %d AND whatsapp_account_id = %d AND deleted_at IS NULL';
		$args = [ (int) $waba->tenant_id, (int) $waba->id ];

		foreach ( [ 'status', 'category', 'language', 'name' ] as $field ) {
			if ( ! empty( $params[ $field ] ) ) {
				$where .= " AND $field = %s";
				$args[] = sanitize_text_field( $params[ $field ] );
			}
		}

		if ( ! empty( $params['phone_number_id'] ) ) {
			$where .= ' AND router_phone_number_id = %d';
			$args[] = (int) $params['phone_number_id'];
		}

		$sql = $wpdb->prepare( "SELECT * FROM $table $where ORDER BY updated_at DESC LIMIT 200", ...$args );
		return $wpdb->get_results( $sql );
	}

	private function template_response( $template ) {
		return ( new TemplateResponseFormatter() )->format( $template );
	}

	private function template_payload( array $params ) {
		if ( ! empty( $params['template'] ) && is_array( $params['template'] ) ) {
			return $params['template'];
		}

		$payload = $params;
		unset( $payload['waba_id'], $payload['phone_number_id'], $payload['requested_callback_url'] );
		return $payload;
	}

	private function sync_pending_template( $template ) {
		$waba = $this->find_waba( (int) $template->whatsapp_account_id );
		if ( ! $waba ) {
			$this->mark_template_poll_error( $template, 'waba_not_found' );
			return [ 'error' => true ];
		}

		$token = ( new TokenService() )->get_active_token( (int) $waba->tenant_id, (int) $waba->id );
		if ( ! $token ) {
			$this->mark_template_poll_error( $template, 'missing_waba_access_token' );
			return [ 'error' => true ];
		}

		$result = ( new MetaApiClient() )->get(
			'templates.list',
			[ 'waba_id' => $waba->waba_id ],
			[
				'fields'   => 'id,name,status,category,language,components,rejected_reason,quality_score,parameter_format',
				'name'     => (string) $template->name,
				'language' => (string) $template->language,
			],
			$token
		);

		if ( empty( $result['success'] ) ) {
			$this->mark_template_poll_error( $template, $result['error'] ?? 'meta_template_status_sync_failed' );
			return [ 'error' => true ];
		}

		$match = $this->find_meta_template_match( $template, $result['data'] ?? [] );
		if ( ! $match ) {
			$this->mark_template_poll_error( $template, 'template_not_found_in_meta' );
			return [ 'error' => true ];
		}

		$previous_status = strtoupper( (string) $template->status );
		$current_status = strtoupper( (string) ( $match['status'] ?? $previous_status ) );
		$changed = $current_status !== $previous_status;
		$this->update_template_from_meta_status( $template, $match, $current_status );

		$notified = false;
		if ( $changed && in_array( $current_status, [ 'APPROVED', 'REJECTED', 'FAILED' ], true ) && $this->should_notify_template_status( $template, $current_status ) ) {
			$this->notify_template_status_change( $waba, $template, $match, $current_status );
			$notified = true;
		}

		return [
			'updated'  => $changed,
			'notified' => $notified,
		];
	}

	private function find_meta_template_match( $template, $items ) {
		if ( isset( $items['id'] ) ) {
			$items = [ $items ];
		}
		if ( ! is_array( $items ) ) {
			return null;
		}

		$meta_template_id = (string) ( $template->meta_template_id ?? '' );
		$name = (string) ( $template->name ?? '' );
		$language = (string) ( $template->language ?? '' );

		foreach ( $items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			if ( $meta_template_id && (string) ( $item['id'] ?? '' ) === $meta_template_id ) {
				return $item;
			}
		}

		foreach ( $items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			if ( $name === (string) ( $item['name'] ?? '' ) && ( ! $language || $language === (string) ( $item['language'] ?? '' ) ) ) {
				return $item;
			}
		}

		return 1 === count( $items ) && is_array( $items[0] ?? null ) ? $items[0] : null;
	}

	private function update_template_from_meta_status( $template, array $meta_template, $status ) {
		global $wpdb;

		$components = $meta_template['components'] ?? [];
		$category = sanitize_text_field( $meta_template['category'] ?? $template->category );
		$payload = [
			'name'             => $meta_template['name'] ?? $template->name,
			'language'         => $meta_template['language'] ?? $template->language,
			'category'         => $category,
			'components'       => $components,
			'parameter_format' => $meta_template['parameter_format'] ?? null,
		];
		$data = [
			'meta_template_id'     => isset( $meta_template['id'] ) ? (string) $meta_template['id'] : $template->meta_template_id,
			'category'             => $category,
			'status'               => $status,
			'friendly_payload'     => wp_json_encode( $payload ),
			'meta_payload'         => wp_json_encode( $payload ),
			'meta_response_json'   => wp_json_encode( $meta_template ),
			'components_json'      => wp_json_encode( $components ),
			'variable_map'         => wp_json_encode( ( new TemplateResponseFormatter() )->extract_variables( $components ) ),
			'body_text'            => $this->extract_body_text( $components ),
			'rejection_reason'     => $meta_template['rejected_reason'] ?? $template->rejection_reason ?? null,
			'synced_at'            => current_time( 'mysql', true ),
			'last_status_check_at' => current_time( 'mysql', true ),
			'last_status_error'    => null,
			'updated_at'           => current_time( 'mysql', true ),
		];

		if ( 'APPROVED' === $status ) {
			$data['approved_at'] = ! empty( $template->approved_at ) ? $template->approved_at : current_time( 'mysql', true );
		}
		if ( in_array( $status, [ 'REJECTED', 'FAILED' ], true ) ) {
			$data['rejected_at'] = ! empty( $template->rejected_at ) ? $template->rejected_at : current_time( 'mysql', true );
		}

		$wpdb->update( TableNameResolver::getTemplatesTable(), $data, [ 'id' => (int) $template->id ] );
	}

	private function mark_template_poll_error( $template, $message ) {
		global $wpdb;

		$wpdb->update(
			TableNameResolver::getTemplatesTable(),
			[
				'last_status_check_at' => current_time( 'mysql', true ),
				'last_status_error'    => sanitize_text_field( $message ),
				'updated_at'           => current_time( 'mysql', true ),
			],
			[ 'id' => (int) $template->id ]
		);
	}

	private function should_notify_template_status( $template, $status ) {
		if ( 'APPROVED' === $status && ! empty( $template->approved_notified_at ) ) {
			return false;
		}

		return true;
	}

	private function notify_template_status_change( $waba, $template, array $meta_template, $status ) {
		$meta_template_id = isset( $meta_template['id'] ) ? (string) $meta_template['id'] : (string) ( $template->meta_template_id ?? '' );
		$item = [
			'event_type'           => 'template_status_updated',
			'message_type'         => $status,
			'wa_message_id'        => $meta_template_id ?: null,
			'wa_from'              => null,
			'meta_waba_id'         => $waba->waba_id,
			'meta_phone_number_id' => null,
			'normalized_payload'   => [
				'event_type'             => 'template_status_updated',
				'meta_event_type'        => 'template_status_poll',
				'status'                 => $status,
				'template_status'        => $status,
				'template_id'            => (int) $template->id,
				'router_template_id'     => (int) $template->id,
				'meta_template_id'       => $meta_template_id ?: null,
				'template_name'          => $meta_template['name'] ?? $template->name,
				'name'                   => $meta_template['name'] ?? $template->name,
				'template_language'      => $meta_template['language'] ?? $template->language,
				'template_category'      => $meta_template['category'] ?? $template->category,
				'meta_waba_id'           => $waba->waba_id,
				'router_waba_id'         => (int) $template->whatsapp_account_id,
				'router_phone_number_id' => ! empty( $template->router_phone_number_id ) ? (int) $template->router_phone_number_id : null,
				'reason'                 => $meta_template['rejected_reason'] ?? null,
				'raw_template'           => $meta_template,
			],
			'item_index'           => 'template-status:' . (int) $template->id . ':' . $status . ':' . ( $meta_template_id ?: 'unknown' ),
		];

		return ( new WebhookRouterService() )->record_synthetic_event( $item );
	}

	private function find_waba( $id ) {
		global $wpdb;
		$table = TableNameResolver::get_table_name( 'whatsapp_accounts' );
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d LIMIT 1", (int) $id ) );
	}

	private function find_template_by_id( $id ) {
		global $wpdb;
		$table = TableNameResolver::getTemplatesTable();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d LIMIT 1", (int) $id ) );
	}

	private function extract_body_text( array $components ) {
		foreach ( $components as $component ) {
			if ( 'BODY' === strtoupper( $component['type'] ?? '' ) ) {
				return (string) ( $component['text'] ?? '' );
			}
		}

		return '';
	}
}
