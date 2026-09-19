<?php

/**
 * PHPUnit bootstrap for the SignoffFlow WordPress integration suite.
 */

$cliapwo_tests_dir = getenv('WP_TESTS_DIR');

if (! is_string($cliapwo_tests_dir) || '' === $cliapwo_tests_dir) {
	$cliapwo_tests_dir = dirname(__DIR__) . '/.cliapwo-test-cache/wordpress-tests-lib';
}

$cliapwo_tests_dir = rtrim($cliapwo_tests_dir, '/\\');

if (! file_exists($cliapwo_tests_dir . '/includes/functions.php')) {
	fwrite(STDERR, "WordPress test library not found. Run tools/run-test-matrix.ps1 or set WP_TESTS_DIR.\n");
	exit(1);
}

define('WP_TESTS_PHPUNIT_POLYFILLS_PATH', dirname(__DIR__) . '/vendor/yoast/phpunit-polyfills');

require_once $cliapwo_tests_dir . '/includes/functions.php';

tests_add_filter(
	'muplugins_loaded',
	static function () {
		require dirname(__DIR__) . '/client-approval-workflow.php';
	}
);

require $cliapwo_tests_dir . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/class-cliapwo-test-case.php';
