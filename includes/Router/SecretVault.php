<?php

namespace WAS\Router;

use WAS\Meta\TokenVault;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SecretVault {

	public static function encrypt( $value ) {
		if ( null === $value || '' === $value ) {
			return null;
		}

		try {
			return TokenVault::encrypt( (string) $value );
		} catch ( \Throwable $e ) {
			return (string) $value;
		}
	}

	public static function decrypt( $value ) {
		if ( null === $value || '' === $value ) {
			return null;
		}

		try {
			return TokenVault::decrypt( (string) $value );
		} catch ( \Throwable $e ) {
			return (string) $value;
		}
	}
}
