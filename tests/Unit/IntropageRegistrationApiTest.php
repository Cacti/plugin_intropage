<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * In-process coverage for the database-backed paths of the third-party panel
 * registration API in includes/registration.php, plus the two helpers
 * initialize_panel_library() delegates to in includes/functions.php
 * (intropage_unregister_missing_panels() and intropage_merge_registered_panels()).
 *
 * The plugin's db_* helpers are the unit-bootstrap stubs: writes are recorded
 * in $GLOBALS['__test_db_calls'] and reads are answered by the router below,
 * driven by per-test values in $GLOBALS['__reg'].
 */

require_once __DIR__ . '/../../includes/functions.php';

/**
 * Routes every scripted read in registration.php to a value from $GLOBALS['__reg'].
 */
function intropage_reg_router(string $kind, string $sql, array $params) {
	$r   = $GLOBALS['__reg'];
	$has = static fn ($needle) => strpos($sql, $needle) !== false;

	if ($kind === 'cell') {
		if ($has('FROM plugin_config') && $has('directory = ?')) {
			return $r['plugin_present'] ? '1' : '0';
		}
		if ($has('plugin_intropage_panel_registration') && $has('COUNT(*)')) {
			return $r['exists'] ? '1' : '0';
		}
		if ($has('FROM plugin_intropage_panel_data') && $has('user_id = ?') && $has('COUNT(*)')) {
			return $r['seed_exists'];
		}
		if ($has('SELECT id') && $has('plugin_intropage_panel_data')) {
			return $r['missing_id'];
		}
		if ($has('SELECT permissions') && $has('plugin_intropage_user_auth')) {
			return $r['admin_perms'];
		}
		if ($has('FROM user_auth ORDER BY id')) {
			return $r['fallback_uid'];
		}

		return '0';
	}

	if ($kind === 'row') {
		return $has('plugin_intropage_panel_registration') ? $r['row'] : array();
	}

	// assoc
	if ($has('INNER JOIN plugin_config')) {
		return $r['load_rows'];
	}
	if ($has('LEFT JOIN plugin_config')) {
		return $r['stale_rows'];
	}
	if ($has('SELECT id') && $has('plugin_intropage_panel_data')) {
		return $r['data_ids'];
	}
	if ($has('plugin_intropage_user_group_auth')) {
		return $r['perm_rows_group'];
	}
	if ($has('plugin_intropage_user_auth') && $has('permissions')) {
		return $r['perm_rows_user'];
	}
	if ($has('WHERE plugin')) {
		return $r['rows_by_plugin'];
	}
	if ($has('plugin_intropage_panel_registration')) {
		return $r['all_rows'];
	}

	return array();
}

/**
 * A full registration row as stored in plugin_intropage_panel_registration.
 */
function intropage_reg_row(array $overrides = array()): array {
	return array_merge(array(
		'panel_id'     => 'demo_panel',
		'plugin'       => 'demo',
		'name'         => 'Demo Panel',
		'description'  => 'A demo',
		'class'        => 'demo',
		'level'        => 1,
		'priority'     => 50,
		'alarm'        => 'grey',
		'width'        => 'quarter-panel',
		'height'       => 'normal',
		'height_fixed' => 0,
		'force'        => 1,
		'requires'     => 'demo',
		'include_file' => '',
		'update_func'  => 'demo_update',
		'details_func' => '',
		'trends_func'  => '',
		'refresh'      => 3600,
		'trefresh'     => 3600,
		'request_vars' => json_encode(array()),
		'enabled'      => 1,
	), $overrides);
}

/**
 * A valid registration input for intropage_register_panel().
 */
function intropage_reg_input(array $overrides = array()): array {
	return array_merge(array(
		'panel_id'    => 'demo_panel',
		'plugin'      => 'demo',
		'name'        => 'Demo Panel',
		'update_func' => 'demo_update',
	), $overrides);
}

