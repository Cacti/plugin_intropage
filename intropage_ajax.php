<?php

chdir('../../');
include_once('./include/auth.php');

    // reload single panel
if (isset_request_var('reload_panel') &&
    get_filter_request_var('reload_panel', FILTER_VALIDATE_REGEXP, array('options' => array('regexp' => '/^[0-9]{1,3}$/')))) {
    
    include_once($config['base_path'] . '/plugins/intropage/include/data.php');
    include_once($config['base_path'] . '/plugins/intropage/include/helpers.php');
    
    // few requered variables
    $maint_days_before = read_config_option('intropage_maint_plugin_days_before');

    // need for thold - isn't any better solution?
    //$current_user  = db_fetch_row('SELECT * FROM user_auth WHERE id=' . $_SESSION['sess_user_id']);
    //$sql_where     = get_graph_permissions_sql($current_user['policy_graphs'], $current_user['policy_hosts'], $current_user['policy_graph_templates']);

    $hosts = get_allowed_devices();
    $allowed_hosts = implode(',', array_column($hosts, 'id'));

    // Retrieve access
    $console_access = (db_fetch_assoc("select realm_id from user_auth_realm where user_id='" . $_SESSION['sess_user_id'] . "' and user_auth_realm.realm_id=8")) ? true : false;

    $panel = db_fetch_row ('select panel,fav_graph_id from plugin_intropage_user_setting where id = ' . get_request_var('reload_panel'));
    if ($panel)	{
	$pokus = $panel['panel'];

	if (isset($panel['fav_graph_id'])) { // fav_graph exception 
	    $data = intropage_favourite_graph($panel['fav_graph_id']);
	} else {        // normal panel
    	    $data = $pokus();
	}
	intropage_display_data(get_request_var('reload_panel'),$data);
    }
    else	{
	echo 'Panel not found';
    }
}
else	{	// reload all
//    include_once('./plugins/intropage/display.php');
//    display_information();
}