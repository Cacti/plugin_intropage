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

function intropage_drop_database() {
	db_execute("DELETE FROM settings WHERE name LIKE 'intropage_%'");
	db_execute('DROP TABLE plugin_intropage_user_setting');
	db_execute('DROP TABLE plugin_intropage_trends');
	db_execute('DROP TABLE plugin_intropage_panel');
	db_execute('UPDATE user_auth SET login_opts=1 WHERE login_opts > 3');
	// new version
	db_execute('DROP TABLE plugin_intropage_panel_definition');

}

intropage_add_panel($panelid, $panelJSON) {
    // insert into plugin_intropage_panel_definition
    
    //return last_inserted_id;
}

intropage_remove_panel($panelid) {
    // delete from plugin_intropage_panel_data
    // delete from plugin_intropage_panel_definition
}


function intropage_initialize_database() {
	global $config, $intropage_settings;

//	api_plugin_db_add_column('intropage', 'user_auth', array('name' => 'intropage_opts', 'type' => 'tinyint(1)', 'NULL' => false, 'default' => '0'));
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
	api_plugin_db_add_column('intropage', 'user_auth', array('name' => 'intropage_top5_polltime', 'type' => 'char(2)', 'NULL' => false, 'default' => 'on'));
	api_plugin_db_add_column('intropage', 'user_auth', array('name' => 'intropage_top5_pollratio', 'type' => 'char(2)', 'NULL' => false, 'default' => 'on'));
	api_plugin_db_add_column('intropage', 'user_auth', array('name' => 'intropage_info', 'type' => 'char(2)', 'NULL' => false, 'default' => 'on'));
	api_plugin_db_add_column('intropage', 'user_auth', array('name' => 'intropage_boost', 'type' => 'char(2)', 'NULL' => false, 'default' => 'on'));
	api_plugin_db_add_column('intropage', 'user_auth', array('name' => 'intropage_favourite_graph', 'type' => 'char(2)', 'NULL' => false, 'default' => 'on'));
	api_plugin_db_add_column('intropage', 'user_auth', array('name' => 'intropage_syslog', 'type' => 'char(2)', 'NULL' => false, 'default' => 'on'));

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


	// few values
	db_execute("REPLACE INTO plugin_intropage_trends (name,value) VALUES ('db_check_result', 'Waiting for data')");
	db_execute("REPLACE INTO plugin_intropage_trends (name,value) VALUES ('db_check_alarm', 'yellow')");
	db_execute("REPLACE INTO plugin_intropage_trends (name,value) VALUES ('db_check_detail', NULL)");
	db_execute("REPLACE INTO plugin_intropage_trends (name,value) VALUES ('db_check_testdate', NULL)");
	db_execute("REPLACE INTO plugin_intropage_trends (name,value) VALUES ('ntp_diff_time', 'Waiting for date')");
	db_execute("REPLACE INTO plugin_intropage_trends (name,value) VALUES ('ntp_testdate', NULL)");
	db_execute("REPLACE INTO plugin_intropage_trends (name,value) VALUES ('ar_poller_finish', 'false')");

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

	$data              = array();
	$data['columns'][] = array('name' => 'id', 'type' => 'int(11)', 'NULL' => false, 'auto_increment' => true);
	$data['columns'][] = array('name' => 'title', 'type' => 'varchar(50)', 'NULL' => false);
	$data['columns'][] = array('name' => 'file', 'type' => 'varchar(200)', 'NULL' => false);
	$data['columns'][] = array('name' => 'refresh_interval', 'type' => 'int(9)', 'default' => '3600', 'NULL' => false);
	$data['type']      = 'InnoDB';
	$data['primary']   = 'id';
	$data['comment']   = 'panel definition';
	api_plugin_db_table_create('intropage', 'plugin_intropage_panel_definition', $data);


	$data              = array();
	$data['columns'][] = array('name' => 'id', 'type' => 'int(11)', 'NULL' => false, 'auto_increment' => true);
	$data['columns'][] = array('name' => 'panel_id', 'type' => 'int(11)', 'NULL' => false);
	$data['columns'][] = array('name' => 'user_id', 'type' => 'int(11)', 'NULL' => false);
	$data['columns'][] = array('name' => 'last_update', 'type' => 'int(11)', 'NULL' => false);
	$data['columns'][] = array('name' => 'data', 'type' => 'text', 'NULL' => true);
	$data['columns'][] = array('name' => 'detail', 'type' => 'text', 'NULL' => true);
	$data['type']      = 'InnoDB';
	$data['primary']   = 'id';
	$data['comment']   = 'panel data';
	api_plugin_db_table_create('intropage', 'plugin_intropage_panel_data', $data);


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

function intropage_upgrade_database() {
	global $config;

	// If action need to be done for upgrade, add it.
	$info = parse_ini_file($config['base_path'] . '/plugins/intropage/INFO', true);
	$info = $info['info'];

	$current = $info['version'];
	$oldv    = db_fetch_cell('SELECT version FROM plugin_config WHERE directory="intropage"');

	if (!cacti_version_compare($oldv, $current, '=')) {
		if (cacti_version_compare($oldv,'0.9','<')) {
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
			api_plugin_db_add_column('intropage', 'user_auth', array('name' => 'intropage_top5_polltime', 'type' => 'char(2)', 'NULL' => false, 'default' => 'on'));
			api_plugin_db_add_column('intropage', 'user_auth', array('name' => 'intropage_top5_pollratio', 'type' => 'char(2)', 'NULL' => false, 'default' => 'on'));
			api_plugin_db_add_column('intropage', 'user_auth', array('name' => 'intropage_info', 'type' => 'char(2)', 'NULL' => false, 'default' => 'on'));
			api_plugin_db_add_column('intropage', 'user_auth', array('name' => 'intropage_boost', 'type' => 'char(2)', 'NULL' => false, 'default' => 'on'));
			api_plugin_db_add_column('intropage', 'user_auth', array('name' => 'intropage_favourite_graph', 'type' => 'char(2)', 'NULL' => false, 'default' => 'on'));

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

		if (cacti_version_compare($oldv,'1.8.2', '<')) {
			db_execute('ALTER TABLE plugin_intropage_trends
				MODIFY COLUMN value varchar(250) NULL DEFAULT NULL');
		}

		if (cacti_version_compare($oldv,'1.8.2', '<')) {
			db_execute("DELETE FROM plugin_intropage_panel
				WHERE panel='intropage_favourite_graph'");
			db_execute("DELETE FROM plugin_intropage_user_setting
				WHERE panel='intropage_favourite_graph' AND fav_graph_id is NULL");
			db_execute("REPLACE INTO plugin_intropage_trends (name,value)
				VALUES ('ar_poller_finish', '1')");
		}

		if (cacti_version_compare($oldv,'1.9.0', '<')) {
			db_execute("ALTER TABLE plugin_intropage_trends ENGINE=InnoDB");
			db_execute("ALTER TABLE plugin_intropage_user_setting ENGINE=InnoDB");
			db_execute("ALTER TABLE plugin_intropage_panel ENGINE=InnoDB");
		}		

		if (!db_column_exists('user_auth', 'intropage_syslog')) {
			api_plugin_db_add_column('intropage', 'user_auth', array('name' => 'intropage_syslog', 'type' => 'char(2)', 'NULL' => false, 'default' => 'on'));
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

		api_plugin_register_hook('intropage', 'page_head', 'intropage_page_head', 'setup.php', 1);
	}
}

