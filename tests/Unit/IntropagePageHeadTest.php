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

	expect($output)->toContain('plugins/intropage/css/common.css');
});

it('does not link a theme stylesheet that does not exist on disk', function () {
	$GLOBALS['__test_theme'] = 'a-theme-that-does-not-exist';

	ob_start();
	intropage_page_head();
	$output = ob_get_clean();

	expect($output)->not->toContain('a-theme-that-does-not-exist.css');
});

it('bundles the plugin card stylesheet when core does not ship one', function () {
	$base = sys_get_temp_dir() . '/intropage-core-' . uniqid('', true);
	mkdir($base, 0777, true);

	$previous = $GLOBALS['config']['base_path'];
	$GLOBALS['config']['base_path'] = $base;

	try {
		ob_start();
		intropage_page_head();
		$output = ob_get_clean();
	} finally {
		$GLOBALS['config']['base_path'] = $previous;
		rmdir($base);
	}

	expect($output)->toContain('plugins/intropage/css/cards.css');
});

it('does not bundle the plugin card stylesheet when core already ships one', function () {
	$base = sys_get_temp_dir() . '/intropage-core-' . uniqid('', true);
	mkdir($base . '/include/css', 0777, true);
	file_put_contents($base . '/include/css/cards.css', '/* core copy */');

	$previous = $GLOBALS['config']['base_path'];
	$GLOBALS['config']['base_path'] = $base;

	try {
		ob_start();
		intropage_page_head();
		$output = ob_get_clean();
	} finally {
		$GLOBALS['config']['base_path'] = $previous;
		unlink($base . '/include/css/cards.css');
		rmdir($base . '/include/css');
		rmdir($base . '/include');
		rmdir($base);
	}

	expect($output)->not->toContain('plugins/intropage/css/cards.css');
});
