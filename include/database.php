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
	db_execute('DROP TABLE IF EXISTS plugin_intropage_user_setting');
	db_execute('DROP TABLE IF EXISTS plugin_intropage_panel');
	db_execute('UPDATE user_auth SET login_opts=1 WHERE login_opts > 3');
	// version 2
	db_execute('DROP TABLE IF EXISTS plugin_intropage_panel_definition');
	db_execute('DROP TABLE IF EXISTS plugin_intropage_panel_data');
	db_execute('DROP TABLE IF EXISTS plugin_intropage_panel_dashboard');
	db_execute('DROP TABLE IF EXISTS plugin_intropage_trends');
}

function intropage_initialize_database() {
	global $config, $intropage_settings;

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
	api_plugin_db_add_column('intropage', 'user_auth', array('name' => 'intropage_cpuload', 'type' => 'char(2)', 'NULL' => false, 'default' => 'on'));
	api_plugin_db_add_column('intropage', 'user_auth', array('name' => 'intropage_mactrack', 'type' => 'char(2)', 'NULL' => false, 'default' => 'on'));
	api_plugin_db_add_column('intropage', 'user_auth', array('name' => 'intropage_mactrack_sites', 'type' => 'char(2)', 'NULL' => false, 'default' => 'on'));
	api_plugin_db_add_column('intropage', 'user_auth', array('name' => 'intropage_top5_ping', 'type' => 'char(2)', 'NULL' => false, 'default' => 'on'));
	api_plugin_db_add_column('intropage', 'user_auth', array('name' => 'intropage_top5_availability', 'type' => 'char(2)', 'NULL' => false, 'default' => 'on'));
	api_plugin_db_add_column('intropage', 'user_auth', array('name' => 'intropage_top5_polltime', 'type' => 'char(2)', 'NULL' => false, 'default' => 'on'));
	api_plugin_db_add_column('intropage', 'user_auth', array('name' => 'intropage_top5_pollratio', 'type' => 'char(2)', 'NULL' => false, 'default' => 'on'));
	api_plugin_db_add_column('intropage', 'user_auth', array('name' => 'intropage_info', 'type' => 'char(2)', 'NULL' => false, 'default' => 'on'));
	api_plugin_db_add_column('intropage', 'user_auth', array('name' => 'intropage_boost', 'type' => 'char(2)', 'NULL' => false, 'default' => 'on'));
	api_plugin_db_add_column('intropage', 'user_auth', array('name' => 'intropage_favourite_graph', 'type' => 'char(2)', 'NULL' => false, 'default' => 'on'));
	api_plugin_db_add_column('intropage', 'user_auth', array('name' => 'intropage_plugin_syslog', 'type' => 'char(2)', 'NULL' => false, 'default' => 'on'));

	$data              = array();
	$data['columns'][] = array('name' => 'cur_timestamp', 'type' => 'timestamp');
	$data['columns'][] = array('name' => 'name', 'type' => 'varchar(50)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'user_id', 'type' => 'int(11)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'value', 'type' => 'varchar(250)', 'NULL' => true, 'default' => null);
	$data['type']      = 'InnoDB';
	$data['comment']   = 'Intropage trends';
	api_plugin_db_table_create('intropage', 'plugin_intropage_trends', $data);

	db_execute('ALTER TABLE plugin_intropage_trends modify cur_timestamp timestamp default current_timestamp on update current_timestamp');

	$data              = array();
	$data['columns'][] = array('name' => 'panel_id', 'type' => 'varchar(50)', 'NULL' => false);
	$data['columns'][] = array('name' => 'file', 'type' => 'varchar(200)', 'NULL' => false);
	$data['columns'][] = array('name' => 'has_detail', 'type' => "enum('yes','no')", 'NULL' => 'no');
	$data['columns'][] = array('name' => 'priority', 'type' => "int(3)", 'default' => '30', 'NULL' => 'no');
	$data['columns'][] = array('name' => 'refresh_interval', 'type' => 'int(9)', 'default' => '3600', 'NULL' => false);
	$data['type']      = 'InnoDB';
	$data['primary']   = 'panel_id';
	$data['comment']   = 'panel definition';
	api_plugin_db_table_create('intropage', 'plugin_intropage_panel_definition', $data);

	$data              = array();
	$data['columns'][] = array('name' => 'panel_id', 'type' => 'int(11)', 'NULL' => false);
	$data['columns'][] = array('name' => 'user_id', 'type' => 'int(11)', 'NULL' => false);
	$data['columns'][] = array('name' => 'dashboard_id', 'type' => 'int(11)', 'NULL' => false);
	$data['type']      = 'InnoDB';
	$data['primary']   = 'panel_id';
	$data['comment']   = 'panel x dashboard dependency';
	api_plugin_db_table_create('intropage', 'plugin_intropage_panel_dashboard', $data);

	$data              = array();
	$data['columns'][] = array('name' => 'id', 'type' => 'int(11)', 'NULL' => false, 'auto_increment' => true);
	$data['columns'][] = array('name' => 'panel_id', 'type' => 'varchar(50)', 'NULL' => false);
	$data['columns'][] = array('name' => 'user_id', 'type' => 'int(11)', 'NULL' => false);
	$data['columns'][] = array('name' => 'last_update', 'type' => 'timestamp', 'default' => 'CURRENT_TIMESTAMP', 'NULL' => false);
	$data['columns'][] = array('name' => 'data', 'type' => 'text', 'NULL' => true);
	$data['columns'][] = array('name' => 'priority', 'type' => 'int(3)', 'default' => '30', 'NULL' => false);
	$data['columns'][] = array('name' => 'alarm', 'type' => "enum('red','green','yellow','gray')", 'default' => 'green', 'NULL' => false);
	$data['columns'][] = array('name' => 'fav_graph_id', 'type' => 'int(11)', 'NULL' => true);
	$data['columns'][] = array('name' => 'fav_graph_timespan', 'type' => 'int(2)', 'default' => '1', 'NULL' => false);

	$data['type']      = 'InnoDB';
	$data['primary']   = 'id';
	$data['comment']   = 'panel data';
	api_plugin_db_table_create('intropage', 'plugin_intropage_panel_data', $data);
	db_execute('ALTER TABLE plugin_intropage_panel_data modify last_update timestamp default current_timestamp on update current_timestamp');

	db_execute("REPLACE INTO plugin_intropage_panel_definition (panel_id,file,has_detail,refresh_interval,priority) values 
		('analyse_log','/plugins/intropage/include/data.php','yes',300,50)");
	db_execute("REPLACE INTO plugin_intropage_panel_definition (panel_id,file,has_detail,refresh_interval,priority) values 
		('analyse_login','/plugins/intropage/include/data.php','yes',300,51)");
	db_execute("REPLACE INTO plugin_intropage_panel_definition (panel_id,file,has_detail,refresh_interval,priority) values 
		('top5_ping','/plugins/intropage/include/data.php','yes',300,60)");
	db_execute("REPLACE INTO plugin_intropage_panel_definition (panel_id,file,has_detail,refresh_interval,priority) values 
		('cpuload','/plugins/intropage/include/data.php','no',60,59)");
	db_execute("REPLACE INTO plugin_intropage_panel_definition (panel_id,file,has_detail,refresh_interval,priority) values 
		('ntp','/plugins/intropage/include/data.php','no',7200,30)");
	db_execute("REPLACE INTO plugin_intropage_panel_definition (panel_id,file,has_detail,refresh_interval,priority) values 
		('graph_data_source','/plugins/intropage/include/data.php','yes',7200,20)");
	db_execute("REPLACE INTO plugin_intropage_panel_definition (panel_id,file,has_detail,refresh_interval,priority) values 
		('graph_host_template','/plugins/intropage/include/data.php','yes',7200,19)");
	db_execute("REPLACE INTO plugin_intropage_panel_definition (panel_id,file,has_detail,refresh_interval,priority) values 
		('graph_host','/plugins/intropage/include/data.php','yes',7200,18)");
	db_execute("REPLACE INTO plugin_intropage_panel_definition (panel_id,file,has_detail,refresh_interval,priority) values 
		('info','/plugins/intropage/include/data.php','no',864000,5)");
	db_execute("REPLACE INTO plugin_intropage_panel_definition (panel_id,file,has_detail,refresh_interval,priority) values 
		('analyse_db','/plugins/intropage/include/data.php','no',864000,7)");
	db_execute("REPLACE INTO plugin_intropage_panel_definition (panel_id,file,has_detail,refresh_interval,priority) values 
		('maint','/plugins/intropage/include/data.php','no',3600,98)");
	db_execute("REPLACE INTO plugin_intropage_panel_definition (panel_id,file,has_detail,refresh_interval,priority) values 
		('admin_alert','/plugins/intropage/include/data.php','no',3600,99)");
	db_execute("REPLACE INTO plugin_intropage_panel_definition (panel_id,file,has_detail,refresh_interval,priority) values 
		('trend','/plugins/intropage/include/data.php','no',300,75)");
	db_execute("REPLACE INTO plugin_intropage_panel_definition (panel_id,file,has_detail,refresh_interval,priority) values 
		('poller_info','/plugins/intropage/include/data.php','yes',60,74)");
	db_execute("REPLACE INTO plugin_intropage_panel_definition (panel_id,file,has_detail,refresh_interval,priority) values 
		('poller_stat','/plugins/intropage/include/data.php','no',60,73)");
	db_execute("REPLACE INTO plugin_intropage_panel_definition (panel_id,file,has_detail,refresh_interval,priority) values 
		('analyse_tree_host_graph','/plugins/intropage/include/data.php','yes',1800,33)");
	db_execute("REPLACE INTO plugin_intropage_panel_definition (panel_id,file,has_detail,refresh_interval,priority) values 
		('top5_availability','/plugins/intropage/include/data.php','yes',300,61)");
	db_execute("REPLACE INTO plugin_intropage_panel_definition (panel_id,file,has_detail,refresh_interval,priority) values 
		('top5_polltime','/plugins/intropage/include/data.php','yes',300,62)");
	db_execute("REPLACE INTO plugin_intropage_panel_definition (panel_id,file,has_detail,refresh_interval,priority) values 
		('top5_pollratio','/plugins/intropage/include/data.php','yes',300,63)");
	db_execute("REPLACE INTO plugin_intropage_panel_definition (panel_id,file,has_detail,refresh_interval,priority) values 
		('thold_event','/plugins/intropage/include/data.php','yes',300,77)");
	db_execute("REPLACE INTO plugin_intropage_panel_definition (panel_id,file,has_detail,refresh_interval,priority) values 
		('boost','/plugins/intropage/include/data.php','no',300,47)");
	db_execute("REPLACE INTO plugin_intropage_panel_definition (panel_id,file,has_detail,refresh_interval,priority) values 
		('extrem','/plugins/intropage/include/data.php','yes',300,78)");
	db_execute("REPLACE INTO plugin_intropage_panel_definition (panel_id,file,has_detail,refresh_interval,priority) values 
		('graph_thold','/plugins/intropage/include/data.php','yes',300,18)");
	db_execute("REPLACE INTO plugin_intropage_panel_definition (panel_id,file,has_detail,refresh_interval,priority) values 
		('mactrack','/plugins/intropage/include/data.php','no',900,28)");
	db_execute("REPLACE INTO plugin_intropage_panel_definition (panel_id,file,has_detail,refresh_interval,priority) values 
		('mactrack_sites','/plugins/intropage/include/data.php','yes',900,27)");
	db_execute("REPLACE INTO plugin_intropage_panel_definition (panel_id,file,has_detail,refresh_interval,priority) values 
		('plugin_syslog','/plugins/intropage/include/data.php','no',900,26)");
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
			api_plugin_db_add_column('intropage', 'user_auth', array('name' => 'intropage_cpuload', 'type' => 'char(2)', 'NULL' => false, 'default' => 'on'));
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
			db_execute('DROP TABLE IF EXISTS plugin_intropage_user_setting');
			db_execute('DROP TABLE IF EXISTS plugin_intropage_panel');
			db_execute('ALTER TABLE plugin_intropage_trends ENGINE=InnoDB');
			db_execute('ALTER TABLE plugin_intropage_user_setting ENGINE=InnoDB');
			db_execute('ALTER TABLE plugin_intropage_panel ENGINE=InnoDB');
			db_execute('DELETE FROM plugin_intropage_trends');
			api_plugin_db_add_column('intropage', 'plugin_intropage_trends', array('name' => 'user_id', 'type' => 'int(11)', 'NULL' => false, 'default' => '0'));
			db_execute('ALTER TABLE plugin_intropage_trends modify cur_timestamp timestamp default current_timestamp on update current_timestamp');
		}		

		if (!db_column_exists('user_auth', 'intropage_plugin_syslog')) {
			api_plugin_db_add_column('intropage', 'user_auth', array('name' => 'intropage_plugin_syslog', 'type' => 'char(2)', 'NULL' => false, 'default' => 'on'));
		}

		// Set the new version
		db_execute("UPDATE plugin_config
			SET version='$current'
			WHERE directory='intropage'");

		api_plugin_register_hook('intropage', 'page_head', 'intropage_page_head', 'setup.php', 1);
	}
}

