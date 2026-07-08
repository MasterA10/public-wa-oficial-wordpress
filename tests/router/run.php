<?php

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/TestCase.php';

$test_files = glob( __DIR__ . '/*Test.php' );
sort( $test_files );

$assertions = 0;
$classes_before = get_declared_classes();

foreach ( $test_files as $file ) {
	require_once $file;
}

$classes_after = get_declared_classes();
$test_classes = array_filter(
	array_diff( $classes_after, $classes_before ),
	fn( $class ) => is_subclass_of( $class, WAS_Router_TestCase::class )
);

try {
	foreach ( $test_classes as $class ) {
		$test = new $class();
		$assertions += $test->run();
	}
	echo "\nOK (" . count( $test_classes ) . " classes, $assertions assertions)\n";
} catch ( Throwable $e ) {
	echo "\nFAIL: " . $e->getMessage() . "\n";
	echo $e->getTraceAsString() . "\n";
	exit( 1 );
}