beforeEach(function () {
	$GLOBALS['__test_db_calls'] = array();
	$GLOBALS['__test_cacti_log'] = array();
	$GLOBALS['__test_config']   = array();
	$GLOBALS['__test_tables']   = array('plugin_intropage_panel_registration' => true);
	$GLOBALS['__test_query']    = 'intropage_reg_router';
	$_SESSION['sess_user_id']   = 5;
	$_REQUEST                   = array();
	$GLOBALS['__test_request']  = array();

	$GLOBALS['__reg'] = array(
		'plugin_present'  => true,
		'exists'          => true,
		'row'             => intropage_reg_row(),
		'all_rows'        => array(),
		'rows_by_plugin'  => array(),
		'load_rows'       => array(),
		'stale_rows'      => array(),
		'data_ids'        => array(),
		'seed_exists'     => '0',
		'missing_id'      => '0',
		'admin_perms'     => json_encode(array()),
		'perm_rows_user'  => array(),
		'perm_rows_group' => array(),
		'fallback_uid'    => '0',
	);
});

afterEach(function () {
	unset($GLOBALS['__test_query'], $GLOBALS['__reg'], $GLOBALS['__test_tables']);
});

function intropage_reg_writes(string $needle): array {
	return array_values(array_filter($GLOBALS['__test_db_calls'], static fn ($c) => strpos($c['sql'], $needle) !== false));
}

it('rejects an invalid plugin name', function () {
	$errors = intropage_validate_panel_registration(intropage_reg_input(array('plugin' => 'bad plugin!')), false);

	expect($errors)->toContain("The 'plugin' must be the plugin directory name (letters, digits or underscores).");
});

it('detects whether a plugin is present', function () {
	expect(intropage_plugin_is_present(''))->toBeFalse();

	$GLOBALS['__reg']['plugin_present'] = true;
	expect(intropage_plugin_is_present('demo'))->toBeTrue();

	$GLOBALS['__reg']['plugin_present'] = false;
	expect(intropage_plugin_is_present('demo'))->toBeFalse();
});

it('reports whether a registration exists', function () {
	$GLOBALS['__reg']['exists'] = true;
	expect(intropage_panel_registration_exists('demo_panel'))->toBeTrue();

	$GLOBALS['__reg']['exists'] = false;
	expect(intropage_panel_registration_exists('demo_panel'))->toBeFalse();
});

it('fetches and decodes a single registration', function () {
	$GLOBALS['__reg']['row'] = intropage_reg_row(array('request_vars' => json_encode(array(array('name' => 'a', 'type' => 'integer')))));

	$row = intropage_get_panel_registration('demo_panel');

	expect($row['request_vars'])->toBe(array(array('name' => 'a', 'type' => 'integer')));

	$GLOBALS['__reg']['row'] = array();
	expect(intropage_get_panel_registration('demo_panel'))->toBeFalse();
});

it('fetches all registrations and by plugin', function () {
	$GLOBALS['__reg']['all_rows']       = array(intropage_reg_row());
	$GLOBALS['__reg']['rows_by_plugin'] = array(intropage_reg_row(array('request_vars' => json_encode(array()))));

	expect(intropage_get_panel_registrations())->toHaveCount(1);
	expect(intropage_get_panel_registrations('demo'))->toHaveCount(1);

	$GLOBALS['__reg']['all_rows'] = array();
	expect(intropage_get_panel_registrations())->toBe(array());
});

it('registers and publishes a panel whose plugin is installed', function () {
	$GLOBALS['__reg']['plugin_present'] = true;

	expect(intropage_register_panel(intropage_reg_input()))->toBeTrue();

	expect(intropage_reg_writes('INSERT INTO plugin_intropage_panel_registration'))->toHaveCount(1);
	expect(intropage_reg_writes('INSERT INTO plugin_intropage_panel_definition'))->toHaveCount(1);
	expect(intropage_reg_writes('INSERT INTO plugin_intropage_panel_data'))->toHaveCount(1);
	expect(intropage_reg_writes('UPDATE plugin_intropage_user_auth'))->toHaveCount(1);
});

