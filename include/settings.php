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

/*
function intropage_config_form() {
	global $fields_user_user_edit_host, $fields_user_group_edit;

	$temp = array(
		'intropage_panels_grp' => array(
			'friendly_name' => __('Intropage Panels', 'intropage'),
			'method' => 'checkbox_group',
			'description' => __('Enable/disable panels', 'intropage'),
			'type' => 'flex',
			'items' => array(
				'analyse_log' => array(
					'value' => '|arg1:analyse_log|',
					'friendly_name' => __('Analyze Log', 'intropage'),
					//    'form_id' => '|arg1:id|',
					'default' => '1'
				),
				'analyse_login' => array(
					'value' => '|arg1:analyse_login|',
					'friendly_name' => __('Analyze Logins', 'intropage'),
					//    'form_id' => '|arg1:id|',
					'default' => '1'
				),
				'thold_event' => array(
					'value' => '|arg1:thold_event|',
					'friendly_name' => __('Threshold Events', 'intropage'),
					'form_id' => '|arg1:id|',
					'default' => '1'
				),
				'analyse_db' => array(
					'value' => '|arg1:analyse_db|',
					'friendly_name' => __('Analyze Database', 'intropage'),
					'form_id' => '|arg1:id|',
					'default' => '1'
				),
				'analyse_tree_host_graph' => array(
					'value' => '|arg1:analyse_tree_host_graph|',
					'friendly_name' => __('Analyze Objects', 'intropage'),
					'form_id' => '|arg1:id|',
					'default' => '1'
				),
		    		'trend' => array(
					'value' => '|arg1:trend|',
					'friendly_name' => __('Trends', 'intropage'),
					'form_id' => '|arg1:id|',
					'default' => '1'
				),
				'extrem' => array(
					'value' => '|arg1:extrem|',
					'friendly_name' => __('24h Extremes', 'intropage'),
					'form_id' => '|arg1:id|',
					'default' => '1'
				),
				'ntp' => array(
					'value' => '|arg1:ntp|',
					'friendly_name' => __('NTP Status', 'intropage'),
					'form_id' => '|arg1:id|',
					'default' => '1'
				),
				'poller_info' => array(
					'value' => '|arg1:poller_info|',
					'friendly_name' => __('Poller Info', 'intropage'),
					'form_id' => '|arg1:id|',
					'default' => '1'
				),
				'poller_stat' => array(
					'value' => '|arg1:poller_stat|',
					'friendly_name' => __('Poller Stats', 'intropage'),
					'form_id' => '|arg1:id|',
					'default' => '1'
				),
				'graph_host' => array(
					'value' => '|arg1:graph_host|',
					'friendly_name' => __('Graph Device', 'intropage'),
					'form_id' => '|arg1:id|',
					'default' => 'on'
				),
				'graph_thold' => array(
					'value' => '|arg1:graph_thold|',
					'friendly_name' => __('Graph Thresholds', 'intropage'),
					'form_id' => '|arg1:id|',
					'default' => 'on'
				),
				'graph_data_source' => array(
					'value' => '|arg1:graph_data_source|',
					'friendly_name' => __('Graph Data Dource', 'intropage'),
					'form_id' => '|arg1:id|',
					'default' => 'on'
				),
				'graph_host_template' => array(
					'value' => '|arg1:graph_host_template|',
					'friendly_name' => __('Graph Device Template', 'intropage'),
					'form_id' => '|arg1:id|',
					'default' => 'on'
				),
				'cpuload' => array(
					'value' => '|arg1:cpuload|',
					'friendly_name' => __('CPU Utilization', 'intropage'),
					'form_id' => '|arg1:id|',
					'default' => 'on'
				),
				'mactrack' => array(
					'value' => '|arg1:mactrack|',
					'friendly_name' => __('MacTrack Plugin', 'intropage'),
					'form_id' => '|arg1:id|',
					'default' => 'on'
				),
				'mactrack_sites' => array(
					'value' => '|arg1:mactrack_sites|',
					'friendly_name' => __('MacTrack Sites', 'intropage'),
					'form_id' => '|arg1:id|',
					'default' => 'on'
				),
				'top5_ping' => array(
					'value' => '|arg1:top5_ping|',
					'friendly_name' => __('Top 5 Ping Times', 'intropage'),
					'form_id' => '|arg1:id|',
					'default' => 'on'
				),
				'top5_availability' => array(
					'value' => '|arg1:top5_availability|',
					'friendly_name' => __('Top 5 Availability', 'intropage'),
					'form_id' => '|arg1:id|',
					'default' => 'on'
				),
				'top5_polltime' => array(
					'value' => '|arg1:top5_polltime|',
					'friendly_name' => __('Top 5 Worst Polling', 'intropage'),
					'form_id' => '|arg1:id|',
					'default' => 'on'
				),
				'top5_pollratio' => array(
					'value' => '|arg1:top5_pollratio|',
					'friendly_name' => __('Top 5 Worst Failed Ratio', 'intropage'),
					'form_id' => '|arg1:id|',
					'default' => 'on'
				),
				'info' => array(
					'value' => '|arg1:info|',
					'friendly_name' => __('System Information', 'intropage'),
					'form_id' => '|arg1:id|',
					'default' => 'on'
				),
				'boost' => array(
					'value' => '|arg1:boost|',
					'friendly_name' => __('Boost Status', 'intropage'),
					'form_id' => '|arg1:id|',
					'default' => 'on'
				),
				'favourite_graph' => array(
					'value' => '|arg1:favourite_graph|',
					'friendly_name' => __('Favourite Graphs', 'intropage'),
					'form_id' => '|arg1:id|',
					'default' => 'on'
				),
				'plugin_syslog' => array(
					'value' => '|arg1:plugin_syslog|',
					'friendly_name' => __('Syslog Plugin', 'intropage'),
					'form_id' => '|arg1:id|',
					'default' => 'on'
				)
			)
		)
	);

	$new = array();
	foreach ($fields_user_user_edit_host as $key => $val) {
		$new = array_merge($new, array($key => $val));
		if ($key == 'realm') {
			$new = array_merge($new, $temp);
		}
	}

	$fields_user_user_edit_host = $new;

	array_push($fields_user_user_edit_host['login_opts']['items'],
		array(
			'radio_value' => '4',
			'radio_caption' => __('Show Intropage in Tab (not selected = intropage in console)', 'intropage')
		)
	);


		// usergroup
		$temp = array(

			   'intropage_panels_grp' => array(
					'friendly_name' => __('Intropage panels'),
					'method' => 'checkbox_group',
					'description' => 'Enable/disable panels',
					'items' => array(
							'intropage_analyse_log' => array(
									'value' => '|arg1:intropage_analyse_log|',
									'friendly_name' => 'Allow panel Analyze log',
								//    'form_id' => '|arg1:id|',
									'default' => '1'
									),
							'intropage_analyse_login' => array(
									'value' => '|arg1:intropage_analyse_login|',
									'friendly_name' => 'Allow panel Analyze logins',
								//    'form_id' => '|arg1:id|',
									'default' => '1'
									),
			),

			)
		);


		$new = array();
		foreach($fields_user_group_edit as $key => $val) {
			$new = array_merge($new,array($key => $val));
			if ($key == 'login_opts') {
				$new = array_merge($new,$temp);
			}
		}


		$fields_user_group_edit = $new;

		array_push ($fields_user_group_edit['login_opts']['items'],array("radio_value"=>"4","radio_caption"=>"Show Intropage (no matter in console or tab)"));

}
*/

