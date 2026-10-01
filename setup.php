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

/**
 * Return the CSP nonce attribute for inline <script> tags, safely across
 * Cacti versions. Newer Cacti releases enforce a Content-Security-Policy that
 * requires a per-request nonce on parser-inserted scripts; older releases lack
 * the CactiSecureHeaders class, so this returns an empty string there.
 *
 * @return string The nonce attribute when supported, otherwise empty string.
 */
function plugin_intropage_csp_nonce(): string {
	if (class_exists('CactiSecureHeaders')) {
		return CactiSecureHeaders::getNonceAttribute();
	}

	return '';
}

/**
 * Plugin install hook: registers all of this plugin's Cacti hooks
 * (settings/arrays, login options, header tabs, console/page-head
 * rendering, graph buttons, poller_bottom, and the full set of
 * user/user-group admin lifecycle hooks), registers its two admin
 * realms, and creates its database tables. Called by Cacti's plugin
 * architecture when the plugin is installed.
 *
 * @return void
 */
function plugin_intropage_install(): void {
	api_plugin_register_hook('intropage', 'config_settings', 'intropage_config_settings', 'includes/settings.php');
	api_plugin_register_hook('intropage', 'config_arrays', 'intropage_config_arrays', 'setup.php');

	api_plugin_register_hook('intropage', 'login_options_navigate', 'intropage_login_options_navigate', 'includes/settings.php');

	api_plugin_register_hook('intropage', 'top_header_tabs', 'intropage_show_tab', 'includes/tab.php');
	api_plugin_register_hook('intropage', 'top_graph_header_tabs', 'intropage_show_tab', 'includes/tab.php');

	api_plugin_register_hook('intropage', 'console_after', 'intropage_console_after', 'includes/settings.php');
	api_plugin_register_hook('intropage', 'page_head', 'intropage_page_head', 'setup.php');

	api_plugin_register_hook('intropage', 'graph_buttons', 'intropage_graph_button', 'includes/functions.php');
	api_plugin_register_hook('intropage', 'graph_buttons_thumbnails', 'intropage_graph_button', 'includes/functions.php');

	// need for collecting poller time
	api_plugin_register_hook('intropage', 'poller_bottom', 'intropage_poller_bottom', 'setup.php');

	// user and user group hooks
	api_plugin_register_hook('intropage', 'user_admin_tab', 'intropage_user_admin_tab', 'includes/settings.php');
	api_plugin_register_hook('intropage', 'user_admin_run_action', 'intropage_user_admin_run_action', 'includes/settings.php');
	api_plugin_register_hook('intropage', 'user_admin_user_save', 'intropage_user_admin_user_save', 'includes/settings.php');
	api_plugin_register_hook('intropage', 'user_remove', 'intropage_user_remove', 'setup.php');
	api_plugin_register_hook('intropage', 'user_group_admin_tab', 'intropage_user_group_admin_tab', 'includes/settings.php');
	api_plugin_register_hook('intropage', 'user_group_admin_run_action', 'intropage_user_group_admin_run_action', 'includes/settings.php');
	api_plugin_register_hook('intropage', 'user_group_admin_save', 'intropage_user_group_admin_save', 'includes/settings.php');
	api_plugin_register_hook('intropage', 'user_group_remove', 'intropage_user_group_remove', 'setup.php');

	// default permission for new user
	api_plugin_register_hook('intropage', 'copy_user', 'intropage_copy_user', 'includes/settings.php');
	api_plugin_register_hook('intropage', 'user_admin_setup_sql_save', 'intropage_user_admin_setup_sql_save', 'includes/settings.php');

	api_plugin_register_realm('intropage', 'intropage.php', 'Intropage Viewer', 1);
	api_plugin_register_realm('intropage', 'intropage_admin.php', 'Intropage Administration', 1);

	$realms = [
		__('Intropage Viewer', 'intropage'),
		__('Intropage Administration', 'intropage')
	];

	intropage_setup_database();
}

/**
 * Plugin uninstall hook: drops all of this plugin's database tables.
 * Called by Cacti's plugin architecture when the plugin is
 * uninstalled.
 *
 * @return void
 *
 * @global array $config Cacti global configuration array; used to
 *                       include the database library.
 */
function plugin_intropage_uninstall(): void {
	global $config;

	include_once($config['base_path'] . '/plugins/intropage/includes/database.php');

	intropage_drop_database();
}