it('registers but retracts a panel whose plugin is absent', function () {
	$GLOBALS['__reg']['plugin_present'] = false;

	expect(intropage_register_panel(intropage_reg_input()))->toBeTrue();

	expect(intropage_reg_writes('INSERT INTO plugin_intropage_panel_registration'))->toHaveCount(1);
	expect(intropage_reg_writes('DELETE FROM plugin_intropage_panel_definition'))->toHaveCount(1);
	expect(intropage_reg_writes('INSERT INTO plugin_intropage_panel_definition'))->toBe(array());
});

it('honors an explicit enabled=false on registration', function () {
	expect(intropage_register_panel(intropage_reg_input(array('enabled' => false))))->toBeTrue();

	$insert = intropage_reg_writes('INSERT INTO plugin_intropage_panel_registration')[0];

	// last positional param before the NOW() columns is the enabled flag
	expect($insert['params'][20])->toBe(0);
});

it('rejects an invalid registration with error strings', function () {
	$result = intropage_register_panel(array('panel_id' => 'x'));

	expect($result)->toBeArray();
	expect($GLOBALS['__test_db_calls'])->toBe(array());
});

it('updates selected attributes of an existing registration', function () {
	$GLOBALS['__reg']['exists'] = true;

	expect(intropage_update_panel_registration('demo_panel', array('priority' => 7, 'alarm' => 'red')))->toBeTrue();

	$updates = intropage_reg_writes('UPDATE plugin_intropage_panel_registration');

	expect($updates)->toHaveCount(1);
	expect($updates[0]['sql'])->toContain('priority');
});

it('updates request_vars and republishes when enabled', function () {
	$GLOBALS['__reg']['exists'] = true;
	$GLOBALS['__reg']['row']    = intropage_reg_row(array('enabled' => 1));

	expect(intropage_update_panel_registration('demo_panel', array('request_vars' => array(array('name' => 'x', 'type' => 'integer')))))->toBeTrue();

	expect(intropage_reg_writes('INSERT INTO plugin_intropage_panel_definition'))->toHaveCount(1);
});

it('rejects an update for an unknown registration', function () {
	$GLOBALS['__reg']['exists'] = false;

	$result = intropage_update_panel_registration('nope', array('priority' => 1));

	expect($result)->toBeArray();
});

it('rejects an update with invalid values', function () {
	$GLOBALS['__reg']['exists'] = true;

	$result = intropage_update_panel_registration('demo_panel', array('height' => 'huge'));

	expect($result)->toBeArray();
});

it('is a no-op update when no known fields change', function () {
	$GLOBALS['__reg']['exists'] = true;

	expect(intropage_update_panel_registration('demo_panel', array('unknown' => 1)))->toBeTrue();
	expect(intropage_reg_writes('UPDATE plugin_intropage_panel_registration'))->toBe(array());
});

it('enables a disabled registration', function () {
	$GLOBALS['__reg']['exists'] = true;

	expect(intropage_enable_panel_registration('demo_panel'))->toBeTrue();
	expect(intropage_reg_writes('SET enabled = 1'))->toHaveCount(1);
	expect(intropage_reg_writes('INSERT INTO plugin_intropage_panel_definition'))->toHaveCount(1);

	$GLOBALS['__reg']['exists'] = false;
	expect(intropage_enable_panel_registration('nope'))->toBeFalse();
});

it('disables a registration without deleting it', function () {
	$GLOBALS['__reg']['exists'] = true;

	expect(intropage_disable_panel_registration('demo_panel'))->toBeTrue();
	expect(intropage_reg_writes('SET enabled = 0'))->toHaveCount(1);
	expect(intropage_reg_writes('DELETE FROM plugin_intropage_panel_definition'))->toHaveCount(1);

	$GLOBALS['__reg']['exists'] = false;
	expect(intropage_disable_panel_registration('nope'))->toBeFalse();
});

