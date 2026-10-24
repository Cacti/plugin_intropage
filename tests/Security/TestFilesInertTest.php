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
	$first = strpos($source, 'const INTROPAGE_E2E_PASS');

	expect($guard)->not->toBeFalse();
	expect($guard)->toBeLessThan($first);
	expect($source)->not->toContain('$_GET');
	expect($source)->not->toContain('$_POST');
	expect($source)->not->toContain('$_REQUEST');
	expect($source)->not->toContain('$_COOKIE');
	expect($source)->not->toContain('$_SERVER');
});

it('does nothing when the coverage prepend file is executed directly', function () {
	$file = realpath(__DIR__ . '/../Integration/coverage-prepend.php');
	$dir  = sys_get_temp_dir() . '/intropage-inert-' . bin2hex(random_bytes(6));

	mkdir($dir, 0700);

	$output = [];
	$status = 1;

	exec('INTROPAGE_COVERAGE_DIR=' . escapeshellarg($dir) . ' ' . escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($file) . ' 2>&1', $output, $status);

	expect($output)->toBe([]);
	expect($status)->toBe(0);
	expect(scandir($dir))->toBe(['.', '..']);
});

/**
 * Writes a script that loads one plugin file and prints a marker.
 */
function intropage_inert_script(string $dir, string $plugin): string {
	$script = $dir . '/run.php';

	file_put_contents($script, '<?php require ' . var_export($plugin . '/includes/index.php', true) . "; echo 'ran';\n");

	return $script;
}

it('writes nothing from the coverage prepend file without the environment', function () {
	$file   = realpath(__DIR__ . '/../Integration/coverage-prepend.php');
	$dir    = sys_get_temp_dir() . '/intropage-inert-' . bin2hex(random_bytes(6));
	$output = [];

	mkdir($dir, 0700);

	exec(escapeshellarg(PHP_BINARY) . ' -d auto_prepend_file=' . escapeshellarg($file) . ' ' . escapeshellarg(intropage_inert_script($dir, dirname($file, 3))) . ' 2>&1', $output);

	expect($output)->toBe(['ran']);
	expect(scandir($dir))->toBe(['.', '..', 'run.php']);
});

it('keeps the coverage dump inside its directory whatever the token file says', function () {
	if (!function_exists('xdebug_start_code_coverage')) {
		$this->markTestSkipped('Xdebug is not loaded.');
	}

	$file = realpath(__DIR__ . '/../Integration/coverage-prepend.php');
	$root = sys_get_temp_dir() . '/intropage-inert-' . bin2hex(random_bytes(6));
	$dir  = $root . '/dumps';

	mkdir($dir, 0700, true);
	file_put_contents($dir . '/current', '../escape');

	$output = [];

	exec('INTROPAGE_COVERAGE_DIR=' . escapeshellarg($dir) . ' ' . escapeshellarg(PHP_BINARY) . ' -d xdebug.mode=coverage -d auto_prepend_file=' . escapeshellarg($file) . ' ' . escapeshellarg(intropage_inert_script($root, dirname($file, 3))) . ' 2>&1', $output);

	expect($output)->toBe(['ran']);
	expect(is_dir($root . '/escape'))->toBeFalse();
	expect(count(glob($dir . '/untagged/*.json')))->toBe(1);
});

it('does nothing when the upgrade runner is executed without the test environment', function () {
	$output = [];
	$status = 1;

	exec('env -u INTROPAGE_E2E_CACTI -u INTROPAGE_E2E_UPGRADE_BASE ' . escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(realpath(__DIR__ . '/../Integration/upgrade-runner.php')) . ' 2>&1', $output, $status);

	expect($output)->toBe([]);
	expect($status)->toBe(0);
});
