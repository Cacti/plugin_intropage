<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * The poller selects panel definitions together with their data rows. A new
 * system panel has no data row yet, so its user_id arrives as NULL.
 */

require_once __DIR__ . '/../../includes/functions.php';

describe('intropage_poller_impersonate', function () {
	it('uses the owning user of a user panel row', function () {
		$_SESSION['sess_user_id'] = 0;

		expect(intropage_poller_impersonate('7'))->toBe(7);
		expect($_SESSION['sess_user_id'])->toBe(7);
	});

	it('runs a system panel as user 0 instead of the previous row user', function () {
		$_SESSION['sess_user_id'] = 7;

		expect(intropage_poller_impersonate(0))->toBe(0);
		expect($_SESSION['sess_user_id'])->toBe(0);
	});

	it('maps the NULL user of a definition without a data row to user 0', function () {
		$_SESSION['sess_user_id'] = 7;

		$user_id = intropage_poller_impersonate(null);

		expect($user_id)->toBe(0);
		expect($_SESSION['sess_user_id'])->toBe(0);
	});

	it('lets get_panel create the missing system panel data row', function () {
		$GLOBALS['__test_saves'] = [];
		$GLOBALS['__test_query'] = function ($kind, $sql, $params) {
			if (strpos($sql, 'FROM plugin_intropage_panel_definition') !== false) {
				return [
					'panel_id' => 'alert_host',
					'name'     => 'Host alerts',
					'level'    => 0,
					'priority' => 10,
					'alarm'    => 'grey',
					'refresh'  => 300,
					'trefresh' => 300,
					'height'   => 'normal',
				];
			}

			return $kind === 'assoc' ? [] : (($kind === 'row') ? [] : '');
		};

		$_SESSION['sess_user_id'] = 7;

		$panel = get_panel('alert_host', intropage_poller_impersonate(null));

		unset($GLOBALS['__test_query']);

		expect($panel['panel_id'])->toBe('alert_host');
		expect($GLOBALS['__test_saves'])->toHaveCount(1);
		expect($GLOBALS['__test_saves'][0]['row']['user_id'])->toBe(0);
	});
});