it('unregisters a panel and removes permissions', function () {
	$GLOBALS['__reg']['exists']          = true;
	$GLOBALS['__reg']['data_ids']        = array(array('id' => 11));
	$GLOBALS['__reg']['perm_rows_user']  = array(array('user_id' => 5, 'permissions' => json_encode(array('demo_panel' => 'on'))));
	$GLOBALS['__reg']['perm_rows_group'] = array(array('user_group_id' => 2, 'permissions' => json_encode(array('other' => 'on'))));

	expect(intropage_unregister_panel('demo_panel'))->toBeTrue();

	expect(intropage_reg_writes('DELETE FROM plugin_intropage_panel_dashboard'))->toHaveCount(1);
	expect(intropage_reg_writes('DELETE FROM plugin_intropage_panel_registration'))->toHaveCount(1);
	expect(intropage_reg_writes('UPDATE plugin_intropage_user_auth'))->toHaveCount(1);

	$GLOBALS['__reg']['exists'] = false;
	expect(intropage_unregister_panel('nope'))->toBeFalse();
});

it('unregisters every panel owned by a plugin', function () {
	$GLOBALS['__reg']['exists']         = true;
	$GLOBALS['__reg']['rows_by_plugin'] = array(
		intropage_reg_row(array('panel_id' => 'p1')),
		intropage_reg_row(array('panel_id' => 'p2')),
	);

	expect(intropage_unregister_plugin_panels('demo'))->toBe(2);
});

it('skips seeding data when a row already exists and the panel is user level', function () {
	$GLOBALS['__reg']['row']         = intropage_reg_row(array('level' => 1));
	$GLOBALS['__reg']['seed_exists'] = '1';

	intropage_apply_panel_registration('demo_panel');

	expect(intropage_reg_writes('INSERT INTO plugin_intropage_panel_data'))->toBe(array());
});

it('does nothing when applying a missing registration', function () {
	$GLOBALS['__reg']['row'] = array();

	intropage_apply_panel_registration('demo_panel');

	expect($GLOBALS['__test_db_calls'])->toBe(array());
});

it('loads registered panels into library shape', function () {
	$GLOBALS['__reg']['load_rows'] = array(intropage_reg_row(array('panel_id' => 'demo_panel')));

	$panels = intropage_load_registered_panels();

	expect($panels)->toHaveKey('demo_panel');
	expect($panels['demo_panel']['update_func'])->toBe('demo_update');
});

it('returns no registered panels when the table is absent', function () {
	$GLOBALS['__test_tables'] = array();

	expect(intropage_load_registered_panels())->toBe(array());
	expect(intropage_prune_stale_registrations())->toBe(0);
});

it('returns no registered panels when none are enabled', function () {
	$GLOBALS['__reg']['load_rows'] = array();

	expect(intropage_load_registered_panels())->toBe(array());
});

it('prunes registrations whose plugin is gone', function () {
	$GLOBALS['__reg']['stale_rows'] = array(array('panel_id' => 'demo_panel', 'plugin' => 'demo'));
	$GLOBALS['__reg']['exists']     = true;

	expect(intropage_prune_stale_registrations())->toBe(1);
	expect(intropage_reg_writes('DELETE FROM plugin_intropage_panel_registration'))->toHaveCount(1);
});

it('prunes nothing when no registrations are stale', function () {
	$GLOBALS['__reg']['stale_rows'] = array();

	expect(intropage_prune_stale_registrations())->toBe(0);
});

it('validates and sanitizes declared request variables', function () {
	$GLOBALS['__reg']['row'] = intropage_reg_row(array('request_vars' => json_encode(array(
		array('name' => 'num', 'type' => 'integer', 'default' => 0),
		array('name' => 'rate', 'type' => 'numeric', 'default' => 1.0),
		array('name' => 'flag', 'type' => 'boolean'),
		array('name' => 'code', 'type' => 'alphanumeric', 'default' => 'x'),
		array('name' => 'text', 'type' => 'string', 'default' => 'd'),
		array('name' => 'absent', 'type' => 'string', 'required' => true, 'default' => 'fallback'),
	))));

	$_REQUEST = array('num' => '42', 'rate' => '2.5', 'flag' => 'yes', 'code' => 'bad code!', 'text' => '<b>hi</b>');
	$GLOBALS['__test_request'] = $_REQUEST;

	$vars = intropage_validate_request_vars('demo_panel');

	expect($vars['num'])->toBe(42);
	expect($vars['rate'])->toBe(2.5);
	expect($vars['flag'])->toBeTrue();
	expect($vars['code'])->toBe('x');
	expect($vars['text'])->toBe('hi');
	expect($vars['absent'])->toBe('fallback');
});