function intropage_config_settings() {
	global $tabs, $settings, $config, $intropage_settings;
	include_once($config['base_path'] . '/plugins/intropage/include/variables.php');

	$tabs['intropage'] = __('Intropage', 'intropage');

	$settings['intropage'] = $intropage_settings;

	if (function_exists('auth_augment_roles')) {
		auth_augment_roles(__('Normal User'), array('intropage.php'));
	}
}

function intropage_login_options_navigate() {
	global $config;

	$console_access = api_plugin_user_realm_auth('index.php');

	if (empty($_SESSION['login_opts']))	{   // potrebuju to mit v session, protoze treba mi zmeni z konzole na tab a pak spatne vykresluju
		$login_opts = db_fetch_cell_prepared('SELECT login_opts FROM user_auth WHERE id = ?', array($_SESSION['sess_user_id']));
		$_SESSION['login_opts'] = $login_opts;
	}

	$newtheme = false;
	if (user_setting_exists('selected_theme', $_SESSION['sess_user_id']) && read_config_option('selected_theme') != read_user_setting('selected_theme')) {
		unset($_SESSION['selected_theme']);
		$newtheme = true;
	}

	if ($_SESSION['login_opts'] == 4) {	// intropage in tab
		header('Location: ' . $config['url_path'] . 'plugins/intropage/intropage.php');
	}

	if ($_SESSION['login_opts'] == 3) {
		header('Location: ' . $config['url_path'] . 'graph_view.php' . ($newtheme ? '?newtheme=1' : ''));
	}
}

