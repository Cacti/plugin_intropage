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

it('logs a deprecation warning and does nothing when a panel is added', function () {
	$GLOBALS['__test_cacti_log'] = array();

	$result = intropage_add_panel('my_panel', '/plugins/my_plugin/file.php', 'yes', 3600, 20, 'My Panel');

	expect($result)->toBeFalse();
	expect($GLOBALS['__test_db_calls'])->toBe(array());
	expect($GLOBALS['__test_cacti_log'])->toHaveCount(1);
	expect($GLOBALS['__test_cacti_log'][0])->toContain('intropage_add_panel() is deprecated');
	expect($GLOBALS['__test_cacti_log'][0])->toContain('my_panel');
	expect($GLOBALS['__test_cacti_log'][0])->toContain('panellib/README.md');
});

it('logs a deprecation warning and does nothing when a panel is removed', function () {
	$GLOBALS['__test_cacti_log'] = array();

	$result = intropage_remove_panel('my_panel');

	expect($result)->toBeFalse();
	expect($GLOBALS['__test_db_calls'])->toBe(array());
	expect($GLOBALS['__test_cacti_log'])->toHaveCount(1);
	expect($GLOBALS['__test_cacti_log'][0])->toContain('intropage_remove_panel() is deprecated');
	expect($GLOBALS['__test_cacti_log'][0])->toContain('my_panel');
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

it('deletes the group auth record by its user_group_id key', function () {
	intropage_user_group_remove(7);

	expect($GLOBALS['__test_db_calls'][0]['sql'])->toContain('WHERE user_group_id = ?');
	expect($GLOBALS['__test_db_calls'][0]['sql'])->not->toContain('WHERE id = ?');
});
