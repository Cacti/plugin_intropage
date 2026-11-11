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
 * once per process, so its behaviour is controlled through globals that each
 * test sets. It mirrors the real helper: the connection object can open even
 * when the schema-readiness check fails, so $syslog_cnn may be populated while
 * the function still returns false. __test_syslog_connect_ok drives the bool
 * result; __test_syslog_cnn_open forces a populated connection regardless.
 */
if (!function_exists('syslog_connect')) {
	function syslog_connect(): bool {
		global $syslog_cnn;

		$ready = !empty($GLOBALS['__test_syslog_connect_ok']);

		$syslog_cnn = ($ready || !empty($GLOBALS['__test_syslog_cnn_open'])) ? new stdClass() : null;

		return $ready;
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
		if (!empty($GLOBALS['__test_syslog_query_false'])) {
			return false;
		}

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
	unset($GLOBALS['__test_syslog_connect_ok'], $GLOBALS['__test_syslog_cnn_open'], $GLOBALS['__test_syslog_query_false'], $GLOBALS['__intropage_syslog_enabled_restore'], $GLOBALS['__intropage_syslog_log_restore']);
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

it('reports failure when the connection is populated but syslog_connect() returns false', function () {
	$GLOBALS['__test_syslog_connect_ok'] = false;
	$GLOBALS['__test_syslog_cnn_open']   = true;

	expect(intropage_syslog_connect())->toBeFalse()
		->and($GLOBALS['syslog_cnn'])->not->toBeNull()
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

it('tolerates a false levels query without warnings or corrupting the priority-0 count', function () {
	$GLOBALS['__test_syslog_connect_ok']  = true;
	$GLOBALS['__test_syslog_query_false'] = true;

	$errors = array();
	set_error_handler(function ($severity, $message) use (&$errors) {
		$errors[] = $message;

		return true;
	});

	try {
		plugin_syslog_levels_trend();
	} finally {
		restore_error_handler();
	}

	expect($errors)->toBeEmpty()
		->and($GLOBALS['__test_cacti_log'])->toBeEmpty();
});

it('skips plugin_syslog_trend collection when the database is unavailable', function () {
	$GLOBALS['__test_syslog_connect_ok'] = false;

	plugin_syslog_trend();

	expect(implode("\n", $GLOBALS['__test_cacti_log']))->toContain('could not open the Syslog database connection');
});

it('shows a waiting message in the devices panel when the database is unavailable', function () {
	$GLOBALS['__test_syslog_connect_ok'] = false;
	$GLOBALS['__test_db_calls']          = array();

	$panel = array('id' => 1, 'height' => 'normal', 'refresh' => 300);
	plugin_syslog_devices($panel, 1, 60);

	expect(implode("\n", $GLOBALS['__test_cacti_log']))->toContain('could not open the Syslog database connection');

	$updates = array_values(array_filter($GLOBALS['__test_db_calls'], static fn ($c) => strpos($c['sql'], 'UPDATE plugin_intropage_panel_data') !== false));
	expect($updates)->not->toBeEmpty()
		->and($updates[0]['params'][0])->toContain('Waiting for data');
});

it('returns an empty devices detail when the database is unavailable', function () {
	$GLOBALS['__test_syslog_connect_ok'] = false;

	$detail = plugin_syslog_devices_detail();

	expect($detail)->toBeArray()
		->and($detail['detail'])->toBe('')
		->and(implode("\n", $GLOBALS['__test_cacti_log']))->toContain('could not open the Syslog database connection');
});
