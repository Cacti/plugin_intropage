<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Optional auto_prepend_file for the end-to-end run: records which plugin
 * lines and branches the real entry points execute and writes one JSON file
 * per request or process, for tests/bin/e2e-coverage-report.php.
 *
 * tests/ ships inside the plugin directory, so this file is inert unless the
 * test process that started PHP exports INTROPAGE_COVERAGE_DIR and PHP runs
 * through the CLI or the built-in server with Xdebug coverage enabled. It
 * reads no request data. Everything it writes goes below that directory, in
 * a sub-directory named by a validated token from the file "current" there.
 */

// Requested directly (web server or CLI): do nothing.
if (realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
	if (PHP_SAPI !== 'cli') {
		http_response_code(404);
	}

	exit;
}

$intropage_cov_dir = getenv('INTROPAGE_COVERAGE_DIR');

if (
	($intropage_cov_dir === false || $intropage_cov_dir === '' || !is_dir($intropage_cov_dir) || !is_writable($intropage_cov_dir))
	|| !in_array(PHP_SAPI, ['cli', 'cli-server'], true)
	|| !function_exists('xdebug_start_code_coverage')
) {
	return;
}

// Only the plugin is of interest; without a filter the branch data of all of Cacti exhausts memory.
// INTROPAGE_COVERAGE_FILES may narrow it to a comma separated list of plugin files.
$intropage_cov_root  = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR;
$intropage_cov_paths = [];

foreach (explode(',', (string) getenv('INTROPAGE_COVERAGE_FILES')) as $intropage_cov_file) {
	if (preg_match('#^[A-Za-z0-9_./-]+$#', $intropage_cov_file) === 1 && strpos($intropage_cov_file, '..') === false && is_file($intropage_cov_root . $intropage_cov_file)) {
		$intropage_cov_paths[] = $intropage_cov_root . $intropage_cov_file;
	}
}

xdebug_set_filter(XDEBUG_FILTER_CODE_COVERAGE, XDEBUG_PATH_INCLUDE, $intropage_cov_paths === [] ? [$intropage_cov_root] : $intropage_cov_paths);

xdebug_start_code_coverage(XDEBUG_CC_UNUSED | XDEBUG_CC_DEAD_CODE | XDEBUG_CC_BRANCH_CHECK);

register_shutdown_function(static function () use ($intropage_cov_dir): void {
	// The branch data of a few large files is bigger than Cacti's own memory limit
	ini_set('memory_limit', '-1');

	$data = xdebug_get_code_coverage();

	xdebug_stop_code_coverage(true);

	$token   = 'untagged';
	$current = @file_get_contents($intropage_cov_dir . '/current');

	if ($current !== false && preg_match('/^[a-z0-9-]{1,80}$/', trim($current)) === 1) {
		$token = trim($current);
	}

	$marker = DIRECTORY_SEPARATOR . 'plugins' . DIRECTORY_SEPARATOR . 'intropage' . DIRECTORY_SEPARATOR;
	$tests  = DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR;
	$keep   = [];

	foreach ($data as $file => $info) {
		if (strpos($file, $marker) === false || strpos($file, $tests) !== false) {
			continue;
		}

		// Keep the lines and the first line and hit count of each branch; the path data is far larger
		$keep[$file] = ['lines' => $info['lines'] ?? [], 'functions' => []];

		foreach ($info['functions'] ?? [] as $function => $detail) {
			foreach ($detail['branches'] ?? [] as $id => $branch) {
				$keep[$file]['functions'][$function]['branches'][$id] = ['line_start' => $branch['line_start'] ?? 0, 'hit' => $branch['hit'] ?? 0];
			}
		}
	}

	if ($keep === []) {
		return;
	}

	$target = $intropage_cov_dir . '/' . $token;

	if (!is_dir($target)) {
		@mkdir($target, 0775, true);
	}

	@file_put_contents($target . '/' . getmypid() . '-' . bin2hex(random_bytes(6)) . '.json', json_encode($keep));
});
