<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * tests/ is part of the plugin directory on a git-clone install, so a web
 * server can be asked for any file in it. The end-to-end test file holds
 * database and login helpers and must produce no output and take no action
 * when it is executed directly instead of by the test runner.
 */

it('does nothing when the end-to-end test file is executed directly', function () {
	$file = realpath(__DIR__ . '/../Integration/IntropageHttpTest.php');

	expect($file)->not->toBeFalse();

	$output = [];
	$status = 1;

	exec('INTROPAGE_E2E_URL=http://127.0.0.1:9 INTROPAGE_E2E_DSN=sqlite::memory: ' . escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($file) . ' 2>&1', $output, $status);

	expect($output)->toBe([]);
	expect($status)->toBe(0);
});

it('guards the end-to-end test file before it declares anything', function () {
	$source = file_get_contents(__DIR__ . '/../Integration/IntropageHttpTest.php');

	$guard = strpos($source, "function_exists('beforeAll')");
	$first = strpos($source, 'const INTROPAGE_E2E_USER');

	expect($guard)->not->toBeFalse();
	expect($guard)->toBeLessThan($first);
	expect($source)->not->toContain('$_GET');
	expect($source)->not->toContain('$_POST');
	expect($source)->not->toContain('$_REQUEST');
	expect($source)->not->toContain('$_COOKIE');
	expect($source)->not->toContain('$_SERVER');
});
