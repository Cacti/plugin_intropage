<?php

function intropage_config_form() {
	global $fields_user_user_edit_host, $fields_user_group_edit;

	$temp = array(
		'intropage_opts' => array(
			'friendly_name' => 'Intro Page Options',
			'method' => 'radio',
			'default' => '0',
			'description' => 'How we should display the intropage. <strong>For users without console access you must choose separated tab</strong>',
			'value' => '|arg1:intropage_opts|',
			'items' => array(
				0 => array(
					'radio_value' => '0',
					'radio_caption' => 'Show the Intropage plugin screen in console screen (you need console access permission!)'),
				1 => array(
					'radio_value' => '1',
					'radio_caption' => 'Show the Intropage plugin screen in separated tab'),
			),
		),

		   'intropage_panels_grp' => array(
				'friendly_name' => __('Intropage panels'),
				'method' => 'checkbox_group',
				'description' => 'Enable/disable panels',
				'items' => array(
						'intropage_analyse_log' => array(
								'value' => '|arg1:intropage_analyse_log|',
								'friendly_name' => 'Allow panel Analayse log',
							//    'form_id' => '|arg1:id|',
								'default' => '1'
								),
						'intropage_analyse_login' => array(
								'value' => '|arg1:intropage_analyse_login|',
								'friendly_name' => 'Allow panel Analayse logins',
							//    'form_id' => '|arg1:id|',
								'default' => '1'
								),
						'intropage_thold_event' => array(
								'value' => '|arg1:intropage_thold_event|',
								'friendly_name' => 'Allow panel Threshold events',
								'form_id' => '|arg1:id|',
								'default' => '1'
								),
						'intropage_analyse_db' => array(
								'value' => '|arg1:intropage_analyse_db|',
								'friendly_name' => 'Allow panel Analayse database',
								'form_id' => '|arg1:id|',
								'default' => '1'
								),
						'intropage_analyse_tree_host_graph' => array(
								'value' => '|arg1:intropage_analyse_tree_host_graph|',
								'friendly_name' => 'Allow panel Analayse tree, hosts, graphs',
								'form_id' => '|arg1:id|',
								'default' => '1'
								),
						'intropage_trend' => array(
								'value' => '|arg1:intropage_trend|',
								'friendly_name' => 'Allow panel Trends',
								'form_id' => '|arg1:id|',
								'default' => '1'
								),
						'intropage_extrem' => array(
								'value' => '|arg1:intropage_extrem|',
								'friendly_name' => 'Allow panel 24h extrems',
								'form_id' => '|arg1:id|',
								'default' => '1'
								),
						'intropage_ntp' => array(
								'value' => '|arg1:intropage_ntp|',
								'friendly_name' => 'Allow panel NTP',
								'form_id' => '|arg1:id|',
								'default' => '1'
								),
						'intropage_poller_info' => array(
								'value' => '|arg1:intropage_poller_info|',
								'friendly_name' => 'Allow panel Poller info',
								'form_id' => '|arg1:id|',
								'default' => '1'
								),
						'intropage_poller_stat' => array(
								'value' => '|arg1:intropage_poller_stat|',
								'friendly_name' => 'Allow panel Poller stats',
								'form_id' => '|arg1:id|',
								'default' => '1'
								),
						'intropage_graph_host' => array(
								'value' => '|arg1:intropage_graph_host|',
								'friendly_name' => 'Allow panel Graph host',
								'form_id' => '|arg1:id|',
								'default' => 'on'
								),
						'intropage_graph_thold' => array(
								'value' => '|arg1:intropage_graph_thold|',
								'friendly_name' => 'Allow panel Graph thold',
								'form_id' => '|arg1:id|',
								'default' => 'on'
								),

						'intropage_graph_data_source' => array(
								'value' => '|arg1:intropage_graph_data_source|',
								'friendly_name' => 'Allow panel Graph data source',
								'form_id' => '|arg1:id|',
								'default' => 'on'
								),
						'intropage_graph_host_template' => array(
								'value' => '|arg1:intropage_graph_host_template|',
								'friendly_name' => 'Allow panel Graph host template',
								'form_id' => '|arg1:id|',
								'default' => 'on'
								),
						'intropage_cpu' => array(
								'value' => '|arg1:intropage_cpu|',
								'friendly_name' => 'Allow panel CPU utilization',
								'form_id' => '|arg1:id|',
								'default' => 'on'
								),
						'intropage_mactrack' => array(
								'value' => '|arg1:intropage_mactrack|',
								'friendly_name' => 'Allow panel Mactrack plugin',
								'form_id' => '|arg1:id|',
								'default' => 'on'
								),
						'intropage_mactrack_sites' => array(
								'value' => '|arg1:intropage_mactrack_sites|',
								'friendly_name' => 'Allow panel Mactrack sites',
								'form_id' => '|arg1:id|',
								'default' => 'on'
								),

						'intropage_top5_ping' => array(
								'value' => '|arg1:intropage_top5_ping|',
								'friendly_name' => 'Allow panel Top 5 ping',
								'form_id' => '|arg1:id|',
								'default' => 'on'
								),
						'intropage_top5_availability' => array(
								'value' => '|arg1:intropage_top5_availability|',
								'friendly_name' => 'Allow panel Top 5 availability',
								'form_id' => '|arg1:id|',
								'default' => 'on'
								),

						'intropage_info' => array(
								'value' => '|arg1:intropage_info|',
								'friendly_name' => 'Allow panel System information',
								'form_id' => '|arg1:id|',
								'default' => 'on'
								),
						'intropage_boost' => array(
								'value' => '|arg1:intropage_boost|',
								'friendly_name' => 'Allow panel Boost',
								'form_id' => '|arg1:id|',
								'default' => 'on'
								),
						'intropage_favourite_graph' => array(
								'value' => '|arg1:intropage_favourite_graph|',
								'friendly_name' => 'Allow panel Favourite graphs',
								'form_id' => '|arg1:id|',
								'default' => 'on'
								),
						)
				),


	);

	$new = array();
	foreach ($fields_user_user_edit_host as $key => $val) {
		$new = array_merge($new, array($key => $val));
		if ($key == 'login_opts') {
			$new = array_merge($new, $temp);
		}
	}

	$fields_user_user_edit_host = $new;

	array_push($fields_user_user_edit_host['login_opts']['items'], array('radio_value' => '4', 'radio_caption' => 'Show Intropage (no matter in console or tab)'));

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
									'friendly_name' => 'Allow panel Analayse log',
								//    'form_id' => '|arg1:id|',
									'default' => '1'
									),
							'intropage_analyse_login' => array(
									'value' => '|arg1:intropage_analyse_login|',
									'friendly_name' => 'Allow panel Analayse logins',
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

	$tabs['intropage']     = 'Intropage';
	$settings['intropage'] = $intropage_settings;
}

