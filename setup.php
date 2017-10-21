<?php

function plugin_intropage_install() {
	api_plugin_register_hook('intropage', 'config_form','intropage_config_form', 'include/settings.php');
	api_plugin_register_hook('intropage', 'config_settings', 'intropage_config_settings', 'include/settings.php');
	api_plugin_register_hook('intropage', 'login_options_navigate', 'intropage_login_options_navigate', 'include/settings.php');
	
	api_plugin_register_hook('intropage', 'top_header_tabs', 'intropage_show_tab', 'include/tab.php');
	api_plugin_register_hook('intropage', 'top_graph_header_tabs', 'intropage_show_tab', 'include/tab.php');
	
	api_plugin_register_hook('intropage', 'console_after', 'intropage_console_after', 'include/settings.php');

	api_plugin_register_hook('intropage', 'user_admin_setup_sql_save', 'intropage_user_admin_setup_sql_save', 'include/settings.php');

	api_plugin_register_realm('intropage', 'intropage.php,intropage_ajax.php', 'Plugin Intropage - view', 1);
	// need for collecting poller time
	api_plugin_register_hook('intropage', 'poller_bottom', 'intropage_poller_bottom', 'setup.php');	
	intropage_setup_database();
}

function plugin_intropage_uninstall () {
	db_execute("DELETE FROM settings WHERE name LIKE 'intropage_%'");
}

function plugin_intropage_version()	{
	global $config;
	$info = parse_ini_file($config['base_path'] . '/plugins/intropage/INFO', true);
	return $info['info'];
}

function plugin_intropage_upgrade() {
	// Here we will upgrade to the newest version
	intropage_check_upgrade();
	return false;
}

function plugin_intropage_check_config () {
	// Here we will check to ensure everything is configured
	intropage_check_upgrade();
	return true;
}

function intropage_check_upgrade() {
	// If action need to be done for upgrade, add it.
	
	$oldv = db_fetch_cell('SELECT version FROM plugin_config WHERE directory="intropage"');
	if ($oldv < 0.9) {
		api_plugin_db_add_column ('user_auth',array('name' => 'intropage_opts', 'type' => 'tinyint(1)', 'NULL' => false, 'default' => '0'));
		db_execute('UPDATE plugin_hooks SET function="intropage_config_form", file="include/settings.php" WHERE name="intropage" AND hook="config_form"');
		db_execute('UPDATE plugin_hooks SET function="intropage_config_settings", file="include/settings.php" WHERE name="intropage" AND hook="config_settings"');
		db_execute('UPDATE plugin_hooks SET function="intropage_show_tab", file="include/tab.php" WHERE name="intropage" AND hook="top_header_tabs"');
		db_execute('UPDATE plugin_hooks SET function="intropage_show_tab", file="include/tab.php" WHERE name="intropage" AND hook="top_graph_header_tabs"');
		db_execute('UPDATE plugin_hooks SET function="intropage_login_options_navigate", file="include/settings.php" WHERE name="intropage" AND hook="login_options_navigate"');
		db_execute('UPDATE plugin_hooks SET function="intropage_console_after", file="include/settings.php" WHERE name="intropage" AND hook="console_after"');
		db_execute('UPDATE user_auth set login_opts=1 WHERE login_opts in (4,5)');
	}
}

function intropage_setup_database() {
	global $config, $intropage_settings;
	api_plugin_db_add_column ('intropage', 'user_auth',array('name' => 'intropage_opts', 'type' => 'tinyint(1)', 'NULL' => false, 'default' => '0'));
	
	include_once($config['base_path'] . '/plugins/intropage/include/variables.php');
	$sql_insert = '';
	foreach ($intropage_settings as $key=>$value)   {
		if (isset($value['default']) && !db_fetch_cell("SELECT value FROM settings WHERE name='$key'")) {
			if ($sql_insert != '') $sql_insert .= ",";
			$sql_insert .= sprintf("(%s,%s)",db_qstr($key),db_qstr($value['default']));
		}
	}
	if ($sql_insert != '') {
		db_execute("INSERT INTO settings (name, value) VALUES $sql_insert");
	}

        $data = array();
        $data['columns'][] = array('name' => 'date', 'type' => 'timestamp', 'default' => '0000-00-00 00:00:00', 'NULL' => false);
        $data['columns'][] = array('name' => 'name', 'type' => 'varchar(50)', 'NULL' => false, 'default' => '0');
        $data['columns'][] = array('name' => 'value', 'type' => 'varchar(50)', 'NULL' => false, 'default' => '0');
	$data['type'] = 'MyISAM';
        $data['comment'] = 'trends';
        api_plugin_db_table_create ('intropage', 'plugin_intropage_trends', $data);

        $data = array();
	$data['columns'][] = array('name' => 'panel', 'type' => 'varchar(30)', 'NULL' => false);
	$data['columns'][] = array('name' => 'size', 'type' => 'int(2)', 'default' => '1', 'NULL' => false);
	$data['columns'][] = array('name' => 'priority', 'type' => 'int(1)', 'default' => '0', 'NULL' => false);
	$data['type'] = 'MyISAM';
        $data['comment'] = 'panel setting';
        api_plugin_db_table_create ('intropage', 'plugin_intropage_panel', $data);

	$sql_insert = '';
	foreach ($panel as $key=>$value)   {
		if (isset($value['size']) && !db_fetch_cell("SELECT size FROM plugin_intropage_panel WHERE panel='$key'")) {
			if ($sql_insert != '') $sql_insert .= ",";
			$sql_insert .= sprintf("(%s,%s,%s)",db_qstr($key),db_qstr($value['size']),db_qstr($value['priority']));
		}
	}

	//cacti_log("INTROPAGE:INSERT INTO plugin_intropage_panel (panel,size,priority) VALUES $sql_insert",true,"Intropage");
	
	if ($sql_insert != '') {
		db_execute("INSERT INTO plugin_intropage_panel (panel,size,priority) VALUES $sql_insert");
	}



}

function intropage_poller_bottom () {
//    global $config;


    $start = db_fetch_cell("SELECT distinct min(start_time) from poller_time");

    $rozdil = db_fetch_cell("SELECT time_to_sec(max(timediff(end_time,start_time))) from poller_time");
    if ($rozdil < 0)
	$rozdil = read_config_option("poller_interval");

    db_execute("insert into plugin_intropage_trends (name,date,value) values ('poller', '$start', '$rozdil')");

// CPU load - linux only
    if (!stristr(PHP_OS, 'win')) {
        $load = sys_getloadavg();
        $load[0] = round ($load[0],2);

	db_execute("insert into plugin_intropage_trends (name,date,value) values ('cpuload', '$start', '" . $load[0] . "')");
    }
// end of cpu load

    db_execute('delete from plugin_intropage_trends where date < date_sub(now(), INTERVAL 20 DAY)');

    // all hosts without permissions!!!
     db_execute("insert into plugin_intropage_trends (name,date,value) select 'host', now(), count(id) FROM host WHERE status='1' AND disabled=''");
     db_execute("insert into plugin_intropage_trends (name,date,value) select 'thold', now(), COUNT(*) FROM thold_data  WHERE (thold_data.thold_alert!=0 OR thold_data.bl_fail_count >= thold_data.bl_fail_trigger)");

}

?>