it('returns an empty set of request variables when none are declared', function () {
	$GLOBALS['__reg']['row'] = intropage_reg_row(array('request_vars' => json_encode(array())));

	expect(intropage_validate_request_vars('demo_panel'))->toBe(array());
});

it('falls back to the default on an invalid integer request variable', function () {
	$GLOBALS['__reg']['row'] = intropage_reg_row(array('request_vars' => json_encode(array(
		array('name' => 'num', 'type' => 'integer', 'default' => -1),
	))));

	$_REQUEST = array('num' => 'abc');
	$GLOBALS['__test_request'] = $_REQUEST;

	expect(intropage_validate_request_vars('demo_panel')['num'])->toBe(-1);
});

it('resolves the admin user from session, config and fallback', function () {
	$_SESSION['sess_user_id'] = 9;
	expect(intropage_registration_admin_user())->toBe(9);

	$_SESSION['sess_user_id']          = 0;
	$GLOBALS['__test_config']['admin_user'] = 3;
	expect(intropage_registration_admin_user())->toBe(3);

	$GLOBALS['__test_config']['admin_user'] = '';
	$GLOBALS['__reg']['fallback_uid']       = '2';
	expect(intropage_registration_admin_user())->toBe(2);
});

it('grants the admin permission only when missing', function () {
	$_SESSION['sess_user_id']        = 5;
	$GLOBALS['__reg']['admin_perms'] = json_encode(array('demo_panel' => 'on'));

	intropage_registration_grant_admin('demo_panel');
	expect(intropage_reg_writes('UPDATE plugin_intropage_user_auth'))->toBe(array());

	$GLOBALS['__reg']['admin_perms'] = json_encode(array());
	intropage_registration_grant_admin('demo_panel');
	expect(intropage_reg_writes('UPDATE plugin_intropage_user_auth'))->toHaveCount(1);
});

it('does not grant when there is no admin user or permission row', function () {
	$_SESSION['sess_user_id']               = 0;
	$GLOBALS['__test_config']['admin_user'] = '';
	$GLOBALS['__reg']['fallback_uid']       = '0';

	intropage_registration_grant_admin('demo_panel');
	expect($GLOBALS['__test_db_calls'])->toBe(array());

	$_SESSION['sess_user_id']        = 5;
	$GLOBALS['__reg']['admin_perms'] = false;
	intropage_registration_grant_admin('demo_panel');
	expect(intropage_reg_writes('UPDATE plugin_intropage_user_auth'))->toBe(array());
});

it('resolves and includes a registration callback file', function () {
	$dir = sys_get_temp_dir() . '/intropage-reg-' . bin2hex(random_bytes(5));
	mkdir($dir . '/plugins/demo', 0775, true);
	file_put_contents($dir . '/plugins/demo/panel.php', "<?php function intropage_reg_included_fn() { return 1; }\n");

	$restore                        = $GLOBALS['config']['base_path'];
	$GLOBALS['config']['base_path'] = $dir;

	try {
		expect(intropage_registration_resolve_file('plugins/demo/panel.php'))->toBe(realpath($dir . '/plugins/demo/panel.php'));

		intropage_include_registration_file(array('include_file' => 'plugins/demo/panel.php'));
		expect(function_exists('intropage_reg_included_fn'))->toBeTrue();

		// A missing file resolves to false.
		expect(intropage_registration_resolve_file('plugins/demo/missing.php'))->toBeFalse();

		// A real file outside the plugins directory is rejected.
		mkdir($dir . '/outside', 0775, true);
		file_put_contents($dir . '/outside/evil.php', "<?php\n");
		expect(intropage_registration_resolve_file('outside/evil.php'))->toBeFalse();

		// A real but non-PHP file inside plugins is rejected.
		file_put_contents($dir . '/plugins/demo/note.txt', 'x');
		expect(intropage_registration_resolve_file('plugins/demo/note.txt'))->toBeFalse();
	} finally {
		$GLOBALS['config']['base_path'] = $restore;
		@unlink($dir . '/plugins/demo/panel.php');
		@unlink($dir . '/plugins/demo/note.txt');
		@unlink($dir . '/outside/evil.php');
		@rmdir($dir . '/outside');
		@rmdir($dir . '/plugins/demo');
		@rmdir($dir . '/plugins');
		@rmdir($dir);
	}
});

