<?php
/* vim: ts=4
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2023 The Cacti Group, Inc.                           |
 | Copyright (C) 2004-2023 Petr Macek                                      |
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

function intropage_config_settings() {
	global $tabs, $settings, $config, $intropage_settings, $trend_timespans;

	include_once($config['base_path'] . '/plugins/intropage/include/variables.php');

	$tabs['intropage'] = __('Intropage', 'intropage');

	$settings['intropage'] = $intropage_settings;

	if (function_exists('auth_augment_roles')) {
		auth_augment_roles(__('Normal User'), array('intropage.php'));
	}
}

function intropage_login_options_navigate() {
	global $config, $login_opts;

	include_once($config['base_path'] . '/plugins/intropage/include/functions.php');

	$console_access = api_plugin_user_realm_auth('index.php');

	$login_opts = get_login_opts();

	$newtheme = false;

	if (user_setting_exists('selected_theme', $_SESSION['sess_user_id']) && read_config_option('selected_theme') != read_user_setting('selected_theme')) {
		unset($_SESSION['selected_theme']);
		$newtheme = true;
	}

	if ($login_opts == 4) {
		header('Location: ' . $config['url_path'] . 'plugins/intropage/intropage.php');
	} elseif ($login_opts == 3) {
		header('Location: ' . $config['url_path'] . 'graph_view.php' . ($newtheme ? '?newtheme=1' : ''));
	}
}

function intropage_console_after() {
	global $config, $panels, $login_opts, $registry;

	include_once($config['base_path'] . '/plugins/intropage/display.php');
	include_once($config['base_path'] . '/plugins/intropage/include/functions.php');

	$login_opts = get_login_opts(true);

	if (api_user_realm_auth('intropage.php') && $config['poller_id'] == 1 || ($config['poller_id'] > 1 && $config['connection'] == 'online'))
		if ($login_opts != 4) {
			$panels = initialize_panel_library();

			process_page_request_variables();

			display_information();
		}
	}

function intropage_user_admin_tab() {
	global $config;

	print '<li class="subTab">';

	if (get_request_var_request('tab') == 'intropage_settings_edit') {
		print '<a class="tab selected" ';
	} else {
		print '<a class="tab" ';
	}

	print 'href="' . html_escape($config['url_path'] . 'user_admin.php?action=user_edit&tab=intropage_settings_edit&id=' . get_request_var('id')) . '">' . __('Intropage', 'intropage') . '</a>';

	print '</li>';

}

function intropage_user_admin_run_action($current_tab){
	global $config, $registry;

	if ($current_tab != 'intropage_settings_edit') {
		return $current_tab;
	}

	include_once($config['base_path'] . '/plugins/intropage/include/functions.php');

	get_filter_request_var('id');

	$panels = initialize_panel_library();

	$fields_intropage_user_edit = array();

	$exists = db_fetch_cell_prepared('SELECT COUNT(*)
		FROM plugin_intropage_user_auth
		WHERE user_id = ?',
		array(get_request_var('id')));

	if (!$exists) {
		db_execute_prepared('INSERT INTO plugin_intropage_user_auth
			(user_id)
			VALUES (?)',
			array(get_request_var('id')));
	}

	$user = db_fetch_row_prepared('SELECT *
		FROM plugin_intropage_user_auth
		WHERE user_id = ?',
		array(get_request_var('id')));

	if (isset($user['permissions'])) {
		$permissions = json_decode($user['permissions'], true);

		$permissions['user_id']    = $user['user_id'];
		$permissions['login_opts'] = $user['login_opts'];

		$user = $permissions;
	}

	$fields = db_fetch_assoc('SELECT panel_id, level, class, name, alarm, description
		FROM plugin_intropage_panel_definition
		UNION
		SELECT "favourite_graph", 1, "graphs", "Favorite Graphs", "green", "Allow you to add your favorite graphs to the dashboard of your choice"
		ORDER BY level, class, name');

       	$header_label = __('[edit: %s]', db_fetch_cell_prepared('SELECT username FROM user_auth WHERE id = ?', array(get_request_var('id'))));

	$prev_level = -1;
	$prev_class = -1;
	$i          = 0;

	foreach ($fields as $field) {
		if ($prev_level != $field['level']) {
			if ($field['level'] == 0) {
				$level = __('System Level Panels', 'intropage');
				$desc  = __('Panels that are appropriate for administrative or power users to access and not typically applicable for general users.  Panels are generally about overall system utilization.', 'intropage');
				$name  = 'spacer_system';
			} else {
				$level = __('User Level Panels', 'intropage');
				$desc  = __('Panels that are appropriate for general users to access.  Permissions are limited to what the current user can view.', 'intropage');
				$name  = 'spacer_user';
			}

			$temp[$name . $i] = array(
				'method'        => 'spacer',
				'friendly_name' => $level,
				'description'   => $desc,
			);
		}

		$i++;

		$prev_level = $field['level'];

		if ($prev_class != $field['class']) {
			$temp[$name . $i] = array(
				'method'        => 'spacer',
				'friendly_name' => $registry[$field['class']]['name'],
				'description'   => $registry[$field['class']]['description'],
			);
		}

		$prev_class = $field['class'];

		if ($field['panel_id'] != 'admin_alert' && $field['panel_id'] != 'maint') {
			$temp[$field['panel_id']] = array(
				'value'         => '|arg1:' . $field['panel_id'] . '|',
				'method'        => 'checkbox',
				'friendly_name' => $field['name'],
				'description'   => $field['description'],
				'default'       => '1'
			);

			$fields_intropage_user_edit = $fields_intropage_user_edit + $temp;
		}

		$i++;
	}

	form_start('user_admin.php?action=user_edit&tab=intropage_settings_edit&id=' . get_request_var('id'));

	print '<div>';
	print "<div class='cactiTableTitle'><span style='padding:3px;'>" . __('You can Allow/Disallow Panels for User','intropage') . ' ' . $header_label . '</span></div>';
	print "<div class='cactiTableButton'><span style='padding:3px;'><input class='checkbox' type='checkbox' id='all' name='all' title='Select All' onClick='selectAllPerms(this.checked)'></a><label class='formCheckboxLabel' title='Select All' for='all'></label></span></div>";
	print '</div>';

	draw_edit_form(
		array(
			'config' => array('no_form_tag' => true),
			'fields' => inject_form_variables($fields_intropage_user_edit, (isset($user) ? $user : array()))
		)
	);

	?>
	<script type='text/javascript'>
	function selectAllPerms(checked) {
		if (checked) {
			$('input[type="checkbox"]').prop('checked', true);
		} else {
			$('input[type="checkbox"]').prop('checked', false);
		}
	}
	</script>
	<?php

	form_save_button(html_escape($config['url_path'] . 'user_admin.php?action=user_edit&tab=general&id=' . get_request_var('id'), 'save'));

	return false;
}

function intropage_user_admin_user_save($save){
	global $config;

	if (get_nfilter_request_var('tab') == 'intropage_settings_edit') {
		$panels   = db_fetch_assoc('SELECT * FROM plugin_intropage_panel_definition');

		$user_id  = get_filter_request_var('id');

		$permissions = array();

		if (db_column_exists('plugin_intropage_user_auth', 'permissions')) {
			$permmode = true;
		} else {
			$permmode = false;
		}

		foreach ($panels as $panel) {
			if (!$permmode) {
				if ($panel['panel_id'] != 'admin_alert' && $panel['panel_id'] != 'maint') {
					db_execute_prepared('UPDATE plugin_intropage_user_auth
						SET `' . $panel['panel_id'] . '` = ?
						WHERE user_id = ?',
						array(get_nfilter_request_var($panel['panel_id']), $user_id));
				}
			} else {
				$permissions[$panel['panel_id']] = (isset_request_var($panel['panel_id']) ? 'on':'');
			}
		}

		if (isset_request_var('favourite_graph')) {
			$permissions['favourite_graph'] = 'on';
		}

		if ($permmode) {
			foreach($permissions as $panel_id => $data) {
				$exists = db_fetch_cell_prepared('SELECT id FROM plugin_intropage_panel_data
					WHERE panel_id = ?
					AND user_id in (0, ?)',
					array($panel_id, $user_id));

				if (!$exists) {
					$panel = db_fetch_row_prepared('SELECT *
						FROM plugin_intropage_panel_definition
						WHERE panel_id = ?',
						array($panel_id));

					$save = array();

					$save['id']               = 0;
					$save['panel_id']         = $panel_id;

					if (isset($panel['level']) && $panel['level'] == 0) {
						$save['user_id'] = 0;
					} else {
						$save['user_id'] = $user_id;
					}

					$save['last_update']      = '0000-00-00';
					$save['data']             = '';
					$save['priority']         = (isset($panel['priority']) ? $panel['priority']:99);
					$save['alarm']            = (isset($panel['alarm']) ? $panel['alarm']:'green');
					$save['refresh_interval'] = (isset($panel['refresh']) ? $panel['refresh']:300);

					$id = sql_save($save, 'plugin_intropage_panel_data');
				}
			}

			db_execute_prepared('UPDATE plugin_intropage_user_auth
				SET permissions = ?
				WHERE user_id = ?',
				array(json_encode($permissions), $user_id));
		}

		raise_message(1);

		header('Location: ' . $config['url_path'] . 'user_admin.php?header=false&action=user_edit&tab=intropage_settings_edit&id=' . $user_id);

		exit;
	}

	return ($save);
}

function intropage_new_user_permission ($user_id) {

	$permissions = array();

	$exists = db_fetch_cell_prepared('SELECT COUNT(*)
		FROM plugin_intropage_user_auth
		WHERE user_id = ?',
		array($user_id));

	if (!$exists) {
		db_execute_prepared('INSERT INTO plugin_intropage_user_auth
			(user_id)
			VALUES (?)',
			array($user_id));
	}

	$user = db_fetch_row_prepared('SELECT *
		FROM plugin_intropage_user_auth
		WHERE user_id = ?',
		array($user_id));

	if ($user['permissions'] == '') {

		$panels = db_fetch_assoc('SELECT panel_id FROM plugin_intropage_panel_definition WHERE level = 1');

		foreach ($panels as $panel) {
			$permissions[$panel['panel_id']] = 'on';
		}

		$permissions['favourite_graph'] = 'on';

		db_execute_prepared('UPDATE plugin_intropage_user_auth
			SET permissions = ?
			WHERE user_id = ?',
			array(json_encode($permissions), $user_id));
	}
}


function intropage_copy_user($user){

	intropage_new_user_permission ($user['new_id']);
	return ($user);
}


function intropage_user_admin_setup_sql_save($save){

	intropage_new_user_permission ($save['id']);
	return ($save);
}