/**
 * Config_arrays hook: augments Cacti's role system to grant the
 * Intropage realms to the Normal User/System Administration roles, and
 * initializes the trend-timespan and refresh-interval option lists
 * (filtering out intervals shorter than the configured poller
 * interval). Called by Cacti's plugin framework via the
 * 'config_arrays' hook on every page load.
 *
 * @return void
 *
 * @global array $intropage_intervals Populated here with the map of
 *                                    refresh interval (seconds) =>
 *                                    display label, filtered to only
 *                                    include intervals >= the poller
 *                                    interval.
 * @global array $trend_timespans     Populated here with the map of
 *                                    trend timespan (seconds) =>
 *                                    display label.
 * @global array $panel_lines         Reserved/declared for parity with
 *                                    other functions in this file; not
 *                                    used directly here.
 */
function intropage_config_arrays(): void {
	global $intropage_intervals, $trend_timespans, $panel_lines;

	// Core builds $user_auth_roles with __('Normal User') in the core domain
	// (include/global_arrays.php), and auth_augment_roles() indexes by that
	// exact string. Adding the intropage domain here would key a new role on
	// translated installs and silently drop the realm.
	auth_augment_roles(__('Normal User'), ['intropage.php']);
	auth_augment_roles(__('System Administration'), ['intropage_admin.php']);

	$trend_timespans = [
		3600   => __('Timespan Last 1 Hour', 'intropage'),
		7200   => __('Timespan Last %d Hours', 2, 'intropage'),
		10800  => __('Timespan Last %d Hours', 3, 'intropage'),
		14400  => __('Timespan Last %d Hours', 4, 'intropage'),
		21600  => __('Timespan Last %d Hours', 6, 'intropage'),
		43200  => __('Timespan Last %d Hours', 12, 'intropage'),
		86400  => __('Timespan Last 1 Day', 'intropage'),
		172800 => __('Timespan Last 2 Days', 'intropage')
	];

	$poller_interval = read_config_option('poller_interval');

	$intropage_intervals = [
		'10'    => __('%d Seconds', 10, 'intropage'),
		'15'    => __('%d Seconds', 15, 'intropage'),
		'20'    => __('%d Seconds', 20, 'intropage'),
		'30'    => __('%d Seconds', 30, 'intropage'),
		'60'    => __('%d Minute', 1, 'intropage'),
		'120'   => __('%d Minutes', 2, 'intropage'),
		'180'   => __('%d Minutes', 3, 'intropage'),
		'240'   => __('%d Minutes', 4, 'intropage'),
		'300'   => __('%d Minutes', 5, 'intropage'),
		'600'   => __('%d Minutes', 10, 'intropage'),
		'900'   => __('%d Minutes', 15, 'intropage'),
		'1200'  => __('%d Minutes', 20, 'intropage'),
		'1800'  => __('%d Minutes', 30, 'intropage'),
		'3600'  => __('%d Hour', 1, 'intropage'),
		'7200'  => __('%d Hours', 2, 'intropage'),
		'14400' => __('%d Hours', 4, 'intropage'),
		'28800' => __('%d Hours', 8, 'intropage'),
		'43200' => __('%d Hours', 12, 'intropage'),
		'86400' => __('%d Day', 1, 'intropage')
	];

	foreach ($intropage_intervals as $key => $name) {
		if ($key < $poller_interval) {
			unset($intropage_intervals[$key]);
		}
	}
}

/**
 * Reads and returns this plugin's version/author/metadata info from its
 * INFO file. Called wherever plugin metadata is needed (e.g.
 * intropage_upgrade_database()).
 *
 * @return array The plugin's info array, as parsed from the INFO
 *               file's '[info]' section.
 *
 * @global array $config Cacti global configuration array; used to
 *                       locate the plugin's INFO file.
 */
function plugin_intropage_version(): array {
	global $config;

	$info = parse_ini_file($config['base_path'] . '/plugins/intropage/INFO', true);

	if (!is_array($info) || !isset($info['info']) || !is_array($info['info'])) {
		return [];
	}

	return $info['info'];
}

/**
 * Plugin upgrade hook: brings the plugin's schema up to date by
 * delegating to intropage_check_upgrade(). Called by Cacti's plugin
 * architecture when the plugin is upgraded to a new version.
 *
 * @return bool Always false.
 */
