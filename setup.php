<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2015-2020 Petr Macek                                      |
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

function plugin_intropage_install() {
	api_plugin_register_hook('intropage', 'config_form', 'intropage_config_form', 'include/settings.php');
	api_plugin_register_hook('intropage', 'config_settings', 'intropage_config_settings', 'include/settings.php');
	api_plugin_register_hook('intropage', 'login_options_navigate', 'intropage_login_options_navigate', 'include/settings.php');
	api_plugin_register_hook('intropage', 'top_header_tabs', 'intropage_show_tab', 'include/tab.php');
	api_plugin_register_hook('intropage', 'top_graph_header_tabs', 'intropage_show_tab', 'include/tab.php');
	api_plugin_register_hook('intropage', 'console_after', 'intropage_console_after', 'include/settings.php');
	api_plugin_register_hook('intropage', 'page_head', 'intropage_page_head', 'setup.php');
	api_plugin_register_hook('intropage', 'user_admin_setup_sql_save', 'intropage_user_admin_setup_sql_save', 'include/settings.php');
	api_plugin_register_hook('intropage', 'user_group_admin_setup_sql_save', 'intropage_user_group_admin_setup_sql_save', 'include/settings.php');
	api_plugin_register_hook('intropage', 'graph_buttons', 'intropage_graph_button', 'include/functions.php');
	api_plugin_register_hook('intropage', 'graph_buttons_thumbnails', 'intropage_graph_button', 'include/functions.php');
	// need for collecting poller time
	api_plugin_register_hook('intropage', 'poller_bottom', 'intropage_poller_bottom', 'setup.php');

	api_plugin_register_realm('intropage', 'intropage.php,intropage_ajax.php', 'Plugin Intropage - view', 1);
	intropage_setup_database();
}

function plugin_intropage_uninstall() {
	global $config;

	include_once($config['base_path'] . '/plugins/intropage/include/database.php');
	intropage_drop_database();
}

function plugin_intropage_version() {
	global $config;
	$info = parse_ini_file($config['base_path'] . '/plugins/intropage/INFO', true);
	return $info['info'];
}

function plugin_intropage_upgrade() {
	// Here we will upgrade to the newest version
	intropage_check_upgrade();
	return false;
}

function plugin_intropage_check_config() {
	// Here we will check to ensure everything is configured
	intropage_check_upgrade();
	return true;
}

function intropage_check_upgrade() {
	global $config;

	include_once($config['base_path'] . '/plugins/intropage/include/database.php');
	intropage_upgrade_database();
}

function intropage_page_head() {
	global $config;

	$selectedTheme = get_selected_theme();

	// style for panels
	print "<link type='text/css' href='" . $config['url_path'] . "plugins/intropage/themes/common.css' rel='stylesheet'>";

	if (file_exists($config['base_path'] . '/plugins/intropage/themes/' . $selectedTheme . '.css')) {
		print "<link type='text/css' href='" . $config['url_path'] . 'plugins/intropage/themes/' . $selectedTheme . ".css' rel='stylesheet'>";
	}
}

function intropage_setup_database() {
	global $config;

	include_once($config['base_path'] . '/plugins/intropage/include/database.php');

	intropage_initialize_database();
}

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


// !!!! mozna tohle dat do samostatneho souboru
function intropage_add_panel($panelid, $panelJSON) {
    // insert into plugin_intropage_panel_definition
    // pridat sloupecek do user_auth
    //return last_inserted_id;
}


function intropage_remove_panel($panelid) {
	db_execute("DELETE FROM plugin_intropage_panel_data WHERE panelid='$panelid'");
	db_execute("DELETE FROM plugin_intropage_panel_definition WHERE panelid='$panelid'");

}

