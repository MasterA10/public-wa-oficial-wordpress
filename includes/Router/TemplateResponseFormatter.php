<?php

namespace WAS\Router;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TemplateResponseFormatter {

	public function format( $template ) {
		$components = $this->decode_json_field( $template->components_json ?? '[]', [] );
		$variables = $this->extract_variables( $components );

		return [
			'id'                    => (int) $template->id,
			'tenant_id'             => (int) $template->tenant_id,
			'waba_id'               => (int) $template->whatsapp_account_id,
			'phone_number_id'       => ! empty( $template->router_phone_number_id ) ? (int) $template->router_phone_number_id : null,
			'meta_template_id'      => $template->meta_template_id ?? null,
			'name'                  => $template->name,
			'language'              => $template->language,
			'category'              => $template->category,
			'status'                => $template->status,
			'components_json'       => $components,
			'components'            => $components,
			'variables'             => $variables,
			'parameter_format'      => $this->parameter_format( $template, $variables ),
			'quality_score'         => $this->quality_score( $template ),
			'requested_callback_url'=> $template->requested_callback_url ?? null,
			'rejection_reason'      => $template->rejection_reason ?? null,
			'last_status_check_at'  => $template->last_status_check_at ?? null,
			'last_status_error'     => $template->last_status_error ?? null,
			'approved_at'           => $template->approved_at ?? null,
			'approved_notified_at'  => $template->approved_notified_at ?? null,
		];
	}

	public function extract_variables( $components ) {
		if ( is_array( $components ) && $this->is_assoc( $components ) ) {
			$components = [ $components ];
		}
		if ( ! is_array( $components ) ) {
			return [];
		}

		$variables = [];
		$seen = [];
		foreach ( $components as $component ) {
			if ( ! is_array( $component ) ) {
				continue;
			}
			$type = strtoupper( (string) ( $component['type'] ?? '' ) );
			$text = $component['text'] ?? null;
			if ( ! is_string( $text ) ) {
				continue;
			}
			if ( ! preg_match_all( '/{{\s*([A-Za-z0-9_]+)\s*}}/', $text, $matches, PREG_SET_ORDER ) ) {
				continue;
			}
			foreach ( $matches as $match ) {
				$name = $match[1];
				$key = $type . ':' . $name;
				if ( isset( $seen[ $key ] ) ) {
					continue;
				}
				$seen[ $key ] = true;
				$variables[] = [
					'name'           => $name,
					'format'         => ctype_digit( $name ) ? 'positional' : 'named',
					'component_type' => $type,
					'placeholder'    => $match[0],
					'example'        => $this->variable_example( $component, $name ),
				];
			}
		}

		return $variables;
	}

	private function parameter_format( $template, array $variables ) {
		foreach ( [ $template->friendly_payload ?? null, $template->meta_response_json ?? null, $template->meta_payload ?? null ] as $json ) {
			$payload = $this->decode_json_field( $json, null );
			if ( is_array( $payload ) && ! empty( $payload['parameter_format'] ) ) {
				return strtolower( (string) $payload['parameter_format'] );
			}
		}

		foreach ( $variables as $variable ) {
			if ( 'named' === ( $variable['format'] ?? '' ) ) {
				return 'named';
			}
		}

		return 'positional';
	}

	private function quality_score( $template ) {
		$payload = $this->decode_json_field( $template->meta_response_json ?? null, null );
		return is_array( $payload ) ? ( $payload['quality_score'] ?? null ) : null;
	}

	private function variable_example( array $component, $name ) {
		$example = $component['example'] ?? null;
		if ( ! is_array( $example ) ) {
			return null;
		}

		if ( ctype_digit( (string) $name ) ) {
			$position = (int) $name - 1;
			$body = $example['body_text'] ?? null;
			if ( is_array( $body ) && isset( $body[0] ) && is_array( $body[0] ) && array_key_exists( $position, $body[0] ) ) {
				return $body[0][ $position ];
			}
			$header = $example['header_text'] ?? null;
			if ( is_array( $header ) && array_key_exists( $position, $header ) ) {
				return $header[ $position ];
			}
		}

		foreach ( [ 'body_text_named_params', 'header_text_named_params' ] as $key ) {
			$items = $example[ $key ] ?? null;
			if ( ! is_array( $items ) ) {
				continue;
			}
			foreach ( $items as $item ) {
				if ( is_array( $item ) && (string) ( $item['param_name'] ?? '' ) === (string) $name ) {
					return $item['example'] ?? null;
				}
			}
		}

		return null;
	}

	private function decode_json_field( $value, $default ) {
		if ( is_array( $value ) ) {
			return $value;
		}
		if ( ! is_string( $value ) || '' === $value ) {
			return $default;
		}
		$decoded = json_decode( $value, true );
		return null === $decoded && JSON_ERROR_NONE !== json_last_error() ? $default : $decoded;
	}

	private function is_assoc( array $value ) {
		return array_keys( $value ) !== range( 0, count( $value ) - 1 );
	}
}
