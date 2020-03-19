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

chdir('../../');
include_once('./include/auth.php');

if (!function_exists("array_column")) {
    function array_column($array,$column_name) {
        return array_map(function($element) use($column_name){return $element[$column_name];}, $array);
    }
}

if (get_filter_request_var('reload_panel', FILTER_VALIDATE_REGEXP, array('options' => array('regexp' => '/^[0-9]{1,3}$/'))))	{
    $panel_id = get_request_var('reload_panel');
}

if (get_filter_request_var('detail_panel', FILTER_VALIDATE_REGEXP, array('options' => array('regexp' => '/^[0-9]{1,3}$/'))))	{
    $panel_id = get_request_var('detail_panel');
}

// automatic reload when poller ends
if (isset_request_var('autoreload'))	{

    $last_poller = db_fetch_cell("SELECT unix_timestamp(cur_timestamp)  
				FROM plugin_intropage_trends
				WHERE name='ar_poller_finish'");

    $last_disp = db_fetch_cell("SELECT unix_timestamp(cur_timestamp)  
				FROM plugin_intropage_trends
				WHERE name='ar_displayed_" . $_SESSION['sess_user_id'] . "'");

    if (!$last_disp)	{
	db_execute("INSERT INTO plugin_intropage_trends (name,value)
                                VALUES ('ar_displayed_" . $_SESSION['sess_user_id'] . "', now())");
        $last_disp = $last_poller;
    }


    if ($last_poller > $last_disp)	{  // fix first double reload (login and poller finish after few seconds
	db_execute("UPDATE plugin_intropage_trends set cur_timestamp=now(),value=now() 
                                WHERE name='ar_displayed_" . $_SESSION['sess_user_id'] . "'");

	print '1';
    }
    else	{
	print '0';
    }
}

// few requered variables
$maint_days_before = read_config_option('intropage_maint_plugin_days_before');

$hosts = get_allowed_devices();
if (count($hosts) > 0)
    $allowed_hosts = implode(',', array_column($hosts, 'id'));
else
    $allowed_hosts = false;

// Retrieve access
$console_access = (db_fetch_assoc("SELECT realm_id FROM user_auth_realm WHERE user_id='" . $_SESSION['sess_user_id'] . "' AND user_auth_realm.realm_id=8")) ? true : false;

include_once($config['base_path'] . '/plugins/intropage/include/helpers.php');	

if (isset_request_var('reload_panel') && isset($panel_id)) {
    include_once($config['base_path'] . '/plugins/intropage/include/data.php');
    
    $panel = db_fetch_row ('SELECT panel,fav_graph_id FROM plugin_intropage_user_setting WHERE id = ' . $panel_id);
    if ($panel)	{
        // exception for ntp and db_check - get data now!
        if (isset_request_var ('autom') && get_request_var ('autom') == 'true')	{
            if ($panel['panel'] == 'intropage_ntp')	{
	        ntp_time2();
	    }

    	    if ($panel['panel'] == 'intropage_analyse_db')	{
	        db_check();
	    }
	}

	$pokus = $panel['panel'];

	if (isset($panel['fav_graph_id'])) { // fav_graph exception
	    $data = intropage_favourite_graph($panel['fav_graph_id']);
	} else { // normal panel
 	    $data = $pokus();
	}

	if (isset_request_var('reload_panel'))	{
	    intropage_display_data(get_request_var('reload_panel'),$data);

	// change panel color or ena/disa detail
?>
       <script type='text/javascript'>
            $('#panel_'+<?php print get_request_var('reload_panel');?>).find('.panel_header').removeClass('color_green');
            $('#panel_'+<?php print get_request_var('reload_panel');?>).find('.panel_header').removeClass('color_yellow');
            $('#panel_'+<?php print get_request_var('reload_panel');?>).find('.panel_header').removeClass('color_red');
            $('#panel_'+<?php print get_request_var('reload_panel');?>).find('.panel_header').addClass('color_<?php print $data['alarm'];?>');

<?php
	    if (isset($data['detail']) && !empty($data['detail']))	{
        	print "$('#panel_'+" . get_request_var('reload_panel') . ").find('.maxim').show();";
    	    }
    	    else	{
        	print "$('#panel_'+" . get_request_var('reload_panel') . ").find('.maxim').hide();";
    	    }
?> 
	</script>
<?php
	// end ofchange panel color or ena/disa detail
    	}
    }
    elseif ($panel_id == 998) {	// exception for admin alert panel
	 print nl2br(read_config_option('intropage_admin_alert'));
    } 
    elseif ($panel_id == 997) {	// exception for maint panel
	 print intropage_maint();
    } 
    else	{
	print __('Panel not found');
    }
}

if (isset_request_var('detail_panel') && isset($panel_id)) {
    include_once($config['base_path'] . '/plugins/intropage/include/data_detail.php');    

    $panel = db_fetch_row ('SELECT panel,fav_graph_id FROM plugin_intropage_user_setting WHERE id = ' . $panel_id);
	if ($panel)	{
	    $pokus = $panel['panel'] . '_detail';
	    $data = $pokus();

	    print '<div id="block" class="color_' . $data['alarm'] . '" ></div>';
	    print '<h3 style="display: inline">' . $data['name'] . '</h3>';
	    print '<br/>' . $data['detail'];
	}
	else	{
	    print __('Panel not found');	
	}
}
