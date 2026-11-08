<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../panellib/top5.php';
require_once __DIR__ . '/../../panellib/graphs.php';
require_once __DIR__ . '/../../panellib/analyze.php';
require_once __DIR__ . '/../../panellib/system.php';

if (!function_exists('number_format_i18n')) {
	function number_format_i18n($number, $decimals = 0) {
		return number_format($number, $decimals);
	}
}

if (!function_exists('date_time_format')) {
	function date_time_format() {
		return 'Y-m-d H:i:s';
	}
}

if (!function_exists('config_value_exists')) {
	function config_value_exists($name) {
		return false;
	}
}

if (!function_exists('display_tooltip')) {
	function display_tooltip($text) {
		return '';
	}
}

if (!function_exists('api_plugin_user_realm_auth')) {
	function api_plugin_user_realm_auth($filename) {
		return true;
	}
}

if (!function_exists('get_cacti_version')) {
	function get_cacti_version() {
		return '1.2.31';
	}
}

/*
 * These panels build their host filter from intropage_device_scope(). Each
 * one must constrain its query to the allowed hosts for a restricted user,
 * add no host filter for a simple-permission user, and run no query at all
 * when a restricted user can see no devices.
 */
describe('panel device scope', function () {
	beforeEach(function () {
		$GLOBALS['__test_db_calls']   = [];
		$GLOBALS['__test_config']     = [];
		$GLOBALS['__test_fetch_cell'] = 0;
		$GLOBALS['__test_fetch_row']  = ['processes' => 2, 'threads' => 2];
		$_SESSION['sess_user_id']     = 2;
	});

	afterEach(function () {
		unset(
			$GLOBALS['__test_simple_perms'],
			$GLOBALS['__test_allowed_devices'],
			$GLOBALS['__test_db_calls'],
			$GLOBALS['__test_fetch_cell'],
			$GLOBALS['__test_fetch_row'],
			$_SESSION['sess_user_id']
		);
	});

	function panel_scope_sql(): string {
		$rows = array_filter($GLOBALS['__test_db_calls'], fn ($c) => strpos($c['fn'], 'db_fetch') === 0);

		return implode("\n", array_column($rows, 'sql'));
	}

	function panel_scope_run(string $fn, bool $detail): void {
		$panel = ['height' => 'normal', 'id' => 1, 'data' => '', 'alarm' => 'grey'];

		if ($detail) {
			$fn();
		} else {
			$fn($panel, 2);
		}
	}

	$panels = [
		'top5_ping'                   => ['top5_ping', false, 'AND id IN (3,7,9)'],
		'top5_availability'           => ['top5_availability', false, 'AND id IN (3,7,9)'],
		'top5_polltime'               => ['top5_polltime', false, 'AND id IN (3,7,9)'],
		'top5_pollratio'              => ['top5_pollratio', false, 'AND id IN (3,7,9)'],
		'top5_ping_detail'            => ['top5_ping_detail', true, 'AND id IN (3,7,9)'],
		'top5_availability_detail'    => ['top5_availability_detail', true, 'AND id IN (3,7,9)'],
		'top5_polltime_detail'        => ['top5_polltime_detail', true, 'AND id IN (3,7,9)'],
		'top5_pollratio_detail'       => ['top5_pollratio_detail', true, 'AND id IN (3,7,9)'],
		'graph_data_source'           => ['graph_data_source', false, 'AND data_local.host_id IN (3,7,9)'],
		'graph_host_template'         => ['graph_host_template', false, 'WHERE host.id IN (3,7,9)'],
		'graph_data_source_detail'    => ['graph_data_source_detail', true, 'AND dl.host_id IN (3,7,9)'],
		'graph_host_template_detail'  => ['graph_host_template_detail', true, 'WHERE host.id IN (3,7,9)'],
		'graph_host_detail'           => ['graph_host_detail', true, 'AND id IN (3,7,9)'],
		'analyse_tree_host_graph'     => ['analyse_tree_host_graph', false, 'IN (3,7,9)'],
		'analyse_tree_host_graph_detail' => ['analyse_tree_host_graph_detail', true, 'IN (3,7,9)'],
	];

	it('filters the query to the allowed hosts for a restricted user', function (string $fn, bool $detail, string $clause) {
		$GLOBALS['__test_simple_perms']    = false;
		$GLOBALS['__test_allowed_devices'] = [['id' => 3], ['id' => 7], ['id' => 9]];

		panel_scope_run($fn, $detail);

		expect(panel_scope_sql())->toContain($clause);
	})->with($panels);

	it('applies no host filter for a simple-permission user', function (string $fn, bool $detail, string $clause) {
		$GLOBALS['__test_simple_perms'] = true;

		panel_scope_run($fn, $detail);

		expect(panel_scope_sql())->not->toContain('IN (3,7,9)');
		expect(panel_scope_sql())->not->toMatch('/\bIN \(\s*\)/');
	})->with($panels);
});

