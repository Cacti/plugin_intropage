<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 +-------------------------------------------------------------------------+
*/

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../panellib/syslog.php';

beforeEach(function () {
	$GLOBALS['__intropage_syslog_base_restore']    = $GLOBALS['config']['base_path'];
	$GLOBALS['__intropage_syslog_enabled_restore'] = $GLOBALS['__test_enabled_plugins'] ?? array();
	$GLOBALS['__intropage_syslog_log_restore']     = $GLOBALS['__test_cacti_log'] ?? array();
});

afterEach(function () {
	$GLOBALS['config']['base_path']          = $GLOBALS['__intropage_syslog_base_restore'];
	$GLOBALS['__test_enabled_plugins']       = $GLOBALS['__intropage_syslog_enabled_restore'];
	$GLOBALS['__test_cacti_log']             = $GLOBALS['__intropage_syslog_log_restore'];
	unset($GLOBALS['__intropage_syslog_base_restore'], $GLOBALS['__intropage_syslog_enabled_restore'], $GLOBALS['__intropage_syslog_log_restore']);
});

/**
 * Returns a test value for the Syslog database cell helper.
 *
 * @param string $sql The query passed by the Syslog panel.
 *
 * @return int A harmless zero result.
 */
if (!function_exists('syslog_db_fetch_cell')) {
	function syslog_db_fetch_cell($sql) {
		return 0;
	}
}

/**
 * Returns a test value for the prepared Syslog database cell helper.
 *
 * @param string $sql    The query passed by the Syslog panel.
 * @param array  $params The bound query parameters.
 *
 * @return int A harmless zero result.
 */
if (!function_exists('syslog_db_fetch_cell_prepared')) {
	function syslog_db_fetch_cell_prepared($sql, $params = array()) {
		return 0;
	}
}

/**
 * Returns no rows for prepared Syslog database queries.
 *
 * @param string $sql    The query passed by the Syslog panel.
 * @param array  $params The bound query parameters.
 *
 * @return array An empty result set.
 */
if (!function_exists('syslog_db_fetch_assoc_prepared')) {
	function syslog_db_fetch_assoc_prepared($sql, $params = array()) {
		return array();
	}
}

/**
 * Runs a Syslog panel callback with a temporary library at the requested path.
 *
 * @param callable   $callback The panel function to invoke.
 * @param string|null $layout   The library layout to create, or null for none.
 *
 * @return array The loaded library marker and captured log messages.
 */
function intropage_syslog_loader_run(callable $callback, ?string $layout): array {
	$base = sys_get_temp_dir() . '/intropage-syslog-' . uniqid();

	if ($layout === 'legacy') {
		mkdir($base . '/plugins/syslog', 0777, true);
		file_put_contents($base . '/plugins/syslog/database.php', "<?php\n\$GLOBALS['__intropage_syslog_library'] = 'legacy';\n");
	} elseif ($layout === 'modern') {
		mkdir($base . '/plugins/syslog/includes', 0777, true);
		file_put_contents($base . '/plugins/syslog/includes/database.php', "<?php\n\$GLOBALS['__intropage_syslog_library'] = 'modern';\n");
	}

	$GLOBALS['config']['base_path']                 = $base;
	$GLOBALS['__test_enabled_plugins']              = array('syslog' => true);
	$GLOBALS['__test_cacti_log']                    = array();
	$GLOBALS['__intropage_syslog_library']          = null;
	$callback();

	return array(
		'library' => $GLOBALS['__intropage_syslog_library'],
		'logs'    => $GLOBALS['__test_cacti_log'],
	);
}

it('loads the Syslog database library from either supported path in plugin_syslog_trend', function () {
	$invoke = static function () {
		plugin_syslog_trend();
	};

	foreach (array('legacy', 'modern') as $layout) {
		$result = intropage_syslog_loader_run($invoke, $layout);

		expect($result['library'])->toBe($layout)
			->and($result['logs'])->toBeEmpty();
	}

	$result = intropage_syslog_loader_run($invoke, null);

	expect($result['library'])->toBeNull()
		->and($result['logs'])->toContain('ERROR: cannot include maint functions');
});

it('loads the Syslog database library from either supported path in plugin_syslog_levels_trend', function () {
	$invoke = static function () {
		plugin_syslog_levels_trend();
	};

	foreach (array('legacy', 'modern') as $layout) {
		$result = intropage_syslog_loader_run($invoke, $layout);

		expect($result['library'])->toBe($layout)
			->and($result['logs'])->toBeEmpty();
	}

	$result = intropage_syslog_loader_run($invoke, null);

	expect($result['library'])->toBeNull()
		->and($result['logs'])->toContain('ERROR: cannot include maint functions');
});

it('loads the Syslog database library from either supported path in plugin_syslog_devices', function () {
	$panel = array('id' => 1, 'height' => 'normal', 'refresh' => 300);
	$invoke = static function () use ($panel) {
		plugin_syslog_devices($panel, 1, 60);
	};

	foreach (array('legacy', 'modern') as $layout) {
		$result = intropage_syslog_loader_run($invoke, $layout);

		expect($result['library'])->toBe($layout)
			->and($result['logs'])->toBeEmpty();
	}

	$result = intropage_syslog_loader_run($invoke, null);

	expect($result['library'])->toBeNull()
		->and($result['logs'])->toContain('ERROR: cannot include maint functions');
});

it('loads the Syslog database library from either supported path in plugin_syslog_devices_detail', function () {
	$invoke = static function () {
		plugin_syslog_devices_detail();
	};

	foreach (array('legacy', 'modern') as $layout) {
		$result = intropage_syslog_loader_run($invoke, $layout);

		expect($result['library'])->toBe($layout)
			->and($result['logs'])->toBeEmpty();
	}

	$result = intropage_syslog_loader_run($invoke, null);

	expect($result['library'])->toBeNull()
		->and($result['logs'])->toContain('ERROR: cannot include maint functions');
});
