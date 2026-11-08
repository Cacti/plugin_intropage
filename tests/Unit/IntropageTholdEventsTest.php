<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * The threshold event queries join the permission table once per permission
 * type. Without a user condition on each join, every user that holds a
 * matching permission adds a copy of the log row.
 */

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../panellib/thold.php';

beforeEach(function () {
	$GLOBALS['__test_db_calls']        = [];
	$GLOBALS['__test_enabled_plugins'] = ['thold' => true];
	$GLOBALS['__test_simple_perms']    = true;
	$GLOBALS['__test_events']          = [];

	$GLOBALS['__test_query'] = function ($kind, $sql, $params) {
		if (strpos($sql, 'FROM plugin_thold_log') !== false) {
			$GLOBALS['__test_events'][] = ['sql' => $sql, 'params' => $params];

			return [
				['description' => 'Threshold <b>ALERT</b>', 'time' => time(), 'status' => 1, 'user0' => null, 'user1' => null, 'user2' => null],
			];
		}

		return $kind === 'assoc' ? [] : '';
	};

	$this->restore = $GLOBALS['config']['base_path'];

	$GLOBALS['config']['base_path'] = sys_get_temp_dir() . '/intropage-thold-events-' . uniqid();
});

afterEach(function () {
	$GLOBALS['config']['base_path'] = $this->restore;

	unset($GLOBALS['__test_query'], $GLOBALS['__test_enabled_plugins'], $GLOBALS['__test_simple_perms']);
});

it('limits the permission joins of the event panel to the panel user', function () {
	$_SESSION['sess_user_id'] = 601;

	thold_event(['id' => 1, 'height' => 'normal', 'data' => '', 'alarm' => 'green'], 601);

	expect($GLOBALS['__test_events'])->toHaveCount(1);

	$sql = $GLOBALS['__test_events'][0]['sql'];

	expect($sql)->toContain('uap0.type=1 AND uap0.user_id = ?');
	expect($sql)->toContain('uap1.type=3 AND uap1.user_id = ?');
	expect($sql)->toContain('uap2.type=4 AND uap2.user_id = ?');
	expect($GLOBALS['__test_events'][0]['params'])->toBe([601, 601, 601]);
});

it('limits the permission joins of the event detail to the session user', function () {
	$_SESSION['sess_user_id'] = 602;

	$panel = thold_event_detail();

	expect($GLOBALS['__test_events'])->toHaveCount(1);
	expect($GLOBALS['__test_events'][0]['sql'])->toContain('uap2.type=4 AND uap2.user_id = ?');
	expect($GLOBALS['__test_events'][0]['params'])->toBe([602, 602, 602]);
	expect($panel['detail'])->toContain('Threshold &lt;b&gt;ALERT&lt;/b&gt;');
});