function intropage_login_options_navigate() {
	global $config;

	$intropage_lopts = db_fetch_cell('SELECT intropage_opts FROM user_auth WHERE id=' . $_SESSION['sess_user_id']);
	$system_lopts    = db_fetch_cell('SELECT login_opts FROM user_auth WHERE id=' . $_SESSION['sess_user_id']);
	$console_access  = (db_fetch_assoc("select realm_id from user_auth_realm where user_id='" . $_SESSION['sess_user_id'] . "' and user_auth_realm.realm_id=8")) ? true : false;

	//echo "console access: $console_access | system login options: $system_lopts | intropake login options: $intropage_lopts<br/>";

	$newtheme = false;
	if (user_setting_exists('selected_theme', $_SESSION['sess_user_id']) && read_config_option('selected_theme') != read_user_setting('selected_theme')) {
		unset($_SESSION['selected_theme']);
		$newtheme = true;
	}
	//echo $system_lopts;

	if ($console_access) {
		if ($system_lopts == 4 && $intropage_lopts == 1) {	// intropage as default
			header('Location: ' . $config['url_path'] . 'plugins/intropage/intropage.php');
		}
		if ($system_lopts == 4 && $intropage_lopts == 0) {
			header('Location: ' . $config['url_path']);
		}
		// ostatni resi asi auth login
	} else {	// no console access
		if ($system_lopts == 4 || $system_lopts == 2) {	// intropage as default
					header('Location: ' . $config['url_path'] . 'plugins/intropage/intropage.php');
		}


		if ($system_lopts == 3) {
			header('Location: ' . $config['url_path'] . 'graph_view.php' . ($newtheme ? '?newtheme=1' : ''));
		}
	}
}

function intropage_console_after() {
	global $config;
	$lopts = db_fetch_cell('SELECT intropage_opts FROM user_auth WHERE id=' . $_SESSION['sess_user_id']);


	if ($lopts == 1) { // in tab
	} else {  // in console
		include_once($config['base_path'] . '/plugins/intropage/display.php');
		display_information();
	}

	// reload
/*
	$timeout = read_user_setting('intropage_autorefresh');
	if ($timeout > 0) {
		$timeout *= 1000;

		print <<<EOF

<script type="text/javascript">
var timeout = setInterval(reloadChat, $timeout);
function reloadChat () {
     $('#megaobal').load('$config[url_path]plugins/intropage/intropage_ajax.php');
}
</script>

EOF;
	
	}
	*/
}

function intropage_user_admin_setup_sql_save($save) {
	global $settings_user;

	$save['intropage_opts']                    = form_input_validate(get_nfilter_request_var('intropage_opts'), 'intropage_opts', '^[01]$', true, 3);
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
	$save['intropage_cpu']                     = form_input_validate(get_nfilter_request_var('intropage_cpu'), 'intropage_cpu', '^on$', true, 3);
	$save['intropage_mactrack']                = form_input_validate(get_nfilter_request_var('intropage_mactrack'), 'intropage_mactrack', '^on$', true, 3);
	$save['intropage_mactrack_sites']          = form_input_validate(get_nfilter_request_var('intropage_mactrack_sites'), 'intropage_mactrack_sites', '^on$', true, 3);
	$save['intropage_top5_ping']               = form_input_validate(get_nfilter_request_var('intropage_top5_ping'), 'intropage_top5_ping', '^on$', true, 3);
	$save['intropage_top5_availability']       = form_input_validate(get_nfilter_request_var('intropage_top5_availability'), 'intropage_top5_availability', '^on$', true, 3);
	$save['intropage_info']                    = form_input_validate(get_nfilter_request_var('intropage_info'), 'intropage_info', '^on$', true, 3);
	$save['intropage_boost']                   = form_input_validate(get_nfilter_request_var('intropage_boost'), 'intropage_boost', '^on$', true, 3);
	$save['intropage_favourite_graph']         = form_input_validate(get_nfilter_request_var('intropage_favourite_graph'), 'intropage_favourite_graph', '^on$', true, 3);
	// maint - always visible

	return $save;
}
