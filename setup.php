<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2015-2019 Petr Macek                                      |
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

	api_plugin_register_hook('intropage', 'user_admin_setup_sql_save', 'intropage_user_admin_setup_sql_save', 'include/settings.php');
	api_plugin_register_hook('intropage', 'user_group_admin_setup_sql_save', 'intropage_user_group_admin_setup_sql_save', 'include/settings.php');

	api_plugin_register_hook('intropage', 'graph_buttons', 'intropage_graph_button', 'include/helpers.php');
	api_plugin_register_hook('intropage', 'graph_buttons_thumbnails', 'intropage_graph_button', 'include/helpers.php');

	api_plugin_register_realm('intropage', 'intropage.php,intropage_ajax.php', 'Plugin Intropage - view', 1);

	// need for collecting poller time
	api_plugin_register_hook('intropage', 'poller_bottom', 'intropage_poller_bottom', 'setup.php');
	intropage_setup_database();
}

function plugin_intropage_uninstall() {
	db_execute("DELETE FROM settings WHERE name LIKE 'intropage_%'");
	db_execute('DROP TABLE plugin_intropage_user_setting');
	db_execute('DROP TABLE plugin_intropage_trends');
	db_execute('DROP TABLE plugin_intropage_panel');
	db_execute('UPDATE user_auth SET login_opts=1 WHERE login_opts > 3');
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
	// If action need to be done for upgrade, add it.

	global $config;
	$info = parse_ini_file($config['base_path'] . '/plugins/intropage/INFO', true);
	$info = $info['info'];

	$current = $info['version'];
	$oldv    = db_fetch_cell('SELECT version FROM plugin_config WHERE directory="intropage"');
	if (!cacti_version_compare($oldv, $current, '=')) {
		if (cacti_version_compare($oldv,'0.9','<')) {
			api_plugin_db_add_column('user_auth', array('name' => 'intropage_opts', 'type' => 'tinyint(1)', 'NULL' => false, 'default' => '0'));
			api_plugin_db_add_column('user_auth', array('name' => 'intropage_analyse_log', 'type' => 'char(2)', 'NULL' => false, 'default' => 'on'));
			api_plugin_db_add_column('user_auth', array('name' => 'intropage_analyse_login', 'type' => 'char(2)', 'NULL' => false, 'default' => 'on'));
			api_plugin_db_add_column('user_auth', array('name' => 'intropage_thold_event', 'type' => 'char(2)', 'NULL' => false, 'default' => 'on'));
			api_plugin_db_add_column('user_auth', array('name' => 'intropage_analyse_db', 'type' => 'char(2)', 'NULL' => false, 'default' => 'on'));
			api_plugin_db_add_column('user_auth', array('name' => 'intropage_analyse_tree_host_graph', 'type' => 'char(2)', 'NULL' => false, 'default' => 'on'));
			api_plugin_db_add_column('user_auth', array('name' => 'intropage_trend', 'type' => 'char(2)', 'NULL' => false, 'default' => 'on'));
			api_plugin_db_add_column('user_auth', array('name' => 'intropage_extrem', 'type' => 'char(2)', 'NULL' => false, 'default' => 'on'));
			api_plugin_db_add_column('user_auth', array('name' => 'intropage_ntp', 'type' => 'char(2)', 'NULL' => false, 'default' => 'on'));
			api_plugin_db_add_column('user_auth', array('name' => 'intropage_poller_info', 'type' => 'char(2)', 'NULL' => false, 'default' => 'on'));
			api_plugin_db_add_column('user_auth', array('name' => 'intropage_poller_stat', 'type' => 'char(2)', 'NULL' => false, 'default' => 'on'));
			api_plugin_db_add_column('user_auth', array('name' => 'intropage_graph_host', 'type' => 'char(2)', 'NULL' => false, 'default' => 'on'));
			api_plugin_db_add_column('user_auth', array('name' => 'intropage_graph_thold', 'type' => 'char(2)', 'NULL' => false, 'default' => 'on'));
			api_plugin_db_add_column('user_auth', array('name' => 'intropage_graph_data_source', 'type' => 'char(2)', 'NULL' => false, 'default' => 'on'));
			api_plugin_db_add_column('user_auth', array('name' => 'intropage_graph_host_template', 'type' => 'char(2)', 'NULL' => false, 'default' => 'on'));
			api_plugin_db_add_column('user_auth', array('name' => 'intropage_cpu', 'type' => 'char(2)', 'NULL' => false, 'default' => 'on'));
			api_plugin_db_add_column('user_auth', array('name' => 'intropage_mactrack', 'type' => 'char(2)', 'NULL' => false, 'default' => 'on'));
			api_plugin_db_add_column('user_auth', array('name' => 'intropage_mactrack_sites', 'type' => 'char(2)', 'NULL' => false, 'default' => 'on'));
			api_plugin_db_add_column('user_auth', array('name' => 'intropage_top5_ping', 'type' => 'char(2)', 'NULL' => false, 'default' => 'on'));
			api_plugin_db_add_column('user_auth', array('name' => 'intropage_top5_availability', 'type' => 'char(2)', 'NULL' => false, 'default' => 'on'));
			api_plugin_db_add_column('user_auth', array('name' => 'intropage_info', 'type' => 'char(2)', 'NULL' => false, 'default' => 'on'));
			api_plugin_db_add_column('user_auth', array('name' => 'intropage_boost', 'type' => 'char(2)', 'NULL' => false, 'default' => 'on'));
			api_plugin_db_add_column('user_auth', array('name' => 'intropage_favourite_graph', 'type' => 'char(2)', 'NULL' => false, 'default' => 'on'));

			db_execute('UPDATE plugin_hooks
				SET function="intropage_config_form", file="include/settings.php"
				WHERE name="intropage"
				AND hook="config_form"');

			db_execute('UPDATE plugin_hooks
				SET function="intropage_config_settings", file="include/settings.php"
				WHERE name="intropage"
				AND hook="config_settings"');

			db_execute('UPDATE plugin_hooks
				SET function="intropage_show_tab", file="include/tab.php"
				WHERE name="intropage"
				AND hook="top_header_tabs"');

			db_execute('UPDATE plugin_hooks
				SET function="intropage_show_tab", file="include/tab.php"
				WHERE name="intropage"
				AND hook="top_graph_header_tabs"');

			db_execute('UPDATE plugin_hooks
				SET function="intropage_login_options_navigate", file="include/settings.php"
				WHERE name="intropage"
				AND hook="login_options_navigate"');

			db_execute('UPDATE plugin_hooks
				SET function="intropage_console_after", file="include/settings.php"
				WHERE name="intropage"
				AND hook="console_after"');

			db_execute('UPDATE user_auth
				SET login_opts=1
				WHERE login_opts IN (4,5)');
		}

		if (cacti_version_compare($oldv,'1.8.1', '<')) {
			db_execute('ALTER TABLE plugin_intropage_trends
				CHANGE COLUMN date cur_timestamp timestamp DEFAULT current_timestamp()');
		}

		// Set the new version
		db_execute("UPDATE plugin_config
			SET version='$current'
			WHERE directory='intropage'");

		// I need it, there is also in setup database, here is for update:
		if (db_fetch_cell("SELECT COUNT(*) FROM plugin_intropage_trends WHERE name='db_check_result'") == 0) {
			db_execute("INSERT INTO plugin_intropage_trends (name,value) VALUES ('db_check_result', 'Waiting for data')");
		}

		if (db_fetch_cell("SELECT COUNT(*) FROM plugin_intropage_trends WHERE name='db_check_alarm'")== 0 ) {
			db_execute("INSERT INTO plugin_intropage_trends (name,value) VALUES ('db_check_alarm', 'yellow')");
		}

		if (db_fetch_cell("SELECT COUNT(*) FROM plugin_intropage_trends WHERE name='db_check_detail'") == 0) {
    	    db_execute("INSERT INTO plugin_intropage_trends (name,value) VALUES ('db_check_detail', NULL)");
		}

		if (db_fetch_cell("SELECT COUNT(*) FROM plugin_intropage_trends WHERE name='db_check_testdate'") == 0) {
			db_execute("INSERT INTO plugin_intropage_trends (name,value) VALUES ('db_check_testdate', NULL)");
		}

		if (db_fetch_cell("SELECT COUNT(*) FROM plugin_intropage_trends WHERE name='ntp_diff_time'") == 0) {
			db_execute("INSERT INTO plugin_intropage_trends (name,value) VALUES ('ntp_diff_time', 'Waiting for date')");
		}

		if (db_fetch_cell("SELECT COUNT(*) FROM plugin_intropage_trends WHERE name='ntp_testdate'") == 0) {
			db_execute("INSERT INTO plugin_intropage_trends (name,value) VALUES ('ntp_testdate', NULL)");
		}
	}
}

function intropage_setup_database() {
	global $config, $intropage_settings;
	api_plugin_db_add_column('intropage', 'user_auth', array('name' => 'intropage_opts', 'type' => 'tinyint(1)', 'NULL' => false, 'default' => '0'));
	api_plugin_db_add_column('intropage', 'user_auth', array('name' => 'intropage_analyse_log', 'type' => 'char(2)', 'NULL' => false, 'default' => 'on'));
	api_plugin_db_add_column('intropage', 'user_auth', array('name' => 'intropage_analyse_login', 'type' => 'char(2)', 'NULL' => false, 'default' => 'on'));
	api_plugin_db_add_column('intropage', 'user_auth', array('name' => 'intropage_thold_event', 'type' => 'char(2)', 'NULL' => false, 'default' => 'on'));
	api_plugin_db_add_column('intropage', 'user_auth', array('name' => 'intropage_analyse_db', 'type' => 'char(2)', 'NULL' => false, 'default' => 'on'));
	api_plugin_db_add_column('intropage', 'user_auth', array('name' => 'intropage_analyse_tree_host_graph', 'type' => 'char(2)', 'NULL' => false, 'default' => 'on'));
	api_plugin_db_add_column('intropage', 'user_auth', array('name' => 'intropage_trend', 'type' => 'char(2)', 'NULL' => false, 'default' => 'on'));
	api_plugin_db_add_column('intropage', 'user_auth', array('name' => 'intropage_extrem', 'type' => 'char(2)', 'NULL' => false, 'default' => 'on'));
	api_plugin_db_add_column('intropage', 'user_auth', array('name' => 'intropage_ntp', 'type' => 'char(2)', 'NULL' => false, 'default' => 'on'));
	api_plugin_db_add_column('intropage', 'user_auth', array('name' => 'intropage_poller_info', 'type' => 'char(2)', 'NULL' => false, 'default' => 'on'));
	api_plugin_db_add_column('intropage', 'user_auth', array('name' => 'intropage_poller_stat', 'type' => 'char(2)', 'NULL' => false, 'default' => 'on'));
	api_plugin_db_add_column('intropage', 'user_auth', array('name' => 'intropage_graph_host', 'type' => 'char(2)', 'NULL' => false, 'default' => 'on'));
	api_plugin_db_add_column('intropage', 'user_auth', array('name' => 'intropage_graph_thold', 'type' => 'char(2)', 'NULL' => false, 'default' => 'on'));
	api_plugin_db_add_column('intropage', 'user_auth', array('name' => 'intropage_graph_data_source', 'type' => 'char(2)', 'NULL' => false, 'default' => 'on'));
	api_plugin_db_add_column('intropage', 'user_auth', array('name' => 'intropage_graph_host_template', 'type' => 'char(2)', 'NULL' => false, 'default' => 'on'));
	api_plugin_db_add_column('intropage', 'user_auth', array('name' => 'intropage_cpu', 'type' => 'char(2)', 'NULL' => false, 'default' => 'on'));
	api_plugin_db_add_column('intropage', 'user_auth', array('name' => 'intropage_mactrack', 'type' => 'char(2)', 'NULL' => false, 'default' => 'on'));
	api_plugin_db_add_column('intropage', 'user_auth', array('name' => 'intropage_mactrack_sites', 'type' => 'char(2)', 'NULL' => false, 'default' => 'on'));
	api_plugin_db_add_column('intropage', 'user_auth', array('name' => 'intropage_top5_ping', 'type' => 'char(2)', 'NULL' => false, 'default' => 'on'));
	api_plugin_db_add_column('intropage', 'user_auth', array('name' => 'intropage_top5_availability', 'type' => 'char(2)', 'NULL' => false, 'default' => 'on'));
	api_plugin_db_add_column('intropage', 'user_auth', array('name' => 'intropage_info', 'type' => 'char(2)', 'NULL' => false, 'default' => 'on'));
	api_plugin_db_add_column('intropage', 'user_auth', array('name' => 'intropage_boost', 'type' => 'char(2)', 'NULL' => false, 'default' => 'on'));
	api_plugin_db_add_column('intropage', 'user_auth', array('name' => 'intropage_favourite_graph', 'type' => 'char(2)', 'NULL' => false, 'default' => 'on'));

	include_once($config['base_path'] . '/plugins/intropage/include/variables.php');
	$sql_insert = '';
	foreach ($intropage_settings as $key => $value) {
		if (isset($value['default']) && !db_fetch_cell("SELECT value FROM settings WHERE name='$key'")) {
			if ($sql_insert != '') {
				$sql_insert .= ',';
			}

			$sql_insert .= sprintf('(%s,%s)', db_qstr($key), db_qstr($value['default']));
		}
	}

	if ($sql_insert != '') {
		db_execute("REPLACE INTO settings (name, value) VALUES $sql_insert");
	}

	$data              = array();
	$data['columns'][] = array('name' => 'cur_timestamp', 'type' => 'timestamp');
	$data['columns'][] = array('name' => 'name', 'type' => 'varchar(50)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'value', 'type' => 'varchar(250)', 'NULL' => true, 'default' => null);
	$data['type']      = 'MyISAM';
	$data['comment']   = 'trends';
	api_plugin_db_table_create('intropage', 'plugin_intropage_trends', $data);

	// I cannot set this in definition above
	// db_execute("ALTER TABLE plugin_intropage_trends MODIFY cur_timestamp timestamp DEFAULT CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP");

	// few values
	db_execute("REPLACE INTO plugin_intropage_trends (name,value) VALUES ('db_check_result', 'Waiting for data')");
	db_execute("REPLACE INTO plugin_intropage_trends (name,value) VALUES ('db_check_alarm', 'yellow')");
	db_execute("REPLACE INTO plugin_intropage_trends (name,value) VALUES ('db_check_detail', NULL)");
	db_execute("REPLACE INTO plugin_intropage_trends (name,value) VALUES ('db_check_testdate', NULL)");
	db_execute("REPLACE INTO plugin_intropage_trends (name,value) VALUES ('ntp_diff_time', 'Waiting for date')");
	db_execute("REPLACE INTO plugin_intropage_trends (name,value) VALUES ('ntp_testdate', NULL)");

	$data              = array();
	$data['columns'][] = array('name' => 'id', 'type' => 'int(11)', 'NULL' => false, 'auto_increment' => true);
	$data['columns'][] = array('name' => 'user_id', 'type' => 'int(11)', 'NULL' => false);
	$data['columns'][] = array('name' => 'panel', 'type' => 'varchar(50)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'priority', 'type' => 'int(11)', 'NULL' => false, 'default' => '50');
	$data['columns'][] = array('name' => 'fav_graph_id', 'type' => 'int(11)', 'NULL' => true, 'default' => null);
	$data['type']      = 'MyISAM';
	$data['primary']   = 'id';
	$data['comment']   = 'intropage user settings';
	api_plugin_db_table_create('intropage', 'plugin_intropage_user_setting', $data);

	$data              = array();
	$data['columns'][] = array('name' => 'id', 'type' => 'int(11)', 'NULL' => false, 'auto_increment' => true);
	$data['columns'][] = array('name' => 'panel', 'type' => 'varchar(50)', 'NULL' => false);
	$data['columns'][] = array('name' => 'priority', 'type' => 'int(1)', 'default' => '0', 'NULL' => false);
	$data['type']      = 'MyISAM';
	$data['primary']   = 'id';
	$data['comment']   = 'panel setting';
	api_plugin_db_table_create('intropage', 'plugin_intropage_panel', $data);

	$sql_insert = '';
	foreach ($panel as $key => $value) {
		if (isset($value['priority']) && !db_fetch_cell("SELECT priority FROM plugin_intropage_panel WHERE panel='$key'")) {
			if ($sql_insert != '') {
				$sql_insert .= ',';
			}

			$sql_insert .= sprintf('(%s,%s)', db_qstr($key), db_qstr($value['priority']));
		}
	}

	if ($sql_insert != '') {
		db_execute("REPLACE INTO plugin_intropage_panel (panel,priority) VALUES $sql_insert");
	}
}

function intropage_poller_bottom() {
	global $config;

	// drive jsem si start daval do  - nesmysl, muzu mit vice polleru a pak tam davam start prvniho
//	$start = db_fetch_cell('SELECT min(start_time) from poller_time');

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

	// cleaning old data
	db_execute('DELETE FROM plugin_intropage_trends
		WHERE cur_timestamp < date_sub(now(), INTERVAL 2 DAY)');

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

	// check NTP
	$last = db_fetch_cell("SELECT UNIX_TIMESTAMP(value)
		FROM plugin_intropage_trends
		WHERE name='ntp_testdate'");

/*
	if (!$last)	{
    	    db_execute("REPLACE INTO plugin_intropage_trends (name,value) VALUES ('ntp_diff_time', '')");
    	    db_execute("REPLACE INTO plugin_intropage_trends (name,value) VALUES ('ntp_testdate', '')");
    	    $last = 0;
	}
*/
	if (time() > ($last + read_config_option('intropage_ntp_interval')))	{
	    include_once($config['base_path'] . '/plugins/intropage/include/helpers.php');
	    ntp_time2();
	}

	// check db
	$last = db_fetch_cell("SELECT UNIX_TIMESTAMP(value)
		FROM plugin_intropage_trends
		WHERE name='db_check_testdate'");

/*
	if (!$last)	{
    	    db_execute("REPLACE INTO plugin_intropage_trends (name,value) VALUES ('db_check_result', 'Waiting for data')");
    	    db_execute("REPLACE INTO plugin_intropage_trends (name,value) VALUES ('db_check_alarm', 'yellow')");
    	    db_execute("REPLACE INTO plugin_intropage_trends (name,value) VALUES ('db_check_detail', NULL)");
    	    db_execute("REPLACE INTO plugin_intropage_trends (name,value) VALUES ('db_check_testdate', NULL)");
    	    $last = 0;
	}
*/
	if (time() > ($last + read_config_option('intropage_analyse_db_interval')))	{
		db_execute_prepared('REPLACE INTO plugin_intropage_trends
			(name, value) VALUES (?, ?)',
			array('db_check_testdate', date('Y-m-d H:i:s', time())));

	    include_once($config['base_path'] . '/plugins/intropage/include/helpers.php');

	    db_check();
	}


	// check poller_table is empty?
	$count = db_fetch_cell("SELECT COUNT(*) FROM poller_output");

	db_execute_prepared('REPLACE INTO plugin_intropage_trends
		(name, value) VALUES (?, ?)',
		array('poller_output', $count));
}

