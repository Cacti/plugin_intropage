<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

require_once __DIR__ . '/../../include/functions.php';
require_once __DIR__ . '/../../panellib/busiest.php';

describe('busiest_cpu device scope', function () {
	beforeEach(function () {
		$GLOBALS['__test_db_calls']    = [];
		$GLOBALS['__test_config']      = ['dsstats_enable' => 'on'];
		$GLOBALS['__test_fetch_row']   = ['id' => 5, 'name' => 'CPU'];
		$GLOBALS['__test_fetch_assoc'] = [];
	});

	afterEach(function () {
		unset(
			$GLOBALS['__test_simple_perms'],
			$GLOBALS['__test_allowed_devices'],
			$GLOBALS['__test_fetch_row'],
			$GLOBALS['__test_fetch_assoc'],
			$GLOBALS['__test_db_calls']
		);
	});

	function captured_select_sql(): string {
		$rows = array_filter($GLOBALS['__test_db_calls'], fn ($c) => $c['fn'] === 'db_fetch_assoc');

		return implode("\n", array_column($rows, 'sql'));
	}

	it('filters the query to the allowed hosts for a restricted user', function () {
		$GLOBALS['__test_simple_perms']    = false;
		$GLOBALS['__test_allowed_devices'] = [['id' => 3], ['id' => 7], ['id' => 9]];

		$panel = ['height' => 'normal', 'id' => 1, 'data' => '', 'alarm' => 'grey'];
		busiest_cpu($panel, 2);

		expect(captured_select_sql())->toContain('AND dl.host_id IN (3,7,9)');
	});

	it('applies no host filter for a simple-permission user', function () {
		$GLOBALS['__test_simple_perms'] = true;

		$panel = ['height' => 'normal', 'id' => 1, 'data' => '', 'alarm' => 'grey'];
		busiest_cpu($panel, 1);

		expect(captured_select_sql())->not->toContain('dl.host_id IN');
	});
});