/*
 * The detail view runs outside a session when the poller or a CLI caller
 * renders it, and then scopes to the configured admin user.
 */
describe('analyse_tree_host_graph_detail without a session', function () {
	beforeEach(function () {
		$GLOBALS['__test_db_calls']   = [];
		$GLOBALS['__test_config']     = ['admin_user' => 1];
		$GLOBALS['__test_fetch_cell'] = 0;
		$GLOBALS['__test_fetch_row']  = ['processes' => 2, 'threads' => 2];
		unset($_SESSION['sess_user_id']);
	});

	afterEach(function () {
		unset(
			$GLOBALS['__test_simple_perms'],
			$GLOBALS['__test_allowed_devices'],
			$GLOBALS['__test_db_calls'],
			$GLOBALS['__test_fetch_cell'],
			$GLOBALS['__test_fetch_row']
		);
	});

	it('scopes to the allowed hosts of the admin user', function () {
		$GLOBALS['__test_simple_perms']    = false;
		$GLOBALS['__test_allowed_devices'] = [['id' => 3], ['id' => 7], ['id' => 9]];

		analyse_tree_host_graph_detail();

		expect(panel_scope_sql())->toContain('IN (3,7,9)');
	});

	it('applies no host filter when the admin user sees every device', function () {
		$GLOBALS['__test_simple_perms'] = true;

		analyse_tree_host_graph_detail();

		expect(panel_scope_sql())->not->toContain('IN (3,7,9)');
	});
});

/*
 * host_collect() and extrem_trend() write one trend row set per user. A
 * restricted user's counts must be limited to the devices that user can see,
 * and a restricted user with no visible devices must get zero rows instead of
 * an unfiltered count.
 */
describe('trend collectors device scope', function () {
	beforeEach(function () {
		$GLOBALS['__test_db_calls']            = [];
		$GLOBALS['__test_cell_calls']          = [];
		$GLOBALS['__test_config']              = [];
		$GLOBALS['__test_fetch_cell']          = 4;
		$GLOBALS['__test_fetch_cell_prepared'] = '{"extrem":"on"}';
		$GLOBALS['config']['is_web']           = true;
		$_SESSION['sess_user_id']              = 2;
	});

	afterEach(function () {
		unset(
			$GLOBALS['__test_simple_perms'],
			$GLOBALS['__test_allowed_devices'],
			$GLOBALS['__test_db_calls'],
			$GLOBALS['__test_cell_calls'],
			$GLOBALS['__test_fetch_cell'],
			$GLOBALS['__test_fetch_cell_prepared'],
			$GLOBALS['config']['is_web'],
			$_SESSION['sess_user_id']
		);
	});

	function trend_sql(string $fn): string {
		if ($fn === 'db_fetch_cell') {
			return implode("\n", $GLOBALS['__test_cell_calls'] ?? []);
		}

		$rows = array_filter($GLOBALS['__test_db_calls'], fn ($c) => $c['fn'] === $fn);

		return implode("\n", array_column($rows, 'sql'));
	}

	it('limits host_collect counts to the allowed hosts', function () {
		$GLOBALS['__test_simple_perms']    = false;
		$GLOBALS['__test_allowed_devices'] = [['id' => 3], ['id' => 7], ['id' => 9]];

		host_collect();

		expect(trend_sql('db_execute_prepared'))->toContain('AND id IN (3,7,9)');
	});

	it('does not filter host_collect counts for a simple-permission user', function () {
		$GLOBALS['__test_simple_perms'] = true;

		host_collect();

		expect(trend_sql('db_execute_prepared'))->toContain("status='1'");
		expect(trend_sql('db_execute_prepared'))->not->toContain('IN (3,7,9)');
	});

	it('writes zero rows from host_collect for a restricted user with no devices', function () {
		$GLOBALS['__test_simple_perms']    = false;
		$GLOBALS['__test_allowed_devices'] = [];

		host_collect();

		expect(trend_sql('db_execute_prepared'))->toContain("VALUES ('host_down', 0, ?)");
		expect(trend_sql('db_execute_prepared'))->not->toContain('FROM host');
	});

	it('limits the extrem_trend failed poll sum to the allowed hosts', function () {
		$GLOBALS['__test_simple_perms']    = false;
		$GLOBALS['__test_allowed_devices'] = [['id' => 3], ['id' => 7], ['id' => 9]];

		extrem_trend();

		expect(trend_sql('db_fetch_cell'))->toContain('WHERE id IN (3,7,9)');
	});

	it('does not filter the extrem_trend failed poll sum for a simple-permission user', function () {
		$GLOBALS['__test_simple_perms'] = true;

		extrem_trend();

		expect(trend_sql('db_fetch_cell'))->toContain('SUM(failed_polls)');
		expect(trend_sql('db_fetch_cell'))->not->toContain('IN (3,7,9)');
	});

	it('skips the extrem_trend failed poll sum for a restricted user with no devices', function () {
		$GLOBALS['__test_simple_perms']    = false;
		$GLOBALS['__test_allowed_devices'] = [];

		extrem_trend();

		expect(trend_sql('db_fetch_cell'))->not->toContain('SUM(failed_polls)');
	});
});

