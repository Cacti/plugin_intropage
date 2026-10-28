<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Upgrade from a 3.0.0 or older install: the per-panel columns of
 * plugin_intropage_user_auth are folded into the permissions JSON.
 *
 * intropage_upgrade_database() also prunes files below base_path, so the
 * test runs it against a throwaway tree that links to the real library and
 * panel directories and never against the checkout.
 */

require_once __DIR__ . '/../../setup.php';

function intropage_upgrade_fixture(): string {
	$base   = sys_get_temp_dir() . '/intropage-upgrade-' . uniqid();
	$plugin = $base . '/plugins/intropage';
	$real   = realpath(__DIR__ . '/../..');

	mkdir($plugin, 0777, true);
	copy($real . '/INFO', $plugin . '/INFO');
	file_put_contents($plugin . '/manifest.json', json_encode(['tombstones' => [], 'expected' => [], 'whitelist' => []]));
	symlink($real . '/includes', $plugin . '/includes');
	symlink($real . '/panellib', $plugin . '/panellib');

	return $base;
}

function intropage_upgrade_user_auth_updates(): array {
	return array_values(array_filter($GLOBALS['__test_db_calls'], function ($call) {
		return strpos($call['sql'], 'UPDATE plugin_intropage_user_auth') !== false
			&& strpos($call['sql'], 'SET permissions') !== false;
	}));
}

it('converts legacy per-panel columns to permissions without failing on the extra grant', function () {
	$base    = intropage_upgrade_fixture();
	$restore = $GLOBALS['config']['base_path'];

	$GLOBALS['__test_db_calls'] = [];
	$GLOBALS['__test_query']    = function ($kind, $sql, $params) {
		if (strpos($sql, 'SELECT version FROM plugin_config') !== false) {
			return '3.0.0';
		}

		if (strpos($sql, 'SELECT * FROM plugin_intropage_user_auth') !== false) {
			return [
				['user_id' => '5', 'login_opts' => '0', 'alert_host' => 'on', 'retired_panel' => 'on'],
				['user_id' => '6', 'login_opts' => '0', 'alert_host' => '', 'retired_panel' => 'on'],
			];
		}

		if (strpos($sql, 'SHOW COLUMNS FROM plugin_intropage_user_auth') !== false) {
			return [['Field' => 'user_id'], ['Field' => 'login_opts'], ['Field' => 'permissions']];
		}

		return $kind === 'assoc' ? [] : '';
	};

	$GLOBALS['config']['base_path'] = $base;

	try {
		intropage_upgrade_database();
	} finally {
		$GLOBALS['config']['base_path'] = $restore;
		unset($GLOBALS['__test_query']);
	}

	$updates = intropage_upgrade_user_auth_updates();

	expect($updates)->toHaveCount(2);

	$first = json_decode($updates[0]['params'][0], true);

	expect($updates[0]['params'][1])->toBe('5');
	expect($first)->toHaveKey('favourite_graph', 'on');
	expect($first)->not->toHaveKey('retired_panel');
	expect($first)->not->toHaveKey('user_id');
	expect($first)->not->toHaveKey('login_opts');

	$second = json_decode($updates[1]['params'][0], true);

	expect($updates[1]['params'][1])->toBe('6');
	expect($second)->toHaveKey('favourite_graph', 'on');
});
