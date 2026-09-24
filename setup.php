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
 * Plugin install hook: registers all of this plugin's Cacti hooks
 * (settings/arrays, login options, header tabs, console/page-head
 * rendering, graph buttons, poller_bottom, and the full set of
 * user/user-group admin lifecycle hooks), registers its two admin
 * realms, and creates its database tables. Called by Cacti's plugin
 * architecture when the plugin is installed.
 *
 * @return void
 */
function plugin_intropage_install() {
	api_plugin_register_hook('intropage', 'config_settings', 'intropage_config_settings', 'include/settings.php');
	api_plugin_register_hook('intropage', 'config_arrays', 'intropage_config_arrays', 'setup.php');

	api_plugin_register_hook('intropage', 'login_options_navigate', 'intropage_login_options_navigate', 'include/settings.php');

	api_plugin_register_hook('intropage', 'top_header_tabs', 'intropage_show_tab', 'include/tab.php');
	api_plugin_register_hook('intropage', 'top_graph_header_tabs', 'intropage_show_tab', 'include/tab.php');

	api_plugin_register_hook('intropage', 'console_after', 'intropage_console_after', 'include/settings.php');
	api_plugin_register_hook('intropage', 'page_head', 'intropage_page_head', 'setup.php');

	api_plugin_register_hook('intropage', 'graph_buttons', 'intropage_graph_button', 'include/functions.php');
	api_plugin_register_hook('intropage', 'graph_buttons_thumbnails', 'intropage_graph_button', 'include/functions.php');

	// need for collecting poller time
	api_plugin_register_hook('intropage', 'poller_bottom', 'intropage_poller_bottom', 'setup.php');

	// user and user group hooks
	api_plugin_register_hook('intropage', 'user_admin_tab', 'intropage_user_admin_tab', 'include/settings.php');
	api_plugin_register_hook('intropage', 'user_admin_run_action', 'intropage_user_admin_run_action', 'include/settings.php');
	api_plugin_register_hook('intropage', 'user_admin_user_save', 'intropage_user_admin_user_save', 'include/settings.php');
	api_plugin_register_hook('intropage', 'user_remove', 'intropage_user_remove', 'setup.php');
	api_plugin_register_hook('intropage', 'user_group_admin_tab', 'intropage_user_group_admin_tab', 'include/settings.php');
	api_plugin_register_hook('intropage', 'user_group_admin_run_action', 'intropage_user_group_admin_run_action', 'include/settings.php');
	api_plugin_register_hook('intropage', 'user_group_admin_save', 'intropage_user_group_admin_save', 'include/settings.php');
	api_plugin_register_hook('intropage', 'user_group_remove', 'intropage_user_group_remove', 'setup.php');

	// default permission for new user
	api_plugin_register_hook('intropage', 'copy_user', 'intropage_copy_user', 'include/settings.php');
	api_plugin_register_hook('intropage', 'user_admin_setup_sql_save', 'intropage_user_admin_setup_sql_save', 'include/settings.php');

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
function plugin_intropage_uninstall() {
	global $config;

	include_once($config['base_path'] . '/plugins/intropage/include/database.php');

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
function intropage_config_arrays() {
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
 *              file's '[info]' section.
 *
 * @global array $config Cacti global configuration array; used to
 *                       locate the plugin's INFO file.
 */
function plugin_intropage_version() {
	global $config;

	$info = parse_ini_file($config['base_path'] . '/plugins/intropage/INFO', true);

	return $info['info'];
}

/**
 * Plugin upgrade hook: brings the plugin's schema up to date by
 * delegating to intropage_check_upgrade(). Called by Cacti's plugin
 * architecture when the plugin is upgraded to a new version.
 *
 * @return bool Always false.
 */
function plugin_intropage_upgrade() {
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
function plugin_intropage_check_config() {
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
function intropage_check_upgrade() {
	global $config;

	include_once($config['base_path'] . '/plugins/intropage/include/database.php');

	intropage_upgrade_database();
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
function intropage_page_head() {
	global $config;

	$selectedTheme = get_selected_theme();

	print "<link type='text/css' href='" . $config['url_path'] . "plugins/intropage/themes/common.css' rel='stylesheet'>";

	if (file_exists($config['base_path'] . '/plugins/intropage/themes/' . $selectedTheme . '.css')) {
		print "<link type='text/css' href='" . $config['url_path'] . 'plugins/intropage/themes/' . $selectedTheme . ".css' rel='stylesheet'>";
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
function intropage_setup_database() {
	global $config;

	include_once($config['base_path'] . '/plugins/intropage/include/database.php');

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
function intropage_poller_bottom() {
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
 *                                identifier for the panel.
 * @param string $file             Path to the file containing the
 *                                panel's rendering function(s).
 * @param string $has_detail       'yes' or 'no', whether the panel has
 *                                a detail view.
 * @param int    $refresh_interval The panel's refresh interval in
 *                                seconds (minimum 60).
 * @param int    $priority         The panel's display priority/order.
 * @param string $description     A short description shown in user
 *                                auth settings.
 *
 * @return string '1' on success, or the database error string on
 *               failure.
 */
function intropage_add_panel($panel_id, $file, $has_detail, $refresh_interval, $priority = 20, $description = '') {
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
 *                        via intropage_add_panel().
 *
 * @return string Always '1'.
 */
function intropage_remove_panel($panel_id) {
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
function intropage_user_remove($user_id) {
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
function intropage_user_group_remove($group_id) {
	db_execute_prepared('DELETE FROM plugin_intropage_user_group_auth WHERE id = ?', [$group_id]);
}
