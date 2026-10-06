<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

/*
 * Unit coverage for intropage_cleanup_legacy_include() in setup.php - repoints
 * any stale include/ plugin_hooks row to includes/ and removes the leftover
 * include/ directory, warning when it cannot be removed.
 */

require_once __DIR__ . '/../../setup.php';

beforeEach(function () {
	$GLOBALS['__test_db_calls']  = [];
	$GLOBALS['__test_cacti_log'] = [];
});

it('repoints stale hooks even when no legacy include/ directory exists', function () {
	$base = sys_get_temp_dir() . '/intropage-legacy-none-' . uniqid();
	mkdir($base . '/plugins/intropage', 0777, true);

	$restore                        = $GLOBALS['config']['base_path'];
	$GLOBALS['config']['base_path'] = $base;

	try {
		intropage_cleanup_legacy_include();
	} finally {
		$GLOBALS['config']['base_path'] = $restore;
	}

	// The hook-row repoint must run even with no directory to remove, so stale
	// rows left behind by an earlier partial cleanup still get healed.
	$sql = implode("\n", array_column($GLOBALS['__test_db_calls'], 'sql'));
	expect($sql)->toContain("REPLACE(file, 'include/', 'includes/')");
	expect($GLOBALS['__test_cacti_log'])->toBe([]);
});

it('repoints stale hooks and removes the legacy include/ directory', function () {
	$base   = sys_get_temp_dir() . '/intropage-legacy-' . uniqid();
	$plugin = $base . '/plugins/intropage';
	mkdir($plugin . '/include', 0777, true);
	file_put_contents($plugin . '/include/functions.php', "<?php\n");

	$restore                        = $GLOBALS['config']['base_path'];
	$GLOBALS['config']['base_path'] = $base;

	try {
		intropage_cleanup_legacy_include();
	} finally {
		$GLOBALS['config']['base_path'] = $restore;
	}

	expect(is_dir($plugin . '/include'))->toBeFalse();

	$sql = implode("\n", array_column($GLOBALS['__test_db_calls'], 'sql'));
	expect($sql)->toContain("REPLACE(file, 'include/', 'includes/')");
	expect($GLOBALS['__test_cacti_log'])->toBe([]);
});

it('warns when the legacy include/ directory cannot be removed', function () {
	$base   = sys_get_temp_dir() . '/intropage-legacy-fail-' . uniqid();
	$plugin = $base . '/plugins/intropage';
	mkdir($plugin, 0777, true);

	$target = $base . '/realtarget';
	mkdir($target, 0777, true);
	file_put_contents($target . '/functions.php', "<?php\n");

	// A symlinked include/ satisfies is_dir() but rmdir() on the link itself
	// fails, so plugin_intropage_rmtree() returns false and the warning runs.
	symlink($target, $plugin . '/include');

	$restore                        = $GLOBALS['config']['base_path'];
	$GLOBALS['config']['base_path'] = $base;

	try {
		intropage_cleanup_legacy_include();
	} finally {
		$GLOBALS['config']['base_path'] = $restore;
	}

	$logged = implode("\n", $GLOBALS['__test_cacti_log']);
	expect($logged)->toContain('legacy include/ directory');
});