function plugin_intropage_upgrade(): bool {
	// Here we will upgrade to the newest version
	intropage_check_upgrade();

	return false;
}

/**
 * Plugin config-check hook: ensures the plugin's schema is up to date
 * by delegating to intropage_check_upgrade(). Called by Cacti's plugin
 * architecture on relevant page loads.
 *
 * @return bool Always true.
 */
function plugin_intropage_check_config(): bool {
	// Here we will check to ensure everything is configured
	intropage_check_upgrade();

	return true;
}

/**
 * Includes the database library and triggers a schema-version check/
 * upgrade. Called from plugin_intropage_upgrade() and
 * plugin_intropage_check_config().
 *
 * @return void
 *
 * @global array $config Cacti global configuration array; used to
 *                       include the database library.
 */
function intropage_check_upgrade(): void {
	global $config;

	include_once($config['base_path'] . '/plugins/intropage/includes/database.php');

	intropage_upgrade_database();
}

/**
 * Removes files and directories that a previous version of this plugin
 * shipped but that have since moved or been deleted, using the tombstone
 * and whitelist lists in manifest.json. Whitelisted (user-data) paths and
 * any VCS metadata (.git*) are never touched; the dev-only tests/ tree is
 * removed. Any path that resolves outside the plugin directory (a tampered
 * manifest.json) is refused, and any file/directory that cannot be removed
 * (e.g. read-only) is reported to the Cacti log. Any top-level entry that is
 * neither expected nor a tombstone nor whitelisted is logged to the Cacti
 * log and left in place. Called from intropage_upgrade_database() on a
 * version change.
 *
 * @return void
 *
 * @global array $config Cacti global configuration array; used to resolve
 *                       the plugin directory.
 */
function intropage_prune_files(): void {
	global $config;

	$plugin_dir    = $config['base_path'] . '/plugins/intropage';
	$manifest_path = $plugin_dir . '/manifest.json';

	if (!is_readable($manifest_path)) {
		return;
	}

	$manifest = json_decode((string) file_get_contents($manifest_path), true);

	if (!is_array($manifest)) {
		cacti_log('WARNING: intropage manifest.json could not be parsed; skipping file prune', false, 'INTROPAGE');

		return;
	}

	$tombstones = isset($manifest['tombstones']) && is_array($manifest['tombstones']) ? $manifest['tombstones'] : [];
	$expected   = isset($manifest['expected'])   && is_array($manifest['expected'])   ? $manifest['expected']   : [];
	$whitelist  = isset($manifest['whitelist'])  && is_array($manifest['whitelist'])  ? $manifest['whitelist']  : [];

	$protected = function (string $rel) use ($whitelist): bool {
		if (strncmp($rel, '.git', 4) === 0 || strncmp($rel, '.md', 3) === 0) {
			return true;
		}

		foreach ($whitelist as $entry) {
			$entry = trim((string) $entry, '/');

			if ($entry !== '' && ($rel === $entry
				|| strncmp($rel, $entry . '/', strlen($entry) + 1) === 0
				|| strncmp($entry, $rel . '/', strlen($rel) + 1) === 0)) {
				return true;
			}
		}

		return false;
	};

	// Security: resolve the plugin directory so a tampered manifest.json
	// cannot steer the prune outside of it.
	$plugin_real = realpath($plugin_dir);

	// Remove tombstoned (moved/deleted) paths plus the dev-only tests/
	// tree and the phpunit.xml test configuration.
	$remove   = $tombstones;
	$remove[] = 'tests/';
	$remove[] = 'phpunit.xml';

	foreach ($remove as $rel) {
		$rel = trim((string) $rel, '/');

		if ($rel === '' || $protected($rel)) {
			continue;
		}

		// A tombstone must never contain '.'/'..' segments; a tampered manifest
		// could use them to escape the plugin directory or target its root.
		$segments = explode('/', $rel);

		if (in_array('.', $segments, true) || in_array('..', $segments, true)) {
			cacti_log(sprintf('WARNING: intropage prune refused to remove %s: path contains a traversal segment (tampered manifest.json?)', $rel), false, 'INTROPAGE');

			continue;
		}

		$path = $plugin_dir . '/' . $rel;

		if (!is_link($path) && !file_exists($path)) {
			continue;
		}

		// Refuse any path that, after resolving symlinks and ../ segments,
		// escapes the plugin directory (protects user data from a tampered
		// manifest.json).
		$anchor = is_link($path) ? dirname($path) : $path;
		$real   = realpath($anchor);

		if ($real === false || ($real !== $plugin_real && strncmp($real, $plugin_real . DIRECTORY_SEPARATOR, strlen((string) $plugin_real) + 1) !== 0)) {
			cacti_log(sprintf('WARNING: intropage prune refused to remove %s: path resolves outside the plugin directory (tampered manifest.json?)', $rel), false, 'INTROPAGE');

			continue;
		}

		if (is_dir($path) && !is_link($path)) {
			$removed = intropage_rmtree($path);
		} else {
			$removed = @unlink($path);
		}

		if (!$removed) {
			cacti_log(sprintf('WARNING: intropage upgrade could not remove %s (check file/directory permissions)', $rel), false, 'INTROPAGE');
		}
	}

	// Surface any top-level entry the manifest does not account for.
	$known = [];

	foreach (array_merge($expected, $tombstones) as $entry) {
		$top = explode('/', trim((string) $entry, '/'))[0];

		if ($top !== '') {
			$known[$top] = true;
		}
	}

	$entries = scandir($plugin_dir);

	foreach (($entries !== false ? $entries : []) as $entry) {
		if ($entry === '.' || $entry === '..' || $entry === 'tests' || $entry === 'phpunit.xml' || $protected($entry) || isset($known[$entry])) {
			continue;
		}

		cacti_log(sprintf('WARNING: intropage upgrade found a file/directory not described in manifest.json: %s (left in place)', $entry), false, 'INTROPAGE');
	}
}

