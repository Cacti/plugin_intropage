<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Runs the plugin's real upgrade code against the Cacti database for the
 * end-to-end tests, from a throwaway plugin tree.
 *
 * The upgrade prunes files below base_path, which would remove the tests/
 * directory of the real plugin, so the test points base_path at a temporary
 * tree whose includes/ and panellib/ are links to the real ones. This file is
 * inert unless it is run by the CLI with both environment variables set by
 * the test process, and it refuses any tree that is not such a temporary tree.
 *
 *   INTROPAGE_E2E_CACTI         Cacti root directory
 *   INTROPAGE_E2E_UPGRADE_BASE  the throwaway tree, below the system temp dir
 */

if (PHP_SAPI !== 'cli') {
	http_response_code(404);

	exit;
}

$intropage_cacti = getenv('INTROPAGE_E2E_CACTI');
$intropage_tree  = getenv('INTROPAGE_E2E_UPGRADE_BASE');

if ($intropage_cacti === false || $intropage_tree === false || !is_file($intropage_cacti . '/include/cli_check.php')) {
	exit(0);
}

$intropage_tmp  = realpath(sys_get_temp_dir());
$intropage_real = realpath($intropage_tree);
$intropage_lib  = realpath($intropage_tree . '/plugins/intropage/includes');

if (
	$intropage_real === false || $intropage_tmp === false || $intropage_lib === false
	|| strpos($intropage_real, $intropage_tmp . DIRECTORY_SEPARATOR) !== 0
	|| $intropage_lib !== realpath(dirname(__DIR__, 2) . '/includes')
	|| !is_file($intropage_tree . '/plugins/intropage/manifest.json')
) {
	fwrite(STDERR, "refusing to run: not a throwaway plugin tree\n");

	exit(1);
}

require $intropage_cacti . '/include/cli_check.php';

global $config;

$config['base_path'] = $intropage_real;

require_once $intropage_real . '/plugins/intropage/includes/database.php';

intropage_upgrade_database();

print "upgrade finished\n";
