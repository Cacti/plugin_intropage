<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * A favourite graph for a user who has no panels yet.
 */

require_once __DIR__ . '/../../includes/functions.php';

beforeEach(function () {
	$GLOBALS['__test_db_calls'] = [];
	$GLOBALS['__test_request']  = [];
	$GLOBALS['__test_queries']  = [];
});

afterEach(function () {
	unset($GLOBALS['__test_query'], $GLOBALS['__test_request'], $GLOBALS['__test_fetch_cell'], $GLOBALS['__test_fetch_assoc']);
});

describe('adding a favourite graph', function () {
	beforeEach(function () {
		$_SESSION['sess_user_id']          = 701;
		$_SESSION['sess_current_timespan'] = 3;
		$_SESSION['dashboard_id']          = 1;

		$GLOBALS['__test_request'] = ['intropage_action' => 'favgraph', 'graph_id' => '5'];
		$GLOBALS['__test_query']   = function ($kind, $sql, $params) {
			$GLOBALS['__test_queries'][] = $sql;

			// a user without panel rows: MAX(priority) is NULL, so the query only works with COALESCE
			if (strpos($sql, 'MAX(priority)') !== false) {
				return strpos($sql, 'COALESCE(MAX(priority), 0)') !== false ? '1' : null;
			}

			return $kind === 'assoc' ? [] : '0';
		};
	});

	it('gives the first favourite of a user without panels priority 1', function () {
		intropage_actions();

		$priority_sql = implode("\n", array_filter($GLOBALS['__test_queries'], static fn ($sql) => strpos($sql, 'MAX(priority)') !== false));

		expect($priority_sql)->toContain('COALESCE(MAX(priority), 0) + 1');

		$inserts = array_values(array_filter($GLOBALS['__test_db_calls'], static fn ($call) => strpos($call['sql'], 'INSERT INTO plugin_intropage_panel_data') !== false));

		expect($inserts)->toHaveCount(1);
		expect($inserts[0]['params'])->toBe([701, '5', 3, '1']);
	});

	it('links the new favourite to the current dashboard', function () {
		intropage_actions();

		$links = array_values(array_filter($GLOBALS['__test_db_calls'], static fn ($call) => strpos($call['sql'], 'INSERT INTO plugin_intropage_panel_dashboard') !== false));

		expect($links)->toHaveCount(1);
		expect($links[0]['params'][1])->toBe(701);
		expect($links[0]['params'][2])->toBe(1);
	});
});
