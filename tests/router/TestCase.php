<?php

abstract class WAS_Router_TestCase {
	private $assertions = 0;

	public function run() {
		$methods = array_filter(
			get_class_methods( $this ),
			fn( $method ) => 0 === strpos( $method, 'test_' )
		);

		foreach ( $methods as $method ) {
			was_router_tests_reset();
			$this->set_up();
			$this->{$method}();
			$this->tear_down();
			echo '.';
		}

		return $this->assertions;
	}

	protected function set_up() {}

	protected function tear_down() {}

	protected function assert_true( $value, $message = 'Expected true.' ) {
		$this->assertions++;
		if ( ! $value ) {
			throw new RuntimeException( $message );
		}
	}

	protected function assert_false( $value, $message = 'Expected false.' ) {
		$this->assert_true( ! $value, $message );
	}

	protected function assert_null( $value, $message = 'Expected null.' ) {
		$this->assertions++;
		if ( null !== $value ) {
			throw new RuntimeException( $message . ' Got: ' . var_export( $value, true ) );
		}
	}

	protected function assert_not_null( $value, $message = 'Expected not null.' ) {
		$this->assertions++;
		if ( null === $value ) {
			throw new RuntimeException( $message );
		}
	}

	protected function assert_same( $expected, $actual, $message = '' ) {
		$this->assertions++;
		if ( $expected !== $actual ) {
			throw new RuntimeException(
				( $message ? $message . ' ' : '' ) .
				'Expected ' . var_export( $expected, true ) . ', got ' . var_export( $actual, true )
			);
		}
	}

	protected function assert_count( $expected, $actual, $message = '' ) {
		$this->assert_same( $expected, count( $actual ), $message );
	}

	protected function assert_array_has_key( $key, $array, $message = '' ) {
		$this->assertions++;
		if ( ! is_array( $array ) || ! array_key_exists( $key, $array ) ) {
			throw new RuntimeException( $message ?: "Expected array key $key." );
		}
	}
}

