<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Saving the dashboard settings form renames existing dashboards. It must
 * not recreate the row, which would reset the shared flag, and must not
 * create dashboards the user never had.
 */

require_once __DIR__ . '/../../includes/functions.php';

beforeEach(function () {
	$GLOBALS['__test_db_calls'] = [];
	$GLOBALS['__test_request']  = [];
	$GLOBALS['__test_realms']   = [];

	$_SESSION['sess_user_id'] = 301;
	$_POST                    = [];
});

afterEach(function () {
	$_POST = [];
});

it('renames an existing dashboard without replacing its row', function () {
	$_POST = ['name_2' => 'Core routers'];

	intropage_action_settings();

	$sql = implode("\n", array_column($GLOBALS['__test_db_calls'], 'sql'));

	expect($sql)->toContain('UPDATE plugin_intropage_dashboard');
	expect($sql)->not->toContain('REPLACE');
	expect($GLOBALS['__test_db_calls'][0]['params'])->toBe(['Core routers', 301, 2]);
});

it('skips names whose dashboard id is not a plain integer', function () {
	$_POST = ['name_x' => 'bad', 'name_03' => 'bad', 'name_1e3' => 'bad'];

	intropage_action_settings();

	expect($GLOBALS['__test_db_calls'])->toBe([]);
});

it('skips a name that was posted as an array', function () {
	$_POST = ['name_2' => ['a', 'b']];

	intropage_action_settings();

	expect($GLOBALS['__test_db_calls'])->toBe([]);
});
