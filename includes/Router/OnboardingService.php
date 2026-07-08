<?php

namespace WAS\Router;

use WAS\Core\TableNameResolver;
use WAS\Meta\MetaApiClient;
use WAS\Meta\MetaAppRepository;
use WAS\Meta\TokenService;
use WAS\WhatsApp\WebhookSubscriptionService;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class OnboardingService {

	public function create_registration( array $payload ) {
		global $wpdb;

		$tenant_id = (int) ( $payload['tenant_id'] ?? 0 );
		$meta_app_id = (int) ( $payload['meta_app_id'] ?? 0 );
		$phone_number = $this->normalize_phone( $payload['phone_number'] ?? '' );
		$callback_url = esc_url_raw( $payload['callback_url'] ?? '' );

		if ( ! $tenant_id || ! $meta_app_id || ! $phone_number || ! $callback_url ) {
			return new WP_Error( 'invalid_onboarding_registration', 'tenant_id, meta_app_id, phone_number e callback_url sao obrigatorios.', [ 'status' => 400 ] );
		}

		if ( ! $this->tenant_exists( $tenant_id ) ) {
			return new WP_Error( 'tenant_not_found', 'Tenant not found.', [ 'status' => 404 ] );
		}

		if ( ! $this->meta_app_exists( $meta_app_id ) ) {
			return new WP_Error( 'meta_app_not_found', 'Meta App not found.', [ 'status' => 404 ] );
		}

		$variants = $this->registration_variants( $phone_number, $payload['phone_number_variants'] ?? [] );
		$table = TableNameResolver::getOnboardingRegistrationsTable();
		$registration = null;

		if ( ! empty( $payload['attempt_id'] ) ) {
			$registration = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM $table WHERE tenant_id = %d AND meta_app_id = %d AND attempt_id = %s LIMIT 1",
					$tenant_id,
					$meta_app_id,
					sanitize_text_field( $payload['attempt_id'] )
				)
			);
		}

		if ( ! $registration ) {
			$registration = $this->find_registration_by_phone_variants( $tenant_id, $meta_app_id, $variants );
		}

		$data = [
			'tenant_id'                   => $tenant_id,
			'meta_app_id'                 => $meta_app_id,
			'company_id'                  => sanitize_text_field( $payload['company_id'] ?? '' ),
			'attempt_id'                  => sanitize_text_field( $payload['attempt_id'] ?? '' ),
			'state'                       => $this->extract_state( $payload['authorization_url'] ?? '' ),
			'phone_number'                => $phone_number,
			'phone_number_variants_json'  => wp_json_encode( $variants ),
			'provider'                    => sanitize_text_field( $payload['provider'] ?? 'meta_whatsapp' ),
			'callback_url'                => $callback_url,
			'authorization_url'           => esc_url_raw( $payload['authorization_url'] ?? '' ),
			'redirect_uri'                => esc_url_raw( $payload['redirect_uri'] ?? '' ),
			'status'                      => 'onboarding',
			'notified_at'                 => null,
			'last_notification_status'    => null,
			'last_notification_error'     => null,
			'updated_at'                  => current_time( 'mysql', true ),
		];

		if ( $registration ) {
			$wpdb->update( $table, $data, [ 'id' => (int) $registration->id ] );
			$id = (int) $registration->id;
		} else {
			$data['created_at'] = current_time( 'mysql', true );
			$wpdb->insert( $table, $data );
			$id = (int) $wpdb->insert_id;
		}

		return [
			'id'                    => $id,
			'status'                => 'onboarding',
			'phone_number'          => $phone_number,
			'phone_number_variants' => $variants,
		];
	}

	public function complete_embedded_signup( array $payload ) {
		global $wpdb;

		$tenant_id = (int) ( $payload['tenant_id'] ?? 0 );
		$meta_app_id = (int) ( $payload['meta_app_id'] ?? 0 );
		$attempt_id = sanitize_text_field( $payload['attempt_id'] ?? '' );

		if ( ! $tenant_id || ! $meta_app_id || ! $attempt_id ) {
			return new WP_Error( 'invalid_embedded_signup', 'tenant_id, meta_app_id e attempt_id sao obrigatorios.', [ 'status' => 400 ] );
		}

		$registration = $this->get_registration_for_completion( $tenant_id, $meta_app_id, $attempt_id );
		if ( ! $registration ) {
			return new WP_Error( 'onboarding_registration_not_found', 'onboarding_registration_not_found', [ 'status' => 404 ] );
		}

		$registrations_table = TableNameResolver::getOnboardingRegistrationsTable();
		$jobs_table = TableNameResolver::getOnboardingReconciliationJobsTable();

		$wpdb->update(
			$registrations_table,
			[
				'attempt_id'                  => $attempt_id,
				'redirect_uri'                => esc_url_raw( $payload['redirect_uri'] ?? $this->object_value( $registration, 'redirect_uri' ) ),
				'meta_waba_id'                => sanitize_text_field( $payload['waba_id'] ?? $this->object_value( $registration, 'meta_waba_id' ) ),
				'owner_business_id'           => sanitize_text_field( $payload['business_id'] ?? $this->object_value( $registration, 'owner_business_id' ) ),
				'business_id'                 => sanitize_text_field( $payload['business_id'] ?? $this->object_value( $registration, 'business_id' ) ),
				'phone_number_id'             => sanitize_text_field( $payload['phone_number_id'] ?? $this->object_value( $registration, 'phone_number_id' ) ),
				'display_phone_number'        => sanitize_text_field( $payload['display_phone_number'] ?? $this->object_value( $registration, 'display_phone_number' ) ),
				'verified_name'               => sanitize_text_field( $payload['verified_name'] ?? $this->object_value( $registration, 'verified_name' ) ),
				'authorization_code_hash'     => ! empty( $payload['authorization_code'] ) ? hash( 'sha256', (string) $payload['authorization_code'] ) : $this->object_value( $registration, 'authorization_code_hash' ),
				'authorization_code_encrypted'=> ! empty( $payload['authorization_code'] ) ? SecretVault::encrypt( $payload['authorization_code'] ) : $this->object_value( $registration, 'authorization_code_encrypted' ),
				'access_token_encrypted'      => ! empty( $payload['access_token'] ) ? SecretVault::encrypt( $payload['access_token'] ) : $this->object_value( $registration, 'access_token_encrypted' ),
				'token_exchange_status'       => ! empty( $payload['access_token'] ) ? 'exchanged' : $this->object_value( $registration, 'token_exchange_status' ),
				'token_exchanged_at'          => ! empty( $payload['access_token'] ) ? current_time( 'mysql', true ) : $this->object_value( $registration, 'token_exchanged_at' ),
				'updated_at'                  => current_time( 'mysql', true ),
			],
			[ 'id' => (int) $registration->id ]
		);

		$registration = $this->get_registration( (int) $registration->id );
		$wpdb->insert(
			$jobs_table,
			[
				'registration_id' => (int) $registration->id,
				'status'          => 'processing',
				'attempts'        => 1,
				'created_at'      => current_time( 'mysql', true ),
				'updated_at'      => current_time( 'mysql', true ),
			]
		);
		$job_id = (int) $wpdb->insert_id;

		$result = $this->reconcile_registration( $registration, $payload );
		if ( is_wp_error( $result ) ) {
			$wpdb->update(
				$jobs_table,
				[
					'status'     => 'failed',
					'last_error' => $result->get_error_message(),
					'updated_at' => current_time( 'mysql', true ),
				],
				[ 'id' => $job_id ]
			);
			return $result;
		}

		$job_status = $result['job_status'] ?? 'completed';
		$job_update = [
			'status'     => $job_status,
			'last_error' => $result['job_error'] ?? null,
			'updated_at' => current_time( 'mysql', true ),
		];
		if ( in_array( $job_status, [ 'completed', 'failed' ], true ) ) {
			$job_update['processed_at'] = current_time( 'mysql', true );
		}

		$wpdb->update( $jobs_table, $job_update, [ 'id' => $job_id ] );

		$result['job_id'] = $job_id;
		unset( $result['job_status'], $result['job_error'] );
		return $result;
	}

	public function create_bundle( array $payload ) {
		global $wpdb;

		foreach ( [ 'tenant', 'meta_app', 'waba', 'phone_number', 'route' ] as $section ) {
			if ( empty( $payload[ $section ] ) || ! is_array( $payload[ $section ] ) ) {
				return new WP_Error( 'invalid_onboarding_bundle', 'tenant, meta_app, waba, phone_number e route sao obrigatorios.', [ 'status' => 400, 'missing_section' => $section ] );
			}
		}

		$admin = new AdminRouterService();

		$tenant = $admin->create_tenant( $payload['tenant'] );
		if ( is_wp_error( $tenant ) ) {
			return $tenant;
		}

		$meta_app = $admin->create_meta_app( $payload['meta_app'] );
		if ( is_wp_error( $meta_app ) ) {
			return $meta_app;
		}

		$waba_payload = array_merge(
			$payload['waba'],
			[
				'tenant_id'   => (int) $tenant->id,
				'meta_app_id' => (int) $meta_app->id,
			]
		);
		$waba = $admin->create_waba( $waba_payload );
		if ( is_wp_error( $waba ) ) {
			return $waba;
		}

		$phone_payload = array_merge(
			$payload['phone_number'],
			[
				'tenant_id' => (int) $tenant->id,
				'waba_id'   => (int) $waba->id,
			]
		);
		$phone = $admin->create_phone_number( $phone_payload );
		if ( is_wp_error( $phone ) ) {
			return $phone;
		}

		$route_payload = array_merge(
			$payload['route'],
			[
				'tenant_id'       => (int) $tenant->id,
				'phone_number_id' => (int) $phone->id,
			]
		);
		$route = $admin->create_route( $route_payload );
		if ( is_wp_error( $route ) ) {
			return $route;
		}

		$wpdb->update(
			TableNameResolver::get_table_name( 'whatsapp_phone_numbers' ),
			[
				'default_route_id' => (int) $route->id,
				'updated_at'       => current_time( 'mysql', true ),
			],
			[ 'id' => (int) $phone->id ]
		);

		return [
			'tenant_id'                => (int) $tenant->id,
			'meta_app_id'              => (int) $meta_app->id,
			'waba_id'                  => (int) $waba->id,
			'phone_number_id'          => (int) $phone->id,
			'route_id'                 => (int) $route->id,
			'router_waba_id'           => (int) $waba->id,
			'router_phone_number_id'   => (int) $phone->id,
			'router_route_id'          => (int) $route->id,
			'meta_waba_id'             => $waba->waba_id,
			'meta_phone_number_id'     => $phone->phone_number_id,
			'whatsapp_phone_number_id' => $phone->phone_number_id,
			'display_phone_number'     => $phone->display_phone_number,
		];
	}

	public function process_pending_reconciliation_jobs( $limit = 10 ) {
		global $wpdb;

		$table = TableNameResolver::getOnboardingReconciliationJobsTable();
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM $table WHERE status = 'pending' ORDER BY created_at ASC LIMIT %d",
				(int) $limit
			)
		);

		if ( count( $rows ) < (int) $limit ) {
			$processing = $wpdb->get_results( "SELECT * FROM $table WHERE status = 'processing' ORDER BY created_at ASC LIMIT 500" );
			foreach ( $processing as $row ) {
				if ( $this->is_processing_job_stale( $row ) ) {
					$rows[] = $row;
				}
				if ( count( $rows ) >= (int) $limit ) {
					break;
				}
			}
		}

		$results = [];
		foreach ( $rows as $row ) {
			$results[] = $this->process_reconciliation_job( (int) $row->id );
		}

		return [
			'processed' => count( $results ),
			'results'   => $results,
		];
	}

	public function enqueue_reconciliation_job( $registration_id ) {
		global $wpdb;

		$table = TableNameResolver::getOnboardingReconciliationJobsTable();
		$existing = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $table WHERE registration_id = %d AND status IN ('pending','processing') ORDER BY created_at DESC LIMIT 1",
				(int) $registration_id
			)
		);

		if ( $existing ) {
			return (int) $existing->id;
		}

		$wpdb->insert(
			$table,
			[
				'registration_id' => (int) $registration_id,
				'status'          => 'pending',
				'attempts'        => 0,
				'created_at'      => current_time( 'mysql', true ),
				'updated_at'      => current_time( 'mysql', true ),
			]
		);

		return (int) $wpdb->insert_id;
	}

	public function process_reconciliation_job( $job_id ) {
		global $wpdb;

		$jobs_table = TableNameResolver::getOnboardingReconciliationJobsTable();
		$job = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $jobs_table WHERE id = %d LIMIT 1",
				(int) $job_id
			)
		);

		if ( ! $job ) {
			return new WP_Error( 'onboarding_reconciliation_job_not_found', 'Job de reconciliacao nao encontrado.', [ 'status' => 404 ] );
		}

		if ( 'processing' === $job->status && ! $this->is_processing_job_stale( $job ) ) {
			return [
				'success'  => false,
				'status'   => 'already_processing',
				'job_id'   => (int) $job->id,
				'attempts' => (int) $job->attempts,
			];
		}

		if ( ! in_array( $job->status, [ 'pending', 'failed', 'processing' ], true ) ) {
			return [
				'success' => true,
				'status'  => 'skipped',
				'job_id'  => (int) $job->id,
			];
		}

		$attempts = (int) $job->attempts + 1;
		$wpdb->update(
			$jobs_table,
			[
				'status'     => 'processing',
				'attempts'   => $attempts,
				'updated_at' => current_time( 'mysql', true ),
			],
			[ 'id' => (int) $job->id ]
		);

		$registration = $this->get_registration( (int) $job->registration_id );
		if ( ! $registration ) {
			$wpdb->update(
				$jobs_table,
				[
					'status'       => 'failed',
					'last_error'   => 'onboarding_registration_not_found',
					'processed_at' => current_time( 'mysql', true ),
					'updated_at'   => current_time( 'mysql', true ),
				],
				[ 'id' => (int) $job->id ]
			);
			return new WP_Error( 'onboarding_registration_not_found', 'Registro de onboarding nao encontrado.', [ 'status' => 404 ] );
		}

		$registration = $this->prepare_registration_for_job_retry( $registration );
		$result = $this->reconcile_registration( $registration, [] );
		if ( is_wp_error( $result ) ) {
			$wpdb->update(
				$jobs_table,
				[
					'status'       => 'failed',
					'last_error'   => $result->get_error_message(),
					'processed_at' => current_time( 'mysql', true ),
					'updated_at'   => current_time( 'mysql', true ),
				],
				[ 'id' => (int) $job->id ]
			);
			return $result;
		}

		$job_status = $result['job_status'] ?? 'completed';
		$job_update = [
			'status'     => $job_status,
			'last_error' => $result['job_error'] ?? null,
			'updated_at' => current_time( 'mysql', true ),
		];
		if ( in_array( $job_status, [ 'completed', 'failed' ], true ) ) {
			$job_update['processed_at'] = current_time( 'mysql', true );
		}

		$wpdb->update( $jobs_table, $job_update, [ 'id' => (int) $job->id ] );

		$result['job_id'] = (int) $job->id;
		unset( $result['job_status'], $result['job_error'] );
		return $result;
	}

	private function is_processing_job_stale( $job ) {
		if ( 'processing' !== ( $job->status ?? '' ) ) {
			return false;
		}

		$timestamp = strtotime( (string) ( $job->updated_at ?? $job->created_at ?? '' ) );
		if ( ! $timestamp ) {
			return false;
		}

		return ( time() - $timestamp ) >= RouterSettings::get_onboarding_processing_stale_seconds();
	}

	private function reconcile_registration( $registration, array $payload ) {
		$access_token_result = $this->get_or_exchange_token( $registration, $payload );
		if ( is_wp_error( $access_token_result ) ) {
			if ( 202 === (int) ( $access_token_result->get_error_data()['status'] ?? 0 ) ) {
				return $this->queued_for_retry_result( $registration, $access_token_result->get_error_code(), $access_token_result->get_error_message() );
			}
			return $access_token_result;
		}

		$access_token = $access_token_result['access_token'];
		$meta_waba_id = sanitize_text_field( $payload['waba_id'] ?? $this->object_value( $registration, 'meta_waba_id' ) );
		if ( ! $meta_waba_id ) {
			return $this->queued_for_retry_result( $registration, 'waiting_meta_waba_id', 'Meta WABA ID ausente.' );
		}

		$account_id = $this->upsert_waba( $registration, $payload, $access_token );
		$account = $this->find_account_by_id( $account_id );
		$meta_phone = $this->select_meta_phone_number( $registration, $payload, $access_token, $account );
		if ( ! $meta_phone ) {
			return $this->queued_for_retry_result( $registration, 'waiting_phone_number', 'Nao foi possivel identificar o numero conectado.' );
		}

		$phone_id = $this->upsert_phone( $registration, $account, $meta_phone );
		( new TokenService() )->store_encrypted_token( (int) $registration->tenant_id, $account_id, $access_token );

		$route_id = ( new RouteRepository() )->create_or_update(
			[
				'tenant_id'        => (int) $registration->tenant_id,
				'phone_number_id'  => $phone_id,
				'name'             => 'Onboarding webhook',
				'target_url'       => $registration->callback_url,
				'secret'           => RouterSettings::get_route_secret(),
				'event_filters'    => [],
				'is_active'        => 1,
				'status'           => 'active',
				'timeout_ms'       => 8000,
				'max_retries'      => 3,
				'priority'         => 100,
			]
		);

		$this->set_default_route( $phone_id, $route_id );
		$this->try_subscribe_webhooks( $account, $access_token );
		$notification = $this->notify_registration_completed( $registration, $account, $phone_id, $route_id );
		$phone = $this->find_phone_by_id( $phone_id );
		$status = 'completed';
		$job_status = 'completed';
		$job_error = null;
		if ( 'failed' === $notification['notification_status'] ) {
			$status_code = (int) ( $notification['notification_status_code'] ?? 0 );
			$status = 409 === $status_code ? 'notification_failed' : 'queued_for_retry';
			$job_status = 409 === $status_code ? 'failed' : 'pending';
			$job_error = 'notification_failed_' . ( $status_code ?: 'unknown' );
		}

		return [
			'success'                  => true,
			'status'                   => $status,
			'waba_id'                  => $account_id,
			'phone_number_id'          => $phone_id,
			'route_id'                 => $route_id,
			'router_waba_id'           => $account_id,
			'router_phone_number_id'   => $phone_id,
			'router_route_id'          => $route_id,
			'token_exchanged'          => (bool) $access_token_result['token_exchanged'],
			'meta_waba_id'             => $account->waba_id,
			'meta_phone_number_id'     => $phone->phone_number_id ?? null,
			'whatsapp_phone_number_id' => $phone->phone_number_id ?? null,
			'display_phone_number'     => $phone->display_phone_number ?? null,
			'verified_name'            => $phone->verified_name ?? null,
			'phone_number'             => $this->normalize_phone( $phone->display_phone_number ?? $this->object_value( $registration, 'phone_number' ) ),
			'message'                  => 'Reconciliação concluída.',
			'notification_status'      => $notification['notification_status'],
			'notification_error'       => $notification['notification_error'],
			'notification_status_code' => $notification['notification_status_code'],
			'job_status'               => $job_status,
			'job_error'                => $job_error,
		];
	}

	private function queued_for_retry_result( $registration, $code, $message ) {
		global $wpdb;

		$wpdb->update(
			TableNameResolver::getOnboardingRegistrationsTable(),
			[
				'last_error' => sanitize_text_field( $message ),
				'updated_at' => current_time( 'mysql', true ),
			],
			[ 'id' => (int) $registration->id ]
		);

		return [
			'success'    => true,
			'status'     => 'queued_for_retry',
			'message'    => $message,
			'error'      => $code,
			'job_status' => 'pending',
			'job_error'  => $code,
		];
	}

	private function prepare_registration_for_job_retry( $registration ) {
		$last_notification_status = (int) $this->object_value( $registration, 'last_notification_status', 0 );
		if ( 'completed' !== $this->object_value( $registration, 'status' ) && ! empty( $registration->notified_at ) && $last_notification_status >= 200 && $last_notification_status < 300 ) {
			global $wpdb;
			$wpdb->update(
				TableNameResolver::getOnboardingRegistrationsTable(),
				[
					'notified_at'              => null,
					'last_notification_status' => null,
					'last_notification_error'  => null,
					'updated_at'               => current_time( 'mysql', true ),
				],
				[ 'id' => (int) $registration->id ]
			);
			return $this->get_registration( (int) $registration->id );
		}

		return $registration;
	}

	private function get_or_exchange_token( $registration, array $payload ) {
		if ( ! empty( $payload['access_token'] ) ) {
			return [
				'access_token'    => (string) $payload['access_token'],
				'token_exchanged' => true,
			];
		}

		$saved = SecretVault::decrypt( $this->object_value( $registration, 'access_token_encrypted' ) );
		if ( $saved ) {
			return [
				'access_token'    => $saved,
				'token_exchanged' => false,
			];
		}

		$meta_waba_id = sanitize_text_field( $payload['waba_id'] ?? $this->object_value( $registration, 'meta_waba_id' ) );
		if ( $meta_waba_id ) {
			$existing_account = $this->find_account_by_waba_id( $meta_waba_id );
			if ( $existing_account ) {
				$existing_token = ( new TokenService() )->get_active_token( (int) $existing_account->tenant_id, (int) $existing_account->id );
				if ( $existing_token ) {
					$this->save_registration_token( (int) $registration->id, $existing_token, 'exchanged' );
					return [
						'access_token'    => $existing_token,
						'token_exchanged' => false,
					];
				}
			}
		}

		$code = ! empty( $payload['authorization_code'] ) ? (string) $payload['authorization_code'] : SecretVault::decrypt( $this->object_value( $registration, 'authorization_code_encrypted' ) );
		if ( ! $code ) {
			return new WP_Error( 'authorization_code_required', 'Authorization code ausente.', [ 'status' => 202 ] );
		}

		$app = $this->get_meta_app( (int) $registration->meta_app_id, true );
		if ( ! $app || empty( $app->app_id ) || empty( $app->app_secret ) ) {
			return new WP_Error( 'meta_app_credentials_missing', 'Meta App sem App ID/App Secret.', [ 'status' => 409 ] );
		}

		$version = $app->graph_version ?: WAS_META_GRAPH_DEFAULT_VERSION;
		$params = [
			'client_id'     => $app->app_id,
			'client_secret' => $app->app_secret,
			'code'          => $code,
		];

		$redirect_uri = $payload['redirect_uri'] ?? $this->object_value( $registration, 'redirect_uri' );
		if ( $redirect_uri ) {
			$params['redirect_uri'] = $redirect_uri;
		}

		$response = wp_remote_get(
			add_query_arg( $params, WAS_META_GRAPH_BASE_URL . '/' . $version . '/oauth/access_token' ),
			[ 'timeout' => 30 ]
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'token_exchange_failed', $response->get_error_message(), [ 'status' => 502 ] );
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		$status = (int) wp_remote_retrieve_response_code( $response );
		if ( $status >= 400 || empty( $body['access_token'] ) ) {
			$message = $body['error']['message'] ?? 'Falha ao trocar code por token.';
			$subcode = (int) ( $body['error']['error_subcode'] ?? 0 );
			if ( 36009 === $subcode || false !== stripos( $message, 'code has been used' ) ) {
				$this->save_registration_token_status( (int) $registration->id, 'code_already_used_without_saved_token' );
				return new WP_Error( 'code_already_used_without_saved_token', $message, [ 'status' => 202, 'meta_error' => $body ] );
			}
			return new WP_Error( 'token_exchange_failed', $message, [ 'status' => 502, 'meta_error' => $body ] );
		}

		$this->save_registration_token( (int) $registration->id, (string) $body['access_token'], 'exchanged' );

		return [
			'access_token'    => $body['access_token'],
			'token_exchanged' => true,
		];
	}

	private function select_meta_phone_number( $registration, array $payload, $access_token, $account ) {
		$requested_id = sanitize_text_field( $payload['phone_number_id'] ?? $this->object_value( $registration, 'phone_number_id' ) );
		$requested_display = sanitize_text_field( $payload['display_phone_number'] ?? $this->object_value( $registration, 'display_phone_number' ) );
		$numbers = $this->fetch_meta_phone_numbers( $account->waba_id, $access_token );
		$variants = json_decode( $registration->phone_number_variants_json ?: '[]', true );

		foreach ( $numbers as $number ) {
			if ( ! empty( $number['display_phone_number'] ) && array_intersect( $variants, $this->phone_match_variants( $number['display_phone_number'] ) ) ) {
				return $number;
			}
		}

		foreach ( $numbers as $number ) {
			if ( $requested_id && (string) ( $number['id'] ?? '' ) === $requested_id ) {
				return $number;
			}
		}

		if ( 1 === count( $numbers ) ) {
			return $numbers[0];
		}

		if ( $requested_id ) {
			return [
				'id'                   => $requested_id,
				'display_phone_number' => $requested_display ?: $requested_id,
				'verified_name'        => sanitize_text_field( $payload['verified_name'] ?? '' ),
			];
		}

		$existing = $this->find_existing_phone_by_variants( (int) $registration->tenant_id, $variants );
		if ( $existing ) {
			return [
				'id'                   => $existing->phone_number_id,
				'display_phone_number' => $existing->display_phone_number,
				'verified_name'        => $existing->verified_name ?? '',
			];
		}

		return null;
	}

	private function fetch_meta_phone_numbers( $meta_waba_id, $access_token ) {
		$result = ( new MetaApiClient() )->get(
			'waba.phone_numbers',
			[ 'waba_id' => $meta_waba_id ],
			[],
			$access_token
		);

		return ! empty( $result['success'] ) && is_array( $result['data'] ?? null ) ? $result['data'] : [];
	}

	private function upsert_waba( $registration, array $payload, $access_token ) {
		global $wpdb;
		$table = TableNameResolver::get_table_name( 'whatsapp_accounts' );
		$meta_waba_id = sanitize_text_field( $payload['waba_id'] ?? $this->object_value( $registration, 'meta_waba_id' ) );
		$existing = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE waba_id = %s LIMIT 1", $meta_waba_id ) );

		$data = [
			'tenant_id'        => (int) $registration->tenant_id,
			'meta_app_id'      => (int) $registration->meta_app_id,
			'meta_business_id' => sanitize_text_field( $payload['business_id'] ?? $this->object_value( $registration, 'business_id' ) ),
			'waba_id'          => $meta_waba_id,
			'name'             => sanitize_text_field( $payload['waba_name'] ?? $meta_waba_id ),
			'status'           => 'active',
			'connected_at'     => $existing && ! empty( $existing->connected_at ) ? $existing->connected_at : current_time( 'mysql', true ),
			'updated_at'       => current_time( 'mysql', true ),
		];

		if ( $existing ) {
			$wpdb->update( $table, $data, [ 'id' => (int) $existing->id ] );
			return (int) $existing->id;
		}

		$data['created_at'] = current_time( 'mysql', true );
		$wpdb->insert( $table, $data );
		return (int) $wpdb->insert_id;
	}

	private function upsert_phone( $registration, $account, array $meta_phone ) {
		global $wpdb;
		$table = TableNameResolver::get_table_name( 'whatsapp_phone_numbers' );
		$meta_phone_id = sanitize_text_field( $meta_phone['id'] ?? $meta_phone['phone_number_id'] ?? '' );
		$existing = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE phone_number_id = %s LIMIT 1", $meta_phone_id ) );

		$data = [
			'tenant_id'            => (int) $registration->tenant_id,
			'whatsapp_account_id'  => (int) $account->id,
			'phone_number_id'      => $meta_phone_id,
			'display_phone_number' => sanitize_text_field( $meta_phone['display_phone_number'] ?? $meta_phone_id ),
			'verified_name'        => sanitize_text_field( $meta_phone['verified_name'] ?? '' ),
			'quality_rating'       => sanitize_text_field( $meta_phone['quality_rating'] ?? 'UNKNOWN' ),
			'status'               => 'active',
			'updated_at'           => current_time( 'mysql', true ),
		];

		if ( $existing ) {
			$wpdb->update( $table, $data, [ 'id' => (int) $existing->id ] );
			return (int) $existing->id;
		}

		$data['is_default'] = $this->tenant_has_phone( (int) $registration->tenant_id ) ? 0 : 1;
		$data['created_at'] = current_time( 'mysql', true );
		$wpdb->insert( $table, $data );
		return (int) $wpdb->insert_id;
	}

	private function notify_registration_completed( $registration, $account, $phone_id, $route_id ) {
		global $wpdb;
		$registrations_table = TableNameResolver::getOnboardingRegistrationsTable();
		$phone = $this->find_phone_by_id( $phone_id );

		$last_notification_status = (int) $this->object_value( $registration, 'last_notification_status', 0 );
		if ( ! empty( $registration->notified_at ) && $last_notification_status >= 200 && $last_notification_status < 300 ) {
			$wpdb->update(
				$registrations_table,
				[
					'status'                 => 'completed',
					'router_waba_id'         => (int) $account->id,
					'router_phone_number_id' => (int) $phone_id,
					'route_id'               => (int) $route_id,
					'phone_number_id'        => $phone->phone_number_id ?? null,
					'display_phone_number'   => $phone->display_phone_number ?? null,
					'verified_name'          => $phone->verified_name ?? null,
					'updated_at'             => current_time( 'mysql', true ),
				],
				[ 'id' => (int) $registration->id ]
			);

			return [
				'notification_status'      => 'delivered',
				'notification_error'       => null,
				'notification_status_code' => $last_notification_status,
				'skipped'                  => true,
			];
		}

		$payload = [
			'event_type'             => 'onboarding_completed',
			'registration_id'        => (int) $registration->id,
			'attempt_id'             => $registration->attempt_id,
			'tenant_id'              => (int) $registration->tenant_id,
			'company_id'             => $registration->company_id,
			'provider'               => $registration->provider,
			'phone_number'           => $this->normalize_phone( $phone->display_phone_number ?? $registration->phone_number ),
			'phone_number_variants'  => json_decode( $registration->phone_number_variants_json ?: '[]', true ),
			'router_phone_number_id'   => (int) $phone_id,
			'router_waba_id'           => (int) $account->id,
			'router_route_id'          => (int) $route_id,
			'meta_phone_number_id'     => $phone->phone_number_id ?? null,
			'whatsapp_phone_number_id' => $phone->phone_number_id ?? null,
			'meta_waba_id'             => $account->waba_id,
			'display_phone_number'     => $phone->display_phone_number ?? null,
			'route_id'                 => (int) $route_id,
			'business_id'              => $account->meta_business_id,
		];

		$headers = [ 'Content-Type' => 'application/json' ];
		$secret = RouterSettings::get_route_secret();
		if ( $secret ) {
			$headers['x-waba-router-secret'] = $secret;
		}

		$response = wp_remote_post(
			$registration->callback_url,
			[
				'headers' => $headers,
				'body'    => wp_json_encode( $payload ),
				'timeout' => RouterSettings::get_onboarding_callback_timeout(),
			]
		);

		$status_code = is_wp_error( $response ) ? null : (int) wp_remote_retrieve_response_code( $response );
		$notification_status = ( $status_code && $status_code < 400 ) ? 'delivered' : 'failed';
		$error = is_wp_error( $response ) ? $response->get_error_message() : ( $status_code >= 400 ? wp_remote_retrieve_body( $response ) : null );

		$wpdb->update(
			$registrations_table,
			[
				'status'                   => 'completed',
				'router_waba_id'           => (int) $account->id,
				'router_phone_number_id'   => (int) $phone_id,
				'route_id'                 => (int) $route_id,
				'phone_number_id'          => $phone->phone_number_id ?? null,
				'display_phone_number'     => $phone->display_phone_number ?? null,
				'verified_name'            => $phone->verified_name ?? null,
				'last_notification_status' => $status_code,
				'last_notification_error'  => $error ? substr( (string) $error, 0, 1000 ) : null,
				'notified_at'              => 'delivered' === $notification_status ? current_time( 'mysql', true ) : null,
				'updated_at'               => current_time( 'mysql', true ),
			],
			[ 'id' => (int) $registration->id ]
		);

		return [
			'notification_status'      => $notification_status,
			'notification_error'       => $error ? substr( (string) $error, 0, 1000 ) : null,
			'notification_status_code' => $status_code,
		];
	}

	private function try_subscribe_webhooks( $account, $access_token ) {
		if ( ! RouterSettings::should_auto_subscribe_webhooks() ) {
			return;
		}

		try {
			$result = ( new WebhookSubscriptionService() )->subscribeWaba( $account->waba_id, $access_token );
			global $wpdb;
			$wpdb->update(
				TableNameResolver::get_table_name( 'whatsapp_accounts' ),
				[
					'webhook_subscribed'          => ! empty( $result['success'] ) ? 1 : 0,
					'webhook_subscription_status' => ! empty( $result['success'] ) ? 'subscribed' : 'error',
					'webhook_last_error'          => empty( $result['success'] ) ? ( $result['error'] ?? 'subscribe_failed' ) : null,
					'updated_at'                  => current_time( 'mysql', true ),
				],
				[ 'id' => (int) $account->id ]
			);
		} catch ( \Throwable $e ) {
			// A assinatura do webhook nao deve derrubar a conclusao do cadastro.
		}
	}

	private function set_default_route( $phone_id, $route_id ) {
		global $wpdb;
		$table = TableNameResolver::get_table_name( 'whatsapp_phone_numbers' );
		$phone = $this->find_phone_by_id( $phone_id );
		if ( $phone && ! empty( $phone->default_route_id ) && (int) $phone->default_route_id !== (int) $route_id ) {
			$wpdb->update(
				TableNameResolver::getRoutesTable(),
				[
					'is_active'  => 0,
					'status'     => 'disabled',
					'updated_at' => current_time( 'mysql', true ),
				],
				[ 'id' => (int) $phone->default_route_id ]
			);
		}

		$wpdb->update(
			$table,
			[
				'default_route_id' => (int) $route_id,
				'updated_at'       => current_time( 'mysql', true ),
			],
			[ 'id' => (int) $phone_id ]
		);
	}

	private function get_registration_for_completion( $tenant_id, $meta_app_id, $attempt_id ) {
		global $wpdb;
		$table = TableNameResolver::getOnboardingRegistrationsTable();
		$registration = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $table WHERE tenant_id = %d AND meta_app_id = %d AND attempt_id = %s LIMIT 1",
				$tenant_id,
				$meta_app_id,
				$attempt_id
			)
		);

		if ( $registration ) {
			return $registration;
		}

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $table WHERE tenant_id = %d AND meta_app_id = %d ORDER BY created_at DESC LIMIT 1",
				$tenant_id,
				$meta_app_id
			)
		);
	}

	private function get_registration( $id ) {
		global $wpdb;
		$table = TableNameResolver::getOnboardingRegistrationsTable();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d LIMIT 1", (int) $id ) );
	}

	private function save_registration_token( $registration_id, $access_token, $status ) {
		global $wpdb;

		$wpdb->update(
			TableNameResolver::getOnboardingRegistrationsTable(),
			[
				'access_token_encrypted' => SecretVault::encrypt( $access_token ),
				'token_exchange_status'  => sanitize_text_field( $status ),
				'token_exchanged_at'     => current_time( 'mysql', true ),
				'updated_at'             => current_time( 'mysql', true ),
			],
			[ 'id' => (int) $registration_id ]
		);
	}

	private function save_registration_token_status( $registration_id, $status ) {
		global $wpdb;

		$wpdb->update(
			TableNameResolver::getOnboardingRegistrationsTable(),
			[
				'token_exchange_status' => sanitize_text_field( $status ),
				'updated_at'            => current_time( 'mysql', true ),
			],
			[ 'id' => (int) $registration_id ]
		);
	}

	private function find_registration_by_phone_variants( $tenant_id, $meta_app_id, array $variants ) {
		global $wpdb;
		$table = TableNameResolver::getOnboardingRegistrationsTable();
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM $table WHERE tenant_id = %d AND meta_app_id = %d AND status IN ('onboarding', 'completed') ORDER BY created_at DESC",
				$tenant_id,
				$meta_app_id
			)
		);

		foreach ( $rows as $row ) {
			$stored = json_decode( $row->phone_number_variants_json ?: '[]', true );
			if ( array_intersect( $variants, is_array( $stored ) ? $stored : [] ) ) {
				return $row;
			}
		}

		return null;
	}

	private function find_existing_phone_by_variants( $tenant_id, array $variants ) {
		global $wpdb;

		$table = TableNameResolver::get_table_name( 'whatsapp_phone_numbers' );
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM $table WHERE tenant_id = %d ORDER BY id DESC LIMIT 500",
				(int) $tenant_id
			)
		);

		foreach ( $rows as $row ) {
			$row_variants = array_merge(
				$this->phone_match_variants( $row->display_phone_number ?? '' ),
				$this->phone_match_variants( $row->phone_number_id ?? '' )
			);
			if ( array_intersect( $variants, $row_variants ) ) {
				return $row;
			}
		}

		return null;
	}

	private function normalize_phone( $value ) {
		$digits = preg_replace( '/\D+/', '', (string) $value );
		return $digits ?: null;
	}

	private function phone_match_variants( $value ) {
		$normalized = $this->normalize_phone( $value );
		if ( ! $normalized ) {
			return [];
		}

		$variants = [ $normalized ];
		if ( 0 === strpos( $normalized, '55' ) && 13 === strlen( $normalized ) ) {
			$variants[] = substr( $normalized, 0, 4 ) . substr( $normalized, 5 );
		} elseif ( 0 === strpos( $normalized, '55' ) && 12 === strlen( $normalized ) ) {
			$variants[] = substr( $normalized, 0, 4 ) . '9' . substr( $normalized, 4 );
		} elseif ( 11 === strlen( $normalized ) ) {
			$variants[] = substr( $normalized, 0, 2 ) . substr( $normalized, 3 );
		} elseif ( 10 === strlen( $normalized ) ) {
			$variants[] = substr( $normalized, 0, 2 ) . '9' . substr( $normalized, 2 );
		}

		return array_values( array_unique( $variants ) );
	}

	private function registration_variants( $phone_number, array $phone_number_variants ) {
		$variants = [];
		foreach ( array_merge( [ $phone_number ], $phone_number_variants ) as $value ) {
			$variants = array_merge( $variants, $this->phone_match_variants( $value ) );
		}

		$variants = array_values( array_unique( array_filter( $variants ) ) );
		sort( $variants );
		return $variants;
	}

	private function extract_state( $authorization_url ) {
		if ( ! $authorization_url ) {
			return null;
		}

		$query = wp_parse_url( $authorization_url, PHP_URL_QUERY );
		if ( ! $query ) {
			return null;
		}

		parse_str( $query, $params );
		return isset( $params['state'] ) ? sanitize_text_field( $params['state'] ) : null;
	}

	private function object_value( $object, $key, $default = null ) {
		return is_object( $object ) && property_exists( $object, $key ) ? $object->{$key} : $default;
	}

	private function tenant_exists( $tenant_id ) {
		global $wpdb;
		$table = TableNameResolver::get_table_name( 'tenants' );
		return (bool) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table WHERE id = %d LIMIT 1", (int) $tenant_id ) );
	}

	private function meta_app_exists( $meta_app_id ) {
		return (bool) $this->get_meta_app( $meta_app_id, false );
	}

	private function get_meta_app( $meta_app_id, $decrypt_secret = false ) {
		global $wpdb;
		$table = TableNameResolver::get_table_name( 'meta_apps' );
		$app = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d LIMIT 1", (int) $meta_app_id ) );
		if ( $app && $decrypt_secret && ! empty( $app->app_secret ) ) {
			$app->app_secret = SecretVault::decrypt( $app->app_secret );
		}
		return $app;
	}

	private function find_account_by_id( $id ) {
		global $wpdb;
		$table = TableNameResolver::get_table_name( 'whatsapp_accounts' );
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d LIMIT 1", (int) $id ) );
	}

	private function find_account_by_waba_id( $meta_waba_id ) {
		if ( ! $meta_waba_id ) {
			return null;
		}

		global $wpdb;
		$table = TableNameResolver::get_table_name( 'whatsapp_accounts' );
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE waba_id = %s LIMIT 1", (string) $meta_waba_id ) );
	}

	private function find_phone_by_id( $id ) {
		global $wpdb;
		$table = TableNameResolver::get_table_name( 'whatsapp_phone_numbers' );
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d LIMIT 1", (int) $id ) );
	}

	private function tenant_has_phone( $tenant_id ) {
		global $wpdb;
		$table = TableNameResolver::get_table_name( 'whatsapp_phone_numbers' );
		return (bool) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table WHERE tenant_id = %d LIMIT 1", (int) $tenant_id ) );
	}
}
