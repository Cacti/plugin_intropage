<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

/*
 * Unit coverage for the third-party panel and user/group cleanup helpers
 * in setup.php: intropage_add_panel(), intropage_remove_panel(),
 * intropage_user_remove(), and intropage_user_group_remove().
 */

beforeAll(function () {
	require_once __DIR__ . '/../../setup.php';
});

beforeEach(function () {
	$GLOBALS['__test_db_calls'] = array();
});

it('registers a new panel definition and its user-auth column', function () {
	$result = intropage_add_panel('my_panel', '/plugins/my_plugin/file.php', 'yes', 3600, 20, 'My Panel');

	expect($result)->toBe('1');

	$call = $GLOBALS['__test_db_calls'][0];

	expect($call['fn'])->toBe('db_execute_prepared');
	expect($call['sql'])->toContain('REPLACE INTO plugin_intropage_panel_definition');
	expect($call['params'])->toBe(array('my_panel', '/plugins/my_plugin/file.php', 'yes', 3600, 20, 'My Panel'));
});

it('removes a panel from every table that references it', function () {
	intropage_remove_panel('my_panel');

	expect($GLOBALS['__test_db_calls'])->toHaveCount(3);

	foreach ($GLOBALS['__test_db_calls'] as $call) {
		expect($call['params'])->toBe(array('my_panel'));
	}
});

it('removes every per-user record on user_remove', function () {
	intropage_user_remove(42);

	expect($GLOBALS['__test_db_calls'])->toHaveCount(4);

	foreach ($GLOBALS['__test_db_calls'] as $call) {
		expect($call['params'])->toBe(array(42));
	}
});

it('removes the group auth record on user_group_remove', function () {
	intropage_user_group_remove(7);

	expect($GLOBALS['__test_db_calls'])->toHaveCount(1);
	expect($GLOBALS['__test_db_calls'][0]['sql'])->toContain('plugin_intropage_user_group_auth');
	expect($GLOBALS['__test_db_calls'][0]['params'])->toBe(array(7));
});
