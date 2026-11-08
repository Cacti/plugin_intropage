<?php
/* vim: ts=4
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group, Inc.                           |
 | Copyright (C) 2004-2025 Petr Macek                                      |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | https://github.com/xmacan/                                              |
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

/*
 * Unit coverage for plugin_intropage_prune_files(): tombstone/tests removal,
 * whitelist and .git protection, and logging of unaccounted-for entries.
 */

require_once __DIR__ . '/../../setup.php';

function intropage_prune_fixture(array $manifest): string {
	$base   = sys_get_temp_dir() . '/intropage-prune-' . uniqid();
	$plugin = $base . '/plugins/intropage';

	mkdir($plugin . '/include', 0777, true);
	file_put_contents($plugin . '/include/old.php', "<?php\n");
	mkdir($plugin . '/tests/Unit', 0777, true);
	file_put_contents($plugin . '/tests/Unit/SomeTest.php', "<?php\n");
	mkdir($plugin . '/userdata', 0777, true);
	file_put_contents($plugin . '/userdata/keep.dat', 'keep');
	mkdir($plugin . '/includes', 0777, true);
	file_put_contents($plugin . '/INFO', "[info]\n");
	file_put_contents($plugin . '/setup.php', "<?php\n");
	file_put_contents($plugin . '/oldfile.php', "<?php\n");
	file_put_contents($plugin . '/stray.php', "<?php\n");
	file_put_contents($plugin . '/phpunit.xml', '');
	file_put_contents($plugin . '/.mdlrc', '');
	file_put_contents($plugin . '/.md_style.rb', '');
	mkdir($plugin . '/.git', 0777, true);
	file_put_contents($plugin . '/.git/config', '');
	file_put_contents($plugin . '/manifest.json', json_encode($manifest));

	return $base;
}

beforeEach(function () {
	$GLOBALS['__test_cacti_log'] = [];
});

it('removes tombstoned paths and the tests/ tree, keeps whitelist/.git/expected, logs strays', function () {
	$manifest = [
		'tombstones' => ['include/', 'oldfile.php', 'userdata/', 'gone.png'],
		'expected'   => ['INFO', 'setup.php', 'includes/', 'manifest.json'],
		'whitelist'  => ['userdata/'],
	];

	$base    = intropage_prune_fixture($manifest);
	$plugin  = $base . '/plugins/intropage';
	$restore = $GLOBALS['config']['base_path'];

	$GLOBALS['config']['base_path'] = $base;

	try {
		plugin_intropage_prune_files();
	} finally {
		$GLOBALS['config']['base_path'] = $restore;
	}

	// Tombstone and the dev-only tests/ tree are gone.
	expect(is_dir($plugin . '/include'))->toBeFalse();
	expect(is_dir($plugin . '/tests'))->toBeFalse();
	expect(is_file($plugin . '/oldfile.php'))->toBeFalse();
	expect(is_file($plugin . '/phpunit.xml'))->toBeFalse();

	// Whitelisted user data, VCS metadata, and expected files are untouched.
	// (userdata/ is even listed as a tombstone, but the whitelist wins.)
	expect(is_file($plugin . '/userdata/keep.dat'))->toBeTrue();
	expect(is_dir($plugin . '/.git'))->toBeTrue();
	expect(is_file($plugin . '/INFO'))->toBeTrue();
	expect(is_dir($plugin . '/includes'))->toBeTrue();
	expect(is_file($plugin . '/.mdlrc'))->toBeTrue();
	expect(is_file($plugin . '/.md_style.rb'))->toBeTrue();

	// An unexpected, non-whitelisted stray is left in place but logged.
	expect(is_file($plugin . '/stray.php'))->toBeTrue();

	$logged = implode("\n", $GLOBALS['__test_cacti_log']);
	expect($logged)->toContain('stray.php');
	expect($logged)->not->toContain('userdata');
	expect($logged)->not->toContain('.git');
	expect($logged)->not->toContain('.mdlrc');
	expect($logged)->not->toContain('.md_style.rb');
});

it('is a safe no-op when the manifest is missing', function () {
	$base    = sys_get_temp_dir() . '/intropage-prune-missing-' . uniqid();
	mkdir($base . '/plugins/intropage', 0777, true);
	$restore = $GLOBALS['config']['base_path'];

	$GLOBALS['config']['base_path'] = $base;

	try {
		plugin_intropage_prune_files();
	} finally {
		$GLOBALS['config']['base_path'] = $restore;
	}

	expect($GLOBALS['__test_cacti_log'])->toBe([]);
});

