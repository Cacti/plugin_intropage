<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * The poller must not run panel code for users that no longer exist, and the
 * rows they left behind are deleted in bounded steps.
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

describe('intropage_poller_due_panels', function () {
	it('selects due rows and leaves out the rows of users that no longer exist', function () {
		$GLOBALS['__test_fetch_assoc'] = [['panel_id' => 'alert_host', 'id' => 4, 'user_id' => 12]];

		$rows = intropage_poller_due_panels();

		$call = $GLOBALS['__test_db_calls'][0];

		expect($rows)->toBe([['panel_id' => 'alert_host', 'id' => 4, 'user_id' => 12]]);
		expect($call['sql'])->toContain('LEFT JOIN user_auth AS ua');
		expect($call['sql'])->toContain('ON ua.id = ud.user_id');
		expect($call['sql'])->toContain('(ud.user_id IS NULL OR ud.user_id = 0 OR ua.id IS NOT NULL)');
	});

	it('keeps the original due condition together as one group', function () {
		intropage_poller_due_panels();

		$sql = preg_replace('/\s+/', ' ', $GLOBALS['__test_db_calls'][0]['sql']);

		expect($sql)->toContain('WHERE (UNIX_TIMESTAMP(last_update) < UNIX_TIMESTAMP() - refresh_interval OR (last_update IS NULL AND level = 0)) AND (');
	});
});

describe('intropage_purge_orphan_rows', function () {
	it('deletes a bounded number of rows of users that do not exist', function () {
		$GLOBALS['__test_fetch_cell'] = '4';

		intropage_purge_orphan_rows();

		$deletes = array_values(array_filter($GLOBALS['__test_db_calls'], static fn ($call) => strpos($call['sql'], 'DELETE FROM') !== false));

		expect($deletes)->toHaveCount(2);

		foreach ($deletes as $delete) {
			$sql = preg_replace('/\s+/', ' ', $delete['sql']);

			expect($sql)->toContain('WHERE user_id > 0 AND user_id NOT IN (SELECT id FROM user_auth) LIMIT 500');
		}

		expect($deletes[0]['sql'])->toContain('plugin_intropage_panel_dashboard');
		expect($deletes[1]['sql'])->toContain('plugin_intropage_panel_data');
	});

	it('uses the limit it is given, and at least one row', function (int $limit, string $expected) {
		$GLOBALS['__test_fetch_cell'] = '4';

		intropage_purge_orphan_rows($limit);

		expect($GLOBALS['__test_db_calls'][1]['sql'])->toContain('LIMIT ' . $expected);
	})->with([
		'ten'      => [10, '10'],
		'zero'     => [0, '1'],
		'negative' => [-5, '1'],
	]);

	it('deletes nothing when the user table is empty or cannot be read', function (string $count) {
		$GLOBALS['__test_fetch_cell'] = $count;

		intropage_purge_orphan_rows();

		$deletes = array_filter($GLOBALS['__test_db_calls'], static fn ($call) => strpos($call['sql'], 'DELETE FROM') !== false);

		expect($deletes)->toBe([]);
	})->with(['zero' => ['0'], 'unreadable' => ['']]);
});
