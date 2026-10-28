<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Panels an administrator has since withdrawn are removed from the user's
 * dashboard, together with the user's own data row for the panel.
 */

require_once __DIR__ . '/../../includes/functions.php';

beforeEach(function () {
	$GLOBALS['__test_db_calls'] = [];

	$GLOBALS['__test_query'] = function ($kind, $sql, $params) {
		if (strpos($sql, 'FROM plugin_intropage_user_auth') !== false) {
			return json_encode(['alert_host' => 'on']);
		}

		if (strpos($sql, 'INNER JOIN plugin_intropage_panel_dashboard') !== false) {
			return [
				['panel_name' => 'alert_host', 'id' => '5'],
				['panel_name' => 'analyse_db', 'id' => '6'],
			];
		}

		return $kind === 'assoc' ? [] : '';
	};
});

afterEach(function () {
	unset($GLOBALS['__test_query']);
});

it('removes only the panels the user may no longer see', function () {
	expect(intropage_remove_prohibited_panels(501, 1))->toBe(1);

	$deletes = array_values(array_filter($GLOBALS['__test_db_calls'], function ($call) {
		return strpos($call['sql'], 'DELETE FROM') !== false;
	}));

	expect($deletes)->toHaveCount(2);
	expect($deletes[0]['sql'])->toContain('plugin_intropage_panel_dashboard');
	expect($deletes[0]['params'])->toBe([501, 1, '6']);
});

it('deletes the user data row by its row id', function () {
	intropage_remove_prohibited_panels(502, 1);

	$deletes = array_values(array_filter($GLOBALS['__test_db_calls'], function ($call) {
		return strpos($call['sql'], 'DELETE FROM plugin_intropage_panel_data') !== false;
	}));

	expect($deletes)->toHaveCount(1);
	expect($deletes[0]['sql'])->toContain('AND id = ?');
	expect($deletes[0]['sql'])->not->toContain('AND panel_id = ?');
	expect($deletes[0]['params'])->toBe([502, '6']);
});

it('removes nothing when every panel is permitted', function () {
	$GLOBALS['__test_query'] = function ($kind, $sql, $params) {
		if (strpos($sql, 'FROM plugin_intropage_user_auth') !== false) {
			return json_encode(['alert_host' => 'on']);
		}

		if (strpos($sql, 'INNER JOIN plugin_intropage_panel_dashboard') !== false) {
			return [['panel_name' => 'alert_host', 'id' => '5']];
		}

		return $kind === 'assoc' ? [] : '';
	};

	expect(intropage_remove_prohibited_panels(503, 1))->toBe(0);
	expect($GLOBALS['__test_db_calls'])->toBe([]);
});