/**
 * Recursively deletes a directory and its contents. Symlinks are removed
 * without being followed. Helper for intropage_prune_files().
 *
 * @param string $dir Absolute path to the directory to remove.
 *
 * @return bool True if the directory and everything under it was removed;
 *              false if any entry could not be deleted.
 */
function intropage_rmtree(string $dir): bool {
	$entries = scandir($dir);
	$ok      = true;

	foreach (($entries !== false ? $entries : []) as $entry) {
		if ($entry === '.' || $entry === '..') {
			continue;
		}

		$path = $dir . '/' . $entry;

		if (is_dir($path) && !is_link($path)) {
			if (!intropage_rmtree($path)) {
				$ok = false;
			}
		} elseif (!@unlink($path)) {
			$ok = false;
		}
	}

	if (!@rmdir($dir)) {
		$ok = false;
	}

	return $ok;
}

/**
 * Page_head hook: emits the &lt;link&gt; tags for the plugin's common
 * stylesheet and, if present, the currently selected theme's
 * stylesheet. Called by Cacti's page rendering via the 'page_head'
 * hook.
 *
 * @return void
 *
 * @global array $config Cacti global configuration array; used to
 *                       build the stylesheet URLs and check for the
 *                       theme file's existence.
 */
function intropage_page_head(): void {
	global $config;

	$selectedTheme = get_selected_theme();

	print get_md5_include_css('plugins/intropage/css/common.css');

	if (file_exists($config['base_path'] . '/plugins/intropage/css/' . $selectedTheme . '.css')) {
		print get_md5_include_css('plugins/intropage/css/' . $selectedTheme . '.css');
	}
}

/**
 * Includes the database library and creates this plugin's database
 * tables. Called from plugin_intropage_install().
 *
 * @return void
 *
 * @global array $config Cacti global configuration array; used to
 *                       include the database library.
 */
function intropage_setup_database(): void {
	global $config;

	include_once($config['base_path'] . '/plugins/intropage/includes/database.php');

	intropage_initialize_database();
}

/**
 * Poller_bottom hook: launches this plugin's poller_intropage.php
 * script as a background process at the end of each Cacti polling
 * cycle, to collect poller performance data for its trend panels.
 * Called by Cacti's poller via the 'poller_bottom' hook.
 *
 * @return void
 *
 * @global array $config Cacti global configuration array; used to
 *                       locate this plugin's poller script and include
 *                       the poller library.
 */
