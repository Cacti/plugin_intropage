<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Dashboard actions that change data must only run for POST requests, which
 * Cacti protects with its CSRF token. A GET link must not be able to remove
 * a panel, share a dashboard or change a login option.
 */

require_once __DIR__ . '/../../includes/functions.php';

function intropage_method_writes(): array {
	return array_values(array_filter($GLOBALS['__test_db_calls'], function ($call) {
		return preg_match('/^\s*(INSERT|UPDATE|DELETE|REPLACE)/i', $call['sql']) === 1;
	}));
}

beforeEach(function () {
	$GLOBALS['__test_db_calls']      = [];
	$GLOBALS['__test_user_settings'] = [];
	$GLOBALS['__test_request']       = [];
	$GLOBALS['__test_realms']        = [];

	$_SESSION['sess_user_id']         = 201;
	$_SESSION['dashboard_id']         = 1;
	$_SESSION['intropage_login_opts'] = 1;
});

afterEach(function () {
	unset($GLOBALS['__test_request'], $GLOBALS['__test_query'], $_SERVER['REQUEST_METHOD']);
});

describe('intropage_is_post_request', function () {
	it('is true only for POST', function () {
		$_SERVER['REQUEST_METHOD'] = 'POST';
		expect(intropage_is_post_request())->toBeTrue();

		$_SERVER['REQUEST_METHOD'] = 'GET';
		expect(intropage_is_post_request())->toBeFalse();

		unset($_SERVER['REQUEST_METHOD']);
		expect(intropage_is_post_request())->toBeFalse();
	});
});

describe('process_page_request_variables', function () {
	it('ignores a dashboard action sent as GET', function () {
		$_SERVER['REQUEST_METHOD'] = 'GET';
		$GLOBALS['__test_request'] = ['intropage_action' => 'share'];

		process_page_request_variables();

		expect(intropage_method_writes())->toBe([]);
	});

	it('runs the same action sent as POST', function () {
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$GLOBALS['__test_request'] = ['intropage_action' => 'share'];

		process_page_request_variables();

		$writes = intropage_method_writes();

		expect($writes)->toHaveCount(1);
		expect($writes[0]['sql'])->toContain('SET shared = 1');
	});

	it('ignores adding a panel sent as GET', function () {
		$_SERVER['REQUEST_METHOD'] = 'GET';
		$GLOBALS['__test_request'] = ['intropage_addpanel' => '3', 'dashboard_id' => '1'];

		process_page_request_variables();

		expect(intropage_method_writes())->toBe([]);
	});

	it('ignores saving settings sent as GET', function () {
		$_SERVER['REQUEST_METHOD'] = 'GET';
		$GLOBALS['__test_request'] = ['action' => 'save', 'save_settings' => '1'];

		process_page_request_variables();

		expect(intropage_method_writes())->toBe([]);
	});

	it('ignores a timespan change sent as GET', function () {
		$_SERVER['REQUEST_METHOD'] = 'GET';
		$GLOBALS['__test_request'] = ['intropage_action_timespan' => 'timespan_3600'];

		process_page_request_variables();

		expect($GLOBALS['__test_user_settings'])->toBe([]);
	});

	it('applies a timespan change sent as POST', function () {
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$GLOBALS['__test_request'] = ['intropage_action_timespan' => 'timespan_3600'];

		process_page_request_variables();

		expect($GLOBALS['__test_user_settings'])->toBe([['intropage_timespan', '3600']]);
	});
});

describe('get_login_opts', function () {
	it('does not switch the login option for a GET request', function () {
		$_SERVER['REQUEST_METHOD'] = 'GET';
		$GLOBALS['__test_request'] = ['intropage_action' => 'loginopt_tab'];

		expect(get_login_opts())->toBe(1);
	});

	it('switches the login option for a POST request', function () {
		$_SERVER['REQUEST_METHOD'] = 'POST';

		foreach (['loginopt_tab' => 4, 'loginopt_graph' => 2, 'loginopt_console' => 1] as $action => $expected) {
			$GLOBALS['__test_request'] = ['intropage_action' => $action];
			$_SESSION['intropage_login_opts'] = 9;

			expect(get_login_opts())->toBe($expected);
		}
	});
});

describe('intropage_graph_button', function () {
	it('renders a non-navigating button and binds one POST handler per page', function () {
		global $redirectPage;

		$_SESSION['sess_current_timespan'] = 5;
		$GLOBALS['__test_query'] = function ($kind, $sql, $params) {
			if (strpos($sql, 'FROM plugin_intropage_user_auth') !== false) {
				return json_encode(['favourite_graph' => 'on']);
			}

			return $kind === 'assoc' ? [] : '0';
		};

		ob_start();
		intropage_graph_button([1 => ['local_graph_id' => 42]]);
		$first = ob_get_clean();

		ob_start();
		intropage_graph_button([1 => ['local_graph_id' => 43]]);
		$second = ob_get_clean();

		expect($first)->toContain('data-graph="42"');
		expect($first)->toContain('class="iconLink intropage_favgraph"');
		expect($first)->not->toContain('intropage_action=favgraph');
		expect($first)->toContain('intropage_action: "favgraph"');
		expect($first)->toContain('__csrf_magic: csrfMagicToken');
		expect($first)->toContain('method=\"post\"');
		expect(substr_count($first, '<script'))->toBe(1);

		expect($second)->toContain('data-graph="43"');
		expect($second)->not->toContain('<script');
	});
});
