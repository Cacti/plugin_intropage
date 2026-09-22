<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
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

it('does nothing when the stored version already matches the plugin version', function () {
	plugin_intropage_check_config();

	expect($GLOBALS['__test_db_calls'])->toBeEmpty();
});