it('ignores an empty include file', function () {
	intropage_include_registration_file(array('include_file' => ''));

	expect(true)->toBeTrue();
});

it('resets the cached panel library snapshot', function () {
	$_SESSION['intropage_panel_library'] = array('x' => 1);

	intropage_registration_reset_cache();

	expect(isset($_SESSION['intropage_panel_library']))->toBeFalse();
});

it('rejects an include_file during validation when it cannot be resolved', function () {
	$errors = intropage_validate_panel_registration(intropage_reg_input(array('include_file' => 'plugins/demo/missing.php')), false);

	expect($errors)->not->toBe(array());
});

it('flags malformed request variable specifications', function () {
	$errors = intropage_validate_request_var_spec(array(
		'not-an-array',
		array('type' => 'integer'),
		array('name' => 'bad name', 'type' => 'integer'),
		array('name' => 'ok'),
	));

	expect(count($errors))->toBeGreaterThanOrEqual(3);
});

it('updates a boolean attribute of a registration', function () {
	$GLOBALS['__reg']['exists'] = true;
	$GLOBALS['__reg']['row']    = intropage_reg_row(array('enabled' => 1));

	expect(intropage_update_panel_registration('demo_panel', array('height_fixed' => true, 'force' => false)))->toBeTrue();

	$update = intropage_reg_writes('UPDATE plugin_intropage_panel_registration')[0];

	expect($update['sql'])->toContain('height_fixed');
	expect($update['params'][0])->toBe(1);
	expect($update['params'][1])->toBe(0);
});

it('builds the full panel library without error', function () {
	$GLOBALS['__reg']['load_rows'] = array(intropage_reg_row(array('panel_id' => 'demo_panel')));

	expect(initialize_panel_library())->toBeArray();
});

/* ---- the two helpers extracted from initialize_panel_library() ---- */

it('removes the footprint of panels whose plugin is gone', function () {
	$GLOBALS['__test_config']['intropage_unregister'] = 'on';
	$GLOBALS['__reg']['missing_id'] = '14';

	intropage_unregister_missing_panels(array('ghost_panel'));

	expect(intropage_reg_writes('DELETE FROM plugin_intropage_panel_dashboard'))->toHaveCount(1);
	expect(intropage_reg_writes('DELETE FROM plugin_intropage_panel_data'))->toHaveCount(1);
	expect(intropage_reg_writes('DELETE FROM plugin_intropage_panel_definition'))->toHaveCount(1);
});

it('skips cleanup when the panel has no data row', function () {
	$GLOBALS['__test_config']['intropage_unregister'] = 'on';
	$GLOBALS['__reg']['missing_id'] = '0';

	intropage_unregister_missing_panels(array('ghost_panel'));

	expect($GLOBALS['__test_db_calls'])->toBe(array());
});

it('does no cleanup unless automatic unregistering is enabled', function () {
	$GLOBALS['__reg']['missing_id'] = '14';

	// No list, or the option off, is a no-op.
	intropage_unregister_missing_panels(array());
	intropage_unregister_missing_panels(array('ghost_panel'));

	expect($GLOBALS['__test_db_calls'])->toBe(array());
});

it('merges registered panels into the built-in library', function () {
	$GLOBALS['__reg']['load_rows'] = array(intropage_reg_row(array('panel_id' => 'demo_panel')));

	$merged = intropage_merge_registered_panels(array('builtin' => array('name' => 'Built-in')));

	expect($merged)->toHaveKey('builtin');
	expect($merged)->toHaveKey('demo_panel');
});

it('leaves the library unchanged when there are no registrations', function () {
	$GLOBALS['__reg']['load_rows'] = array();

	$merged = intropage_merge_registered_panels(array('builtin' => array('name' => 'Built-in')));

	expect($merged)->toBe(array('builtin' => array('name' => 'Built-in')));
});
