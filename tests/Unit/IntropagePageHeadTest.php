<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

/*
 * Unit coverage for intropage_page_head() in setup.php.
 */

beforeAll(function () {
	require_once __DIR__ . '/../../setup.php';
});

beforeEach(function () {
	unset($GLOBALS['__test_theme']);
});

it('always includes the common stylesheet', function () {
	ob_start();
	intropage_page_head();
	$output = ob_get_clean();

	expect($output)->toContain('plugins/intropage/themes/common.css');
});

it('does not link a theme stylesheet that does not exist on disk', function () {
	$GLOBALS['__test_theme'] = 'a-theme-that-does-not-exist';

	ob_start();
	intropage_page_head();
	$output = ob_get_clean();

	expect($output)->not->toContain('a-theme-that-does-not-exist.css');
});
