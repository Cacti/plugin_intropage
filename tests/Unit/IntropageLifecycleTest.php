<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Unit coverage for the plugin lifecycle contract functions in setup.php:
 * plugin_intropage_uninstall(), plugin_intropage_check_config(), and
 * plugin_intropage_upgrade() (both delegate to intropage_check_upgrade()
 * -> intropage_upgrade_database()).
 *
 * intropage_upgrade_database()'s migration branches are extensive; the
 * stored version is set to match the current plugin version here so
 * that already-current path is skipped, keeping these tests focused on
 * the wrapper functions' own contract rather than re-testing every
 * historical migration step.
 */

beforeAll(function () {
	require_once __DIR__ . '/../../setup.php';
});

beforeEach(function () {
	$GLOBALS['__test_db_calls']  = array();
	$GLOBALS['__test_fetch_cell'] = plugin_intropage_version()['version'];
});

it('drops every table it owns on uninstall', function () {
	plugin_intropage_uninstall();

	$drops = array_filter($GLOBALS['__test_db_calls'], function ($call) {
		return $call['fn'] === 'db_execute' && stripos($call['sql'], 'DROP TABLE') !== false;
	});

	expect(count($drops))->toBe(7);
});

it('reports the config as always valid', function () {
	expect(plugin_intropage_check_config())->toBeTrue();
});

it('reports that no upgrade is pending', function () {
	expect(plugin_intropage_upgrade())->toBeFalse();
});

it('repoints stale include/ hooks even when the stored version already matches', function () {
	plugin_intropage_check_config();

	// On an up-to-date install the only work is the idempotent include/ ->
	// includes/ plugin_hooks repoint; no schema migration or version write runs.
	expect($GLOBALS['__test_db_calls'])->toHaveCount(1);

	$call = $GLOBALS['__test_db_calls'][0];

	expect($call['fn'])->toBe('db_execute')
		->and($call['sql'])->toContain('UPDATE plugin_hooks')
		->and($call['sql'])->toContain("REPLACE(file, 'include/', 'includes/')")
		->and($call['sql'])->toContain("file LIKE 'include/%'");
});
