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

function intropage_config_form() {
	global $fields_user_user_edit_host, $fields_user_group_edit;

	$temp = array(
		'intropage_panels_grp' => array(
			'friendly_name' => __('Intropage Panels', 'intropage'),
			'method' => 'checkbox_group',
			'description' => __('Enable/disable panels', 'intropage'),
			'type' => 'flex',
			'items' => array(
				'intropage_analyse_log' => array(
					'value' => '|arg1:intropage_analyse_log|',
					'friendly_name' => __('Analyze Log', 'intropage'),
					//    'form_id' => '|arg1:id|',
					'default' => '1'
				),
				'intropage_analyse_login' => array(
					'value' => '|arg1:intropage_analyse_login|',
					'friendly_name' => __('Analyze Logins', 'intropage'),
					//    'form_id' => '|arg1:id|',
					'default' => '1'
				),
				'intropage_thold_event' => array(
					'value' => '|arg1:intropage_thold_event|',
					'friendly_name' => __('Threshold Events', 'intropage'),
					'form_id' => '|arg1:id|',
					'default' => '1'
				),
				'intropage_analyse_db' => array(
					'value' => '|arg1:intropage_analyse_db|',
					'friendly_name' => __('Analyze Database', 'intropage'),
					'form_id' => '|arg1:id|',
					'default' => '1'
				),
				'intropage_analyse_tree_host_graph' => array(
					'value' => '|arg1:intropage_analyse_tree_host_graph|',
					'friendly_name' => __('Analyze Objects', 'intropage'),
					'form_id' => '|arg1:id|',
					'default' => '1'
				),
		    		'intropage_trend' => array(
					'value' => '|arg1:intropage_trend|',
					'friendly_name' => __('Trends', 'intropage'),
					'form_id' => '|arg1:id|',
					'default' => '1'
				),
				'intropage_extrem' => array(
					'value' => '|arg1:intropage_extrem|',
					'friendly_name' => __('24h Extremes', 'intropage'),
					'form_id' => '|arg1:id|',
					'default' => '1'
				),
				'intropage_ntp' => array(
					'value' => '|arg1:intropage_ntp|',
					'friendly_name' => __('NTP Status', 'intropage'),
					'form_id' => '|arg1:id|',
					'default' => '1'
				),
				'intropage_poller_info' => array(
					'value' => '|arg1:intropage_poller_info|',
					'friendly_name' => __('Poller Info', 'intropage'),
					'form_id' => '|arg1:id|',
					'default' => '1'
				),
				'intropage_poller_stat' => array(
					'value' => '|arg1:intropage_poller_stat|',
					'friendly_name' => __('Poller Stats', 'intropage'),
					'form_id' => '|arg1:id|',
					'default' => '1'
				),
				'intropage_graph_host' => array(
					'value' => '|arg1:intropage_graph_host|',
					'friendly_name' => __('Graph Device', 'intropage'),
					'form_id' => '|arg1:id|',
					'default' => 'on'
				),
				'intropage_graph_thold' => array(
					'value' => '|arg1:intropage_graph_thold|',
					'friendly_name' => __('Graph Thresholds', 'intropage'),
					'form_id' => '|arg1:id|',
					'default' => 'on'
				),
				'intropage_graph_data_source' => array(
					'value' => '|arg1:intropage_graph_data_source|',
					'friendly_name' => __('Graph Data Dource', 'intropage'),
					'form_id' => '|arg1:id|',
					'default' => 'on'
				),
				'intropage_graph_host_template' => array(
					'value' => '|arg1:intropage_graph_host_template|',
					'friendly_name' => __('Graph Device Template', 'intropage'),
					'form_id' => '|arg1:id|',
					'default' => 'on'
				),
				'intropage_cpuload' => array(
					'value' => '|arg1:intropage_cpuload|',
					'friendly_name' => __('CPU Utilization', 'intropage'),
					'form_id' => '|arg1:id|',
					'default' => 'on'
				),
				'intropage_mactrack' => array(
					'value' => '|arg1:intropage_mactrack|',
					'friendly_name' => __('MacTrack Plugin', 'intropage'),
					'form_id' => '|arg1:id|',
					'default' => 'on'
				),
				'intropage_mactrack_sites' => array(
					'value' => '|arg1:intropage_mactrack_sites|',
					'friendly_name' => __('MacTrack Sites', 'intropage'),
					'form_id' => '|arg1:id|',
					'default' => 'on'
				),
				'intropage_top5_ping' => array(
					'value' => '|arg1:intropage_top5_ping|',
					'friendly_name' => __('Top 5 Ping Times', 'intropage'),
					'form_id' => '|arg1:id|',
					'default' => 'on'
				),
				'intropage_top5_availability' => array(
					'value' => '|arg1:intropage_top5_availability|',
					'friendly_name' => __('Top 5 Availability', 'intropage'),
					'form_id' => '|arg1:id|',
					'default' => 'on'
				),
				'intropage_top5_polltime' => array(
					'value' => '|arg1:intropage_top5_polltime|',
					'friendly_name' => __('Top 5 Worst Polling', 'intropage'),
					'form_id' => '|arg1:id|',
					'default' => 'on'
				),
				'intropage_top5_pollratio' => array(
					'value' => '|arg1:intropage_top5_pollratio|',
					'friendly_name' => __('Top 5 Worst Failed Ratio', 'intropage'),
					'form_id' => '|arg1:id|',
					'default' => 'on'
				),
				'intropage_info' => array(
					'value' => '|arg1:intropage_info|',
					'friendly_name' => __('System Information', 'intropage'),
					'form_id' => '|arg1:id|',
					'default' => 'on'
				),
				'intropage_boost' => array(
					'value' => '|arg1:intropage_boost|',
					'friendly_name' => __('Boost Status', 'intropage'),
					'form_id' => '|arg1:id|',
					'default' => 'on'
				),
				'intropage_favourite_graph' => array(
					'value' => '|arg1:intropage_favourite_graph|',
					'friendly_name' => __('Favourite Graphs', 'intropage'),
					'form_id' => '|arg1:id|',
					'default' => 'on'
				),
				'intropage_plugin_syslog' => array(
					'value' => '|arg1:intropage_plugin_syslog|',
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

	/*
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
	*/
}

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

function intropage_user_admin_setup_sql_save($save) {
	global $settings_user;

	$save['intropage_analyse_log']             = form_input_validate(get_nfilter_request_var('intropage_analyse_log'), 'intropage_analyse_log', '^on$', true, 3);
	$save['intropage_analyse_login']           = form_input_validate(get_nfilter_request_var('intropage_analyse_login'), 'intropage_analyse_login', '^on$', true, 3);
	$save['intropage_thold_event']             = form_input_validate(get_nfilter_request_var('intropage_thold_event'), 'intropage_thold_event', '^on$', true, 3);
	$save['intropage_analyse_db']              = form_input_validate(get_nfilter_request_var('intropage_analyse_db'), 'intropage_analyse_db', '^on$', true, 3);
	$save['intropage_analyse_tree_host_graph'] = form_input_validate(get_nfilter_request_var('intropage_analyse_tree_host_graph'), 'intropage_analyse_tree_host_graph', '^on$', true, 3);
	$save['intropage_trend']                   = form_input_validate(get_nfilter_request_var('intropage_trend'), 'intropage_trend', '^on$', true, 3);
	$save['intropage_extrem']                  = form_input_validate(get_nfilter_request_var('intropage_extrem'), 'intropage_extrem', '^on$', true, 3);
	$save['intropage_ntp']                     = form_input_validate(get_nfilter_request_var('intropage_ntp'), 'intropage_ntp', '^on$', true, 3);
	$save['intropage_poller_info']             = form_input_validate(get_nfilter_request_var('intropage_poller_info'), 'intropage_poller_info', '^on$', true, 3);
	$save['intropage_poller_stat']             = form_input_validate(get_nfilter_request_var('intropage_poller_stat'), 'intropage_poller_stat', '^on$', true, 3);
	$save['intropage_graph_host']              = form_input_validate(get_nfilter_request_var('intropage_graph_host'), 'intropage_graph_host', '^on$', true, 3);
	$save['intropage_graph_thold']             = form_input_validate(get_nfilter_request_var('intropage_graph_thold'), 'intropage_graph_thold', '^on$', true, 3);
	$save['intropage_graph_data_source']       = form_input_validate(get_nfilter_request_var('intropage_graph_data_source'), 'intropage_graph_data_source', '^on$', true, 3);
	$save['intropage_graph_host_template']     = form_input_validate(get_nfilter_request_var('intropage_graph_host_template'), 'intropage_graph_host_template', '^on$', true, 3);
	$save['intropage_cpuload']                 = form_input_validate(get_nfilter_request_var('intropage_cpuload'), 'intropage_cpuload', '^on$', true, 3);
	$save['intropage_mactrack']                = form_input_validate(get_nfilter_request_var('intropage_mactrack'), 'intropage_mactrack', '^on$', true, 3);
	$save['intropage_mactrack_sites']          = form_input_validate(get_nfilter_request_var('intropage_mactrack_sites'), 'intropage_mactrack_sites', '^on$', true, 3);
	$save['intropage_top5_ping']               = form_input_validate(get_nfilter_request_var('intropage_top5_ping'), 'intropage_top5_ping', '^on$', true, 3);
	$save['intropage_top5_availability']       = form_input_validate(get_nfilter_request_var('intropage_top5_availability'), 'intropage_top5_availability', '^on$', true, 3);
	$save['intropage_top5_polltime']           = form_input_validate(get_nfilter_request_var('intropage_top5_polltime'), 'intropage_top5_polltime', '^on$', true, 3);
	$save['intropage_top5_pollratio']	   = form_input_validate(get_nfilter_request_var('intropage_top5_pollratio'), 'intropage_top5_pollratio', '^on$', true, 3);
	$save['intropage_info']                    = form_input_validate(get_nfilter_request_var('intropage_info'), 'intropage_info', '^on$', true, 3);
	$save['intropage_boost']                   = form_input_validate(get_nfilter_request_var('intropage_boost'), 'intropage_boost', '^on$', true, 3);
	$save['intropage_favourite_graph']         = form_input_validate(get_nfilter_request_var('intropage_favourite_graph'), 'intropage_favourite_graph', '^on$', true, 3);
	$save['intropage_syslog']         	   = form_input_validate(get_nfilter_request_var('intropage_syslog'), 'intropage_syslog', '^on$', true, 3);
	// maint - always visible

	return $save;
}