function intropage_poller_bottom(): void {
	global $config;

	include_once($config['library_path'] . '/poller.php');

	$command_string = trim(read_config_option('path_php_binary'));

	if (trim($command_string) == '') {
		$command_string = 'php';
	}

	$extra_args = ' -q ' . $config['base_path'] . '/plugins/intropage/poller_intropage.php';

	exec_background($command_string, $extra_args);
}

// add third party panel:
// 1) include this file
// 2) call intropage_add_panel('my_panel','/plugins/your_plugin/file.php','yes',3600,20) {
// panel_id - your name (lowercase, without spaces, unique)
// file - path to your code. It must contain function my_panel() (and my_panel_detail() if your panel has detail)
// example functions are in /plugin/intropage/include/data.php and data_detail.php
// has_detail - yes or no
// refresh_interval - in second, min is 60
// priority - for displaying
// description - small description, it is visible in user auth settings
/**
 * Public extension API: registers a third-party dashboard panel by
 * inserting its definition and adding a corresponding per-user
 * visibility column to the user-auth table. Intended to be called by
 * other plugins wishing to add their own Intropage panel.
 *
 * @param string $panel_id         A unique, lowercase, space-free
 *                                 identifier for the panel.
 * @param string $file             Path to the file containing the
 *                                 panel's rendering function(s).
 * @param string $has_detail       'yes' or 'no', whether the panel has
 *                                 a detail view.
 * @param int    $refresh_interval The panel's refresh interval in
 *                                 seconds (minimum 60).
 * @param int    $priority         The panel's display priority/order.
 * @param string $description      A short description shown in user
 *                                 auth settings.
 *
 * @return string '1' on success, or the database error string on
 *                failure.
 */
function intropage_add_panel(string $panel_id, string $file, string $has_detail, int $refresh_interval, int $priority = 20, string $description = ''): string {
	if (db_execute_prepared('REPLACE INTO plugin_intropage_panel_definition
		(panel_id,file,has_detail,refresh_interval, priority, description)
		VALUES (?,?,?,?,?,?)', [$panel_id, $file, $has_detail, $refresh_interval, $priority, $description]) == 1) {
		api_plugin_db_add_column('intropage', 'plugin_intropage_user_auth', ['name' => $panel_id, 'type' => 'char(2)', 'NULL' => false, 'default' => 'on']);

		return ('1');
	} else {
		return db_error();
	}
}

// remove third party panel
/**
 * Public extension API: unregisters a third-party dashboard panel,
 * removing its stored data, definition, and per-user visibility column.
 * Intended to be called by other plugins removing their own Intropage
 * panel.
 *
 * @param string $panel_id The panel identifier previously registered
 *                         via intropage_add_panel().
 *
 * @return string Always '1'.
 */
function intropage_remove_panel(string $panel_id): string {
	db_execute_prepared('DELETE FROM plugin_intropage_panel_data WHERE panel_id = ?', [$panel_id]);
	db_execute_prepared('DELETE FROM plugin_intropage_panel_definition WHERE panel_id = ?', [$panel_id]);
	db_execute_prepared('ALTER TABLE plugin_intropage_user_auth DROP ?',[$panel_id]);

	return ('1');
}

/**
 * User_remove hook: cleans up all of this plugin's per-user data
 * (panel data/dashboard associations, settings, auth) for a deleted
 * user. Called by Cacti's user admin via the 'user_remove' hook.
 *
 * @param int $user_id The id of the user being removed.
 *
 * @return void
 */
function intropage_user_remove($user_id): void {
	db_execute_prepared('DELETE FROM plugin_intropage_panel_data WHERE user_id = ?', [$user_id]);
	db_execute_prepared('DELETE FROM plugin_intropage_panel_dashboard WHERE user_id = ?', [$user_id]);
	db_execute_prepared('DELETE FROM settings_user WHERE user_id = ?', [$user_id]);
	db_execute_prepared('DELETE FROM plugin_intropage_user_auth WHERE user_id = ?', [$user_id]);
}

/**
 * User_group_remove hook: cleans up this plugin's stored
 * authorization data for a deleted user group. Called by Cacti's user
 * admin via the 'user_group_remove' hook.
 *
 * @param int $group_id The id of the user group being removed.
 *
 * @return void
 */
function intropage_user_group_remove($group_id): void {
	db_execute_prepared('DELETE FROM plugin_intropage_user_group_auth WHERE id = ?', [$group_id]);
}