it('logs and skips pruning when the manifest is malformed', function () {
	$base   = sys_get_temp_dir() . '/intropage-prune-bad-' . uniqid();
	$plugin = $base . '/plugins/intropage';
	mkdir($plugin, 0777, true);
	file_put_contents($plugin . '/manifest.json', 'not json');
	mkdir($plugin . '/tests', 0777, true);
	$restore = $GLOBALS['config']['base_path'];

	$GLOBALS['config']['base_path'] = $base;

	try {
		plugin_intropage_prune_files();
	} finally {
		$GLOBALS['config']['base_path'] = $restore;
	}

	// A malformed manifest must not delete anything.
	expect(is_dir($plugin . '/tests'))->toBeTrue();
	expect(implode("\n", $GLOBALS['__test_cacti_log']))->toContain('could not be parsed');
});

it('refuses to remove a tombstone that resolves outside the plugin directory', function () {
	$manifest = [
		'tombstones' => ['../escapee.txt'],
		'expected'   => ['manifest.json'],
		'whitelist'  => [],
	];

	$base    = intropage_prune_fixture($manifest);
	$plugin  = $base . '/plugins/intropage';
	$outside = $base . '/plugins/escapee.txt';
	file_put_contents($outside, 'precious user data');
	$restore = $GLOBALS['config']['base_path'];

	$GLOBALS['config']['base_path'] = $base;

	try {
		plugin_intropage_prune_files();
	} finally {
		$GLOBALS['config']['base_path'] = $restore;
	}

	// The out-of-tree file is untouched and the refusal is logged.
	expect(is_file($outside))->toBeTrue();
	expect(implode("\n", $GLOBALS['__test_cacti_log']))->toContain('a traversal segment');
});

it('warns when a tombstoned path cannot be removed', function () {
	$manifest = [
		'tombstones' => ['locked/'],
		'expected'   => ['manifest.json'],
		'whitelist'  => [],
	];

	$base   = intropage_prune_fixture($manifest);
	$plugin = $base . '/plugins/intropage';
	mkdir($plugin . '/locked/sub', 0777, true);
	file_put_contents($plugin . '/locked/sub/data', 'x');
	chmod($plugin . '/locked/sub', 0500); // read-only dir: its child cannot be unlinked
	$restore = $GLOBALS['config']['base_path'];

	$GLOBALS['config']['base_path'] = $base;

	try {
		plugin_intropage_prune_files();
	} finally {
		$GLOBALS['config']['base_path'] = $restore;
		@chmod($plugin . '/locked/sub', 0700);
	}

	expect(implode("\n", $GLOBALS['__test_cacti_log']))->toContain('could not remove');
})->skip(function () {
	return function_exists('posix_getuid') && posix_getuid() === 0;
}, 'permission checks are bypassed for the root user');

it('refuses a tombstone that escapes through a symlinked directory', function () {
	$manifest = [
		'tombstones' => ['escdir/secret.txt'],
		'expected'   => ['manifest.json'],
		'whitelist'  => [],
	];

	$base    = intropage_prune_fixture($manifest);
	$plugin  = $base . '/plugins/intropage';
	$outside = $base . '/outside';
	mkdir($outside, 0777, true);
	file_put_contents($outside . '/secret.txt', 'precious user data');
	@symlink($outside, $plugin . '/escdir');
	$restore = $GLOBALS['config']['base_path'];

	$GLOBALS['config']['base_path'] = $base;

	try {
		plugin_intropage_prune_files();
	} finally {
		$GLOBALS['config']['base_path'] = $restore;
	}

	// The out-of-tree file reached through the symlink is untouched and logged.
	expect(is_file($outside . '/secret.txt'))->toBeTrue();
	expect(implode("\n", $GLOBALS['__test_cacti_log']))->toContain('outside the plugin directory');
})->skip(function () {
	$probe = sys_get_temp_dir() . '/.prune-symlink-probe-' . uniqid();
	$ok = @symlink(__FILE__, $probe);
	@unlink($probe);

	return $ok === false;
}, 'symlinks are not supported on this filesystem');

it('protects a whitelisted file from a tombstone on its parent directory', function () {
	$manifest = [
		'tombstones' => ['userdata/'],
		'expected'   => ['manifest.json'],
		'whitelist'  => ['userdata/keep.dat'],
	];

	$base    = intropage_prune_fixture($manifest);
	$plugin  = $base . '/plugins/intropage';
	$restore = $GLOBALS['config']['base_path'];

	$GLOBALS['config']['base_path'] = $base;

	try {
		plugin_intropage_prune_files();
	} finally {
		$GLOBALS['config']['base_path'] = $restore;
	}

	// A whitelisted file shields its parent directory from a tombstone.
	expect(is_file($plugin . '/userdata/keep.dat'))->toBeTrue();
});
