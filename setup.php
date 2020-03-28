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
	api_plugin_register_hook('intropage', 'graph_buttons', 'intropage_graph_button', 'include/helpers.php');
	api_plugin_register_hook('intropage', 'graph_buttons_thumbnails', 'intropage_graph_button', 'include/helpers.php');
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

	// poller stats
	$stats = db_fetch_assoc('SELECT id, total_time, date_sub(last_update, interval round(total_time) second) AS start
		FROM poller
		ORDER BY id
		LIMIT 5');

	foreach ($stats as $stat) {
		db_execute_prepared("REPLACE INTO plugin_intropage_trends
			(name, cur_timestamp, value) VALUES
			('poller', ?, ?)",
			array($stat['start'], $stat['id'] . ':' . round($stat['total_time'])));
	}

	// CPU load - linux only
	if (!stristr(PHP_OS, 'win')) {
		$load    = sys_getloadavg();
		$load[0] = round($load[0], 2);

		db_execute_prepared('REPLACE INTO plugin_intropage_trends
			(name, cur_timestamp, value) VALUES
			("cpuload", ?, ?)',
			array($stat['start'], $load[0]));
	}

	// failed polls
	$count = db_fetch_cell('SELECT sum(failed_polls) FROM host;');
	db_execute_prepared('REPLACE INTO plugin_intropage_trends
		(name, value) VALUES (?, ?)',
		array('failed_polls', $count));


	// cleaning old data
	db_execute("DELETE FROM plugin_intropage_trends
		WHERE cur_timestamp < date_sub(now(), INTERVAL 2 DAY) AND
		name IN ('poller','cpuload','failed_polls','host','thold','poller_output','syslog_incoming','syslog_total','syslog_alert')");

	// trends - all hosts without permissions!!!
	db_execute("REPLACE INTO plugin_intropage_trends
		(name, value)
		SELECT 'host', COUNT(id)
		FROM host
		WHERE status='1'
		AND disabled=''");

	if (db_fetch_cell("SELECT directory FROM plugin_config WHERE directory='thold' AND status=1")) {
		db_execute("REPLACE INTO plugin_intropage_trends
			(name,value)
			SELECT 'thold', COUNT(*)
			FROM thold_data
			WHERE thold_data.thold_alert!=0
			OR thold_data.bl_fail_count >= thold_data.bl_fail_trigger");
	}

	// automatic autorefresh
	db_execute("UPDATE plugin_intropage_trends
		SET cur_timestamp=now() where name = 'ar_poller_finish'");

	// check NTP
	$last = db_fetch_cell("SELECT UNIX_TIMESTAMP(value)
		FROM plugin_intropage_trends
		WHERE name='ntp_testdate'");

	if (time() > ($last + read_config_option('intropage_ntp_interval')))	{
	    include_once($config['base_path'] . '/plugins/intropage/include/helpers.php');
	    ntp_time2();
	}

	// plugin syslog
	if (db_fetch_cell("SELECT directory FROM plugin_config WHERE directory='syslog' and status=1")) {

		$line = syslog_db_fetch_row("SHOW TABLE STATUS LIKE 'syslog_incoming'");
		$i_rows = $line['Auto_increment'];
		$line = syslog_db_fetch_row("SHOW TABLE STATUS LIKE 'syslog'");
		$total_rows = $line['Auto_increment'];
		$alert_rows = syslog_db_fetch_cell('SELECT ifnull(sum(count),0) FROM syslog_logs WHERE
			logtime > date_sub(now(), INTERVAL ' . read_config_option('poller_interval') .' SECOND)');

/*
		$last_inc = db_fetch_cell("SELECT ifnull(value,0) FROM plugin_intropage_trends WHERE name='syslog_incoming' ORDER BY cur_timestamp DESC LIMIT 1");
		$last_tot = db_fetch_cell("SELECT ifnull(value,0) FROM plugin_intropage_trends WHERE name='syslog_total' ORDER BY cur_timestamp DESC LIMIT 1");
		$last_ale = db_fetch_cell("SELECT ifnull(value,0) FROM plugin_intropage_trends WHERE name='syslog_alert' ORDER BY cur_timestamp DESC LIMIT 1");

		if (db_fetch_cell("SELECT count(value) FROM plugin_intropage_trends WHERE name='syslog_total'") == 1)	{
			db_execute("UPDATE plugin_intropage_trends SET value=0 WHERE name='syslog_incoming'");
			db_execute("UPDATE plugin_intropage_trends SET value=0 WHERE name='syslog_total'");
			db_execute("UPDATE plugin_intropage_trends SET value=0 WHERE name='syslog_alert'");
		}

		db_execute("INSERT INTO plugin_intropage_trends (name,value) VALUES ('syslog_incoming','" . ($i_rows - $last_inc) . "')");
		db_execute("INSERT INTO plugin_intropage_trends (name,value) VALUES ('syslog_total','" . ($total_rows - $last_tot) . "')");
		db_execute("INSERT INTO plugin_intropage_trends (name,value) VALUES ('syslog_alert','" . ($alert_rows - $last_ale) . "')");
*/
		db_execute("INSERT INTO plugin_intropage_trends (name,value) VALUES ('syslog_incoming','" . $i_rows . "')");
		db_execute("INSERT INTO plugin_intropage_trends (name,value) VALUES ('syslog_total','" . $total_rows . "')");
		db_execute("INSERT INTO plugin_intropage_trends (name,value) VALUES ('syslog_alert','" . $alert_rows . "')");


	}

	// check db
	if (read_config_option('intropage_analyse_db_interval') > 0)	{
	    $last = db_fetch_cell("SELECT UNIX_TIMESTAMP(value)
		FROM plugin_intropage_trends
		WHERE name='db_check_testdate'");

	    if (time() > ($last + read_config_option('intropage_analyse_db_interval')))	{
		include_once($config['base_path'] . '/plugins/intropage/include/helpers.php');
		db_check();
	    }
	}


	// check poller_table is empty?
	$count = db_fetch_cell("SELECT COUNT(*) FROM poller_output");

	db_execute_prepared('REPLACE INTO plugin_intropage_trends
		(name, value) VALUES (?, ?)',
		array('poller_output', $count));
}

