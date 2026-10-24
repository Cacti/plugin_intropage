<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * The panel permission set by an administrator must be enforced when a panel
 * is added, reloaded or opened in the detail dialog, not only when the
 * dashboard is rendered. User ids are unique per test because
 * is_panel_allowed() caches the resolved permissions per user.
 */

require_once __DIR__ . '/../../includes/functions.php';

/**
 * Scripts the panel tables for one test.
 *
 * @param array $permissions panel_id => 'on' map stored for the user
 * @param array $rows        panel data rows keyed by row id
 */
function intropage_access_fixture(array $permissions, array $rows): void {
	$GLOBALS['__test_query'] = function ($kind, $sql, $params) use ($permissions, $rows) {
		if (strpos($sql, 'FROM plugin_intropage_user_auth') !== false) {
			return json_encode($permissions);
		}

		if (strpos($sql, 'FROM plugin_intropage_panel_definition') !== false) {
			$name = $params[0] ?? '';

			return [
				'panel_id' => $name,
				'name'     => $name,
				'level'    => 0,
				'priority' => 10,
				'alarm'    => 'grey',
				'refresh'  => 300,
				'trefresh' => 300,
				'height'   => 'normal',
			];
		}

		if (strpos($sql, 'FROM plugin_intropage_panel_data') !== false) {
			$row = $rows[$params[0]] ?? null;

			if ($row === null) {
				return $kind === 'cell' ? '' : [];
			}

			return $kind === 'cell' ? $row['panel_id'] : $row;
		}

		return $kind === 'assoc' ? [] : '';
	};
}

function intropage_access_row(int $id, string $panel_id): array {
	return [
		'id'               => $id,
		'panel_id'         => $panel_id,
		'user_id'          => 0,
		'ts'               => time(),
		'refresh_interval' => 300,
		'trend_interval'   => 300,
		'alarm'            => 'grey',
		'height'           => 'normal',
		'data'             => '',
		'fav_graph_id'     => null,
	];
}

function intropage_access_inserts(): array {
	return array_values(array_filter($GLOBALS['__test_db_calls'], function ($call) {
		return strpos($call['sql'], 'INSERT INTO plugin_intropage_panel_dashboard') !== false;
	}));
}

beforeEach(function () {
	$GLOBALS['__test_db_calls'] = [];
	$GLOBALS['__test_saves']    = [];
	$GLOBALS['__test_request']  = [];

	$_SESSION['intropage_login_opts'] = 1;
	$_SESSION['dashboard_id']         = 1;
});

afterEach(function () {
	unset($GLOBALS['__test_query'], $GLOBALS['__test_request']);
});

describe('intropage_panel_visible', function () {
	it('follows the stored panel permission', function () {
		$_SESSION['sess_user_id'] = 101;

		intropage_access_fixture(['alert_host' => 'on'], []);

		expect(intropage_panel_visible('alert_host'))->toBeTrue();
		expect(intropage_panel_visible('analyse_db'))->toBeFalse();
	});

	it('always shows the admin alert and maintenance panels', function () {
		$_SESSION['sess_user_id'] = 102;

		intropage_access_fixture([], []);

		expect(intropage_panel_visible('admin_alert'))->toBeTrue();
		expect(intropage_panel_visible('maint'))->toBeTrue();
	});

	it('hides a panel with no name', function () {
		$_SESSION['sess_user_id'] = 103;

		intropage_access_fixture(['alert_host' => 'on'], []);

		expect(intropage_panel_visible(null))->toBeFalse();
	});
});

describe('adding a panel', function () {
	it('refuses a panel row the user has no permission for', function () {
		$_SESSION['sess_user_id'] = 104;

		intropage_access_fixture(['alert_host' => 'on'], [7 => intropage_access_row(7, 'analyse_db')]);
		$GLOBALS['__test_request'] = ['intropage_addpanel' => '7', 'dashboard_id' => '1'];

		intropage_action_add_panel();

		expect(intropage_access_inserts())->toBe([]);
	});

	it('adds a panel row the user has permission for', function () {
		$_SESSION['sess_user_id'] = 105;

		intropage_access_fixture(['alert_host' => 'on'], [8 => intropage_access_row(8, 'alert_host')]);
		$GLOBALS['__test_request'] = ['intropage_addpanel' => '8', 'dashboard_id' => '1'];

		intropage_action_add_panel();

		$inserts = intropage_access_inserts();

		expect($inserts)->toHaveCount(1);
		expect($inserts[0]['params'])->toBe(['8', 105, '1']);
	});

	it('refuses to create a panel by name without permission', function () {
		$_SESSION['sess_user_id'] = 106;

		intropage_access_fixture(['alert_host' => 'on'], []);
		$GLOBALS['__test_request'] = ['intropage_addpanel' => 'analyse_db', 'dashboard_id' => '1'];

		intropage_action_add_panel();

		expect($GLOBALS['__test_saves'])->toBe([]);
		expect(intropage_access_inserts())->toBe([]);
	});

	it('creates a panel by name with permission', function () {
		$_SESSION['sess_user_id'] = 107;

		intropage_access_fixture(['alert_host' => 'on'], []);
		$GLOBALS['__test_request'] = ['intropage_addpanel' => 'alert_host', 'dashboard_id' => '1'];

		intropage_action_add_panel();

		expect($GLOBALS['__test_saves'])->toHaveCount(1);
		expect($GLOBALS['__test_saves'][0]['row']['panel_id'])->toBe('alert_host');
		expect(intropage_access_inserts())->toHaveCount(1);
	});
});

describe('reload and detail endpoints', function () {
	it('does not reload a panel the user has no permission for', function () {
		$_SESSION['sess_user_id'] = 108;

		intropage_access_fixture(['alert_host' => 'on'], [9 => intropage_access_row(9, 'analyse_db')]);
		$GLOBALS['__test_request'] = ['panel_id' => '9', 'force' => 'true'];

		ob_start();
		intropage_reload_panel();
		$output = ob_get_clean();

		expect($output)->toBe('Panel not found');
		expect($GLOBALS['__test_db_calls'])->toBe([]);
	});

	it('does not show the details of a panel the user has no permission for', function () {
		$_SESSION['sess_user_id'] = 109;

		intropage_access_fixture(['alert_host' => 'on'], [9 => intropage_access_row(9, 'analyse_db')]);
		$GLOBALS['__test_request'] = ['panel_id' => '9'];

		ob_start();
		intropage_detail_panel();
		$output = ob_get_clean();

		expect($output)->toBe('Panel Not Found');
	});
});
