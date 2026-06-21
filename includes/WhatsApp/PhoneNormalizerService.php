<?php
/**
 * PhoneNormalizerService class.
 *
 * @package WAS\WhatsApp
 */

namespace WAS\WhatsApp;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Service for normalizing and validating phone numbers.
 */
class PhoneNormalizerService {

	/**
	 * Normalize a raw phone number and generate candidates (especially for Brazil).
	 *
	 * @param string $raw The raw phone number.
	 * @return array
	 */
	public function normalize( $raw ) {
		$digits = preg_replace( '/\D+/', '', $raw );

		if ( '' === $digits ) {
			return [
				'success'    => false,
				'message'    => 'Telefone vazio.',
				'candidates' => [],
			];
		}

		// Remove leading zeros.
		$digits = ltrim( $digits, '0' );

		// Detect country. Currently assuming Brazil if it doesn't look like an international number.
		// If it's less than 12 digits and doesn't start with 55, we prefix with 55.
		if ( strlen( $digits ) <= 11 && ! str_starts_with( $digits, '55' ) ) {
			$digits = '55' . $digits;
		}

		$candidates = [ $digits ];

		// Handle Brazil specific "nono dígito" (9th digit) logic.
		if ( str_starts_with( $digits, '55' ) ) {
			$national = substr( $digits, 2 );

			if ( 10 === strlen( $national ) ) {
				// DDD + 8 digits: generate version with 9th digit.
				$ddd    = substr( $national, 0, 2 );
				$number = substr( $national, 2 );

				$candidates[] = '55' . $ddd . '9' . $number;
			} elseif ( 11 === strlen( $national ) ) {
				// DDD + 9 digits: generate version without the 9 as fallback.
				$ddd    = substr( $national, 0, 2 );
				$number = substr( $national, 2 );

				if ( str_starts_with( $number, '9' ) ) {
					$candidates[] = '55' . $ddd . substr( $number, 1 );
				}
			}
		}

		return [
			'success'    => true,
			'raw'        => $raw,
			'normalized' => $digits,
			'candidates' => array_values( array_unique( $candidates ) ),
		];
	}
}