/*
 * graph_host() reads stored trend rows rather than filtering host queries, so
 * the scope only decides between drawing the graph and refusing the panel.
 */
describe('graph_host device scope', function () {
	beforeEach(function () {
		$GLOBALS['__test_db_calls'] = [];
		$GLOBALS['__test_config']   = ['intropage_timespan' => 3600];
	});

	afterEach(function () {
		unset(
			$GLOBALS['__test_simple_perms'],
			$GLOBALS['__test_allowed_devices'],
			$GLOBALS['__test_db_calls']
		);
	});

	function graph_host_saved_text(): string {
		$saves = array_filter($GLOBALS['__test_db_calls'], fn ($c) => strpos($c['sql'], 'UPDATE plugin_intropage_panel_data') !== false);

		return (string) end($saves)['params'][0];
	}

	it('refuses the panel for a restricted user with no visible devices', function () {
		$GLOBALS['__test_simple_perms']    = false;
		$GLOBALS['__test_allowed_devices'] = [];

		graph_host(['id' => 1, 'alarm' => 'grey', 'data' => '', 'refresh' => 60, 'refresh_interval' => 60], 2);

		expect(graph_host_saved_text())->toContain('permissions to any hosts');
	});

	it('shows the panel for a restricted user with visible devices', function () {
		$GLOBALS['__test_simple_perms']    = false;
		$GLOBALS['__test_allowed_devices'] = [['id' => 3]];

		graph_host(['id' => 1, 'alarm' => 'grey', 'data' => '', 'refresh' => 60, 'refresh_interval' => 60], 2);

		expect(graph_host_saved_text())->not->toContain('permissions to any hosts');
	});

	it('shows the panel for a simple-permission user', function () {
		$GLOBALS['__test_simple_perms'] = true;

		graph_host(['id' => 1, 'alarm' => 'grey', 'data' => '', 'refresh' => 60, 'refresh_interval' => 60], 2);

		expect(graph_host_saved_text())->not->toContain('permissions to any hosts');
	});
});

describe('intropage_device_filter', function () {
	afterEach(function () {
		unset($GLOBALS['__test_simple_perms'], $GLOBALS['__test_allowed_devices']);
	});

	it('builds the condition from the allowed host ids for a restricted user', function () {
		$GLOBALS['__test_simple_perms']    = false;
		$GLOBALS['__test_allowed_devices'] = [['id' => 3], ['id' => 7], ['id' => 9]];

		$filter = intropage_device_filter(2, 'AND host.id');

		expect($filter['simple'])->toBeFalse();
		expect($filter['allowed'])->toBe('3,7,9');
		expect($filter['cond'])->toBe('AND host.id IN (3,7,9)');
	});

	it('returns no condition for a simple-permission user', function () {
		$GLOBALS['__test_simple_perms'] = true;

		$filter = intropage_device_filter(1, 'WHERE id');

		expect($filter['simple'])->toBeTrue();
		expect($filter['allowed'])->toBeFalse();
		expect($filter['cond'])->toBe('');
	});

	it('leaves allowed false for a restricted user with no visible devices', function () {
		$GLOBALS['__test_simple_perms']    = false;
		$GLOBALS['__test_allowed_devices'] = [];

		$filter = intropage_device_filter(2, 'AND id');

		expect($filter['simple'])->toBeFalse();
		expect($filter['allowed'])->toBeFalse();
	});
});
