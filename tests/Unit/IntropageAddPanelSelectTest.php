<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * The add-panel dropdown (intropage_addpanel_select()) must label each option
 * with the panel's source prefix ("Core - " / "<Plugin> - "), the same way the
 * Panel Library list does, and order the options by that prefixed label so the
 * menu reads as sorted and grouped by source.
 *
 * The plugin's db_* helpers are the unit-bootstrap stubs; reads are answered by
 * the router built in intropage_addpanel_fixture(). User ids are unique per
 * test because is_panel_allowed() caches the resolved permissions per user.
 */

require_once __DIR__ . '/../../includes/functions.php';

/**
 * Scripts the queries intropage_addpanel_select() (and the helpers it calls)
 * make for one test.
 *
 * @param array $panels  Rows the panel-definition query returns (each
 *                       ['panel_id' => ..., 'name' => ...]).
 * @param array $uniqids panel_id => per-user panel-data id ('' when none).
 * @param bool  $sourced Whether a registration row maps each panel to a source.
 * @param array $perms   panel_id => 'on' permissions stored for the user.
 */
function intropage_addpanel_fixture(array $panels, array $uniqids, bool $sourced, array $perms): void {
	$GLOBALS['__test_enabled_plugins'] = ['intropage' => true];
	$GLOBALS['__test_tables']          = ['plugin_intropage_panel_registration' => $sourced];

	$GLOBALS['__test_query'] = function ($kind, $sql, $params) use ($panels, $uniqids, $sourced, $perms) {
		if ($kind === 'assoc') {
			if (strpos($sql, 'DISTINCT') !== false && strpos($sql, 'plugin_intropage_panel_definition') !== false) {
				return $panels;
			}

			if ($sourced && strpos($sql, 'SELECT panel_id, plugin') !== false
				&& strpos($sql, 'plugin_intropage_panel_registration') !== false) {
				return array_map(static fn ($p) => ['panel_id' => $p['panel_id'], 'plugin' => 'intropage'], $panels);
			}

			return [];
		}

		if ($kind === 'cell') {
			if (strpos($sql, 'FROM plugin_intropage_panel_data') !== false && strpos($sql, 'SELECT id') !== false) {
				return $uniqids[$params[1]] ?? '';
			}

			if (strpos($sql, 'plugin_intropage_user_auth') !== false) {
				return json_encode($perms);
			}

			return '';
		}

		return [];
	};
}

function intropage_addpanel_render(int $dashboard_id = 1): string {
	ob_start();
	intropage_addpanel_select($dashboard_id);

	return (string) ob_get_clean();
}

beforeEach(function () {
	$GLOBALS['__test_db_calls'] = [];
	$_SESSION['dashboard_id']   = 1;

	// Rebuild the statically cached panel library and drop the per-request
	// source/disabled caches so each test starts from a clean slate.
	$GLOBALS['intropage_panel_library_stale'] = true;
	unset($GLOBALS['intropage_panel_sources'], $GLOBALS['intropage_disabled_panels']);
});

afterEach(function () {
	unset($GLOBALS['__test_query'], $GLOBALS['__test_tables'], $GLOBALS['__test_enabled_plugins']);
});

describe('intropage_addpanel_select', function () {
	it('prefixes an option with its registered source', function () {
		$_SESSION['sess_user_id'] = 3101;

		// 'alert_host' is a built-in (Core) panel with no plugin requirement, so
		// is_panel_enabled() reports it enabled from the rebuilt library.
		intropage_addpanel_fixture(
			[['panel_id' => 'alert_host', 'name' => 'Host alerts']],
			['alert_host' => '7'],
			true,
			['alert_host' => 'on']
		);

		$html = intropage_addpanel_render();

		expect($html)->toContain("<option value='7'>Core - Host alerts</option>");
	});

	it('leaves the label unchanged when the panel has no registered source', function () {
		$_SESSION['sess_user_id'] = 3102;

		intropage_addpanel_fixture(
			[['panel_id' => 'alert_host', 'name' => 'Host alerts']],
			['alert_host' => '7'],
			false,
			['alert_host' => 'on']
		);

		$html = intropage_addpanel_render();

		expect($html)->toContain("<option value='7'>Host alerts</option>");
		expect($html)->not->toContain('Core - ');
	});

	it('offers a panel with no data row yet by its panel id', function () {
		$_SESSION['sess_user_id'] = 3106;

		intropage_addpanel_fixture(
			[['panel_id' => 'alert_host', 'name' => 'Host alerts']],
			['alert_host' => ''],
			true,
			['alert_host' => 'on']
		);

		$html = intropage_addpanel_render();

		expect($html)->toContain("<option value='alert_host'>Core - Host alerts</option>");
	});

	it('orders options by the prefixed label, not the database name', function () {
		$_SESSION['sess_user_id'] = 3103;

		// Fed Host-before-Database; the prefixed-label sort must flip them so
		// "Core - Database Checks" precedes "Core - Host alerts".
		intropage_addpanel_fixture(
			[
				['panel_id' => 'alert_host', 'name' => 'Host alerts'],
				['panel_id' => 'analyse_db', 'name' => 'Database Checks'],
			],
			['alert_host' => '5', 'analyse_db' => '6'],
			true,
			['alert_host' => 'on', 'analyse_db' => 'on']
		);

		$html = intropage_addpanel_render();

		expect(strpos($html, 'Core - Database Checks'))->toBeLessThan(strpos($html, 'Core - Host alerts'));
	});

	it('marks a panel the user may not see as disabled (existing row)', function () {
		$_SESSION['sess_user_id'] = 3104;

		intropage_addpanel_fixture(
			[['panel_id' => 'alert_host', 'name' => 'Host alerts']],
			['alert_host' => '9'],
			true,
			[]
		);

		$html = intropage_addpanel_render();

		expect($html)->toContain("<option value='addpanel_9' disabled='disabled'>");
	});

	it('marks a panel the user may not see as disabled (no row yet)', function () {
		$_SESSION['sess_user_id'] = 3105;

		intropage_addpanel_fixture(
			[['panel_id' => 'alert_host', 'name' => 'Host alerts']],
			['alert_host' => ''],
			true,
			[]
		);

		$html = intropage_addpanel_render();

		expect($html)->toContain("<option value='addpanel_' disabled='disabled'>");
	});
});
