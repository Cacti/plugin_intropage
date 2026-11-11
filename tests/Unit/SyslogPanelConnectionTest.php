<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 +-------------------------------------------------------------------------+
*/

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../panellib/syslog.php';

/*
 * Flag-driven Syslog connection stub. syslog_connect() can only be declared
 * once per process, so its behaviour is controlled through a global flag that
 * each test sets. It mirrors the real helper: it opens the global $syslog_cnn
 * connection when the flag is set and leaves it closed otherwise.
 */
if (!function_exists('syslog_connect')) {
	function syslog_connect(): bool {
		global $syslog_cnn;

		if (!empty($GLOBALS['__test_syslog_connect_ok'])) {
			$syslog_cnn = new stdClass();

			return true;
		}

		$syslog_cnn = null;

		return false;
	}
}

if (!function_exists('syslog_db_fetch_cell')) {
	function syslog_db_fetch_cell($sql) {
		return 0;
	}
}

if (!function_exists('syslog_db_fetch_cell_prepared')) {
	function syslog_db_fetch_cell_prepared($sql, $params = array()) {
		return 0;
	}
}

if (!function_exists('syslog_db_fetch_assoc_prepared')) {
	function syslog_db_fetch_assoc_prepared($sql, $params = array()) {
		return array();
	}
}

beforeEach(function () {
	$GLOBALS['__intropage_syslog_enabled_restore'] = $GLOBALS['__test_enabled_plugins'] ?? array();
	$GLOBALS['__intropage_syslog_log_restore']     = $GLOBALS['__test_cacti_log'] ?? array();
	$GLOBALS['__test_enabled_plugins']             = array('syslog' => true);
	$GLOBALS['__test_cacti_log']                   = array();
	$GLOBALS['syslog_cnn']                         = null;
});

afterEach(function () {
	$GLOBALS['__test_enabled_plugins'] = $GLOBALS['__intropage_syslog_enabled_restore'];
	$GLOBALS['__test_cacti_log']       = $GLOBALS['__intropage_syslog_log_restore'];
	$GLOBALS['syslog_cnn']             = null;
	unset($GLOBALS['__test_syslog_connect_ok'], $GLOBALS['__intropage_syslog_enabled_restore'], $GLOBALS['__intropage_syslog_log_restore']);
});

it('reports the syslog database ready when the connection opens', function () {
	$GLOBALS['__test_syslog_connect_ok'] = true;

	expect(intropage_syslog_connect())->toBeTrue()
		->and($GLOBALS['syslog_cnn'])->not->toBeNull()
		->and($GLOBALS['__test_cacti_log'])->toBeEmpty();
});

it('logs and reports failure when the syslog database cannot be opened', function () {
	$GLOBALS['__test_syslog_connect_ok'] = false;

	expect(intropage_syslog_connect())->toBeFalse()
		->and(implode("\n", $GLOBALS['__test_cacti_log']))->toContain('could not open the Syslog database connection');
});

it('skips plugin_syslog_levels_trend collection when the database is unavailable', function () {
	$GLOBALS['__test_syslog_connect_ok'] = false;

	plugin_syslog_levels_trend();

	expect(implode("\n", $GLOBALS['__test_cacti_log']))->toContain('could not open the Syslog database connection');
});

it('runs a syslog panel update without errors when the database is available', function () {
	$GLOBALS['__test_syslog_connect_ok'] = true;

	$panel = array('id' => 1, 'height' => 'normal', 'refresh' => 300);
	plugin_syslog_devices($panel, 1, 60);

	expect($GLOBALS['__test_cacti_log'])->toBeEmpty();
});
