<?php
/* vim: ts=4
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

	db_execute('ALTER TABLE user_auth drop column if exists intropage_analyse_log');
	db_execute('ALTER TABLE user_auth drop column if exists intropage_analyse_login');
	db_execute('ALTER TABLE user_auth drop column if exists intropage_thold_event');
	db_execute('ALTER TABLE user_auth drop column if exists intropage_analyse_db');
	db_execute('ALTER TABLE user_auth drop column if exists intropage_analyse_tree_host_graph');
	db_execute('ALTER TABLE user_auth drop column if exists intropage_trend');
	db_execute('ALTER TABLE user_auth drop column if exists intropage_extrem');
	db_execute('ALTER TABLE user_auth drop column if exists intropage_ntp');
	db_execute('ALTER TABLE user_auth drop column if exists intropage_poller_info');
	db_execute('ALTER TABLE user_auth drop column if exists intropage_poller_stat');
	db_execute('ALTER TABLE user_auth drop column if exists intropage_graph_host');
	db_execute('ALTER TABLE user_auth drop column if exists intropage_graph_thold');
	db_execute('ALTER TABLE user_auth drop column if exists intropage_graph_data_source');
	db_execute('ALTER TABLE user_auth drop column if exists intropage_graph_host_template');
	db_execute('ALTER TABLE user_auth drop column if exists intropage_cpuload');
	db_execute('ALTER TABLE user_auth drop column if exists intropage_cpu');
	db_execute('ALTER TABLE user_auth drop column if exists intropage_mactrack');
	db_execute('ALTER TABLE user_auth drop column if exists intropage_mactrack_sites');
	db_execute('ALTER TABLE user_auth drop column if exists intropage_top5_ping');
	db_execute('ALTER TABLE user_auth drop column if exists intropage_top5_availability');
	db_execute('ALTER TABLE user_auth drop column if exists intropage_top5_polltime');
	db_execute('ALTER TABLE user_auth drop column if exists intropage_top5_pollratio');
	db_execute('ALTER TABLE user_auth drop column if exists intropage_info');
	db_execute('ALTER TABLE user_auth drop column if exists intropage_boost');
	db_execute('ALTER TABLE user_auth drop column if exists intropage_favourite_graph');
	db_execute('ALTER TABLE user_auth drop column if exists intropage_plugin_syslog');

	// version 2
	db_execute('DROP TABLE IF EXISTS plugin_intropage_panel_definition');
	db_execute('DROP TABLE IF EXISTS plugin_intropage_panel_data');
	db_execute('DROP TABLE IF EXISTS plugin_intropage_panel_dashboard');
	db_execute('DROP TABLE IF EXISTS plugin_intropage_trends');
	db_execute('DROP TABLE IF EXISTS plugin_intropage_user_auth');
}

function intropage_initialize_database() {
	global $config;

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
	$data['columns'][] = array('name' => 'description', 'type' => 'varchar(200)', 'default' => '', 'NULL' => true);

	$data['type']      = 'InnoDB';
	$data['primary']   = 'panel_id';
	$data['comment']   = 'panel definition';
	api_plugin_db_table_create('intropage', 'plugin_intropage_panel_definition', $data);

	$data              = array();
	$data['columns'][] = array('name' => 'panel_id', 'type' => 'int(11)', 'NULL' => false);
	$data['columns'][] = array('name' => 'user_id', 'type' => 'int(11)', 'NULL' => false);
	$data['columns'][] = array('name' => 'dashboard_id', 'type' => 'int(11)', 'NULL' => false);
	$data['type']      = 'InnoDB';
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

	db_execute("REPLACE INTO plugin_intropage_panel_definition (panel_id,file,has_detail,refresh_interval,priority,description) values 
		('analyse_log','/plugins/intropage/include/data.php','yes',300,50,'Analyse cacti log (last xxx rows). MAY CONTAIN SENSITIVE INFORMATION FOR ALL USERS!')");
	db_execute("REPLACE INTO plugin_intropage_panel_definition (panel_id,file,has_detail,refresh_interval,priority,description) values 
		('analyse_login','/plugins/intropage/include/data.php','yes',300,51,'Analyse last logins. MAY CONTAIN SENSITIVE INFORMATION FOR ALL USERS!')");
	db_execute("REPLACE INTO plugin_intropage_panel_definition (panel_id,file,has_detail,refresh_interval,priority,description) values 
		('top5_ping','/plugins/intropage/include/data.php','yes',300,60,'Hosts with the worst ping response')");
	db_execute("REPLACE INTO plugin_intropage_panel_definition (panel_id,file,has_detail,refresh_interval,priority,description) values 
		('cpuload','/plugins/intropage/include/data.php','no',60,59,'CPU utilization graph (only Linux). MAY CONTAIN SENSITIVE INFORMATION FOR ALL USERS!')");
	db_execute("REPLACE INTO plugin_intropage_panel_definition (panel_id,file,has_detail,refresh_interval,priority,description) values 
		('ntp','/plugins/intropage/include/data.php','no',7200,30,'Diference between localtime and NTP. MAY CONTAIN SENSITIVE INFORMATION FOR ALL USERS!')");
	db_execute("REPLACE INTO plugin_intropage_panel_definition (panel_id,file,has_detail,refresh_interval,priority,description) values 
		('graph_data_source','/plugins/intropage/include/data.php','yes',7200,20,'Graph of datasources')");
	db_execute("REPLACE INTO plugin_intropage_panel_definition (panel_id,file,has_detail,refresh_interval,priority,description) values 
		('graph_host_template','/plugins/intropage/include/data.php','yes',7200,19,'Graph of host templates')");
	db_execute("REPLACE INTO plugin_intropage_panel_definition (panel_id,file,has_detail,refresh_interval,priority,description) values 
		('graph_host','/plugins/intropage/include/data.php','yes',7200,18,'Graph of hosts (up,down,...)')");
	db_execute("REPLACE INTO plugin_intropage_panel_definition (panel_id,file,has_detail,refresh_interval,priority,description) values 
		('info','/plugins/intropage/include/data.php','no',864000,5,'Info about system/cacti. MAY CONTAIN SENSITIVE INFORMATION FOR ALL USERS!')");
	db_execute("REPLACE INTO plugin_intropage_panel_definition (panel_id,file,has_detail,refresh_interval,priority,description) values 
		('analyse_db','/plugins/intropage/include/data.php','no',864000,7,'Analyse MySQL database. MAY CONTAIN SENSITIVE INFORMATION FOR ALL USERS!')");
	db_execute("REPLACE INTO plugin_intropage_panel_definition (panel_id,file,has_detail,refresh_interval,priority,description) values 
		('maint','/plugins/intropage/include/data.php','no',300,98,'Maint plugin')");
	db_execute("REPLACE INTO plugin_intropage_panel_definition (panel_id,file,has_detail,refresh_interval,priority,description) values 
		('admin_alert','/plugins/intropage/include/data.php','no',3600,99,'Extra admin notify panel for all users')");
	db_execute("REPLACE INTO plugin_intropage_panel_definition (panel_id,file,has_detail,refresh_interval,priority,description) values
		('trend','/plugins/intropage/include/data.php','no',300,75,'Few trends (down hosts, trigged tholds,...)')");
	db_execute("REPLACE INTO plugin_intropage_panel_definition (panel_id,file,has_detail,refresh_interval,priority,description) values 
		('poller_info','/plugins/intropage/include/data.php','yes',60,74,'Poller information. MAY CONTAIN SENSITIVE INFORMATION FOR ALL USERS!')");
	db_execute("REPLACE INTO plugin_intropage_panel_definition (panel_id,file,has_detail,refresh_interval,priority,description) values 
		('poller_stat','/plugins/intropage/include/data.php','no',60,73,'Poller statistics. MAY CONTAIN SENSITIVE INFORMATION FOR ALL USERS!')");
	db_execute("REPLACE INTO plugin_intropage_panel_definition (panel_id,file,has_detail,refresh_interval,priority,description) values 
		('analyse_tree_host_graph','/plugins/intropage/include/data.php','yes',1800,33,'Analyse trees, hosts, ...')");
	db_execute("REPLACE INTO plugin_intropage_panel_definition (panel_id,file,has_detail,refresh_interval,priority,description) values
		('top5_availability','/plugins/intropage/include/data.php','yes',300,61,'Host with the worst availability')");
	db_execute("REPLACE INTO plugin_intropage_panel_definition (panel_id,file,has_detail,refresh_interval,priority,description) values
		('top5_polltime','/plugins/intropage/include/data.php','yes',300,62,'Hosts with the worst polling time')");
	db_execute("REPLACE INTO plugin_intropage_panel_definition (panel_id,file,has_detail,refresh_interval,priority,description) values
		('top5_pollratio','/plugins/intropage/include/data.php','yes',300,63,'Hosts with the worst polling ratio')");
	db_execute("REPLACE INTO plugin_intropage_panel_definition (panel_id,file,has_detail,refresh_interval,priority,description) values
		('thold_event','/plugins/intropage/include/data.php','yes',300,77,'Plugin thold - last events')");
	db_execute("REPLACE INTO plugin_intropage_panel_definition (panel_id,file,has_detail,refresh_interval,priority,description) values 
		('boost','/plugins/intropage/include/data.php','no',300,47,'Information about boost process. MAY CONTAIN SENSITIVE INFORMATION FOR ALL USERS!')");
	db_execute("REPLACE INTO plugin_intropage_panel_definition (panel_id,file,has_detail,refresh_interval,priority,description) values 
		('extrem','/plugins/intropage/include/data.php','yes',300,78,'Table with 24 hours extrems (longest poller run, down hosts)')");
	db_execute("REPLACE INTO plugin_intropage_panel_definition (panel_id,file,has_detail,refresh_interval,priority,description) values
		('graph_thold','/plugins/intropage/include/data.php','yes',300,18,'Plugin Thold graph (all, trigerred, ...)')");
	db_execute("REPLACE INTO plugin_intropage_panel_definition (panel_id,file,has_detail,refresh_interval,priority,description) values 
		('mactrack','/plugins/intropage/include/data.php','no',900,28,'Plugin Mactrack statistics. MAY CONTAIN SENSITIVE INFORMATION FOR ALL USERS!')");
	db_execute("REPLACE INTO plugin_intropage_panel_definition (panel_id,file,has_detail,refresh_interval,priority,description) values 
		('mactrack_sites','/plugins/intropage/include/data.php','yes',900,27,'Plugin Mactrack sites statistics. MAY CONTAIN SENSITIVE INFORMATION FOR ALL USERS!')");
	db_execute("REPLACE INTO plugin_intropage_panel_definition (panel_id,file,has_detail,refresh_interval,priority,description) values 
		('plugin_syslog','/plugins/intropage/include/data.php','no',900,26,'Plugin Syslog statistics. MAY CONTAIN SENSITIVE INFORMATION FOR ALL USERS!')");

	$data              = array();
	$data['columns'][] = array('name' => 'user_id', 'type' => 'int(11)', 'NULL' => false);
	$data['columns'][] = array('name' => 'login_opts', 'type' => 'tinyint(1)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'analyse_log', 'type' => 'char(2)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'analyse_login', 'type' => 'char(2)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'thold_event', 'type' => 'char(2)', 'NULL' => false, 'default' => 'on');
	$data['columns'][] = array('name' => 'analyse_db', 'type' => 'char(2)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'analyse_tree_host_graph', 'type' => 'char(2)', 'NULL' => false, 'default' => 'on');
	$data['columns'][] = array('name' => 'trend', 'type' => 'char(2)', 'NULL' => false, 'default' => 'on');
	$data['columns'][] = array('name' => 'extrem', 'type' => 'char(2)', 'NULL' => false, 'default' => 'on');
	$data['columns'][] = array('name' => 'ntp', 'type' => 'char(2)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'poller_info', 'type' => 'char(2)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'poller_stat', 'type' => 'char(2)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'graph_host', 'type' => 'char(2)', 'NULL' => false, 'default' => 'on');
	$data['columns'][] = array('name' => 'graph_thold', 'type' => 'char(2)', 'NULL' => false, 'default' => 'on');
	$data['columns'][] = array('name' => 'graph_data_source', 'type' => 'char(2)', 'NULL' => false, 'default' => 'on');
	$data['columns'][] = array('name' => 'graph_host_template', 'type' => 'char(2)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'cpuload', 'type' => 'char(2)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'mactrack', 'type' => 'char(2)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'mactrack_sites', 'type' => 'char(2)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'top5_ping', 'type' => 'char(2)', 'NULL' => false, 'default' => 'on');
	$data['columns'][] = array('name' => 'top5_availability', 'type' => 'char(2)', 'NULL' => false, 'default' => 'on');
	$data['columns'][] = array('name' => 'top5_polltime', 'type' => 'char(2)', 'NULL' => false, 'default' => 'on');
	$data['columns'][] = array('name' => 'top5_pollratio', 'type' => 'char(2)', 'NULL' => false, 'default' => 'on');
	$data['columns'][] = array('name' => 'info', 'type' => 'char(2)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'boost', 'type' => 'char(2)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'favourite_graph', 'type' => 'char(2)', 'NULL' => false, 'default' => 'on');
	$data['columns'][] = array('name' => 'plugin_syslog', 'type' => 'char(2)', 'NULL' => false, 'default' => '');
	$data['type']      = 'InnoDB';
	$data['primary']   = 'user_id';
	$data['comment']   = 'authorization';
	api_plugin_db_table_create('intropage', 'plugin_intropage_user_auth', $data);
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


		if (cacti_version_compare($oldv,'2.0.2', '<')) {
			// a lot of changes, so:
		    	intropage_drop_database();
		    	intropage_initialize_database();
		    
			api_plugin_register_hook('intropage', 'user_admin_tab', 'intropage_user_admin_tab', 'includes/settings.php');
			api_plugin_register_hook('intropage', 'user_admin_run_action', 'intropage_user_admin_run_action', 'includes/settings.php');
    			api_plugin_register_hook('intropage', 'user_admin_user_save', 'intropage_user_admin_user_save', 'includes/settings.php');
			api_plugin_register_hook('intropage', 'user_remove', 'intropage_user_remove', 'setup.php');
		}

		// Set the new version
		db_execute("UPDATE plugin_config
			SET version='$current'
			WHERE directory='intropage'");

		api_plugin_register_hook('intropage', 'page_head', 'intropage_page_head', 'setup.php', 1);
	}
}