function intropage_console_after() {
	global $config;

	if (empty($_SESSION['login_opts']))	{   // potrebuju to mit v session, protoze treba mi zmeni z konzole na tab a pak spatne vykresluju
		$login_opts = db_fetch_cell_prepared('SELECT login_opts
			FROM user_auth
			WHERE id = ?',
			array($_SESSION['sess_user_id']));

		$_SESSION['login_opts'] = $login_opts;
	}

	if ($_SESSION['login_opts'] != 4) { // in tab, otherwise it displays intropage in tab and console too
		include_once($config['base_path'] . '/plugins/intropage/display.php');
		display_information();
	}
}


function intropage_user_admin_tab() {
	global $config;
	
	print '<li class="subTab">';

	if (get_request_var_request("tab") == "intropage_settings_edit") {
		print '<a class="tab selected" ';
	}
	else {
		print '<a class="tab" ';
	}

	print 'href="' . html_escape($config['url_path'] .  'user_admin.php?action=user_edit&tab=intropage_settings_edit&id=' . get_request_var('id')) . '">Intropage</a>';
	print '</li>';

}

function intropage_user_admin_run_action(){
	global $config;

	input_validate_input_number(get_request_var('id'));

	$fields_intropage_user_edit = array(
	        'general_header' => array(
                        'friendly_name' => __('Intropage - All panels', 'intropage'),
                        'method' => 'spacer',
                ),
	);

        if (db_fetch_cell_prepared('SELECT count(*) FROM plugin_intropage_user_auth WHERE user_id= ?',array(get_request_var('id'))) == 0) {
                db_execute_prepared('INSERT INTO plugin_intropage_user_auth (user_id) VALUES ( ? )', array(get_request_var('id')));
        }


	$user = db_fetch_row_prepared('SELECT * FROM plugin_intropage_user_auth WHERE user_id= ?', array(get_request_var('id')));
	$fields = db_fetch_assoc('SELECT panel_id, description FROM plugin_intropage_panel_definition');

	foreach ($fields as $field) {
		if ($field['panel_id'] != 'admin_alert' && $field['panel_id'] != 'maint') {
			$temp[$field['panel_id']] = array(
				'value' => '|arg1:' . $field['panel_id'] . '|',
				'method' => 'checkbox',
				'friendly_name' => ucwords(str_replace('_', ' ', $field['panel_id'])),
				'description' => $field['description'],
				'default' => '1'	);

			$fields_intropage_user_edit = $fields_intropage_user_edit + $temp;
		}
	}

	form_start('?action=user_edit&tab=intropage_settings_edit&id=' . get_request_var('id'));
        html_start_box(__('You can allow/disallow panels for user','intropage'), '100%', '', '3', 'center', '');

        draw_edit_form(
                array(
                        'config' => array('no_form_tag' => true),
                        'fields' => inject_form_variables($fields_intropage_user_edit, (isset($user) ? $user : array()))
                )
        );

        html_end_box();

        form_save_button(html_escape($config['url_path'] . 'user_admin.php?action=user_edit&tab=general&id=' . get_request_var('id'),'save'));
	form_end();

}


function intropage_user_admin_user_save($save){

	$panels = db_fetch_assoc('SELECT panel_id FROM plugin_intropage_panel_definition');

	foreach ($panels as $panel) {
		if ($panel['panel_id'] != 'admin_alert' && $panel['panel_id'] != 'maint') {
			db_execute('update plugin_intropage_user_auth set ' . $panel['panel_id'] . '="' . get_nfilter_request_var($panel['panel_id']) .
			'" WHERE user_id = ' . get_request_var('id'));
		}
	}
	
	return ($save);
}



//function intropage_user_admin_action($action){
	// tohle se pousti, kdyz admin klepne na editaci libovolneho uzivatele
//}


