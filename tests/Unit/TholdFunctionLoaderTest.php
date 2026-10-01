<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

/*
 * Unit coverage for intropage_load_thold_functions() and the Thold panel
 * renderers' behaviour when the Thold plugin is absent, in panellib/thold.php.
 */

require_once __DIR__ . '/../../panellib/thold.php';

if (!function_exists('api_plugin_is_enabled')) {
	function api_plugin_is_enabled($plugin) {
		return false;
	}
}

if (!function_exists('api_plugin_user_realm_auth')) {
	function api_plugin_user_realm_auth($file) {
		return false;
	}
}

if (!function_exists('save_panel_result')) {
	function save_panel_result($panel, $user_id) {
		$GLOBALS['__intropage_saved_panel'] = $panel;
	}
}

if (!function_exists('get_user_list')) {
	function get_user_list() {
		return array();
	}
}

beforeEach(function () {
	$GLOBALS['__intropage_thold_base_restore'] = $GLOBALS['config']['base_path'];
});

afterEach(function () {
	$GLOBALS['config']['base_path'] = $GLOBALS['__intropage_thold_base_restore'];
});

function intropage_thold_sandbox(string $suffix): string {
	$base = sys_get_temp_dir() . '/intropage-thold-' . $suffix . '-' . uniqid();
	$GLOBALS['config']['base_path'] = $base;

	return $base;
}

it('loads Thold functions from the new includes/functions.php path', function () {
	$base = intropage_thold_sandbox('new');
	mkdir($base . '/plugins/thold/includes', 0777, true);
	file_put_contents($base . '/plugins/thold/includes/functions.php', "<?php\n");

	expect(intropage_load_thold_functions())->toBeTrue();
});

it('falls back to the legacy thold_functions.php path', function () {
	$base = intropage_thold_sandbox('legacy');
	mkdir($base . '/plugins/thold', 0777, true);
	file_put_contents($base . '/plugins/thold/thold_functions.php', "<?php\n");

	expect(intropage_load_thold_functions())->toBeTrue();
});

it('returns false when no Thold library is present', function () {
	$base = intropage_thold_sandbox('none');
	mkdir($base . '/plugins/thold', 0777, true);

	expect(intropage_load_thold_functions())->toBeFalse();
});

it('renders the thold graph panel as grey when Thold is not installed', function () {
	intropage_thold_sandbox('graph');
	$GLOBALS['__intropage_saved_panel'] = array();

	$panel = array('id' => 1, 'refresh' => 300, 'alarm' => 'green');
	graph_thold($panel, 1);

	expect($GLOBALS['__intropage_saved_panel']['alarm'])->toBe('grey');
});

it('renders the thold detail panels when Thold is not installed', function () {
	intropage_thold_sandbox('detail');

	$detail = graph_thold_detail();
	$events = thold_event_detail();

	expect($detail['alarm'])->toBe('grey');
	expect($events['alarm'])->toBe('yellow');
});

it('skips thold_collect when the Thold threshold API is unavailable', function () {
	intropage_thold_sandbox('collect');
	$GLOBALS['__test_db_calls'] = array();

	thold_collect();

	expect($GLOBALS['__test_db_calls'])->toBeEmpty();
});
