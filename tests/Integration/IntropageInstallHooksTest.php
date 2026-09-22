<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

/*
 * Integration coverage for plugin_intropage_install(): verifies every hook
 * and both realms the plugin depends on at runtime are actually
 * registered, together with the schema it needs, in a single end-to-end
 * pass.
 */

beforeAll(function () {
	require_once __DIR__ . '/../../setup.php';
});

beforeEach(function () {
	$GLOBALS['__test_registered_hooks']  = array();
	$GLOBALS['__test_registered_realms'] = array();
	$GLOBALS['__test_db_calls']          = array();
});

it('registers every hook intropage depends on, both realms, and provisions its schema', function () {
	plugin_intropage_install();

	$hooks = array();
	foreach ($GLOBALS['__test_registered_hooks'] as $registered) {
		$hooks[$registered['hook']] = $registered;
	}

	foreach (array('config_settings', 'config_arrays', 'login_options_navigate', 'top_header_tabs', 'top_graph_header_tabs', 'console_after', 'page_head', 'graph_buttons', 'poller_bottom', 'user_remove', 'user_group_remove') as $expected) {
		expect($hooks)->toHaveKey($expected);
		expect($hooks[$expected]['name'])->toBe('intropage');
	}

	$realms = array_column($GLOBALS['__test_registered_realms'], 'file');

	expect($realms)->toContain('intropage.php');
	expect($realms)->toContain('intropage_admin.php');
});
