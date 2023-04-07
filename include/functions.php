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

define('PANEL_SYSTEM', 0);
define('PANEL_USER',   1);

if (isset($run_from_poller)) {
	$_SESSION['sess_user_id'] = 0;
}

function intropage_get_allowed_devices($user_id) {
	$x  = 0;
	$us = read_user_setting('hide_disabled', false, false, $user_id);

	if ($us == 'on') {
		set_user_setting('hide_disabled', '', $user_id);
	}

	$allowed = get_allowed_devices('', 'null', -1, $x, $user_id);

	if ($us == 'on') {
		set_user_setting('hide_disabled', 'on', $user_id);
	}

	if (cacti_count($allowed)) {
		return implode(',', array_column($allowed, 'id'));
	} else {
		return false;
	}
}

if (!function_exists('array_column')) {
    function array_column($array,$column_name) {
        return array_map(function($element) use($column_name) {
			return $element[$column_name];
		}, $array);
    }
}

function process_page_request_variables() {
	set_default_action();

	if (isset_request_var('intropage_addpanel')) {
		intropage_action_add_panel();
	} elseif (isset_request_var('action') && isset_request_var('save_settings')) {
		intropage_action_settings();
	} elseif (isset_request_var('intropage_action')) {
		intropage_actions();
	} elseif (get_request_var('action') != '') {
		switch(get_request_var('action')) {
			case 'configure':
				intropage_configure_panel();

				break;
			case 'autoreload':
				intropage_autoreload();

				break;
			case 'reload':
				intropage_reload_panel();

				break;
			case 'details':
				intropage_detail_panel();

				break;
			case 'graph':
				intropage_display_graph();

				break;
		}

		exit;
	}
}

function intropage_action_add_panel() {
	$dashboard_id = get_filter_request_var('dashboard_id');

	if (is_numeric(get_nfilter_request_var('intropage_addpanel'))) {
		$addpanel = get_filter_request_var('intropage_addpanel');
	} else {
		$panel_id = get_nfilter_request_var('intropage_addpanel');

		$panel = db_fetch_row_prepared('SELECT *
			FROM plugin_intropage_panel_definition
			WHERE panel_id = ?',
			array($panel_id));

		$save = array();

		$save['id']               = 0;
		$save['panel_id']         = $panel_id;
		$save['user_id']          = ($panel['level'] == PANEL_SYSTEM ? 0 : $_SESSION['sess_user_id']);
		$save['last_update']      = '0000-00-00';
		$save['data']             = '';
		$save['priority']         = $panel['priority'];
		$save['alarm']            = $panel['alarm'];
		$save['refresh_interval'] = $panel['refresh'];

		$addpanel = sql_save($save, 'plugin_intropage_panel_data');
	}

	if ($addpanel != '' && $dashboard_id > 0) {
		db_execute_prepared('INSERT INTO plugin_intropage_panel_dashboard
			(panel_id, user_id, dashboard_id)
			VALUES (?, ?, ?)',
			array($addpanel, $_SESSION['sess_user_id'], $dashboard_id));

		// Refresh the data if allowed
	}
}

function intropage_action_settings() {
	foreach($_POST as $var => $value) {
		if (strpos($var, 'name_') !== false) {
			$dashboard_id = str_replace('name_', '', $var);

			db_execute_prepared('REPLACE INTO plugin_intropage_dashboard
				(user_id, dashboard_id, name)
				VALUES (?, ?, ?)',
				array($_SESSION['sess_user_id'], $dashboard_id, $value));
		}
	}

	// panel refresh
	if (api_user_realm_auth('intropage_admin.php')) {
		$panels = db_fetch_assoc_prepared('SELECT pda.panel_id AS panel_name, pda.id AS id
			FROM plugin_intropage_panel_data AS pda
			WHERE pda.user_id IN (0, ?)
			AND pda.fav_graph_id IS NULL',
			array($_SESSION['sess_user_id']));
	} else {
		$panels = db_fetch_assoc_prepared('SELECT pda.panel_id AS panel_name, pda.id AS id
			FROM plugin_intropage_panel_data AS pda
			WHERE pda.user_id = ?
			AND pda.fav_graph_id IS NULL',
			array($_SESSION['sess_user_id']));
	}

	if (cacti_sizeof($panels)) {
		foreach ($panels as $panel) {
			$interval = get_filter_request_var('crefresh_' . $panel['id'], FILTER_VALIDATE_INT);

			if ($interval >= 0 && $interval <= 999999999) {
				db_execute_prepared('UPDATE plugin_intropage_panel_data
					SET refresh_interval = ?
					WHERE id = ?',
					array($interval, $panel['id']));
			}

			$interval = get_filter_request_var('trefresh_' . $panel['id'], FILTER_VALIDATE_INT);

			if ($interval >= 0 && $interval <= 999999999) {
				db_execute_prepared('UPDATE plugin_intropage_panel_data
					SET trend_interval = ?
					WHERE id = ?',
					array($interval, $panel['id']));
			}
		}
	}

	raise_message(1);
}

function intropage_actions() {
	global $login_opts, $config;

	$actionvar = get_filter_request_var('intropage_action', FILTER_VALIDATE_REGEXP, array('options' => array('regexp' => '/^([a-z0-9_-]+)$/')));

	$values = explode('_', $actionvar);

	// few parameters from input type select has format reset_all, refresh_180, ... first is action
	$action = $values[0];

	if (isset($values[1])) {
		$value = trim($values[1]);
	} else {
		$value = '';
	}
	if (isset($values[2])) {
		$value_ext = trim($values[2]);
	}

	switch ($action) {
	case 'addpanelselect':
		intropage_addpanel_select(get_filter_request_var('panel_id'), get_filter_request_var('dashboard_id'));

		exit;

		break;
	case 'droppanel':
		if (get_filter_request_var('panel_id')) {
			db_execute_prepared('DELETE FROM plugin_intropage_panel_dashboard
				WHERE user_id = ?
				AND panel_id = ?',
				array($_SESSION['sess_user_id'], get_request_var('panel_id')));

			// Delete user data, but not system user data
			db_execute_prepared('DELETE FROM plugin_intropage_panel_data
				WHERE user_id = ?
				AND id = ?',
				array($_SESSION['sess_user_id'], get_request_var('panel_id')));
		}

		break;
	case 'removepage':
		if (filter_var($value, FILTER_VALIDATE_INT)) {
			db_execute_prepared('DELETE FROM plugin_intropage_dashboard
				WHERE user_id = ?
				AND dashboard_id = ?',
				array($_SESSION['sess_user_id'], $value));

			db_execute_prepared('DELETE FROM plugin_intropage_panel_dashboard
				WHERE user_id = ?
				AND dashboard_id = ?',
				array($_SESSION['sess_user_id'], $value));

			$dashboard_id = db_fetch_cell_prepared('SELECT MIN(dashboard_id)
				FROM plugin_intropage_dashboard
				WHERE user_id = ?',
				array($_SESSION['sess_user_id']));

			raise_message('dashboard_removed', __('Dashboard has been removed', 'intropage'), MESSAGE_LEVEL_INFO);

			if ($login_opts == 4) {
				header('Location: ' . html_escape($config['url_path']) . 'plugins/intropage/intropage.php?header=false&dashboard_id=' . $dashboard_id);
			} else {
				header('Location: ' . html_escape($config['url_path']) . 'index.php?header=false&dashboard_id=' . $dashboard_id);
			}

			exit;
		}

		break;
	case 'addpage':
		if (filter_var($value, FILTER_VALIDATE_INT)) {
			$dashboard_id = db_fetch_cell_prepared('SELECT MAX(dashboard_id)+1
				FROM plugin_intropage_dashboard
				WHERE user_id = ?',
				array($_SESSION['sess_user_id']));

			db_execute_prepared('INSERT INTO plugin_intropage_dashboard
				(user_id, dashboard_id, name)
				VALUES (?, ?, ?)',
				array($_SESSION['sess_user_id'], $dashboard_id, __('New Dashboard', 'intropage')));

			raise_message('dashboard_added', __('Dashboard has been added', 'intropage'), MESSAGE_LEVEL_INFO);

			if ($login_opts == 4) {
				header('Location: ' . html_escape($config['url_path']) . 'plugins/intropage/intropage.php?header=false&dashboard_id=' . $dashboard_id);
			} else {
				header('Location: ' . html_escape($config['url_path']) . 'index.php?header=false&dashboard_id=' . $dashboard_id);
			}

			exit;
		}

		break;
	case 'favgraph':
		if (get_filter_request_var('graph_id')) {
			// already fav?

			$exists = db_fetch_cell_prepared('SELECT COUNT(*)
				FROM plugin_intropage_panel_data
				WHERE user_id = ?
				AND fav_graph_id = ?
				AND fav_graph_timespan = ?',
				array($_SESSION['sess_user_id'], get_request_var('graph_id'), $_SESSION['sess_current_timespan']));

			if ($exists) {
				db_execute_prepared('DELETE FROM plugin_intropage_panel_data
					WHERE user_id = ?
					AND fav_graph_id = ?
					AND fav_graph_timespan = ?',
					array($_SESSION['sess_user_id'],get_request_var('graph_id'),$_SESSION['sess_current_timespan']));
			}

			if ($_SESSION['sess_current_timespan'] == 0) {
				raise_message('custom_error',__('Cannot add zoomed or custom timespaned graph, changing timespan to Last half hour'));
				$span = 1;
			} else {
				$span = $_SESSION['sess_current_timespan'];
			}

			$prio = db_fetch_cell_prepared('SELECT MAX(priority) + 1
				FROM plugin_intropage_panel_data
				WHERE user_id = ?',
				array($_SESSION['sess_user_id']));

			db_execute_prepared('INSERT INTO plugin_intropage_panel_data
				(user_id, panel_id, fav_graph_id, fav_graph_timespan, priority)
				VALUES (?, "favourite_graph", ?, ?, ?)',
				array($_SESSION['sess_user_id'],get_request_var('graph_id'), $span, $prio));

			$id = db_fetch_insert_id();
			db_execute_prepared('INSERT INTO plugin_intropage_panel_dashboard
				(panel_id, user_id, dashboard_id) VALUES ( ?, ?, ?)',
				array($id, $_SESSION['sess_user_id'], $_SESSION['dashboard_id']));
		}

		break;
	case 'order':
		if (isset_request_var('xdata')) {
			$error = false;
			$order = array();
			$priority = 90; // >90 are fav. graphs

			foreach (get_nfilter_request_var('xdata') as $data) {
				list($a, $b) = explode('_', $data);

				if (filter_var($b, FILTER_VALIDATE_INT)) {
					array_push($order, $b);
				} else {
					$error = true;
				}

				if (!$error) {
					db_execute_prepared('UPDATE plugin_intropage_panel_data
						SET priority = ?
						WHERE user_id = ?
						AND id = ?',
						array ($priority, $_SESSION['sess_user_id'], $b));

  					$priority--;
				}
			}
		}

		break;
	case 'refresh':
		if (filter_var($value, FILTER_VALIDATE_INT)) {
			set_user_setting('intropage_autorefresh', $value);
		}

		break;
	case 'period':
		if (filter_var($value, FILTER_VALIDATE_INT)) {
			set_user_setting('intropage_important_period', $value);
		}

		break;
	case 'lines':
		if (filter_var($value, FILTER_VALIDATE_INT)) {
			set_user_setting('intropage_number_of_lines', $value);
		}

		break;
	case 'timespan':
		$timespan = $value;

		if (filter_var($value, FILTER_VALIDATE_INT)) {
			set_user_setting('intropage_timespan', $value);
		}

		$panels = db_fetch_assoc_prepared('SELECT DISTINCT ipd.panel_id
			FROM plugin_intropage_panel_dashboard AS ipda
			INNER JOIN plugin_intropage_panel_data AS ipd
			ON ipda.panel_id = ipd.id
			WHERE ipda.user_id = ?',
			array($_SESSION['sess_user_id']));

		foreach($panels as $panel) {
			$qpanel = get_panel_details($panel['panel_id'], $_SESSION['sess_user_id']);

			if (isset($qpanel['definition']['trends_func']) && $qpanel['definition']['trends_func'] != '') {
				if (function_exists($qpanel['definition']['update_func'])) {
					if ($qpanel['definition']['level'] == 0) {
						$qpanel['definition']['update_func']($qpanel, 0, $timespan);
					} else {
						$qpanel['definition']['update_func']($qpanel, $_SESSION['sess_user_id'], $timespan);
					}
				}
			}
		}

		break;
	case 'important':
		if ($value == 'first') {
			set_user_setting('intropage_display_important_first', 'on');
		} else {
			set_user_setting('intropage_display_important_first', 'off');
		}

		break;
	case 'loginopt':
		if ($value == 'graph') {
			db_fetch_cell_prepared('UPDATE user_auth SET login_opts = 3 WHERE id = ?', array($_SESSION['sess_user_id']));
		} elseif ($value == 'console') {
			db_fetch_cell_prepared('UPDATE user_auth SET login_opts = 2 WHERE id = ?', array($_SESSION['sess_user_id']));
		} elseif ($value == 'tab') {
			db_fetch_cell_prepared('UPDATE user_auth SET login_opts = 4 WHERE id = ?', array($_SESSION['sess_user_id']));
		}

		break;
	case 'forcereload':
		$panels = initialize_panel_library();

		update_registered_panels($panels);

		raise_message('panellibrefresh', __('Intropage Panel Library Refreshed from Panel Library', 'intropage'), MESSAGE_LEVEL_INFO);

		break;
	case 'displaywide':
		if ($value == 'on') {
			set_user_setting('intropage_display_wide', 'on');
		} else {
			set_user_setting('intropage_display_wide', 'off');
		}

		break;
	case 'share':
		db_execute_prepared ('UPDATE plugin_intropage_dashboard
			SET shared = 1
			WHERE user_id = ? AND dashboard_id = ?',
			array ($_SESSION['sess_user_id'], $_SESSION['dashboard_id']));
		break;
	case 'unshare':
		db_execute_prepared ('UPDATE plugin_intropage_dashboard
			SET shared = 0
			WHERE user_id = ? AND dashboard_id = ?',
			array ($_SESSION['sess_user_id'], $_SESSION['dashboard_id']));

		break;
	case 'useshared':

		if (filter_var($value, FILTER_VALIDATE_INT) && filter_var($value_ext, FILTER_VALIDATE_INT)) {
			$shared = db_fetch_cell_prepared('SELECT shared FROM plugin_intropage_dashboard
				WHERE dashboard_id = ? AND user_id = ?',
				array($value, $value_ext));

			if ($shared) {
				$username = get_username($value_ext);

				$dashboard = db_fetch_row_prepared ('SELECT name, user_id
					FROM plugin_intropage_dashboard
					WHERE dashboard_id = ? AND user_id = ?',
					array ($value, $value_ext));

				$new_dashboard_id = db_fetch_cell_prepared('SELECT MAX(dashboard_id)+1
					FROM plugin_intropage_dashboard
					WHERE user_id = ?',
					array($_SESSION['sess_user_id']));

				db_execute_prepared('INSERT INTO plugin_intropage_dashboard
					(user_id, dashboard_id, name)
					VALUES (?, ?, ?)',
					array($_SESSION['sess_user_id'], $new_dashboard_id, $username . '-' . $dashboard['name']));

				$ids_panels = db_fetch_assoc_prepared('SELECT panel_id
					FROM plugin_intropage_panel_dashboard
					WHERE dashboard_id = ? AND user_id = ?',
					array($value, $value_ext));

				foreach ($ids_panels as $id_panel) {
					db_execute_prepared('INSERT INTO plugin_intropage_panel_data
						(panel_id,user_id,data,priority,refresh_interval,trend_interval,fav_graph_id,fav_graph_timespan)
						SELECT panel_id, ? ,data,priority,refresh_interval,trend_interval,fav_graph_id,fav_graph_timespan
						FROM plugin_intropage_panel_data
						WHERE id = ?',
						array ($_SESSION['sess_user_id'],$id_panel['panel_id']));

					$last = db_fetch_insert_id();

					db_execute_prepared('INSERT INTO plugin_intropage_panel_dashboard
						(panel_id, user_id, dashboard_id)
						VALUES (?, ?, ?)',
						array($last, $_SESSION['sess_user_id'], $new_dashboard_id));
				}

				raise_message('dashboard_added', __('Dashboard has been added, please wait few poller cycle for data', 'intropage'), MESSAGE_LEVEL_INFO);

				if ($login_opts == 4) {
					header('Location: ' . html_escape($config['url_path']) . 'plugins/intropage/intropage.php?header=false&dashboard_id=' . $new_dashboard_id);
				} else {
					header('Location: ' . html_escape($config['url_path']) . 'index.php?header=false&dashboard_id=' . $new_dashboard_id);
				}

				exit;

			} else {
				raise_message('share_panel_error', __('Error - trying share non-shared dashboard', 'intropage'), MESSAGE_LEVEL_INFO);
			}
		}
		break;
	}
}

function is_panel_enabled($panel_id) {
	$panels = initialize_panel_library();

	// Panel library prunes unavailable panels
	if (!isset($panels[$panel_id])) {
		return false;
	}

	if ($panels[$panel_id]['requires'] !== false) {
		$plugins = explode(',', $panels[$panel_id]['requires']);
		$good    = true;

		foreach($plugins as $plugin) {
			$plugin = trim($plugin);

			if (!api_plugin_is_enabled($plugin)) {
				$good = false;
			}
		}

		if (!$good) {
			return false;
		}
	}

	return true;
}

function is_panel_allowed($panel_id, $user_id = 0) {
	static $permissions = array();

	if ($user_id == 0) {
		$user_id = $_SESSION['sess_user_id'];
	}

	if (!isset($permissions[$user_id])) {
		$perms = json_decode(
			db_fetch_cell_prepared('SELECT permissions
				FROM plugin_intropage_user_auth
				WHERE user_id = ?',
				array($user_id)),
			true
		);

		$permissions[$user_id] = $perms;
	}

	if (isset($permissions[$user_id][$panel_id])) {
		if ($permissions[$user_id][$panel_id] == 'on') {
			return true;
		}
	}

	return false;
}

function get_allowed_panels($user_id = 0) {
	if ($user_id == 0) {
		$user_id = $_SESSION['sess_user_id'];
	}

	$permissions =	db_fetch_cell_prepared('SELECT permissions
			FROM plugin_intropage_user_auth
			WHERE user_id = ?',
			array($user_id));

	return ($permissions === false) ? false : json_decode($permissions,true);
}

function intropage_reload_panel() {
	global $panels;

	$panel_id = get_filter_request_var('panel_id');

	$forced_update = get_nfilter_request_var('force') == 'true' ? true:false;

	$panel = db_fetch_row_prepared('SELECT *
		FROM plugin_intropage_panel_data
		WHERE id = ?
		AND user_id IN (0, ?)',
		array($panel_id, $_SESSION['sess_user_id']));

	// Close the session to allow other tabs to operate
	session_write_close();

	if (cacti_sizeof($panel)) {
		// Source panel (not favgraph)
		if (isset($panels[$panel['panel_id']])) {
			$spanel = $panels[$panel['panel_id']];
		}

		if ($panel['fav_graph_id'] > 0) {
			$data = intropage_favourite_graph($panel['fav_graph_id'], $panel['fav_graph_timespan']);
		} elseif (cacti_sizeof($spanel)) {
			$function = $spanel['update_func'];
			$user_id  = ($spanel['level'] == PANEL_SYSTEM ? 0 : $_SESSION['sess_user_id']);

			$qpanel = get_panel_details($panel['panel_id'], $user_id);

			if (function_exists($function)) {
				if ($forced_update || time() > ($qpanel['last'] + $qpanel['refresh'])) {
					// Reload the data
					$function($qpanel, $user_id);
				}

				// Return the data for display
				$data = display_panel_results($qpanel['panel_id'], $user_id);
			} else {
				$data = __('The Panel includes a render function but it does not exist.', 'intropage');
			}
		} else {
			$data = __('The Panel does not have a render function.', 'intropage');
		}

		intropage_display_data($panel_id, $data);

		$css  = isset($data['alarm']) && $data['alarm'] !== '' ? $data['alarm'] : 'grey';
		$name = isset($data['name'])  ? html_escape($data['name']):__esc('Not Found', 'intropage');

		?>
		<script type='text/javascript'>
			$('#panel_'+<?php print get_request_var('panel_id');?>).find('.panel_name').html('<?php print $name;?>');
			$('#panel_'+<?php print get_request_var('panel_id');?>).find('.panel_header').removeClass('color_green');
			$('#panel_'+<?php print get_request_var('panel_id');?>).find('.panel_header').removeClass('color_yellow');
			$('#panel_'+<?php print get_request_var('panel_id');?>).find('.panel_header').removeClass('color_red');
			$('#panel_'+<?php print get_request_var('panel_id');?>).find('.panel_header').removeClass('color_grey');
			$('#panel_'+<?php print get_request_var('panel_id');?>).find('.panel_header').addClass('color_<?php print $css;?>');
		<?php

		if (!empty($spanel['details_func'])) {
			print "$('#panel_'+" . get_request_var('panel_id') . ").find('.maxim').show();";
		} else {
			print "$('#panel_'+" . get_request_var('panel_id') . ").find('.maxim').hide();";
		}
		?>
		</script>
		<?php
	} elseif ($panel_id == 998) {	// exception for admin alert panel
		print nl2br(read_config_option('intropage_admin_alert'));
	} elseif ($panel_id == 997) {	// exception for maint panel
		if (function_exists('intropage_maint')) {
			print intropage_maint();
		}
	} else {
		print __('Panel not found');
	}

	exit;
}

function intropage_detail_panel() {
	global $panels;

	$panel_id = get_filter_request_var('panel_id');

	if ($panel_id != 'analyze_db') {
		$forced_update = filter_var(get_nfilter_request_var('force'), FILTER_VALIDATE_BOOLEAN);
	} else {
		$forced_update = false;
	}

	$panel = get_panel_details($panel_id, $_SESSION['sess_user_id']);

	// Close the session to allow other tabs to operate
	session_write_close();

	if (cacti_sizeof($panel)) {
		$spanel         = $panel['definition'];
		$data['alarm']  = $spanel['alarm'];
		$data['name']   = $spanel['name'];
		$data['detail'] = '';

		$function = $spanel['details_func'];

		if (function_exists($function)) {
			$data = $function();
		} else {
			$data['detail'] = __('Details Function does not exist.', 'intropage');
		}

		print '<div class="cactiTableTitle">'  . $data['name']  . '</div>';
		print '<div class="cactiTableButton"><i class="fas fa-circle color_' . $data['alarm'] . '_bubble"></i></div>';
		print $data['detail'];
	} else {
		print __('Panel Not Found');
	}

	exit;
}

function intropage_autoreload() {
	$last_poller = db_fetch_cell("SELECT unix_timestamp(cur_timestamp)
		FROM plugin_intropage_trends
		WHERE name='ar_poller_finish'");

	$last_disp = db_fetch_cell_prepared('SELECT unix_timestamp(cur_timestamp)
		FROM plugin_intropage_trends
		WHERE name = ?',
		array('ar_displayed_' . $_SESSION['sess_user_id']));

	if (!$last_disp) {
		db_execute_prepared('INSERT INTO plugin_intropage_trends (name,value)
			VALUES (?, NOW())',
			array('ar_displayed_' . $_SESSION['sess_user_id']));

		$last_disp = $last_poller;
	}

	if ($last_poller > $last_disp) {
		db_execute_prepared("UPDATE plugin_intropage_trends
			SET cur_timestamp = NOW(), value = NOW()
			WHERE name = ?",
			array('ar_displayed_' . $_SESSION['sess_user_id']));

		print '1';
	} else {
		print '0';
	}

	exit;
}

function get_panel_details($panel_id, $user_id = 0) {
	// Either fetch by row id or panel_id
	if (is_numeric($panel_id)) {
		$panel = db_fetch_row_prepared('SELECT *, UNIX_TIMESTAMP(last_update) AS ts
			FROM plugin_intropage_panel_data
			WHERE id = ?
			AND user_id IN (0, ?)
			LIMIT 1',
			array($panel_id, $user_id));

		$panel_id = $panel['panel_id'];
	} else {
		$panel = db_fetch_row_prepared('SELECT *, UNIX_TIMESTAMP(last_update) AS ts
			FROM plugin_intropage_panel_data
			WHERE panel_id = ?
			AND user_id IN(0, ?)
			LIMIT 1',
			array($panel_id, $user_id));
	}

	$definition = db_fetch_row_prepared('SELECT *
		FROM plugin_intropage_panel_definition
		WHERE panel_id = ?',
		array($panel_id));

	// favourite graph exception
	if (!cacti_sizeof($definition)) {
		$definition = array ();

		$definition['name']	= '';
		$definition['refresh']	= 300;
		$definition['trefresh']	= false;
		$definition['level']	= $_SESSION['sess_user_id'];
		$definition['priority']	= 99;
		$definition['alarm']	= 'grey';
	}

	if (cacti_sizeof($panel)) {
		$last_update      = $panel['ts'];
		$refresh_interval = $panel['refresh_interval'];
		$trend_interval   = $panel['trend_interval'];
		$next_update      = $last_update + $refresh_interval - time();

		$panel['name']    = $definition['name'] . __(' [ Updates in %s ]', intropage_readable_interval($next_update), 'intropage');
	} else {
		$last_update      = time();
		$refresh_interval = $definition['refresh'];
		$trend_interval   = $definition['trefresh'];
		$next_update      = $refresh_interval;

		$panel = array();

		$panel['id']               = 0;
		$panel['panel_id']         = $panel_id;
		$panel['user_id']          = ($definition['level'] == PANEL_SYSTEM ? 0 : $_SESSION['sess_user_id']);
		$panel['last_update']      = $last_update;
		$panel['data']             = '';
		$panel['priority']         = $definition['priority'];
		$panel['alarm']            = $definition['alarm'];
		$panel['refresh_interval'] = $definition['refresh'];
		$panel['trend_interval']   = $definition['trefresh'];

		$panel['id']   = sql_save($panel, 'plugin_intropage_panel_data');
		$panel['name'] = $definition['name'] . __(' [ Updates in %s ]', intropage_readable_interval($next_update), 'intropage');
	}

	return array(
		'id'         => $panel['id'],
		'panel_id'   => $panel_id,
		'name'       => $panel['name'],
		'last'       => $last_update,
		'data'       => '',
		'next'       => $next_update,
		'alarm'      => $panel['alarm'],
		'refresh'    => $refresh_interval,
		'trefresh'   => $trend_interval,
		'panel'      => $panel,
		'definition' => $definition
	);
}

function intropage_readable_interval($value, $round = 0, $short = true) {
	if ($value <= 0) {
		return '-';
	}

	if ($value < 60) {
		$value = round($value, $round);
		return $short ? __('%s Sec', $value, 'intropage') : __('%s Seconds', $value, 'intropage');
	} else {
		$value = $value / 60;
	}

	if ($value < 60) {
		$value = round($value, $round);
		return $short ? __('%s Min', $value, 'intropage') : __('%s Minutes', $value, 'intropage');
	} else {
		$value = $value / 60;
	}

	if ($value < 24) {
		$value = round($value, $round);
		return $short ? __('%s Hrs', $value, 'intropage') : __('%s Hours', $value, 'intropage');
	} else {
		$value = $value / 24;
		$value = round($value, $round);
		return __('%s Days', $value, 'intropage');
	}
}

function get_user_list() {
	global $config;

	if ($config['is_web'] && $_SESSION['sess_user_id'] > 0) {
		// specific user wants his panel only
		$users = array(
			array(
				'id' => $_SESSION['sess_user_id']
			)
		);
	} else { // poller wants all
		$users = db_fetch_assoc("SELECT t1.id AS id
			FROM user_auth AS t1
			JOIN plugin_intropage_user_auth AS t2
			ON t1.id = t2.user_id
			WHERE t1.enabled = 'on'");
	}

	return $users;
}

function save_panel_result($panel, $user_id = 0) {
	db_execute_prepared('UPDATE plugin_intropage_panel_data
		SET data = ?, alarm = ?, user_id = ?, last_update = NOW()
		WHERE id = ?',
		array($panel['data'], $panel['alarm'], $user_id, $panel['id']));
}

function display_panel_results($panel_id, $user_id = 0) {
	$panel = get_panel_details($panel_id, $user_id);

	$data = db_fetch_row_prepared("SELECT id, data, alarm, last_update,
		concat(floor(TIME_FORMAT(SEC_TO_TIME(refresh_interval), '%H') / 24), 'd ',
		MOD(TIME_FORMAT(SEC_TO_TIME(refresh_interval), '%H'), 24), 'h:',
		TIME_FORMAT(SEC_TO_TIME(refresh_interval), '%im')) AS recheck
		FROM plugin_intropage_panel_data
		WHERE panel_id = ?
		AND user_id IN (0, ?)",
		array($panel_id, $user_id));

	if (cacti_sizeof($data) && trim((string) $data['data']) == '') {
		if (!empty($panel['force']) && $panel['force']) {
			$data['data'] = __('No Data Present.  Either Force Update, or wait for next Cacti Polling cycle.', 'intropage');
		} else {
			$data['data'] = __('No Data Present.  This Panel does not allow for Forced Updates.  You will have to wait until the Cacti\'s Poller to perform the check.', 'intropage');
		}
	}

	$data['name'] = $panel['name'];

	return $data;
}

function get_console_access($user_id) {
	return (db_fetch_assoc_prepared('SELECT realm_id
		FROM user_auth_realm
		WHERE user_id = ?
		AND user_auth_realm.realm_id=8',
		array($user_id))) ? true : false;
}

function initialize_panel_library() {
	global $config, $registry;

	static $panel_library = array();

	if (!sizeof($panel_library)) {
		$panels    = array();
		$uninstall = array();

		$files  = glob($config['base_path'] . '/plugins/intropage/panellib/*.php');

		if (cacti_sizeof($files)) {
			foreach($files as $file) {
				$basename = str_replace('.php', '', basename($file));

				if (basename($file) != 'index.php') {
					include_once($file);
				}

				if (function_exists('register_' . $basename)) {
					$base_panels = call_user_func('register_' . $basename);

					// Check to see if the panel should be activated
					foreach($base_panels as $panel_id => $p) {
						if (isset($p['requires']) && $p['requires'] !== false) {
							$plugins = explode(' ', $p['requires']);
							foreach($plugins as $plugin) {
								$status = db_fetch_cell_prepared('SELECT `status`
									FROM plugin_config
									WHERE directory = ?',
									array($plugin));

								if (empty($status)) {
									$uninstall[] = $panel_id;
									unset($base_panels[$panel_id]);
									break;
								}
							}
						}
					}

					$panels += $base_panels;
				}
			}
		}

		// Handle unregistered panels
		if (cacti_sizeof($uninstall) && read_config_option('intropage_unregister') == 'on') {
			foreach($uninstall as $panel_id) {
				$id = db_fetch_cell_prepared('SELECT id
					FROM plugin_intropage_panel_data
					WHERE panel_id = ?',
					array($panel_id));

				if ($id > 0) {
					db_execute_prepared('DELETE FROM plugin_intropage_panel_dashboard
						WHERE panel_id = ?',
						array($id));

					db_execute_prepared('DELETE FROM plugin_intropage_panel_data
						WHERE id = ?',
						array($id));

					db_execute_prepared('DELETE FROM plugin_intropage_definition
						WHERE panel_id = ?',
						array($panel_id));
				}
			}
		}

		$panel_library = $panels;

		$_SESSION['intropage_panel_library'] = $panel_library;
	}

	return $panel_library;
}

function update_registered_panels($panels) {
	$prefix = 'INSERT INTO plugin_intropage_panel_definition
		(panel_id, name, level, class, priority, alarm, requires, update_func, details_func, trends_func, refresh, trefresh, description) VALUES';

	$suffix = 'ON DUPLICATE KEY UPDATE
		name=VALUES(name),
		level=VALUES(level),
		class=VALUES(class),
		priority=VALUES(priority),
		alarm=VALUES(alarm),
		requires=VALUES(requires),
		update_func=VALUES(update_func),
		details_func=VALUES(details_func),
		trends_func=VALUES(trends_func),
		refresh=VALUES(refresh),
		trefresh=VALUES(trefresh),
		description=VALUES(description)';

	$sql = array();

	if (cacti_sizeof($panels)) {
		foreach($panels as $panel_id => $panel) {
			$sql[] = '(' .
				db_qstr($panel_id)              . ', ' .
				db_qstr($panel['name'])         . ', ' .
				db_qstr($panel['level'])        . ', ' .
				db_qstr($panel['class'])        . ', ' .
				db_qstr($panel['priority'])     . ', ' .
				db_qstr($panel['alarm'])        . ', ' .
				db_qstr($panel['requires'])     . ', ' .
				db_qstr($panel['update_func'])  . ', ' .
				db_qstr($panel['details_func']) . ', ' .
				db_qstr($panel['trends_func'])  . ', ' .
				db_qstr($panel['refresh'])      . ', ' .
				db_qstr($panel['trefresh'])     . ', ' .
				db_qstr($panel['description'])  .
			')';
		}

		db_execute($prefix . implode(', ', $sql) . $suffix);
	}
}

function intropage_favourite_graph($fav_graph_id, $fav_graph_timespan) {
	global $config, $graph_timeshifts;

	$lines = read_user_setting('intropage_number_of_lines', read_config_option('intropage_number_of_lines'), false, $_SESSION['sess_user_id']);

	if ($lines == 5) {
		$graph_height = 100;
	} elseif ($lines == 10) {
		$graph_height = 170;
	} else {
		$graph_height = 250;
	}

	if (isset($fav_graph_id)) {
		$result = array(
			'name' => '', // we don't need name here
			'alarm' => 'grey',
			'data' => '',
		);

		include_once($config['base_path'] . '/lib/time.php');

		$result['name'] .= ' ' . db_fetch_cell_prepared('SELECT title_cache
			FROM graph_templates_graph
			WHERE local_graph_id = ?',
			array($fav_graph_id));

		$result['name'] .= ' - ' .  $graph_timeshifts[$fav_graph_timespan];

		$timespan = array();
		$first_weekdayid = read_user_setting('first_weekdayid');

		get_timespan( $timespan, time(),$fav_graph_timespan , $first_weekdayid);

		$result['data'] = '<table class="cactiTable"><tr><td class="center"><img class="intrograph" src="' . $config['url_path'] .
			'graph_image.php' .
			'?local_graph_id=' . $fav_graph_id .
			'&graph_height=' . $graph_height .
			'&graph_width=300' .
			'&disable_cache=true' .
			'&graph_start=' . $timespan['begin_now'] .
			'&graph_end=' . $timespan['end_now'] .
			'&graph_nolegend=true" /></td></tr></table>';

		return $result;
	}
}

function intropage_prepare_graph($dispdata, $user_id) {
	global $config;

        $lines = read_user_setting('intropage_number_of_lines', read_config_option('intropage_number_of_lines'), false, $user_id);

        if ($lines == 5) {
                $graph_height = 150;
        } elseif ($lines == 10) {
                $graph_height = 200;
        } else {
                $graph_height = 270;
        }

	$content = '';

	// line graph
	if (isset($dispdata['line'])) {
		$xid = 'x' . substr(md5($dispdata['line']['title1']), 0, 7);

		// Start chart attributes
		$chart = array(
			'bindto' => "#line_$xid",
			'size' => array(
				'height' => 100,
				'width'  => 150
			),
			'point' => array (
				'r' => 1.5
			),
			'zoom' => array(
				'enabled' => 'true',
				'type'    => 'drag'
			),
			'data' => array(
				'type'   => 'line',
				'x'      => 'x',
				'Format' => '%Y-%m-%d %H:%M:%S'
			)
		);

		$columns   = array();
		$axes      = array();
		$axis      = array();

		// Add the X Axis first
		$columns[] = array_merge(array('x'), $dispdata['line']['label1']);

		// Add upto 5 Lines
		for ($i = 1; $i < 6; $i++) {
			if (isset($dispdata['line']["data$i"]) && cacti_sizeof($dispdata['line']["data$i"])) {
				$columns[] = array_merge(array($dispdata['line']["title$i"]), $dispdata['line']["data$i"]);

				if (isset($dispdata['line']['unit2']['series'])) {
					if (in_array("data$i", $dispdata['line']['unit2']['series'], true)) {
						$axes[$dispdata['line']["title$i"]] = 'y2';
					} else {
						$axes[$dispdata['line']["title$i"]] = 'y';
					}
				} else {
					$axes[$dispdata['line']["title$i"]] = 'y';
				}
			}
		}

		// Setup Axes support
		if (isset($dispdata['line']['unit2']['series'])) {
			$axes = array();

			foreach($dispdata['line']['unit2']['series'] as $series) {
				$number = str_replace('data', '', $series);
				$axes[$dispdata['line']["title$number"]] = 'y2';
			}
		}

		// Setup the Axis
		$axis['x'] = array(
			'type' => 'timeseries',
			'tick' => array(
				'format'  => '%H:%M',
				'culling' => array('max' => 6),
			)
		);

		if (isset($dispdata['line']['unit1'])) {
			$axis['y'] = array(
				'tick' => array(
					'culling' => array('max' => 8)
				),
				'label' => array(
					'text' => $dispdata['line']['unit1']['title'],
				),
				'show' => true
			);
		}

		if (isset($dispdata['line']['unit2'])) {
			$axis['y2'] = array(
				'tick' => array(
					'culling' => array ('max' => 8)
				),
				'label' => array(
					'text' => $dispdata['line']['unit2']['title'],
				),
				'show' => true
			);
		}

		$chart['data']['columns'] = $columns;
		$chart['data']['axes']    = $axes;
		$chart['axis']            = $axis;

		$chart_data = json_encode($chart);
		$content .= '<div style="height: ' . $graph_height . 'px;" class="chart_wrapper center" id="line_' . $xid. '"></div>';
		$content .= '<script type="text/javascript">';
		$content .= 'panels.line_' . $xid . ' = bb.generate(' . $chart_data . ');';
		$content .= '</script>';
	} // line graph end

	if (isset($dispdata['pie'])) {
		$xid = 'x'. substr(md5($dispdata['pie']['title']), 0, 7);

		$content .= "<div class='chart_wrapper center' id=\"pie_$xid\"></div>";
		$content .= '<script type="text/javascript">';
		$content .= 'panels.pie_' . $xid . ' = bb.generate({';
		$content .= " bindto: \"#pie_$xid\",";

		$content .= " size: {";
		$content .= "  height: $graph_height";
		$content .= " },";

		$content .= " data: {";
		$content .= "  columns: [";

		foreach ($dispdata['pie']['data'] as $key => $value) {
			$content .= "['" . $dispdata['pie']['label'][$key] . "', " . $value . "],";
		}

		$content .= "  ],";
		$content .= "  type: 'pie',";
		$content .= "  },";

		$content .= "  pie: {";
		$content .= "    label: {";
		$content .= "      format: function (value, ratio, id) {";
		$content .= "        return (value);";
		$content .= "      }";
		$content .= "    }";
		$content .= "  },";

		$content .= "legend: { position: 'right' },";

		$content .= "});";
		$content .= "</script>";
	}   // pie graph end

	if (isset($dispdata['treemap'])) {
		$xid = 'x'. substr(md5($dispdata['treemap']['title']), 0, 7);

		$content .= "<div class='chart_wrapper center' id=\"treemap_$xid\"></div>";
		$content .= '<script type="text/javascript">';
		$content .= 'panels.treemap_' . $xid . ' = bb.generate({';
		$content .= " bindto: \"#treemap_$xid\",";

		$content .= " size: {";
		$content .= "  height: $graph_height";
		$content .= " },";

		$content .= " data: {";
		$content .= "  columns: [";

		foreach ($dispdata['treemap']['data'] as $key => $value) {
			$content .= "['" . $dispdata['treemap']['label'][$key] . "', " . $value . "],";
		}

		$content .= "  ],";
		$content .= "  type: 'treemap',";
		$content .= "  labels: {";
		$content .= "    colors: '#fff'";
		$content .= "  }";
		$content .= "  },";

		$content .= "  treemap: {";
		$content .= "    label: {";
		$content .= "      threshold: 0.03, show: true,";
		$content .= "    }";
		$content .= "  },";

		$content .= "});";
		$content .= "</script>";
	}   // treemap graph end

	return ($content);
}

function tail_log($log_file, $nbr_lines = 1000, $adaptive = true) {
	if (!(file_exists($log_file) && is_readable($log_file))) {
		return false;
	}

	$f_handle = @fopen($log_file, 'rb');
	if ($f_handle === false) {
		return false;
	}

	if (!$adaptive) {
		$buffer = 4096;
	} else {
		$buffer = ($nbr_lines < 2 ? 64 : ($nbr_lines < 10 ? 512 : 4096));
	}

	fseek($f_handle, -1, SEEK_END);

	if (fread($f_handle, 1) != "\n") {
		$nbr_lines -= 1;
	}

	// Start reading
	$output = '';
	$chunk  = '';
	// While we would like more
	while (ftell($f_handle) > 0 && $nbr_lines >= 0) {
		// Figure out how far back we should jump
		$seek = min(ftell($f_handle), $buffer);
		// Do the jump (backwards, relative to where we are)
		fseek($f_handle, -$seek, SEEK_CUR);
		// Read a chunk and prepend it to our output
		$output = ($chunk = fread($f_handle, $seek)) . $output;
		// Jump back to where we started reading
		fseek($f_handle, -mb_strlen($chunk, '8bit'), SEEK_CUR);
		// Decrease our line counter
		$nbr_lines -= substr_count($chunk, "\n");
	}

	// While we have too many lines (Because of buffer size we might have read too many)
	while ($nbr_lines++ < 0) {
		// Find first newline and remove all text before that
		$output = substr($output, strpos($output, "\n") + 1);
	}

	// Close file
	fclose($f_handle);

	return explode("\n", $output);
}

function human_filesize($bytes, $decimals = 2) {
	$size   = array('B', 'kB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB');
	$factor = floor((strlen($bytes) - 1) / 3);
	return sprintf("%.{$decimals}f", $bytes / pow(1024, $factor)) . @$size[$factor];
}

function intropage_display_panel($panel_id, $dashboard_id) {
	global $config;

	$panels = initialize_panel_library();

	$k_id = db_fetch_cell_prepared('SELECT panel_id
		FROM plugin_intropage_panel_data
		WHERE id = ?',
		array($panel_id));

	if ($k_id == 'favourite_graph') {
		$width = 'quarter-panel';
	} else {
		$width = $panels[$k_id]['width'];
	}

	print '<li id="panel_' . $panel_id . '" class="' . $width . ' flexchild">';
	print '<div class="panel_wrapper">';

	print '<div class="panel_header color_grey">';
	print '<div class="panel_name"></div>';

	printf("<div class='panel_actions'><a href='%s' data-panel='panel_$panel_id' class='header_link droppanel' title='" . __esc('Disable panel', 'intropage') . "'><i class='fa fa-times'></i></a>", "?intropage_action=droppanel&panel_id=$panel_id&dashboard_id=$dashboard_id");

	if (isset($panels[$k_id]['force']) && $panels[$k_id]['force'] === true) {
		printf("<a href='#' id='reloadid_" . $panel_id . "' title='" . __esc('Reload Panel', 'intropage') . "' class='header_link reload_panel_now'><i class='fa fa-retweet'></i></a>");
	}

	printf("<a href='#' title='" . __esc('Show Details', 'intropage') . "' class='header_link maxim' detail-panel='%s'><i class='fa fa-window-maximize'></i></a></div>", $panel_id);

	print ' </div>';
	print "	<table class='cactiTable'>";
	print "	    <tr><td>";

	print "<div class='panel_data'>";
	print __('Loading data ...', 'intropage');
	print '</div>';	// end of panel_data
	print '</td></tr>';
	html_end_box(false);
	print '</li>';
}

function intropage_display_data($panel_id, $data) {
	if (isset($data['data']) && trim((string) $data['data']) != '') {
		print $data['data'];
	} else {
		print '<table class="cactiTable"><tr><td>' . __('No Data Found.  Either wait for next check, <br/>or use the Force Reload if available.', 'intropage') . '</td></tr></table>';
	}
}

function intropage_addpanel_select($dashboard_id) {
	print "<select id='intropage_addpanel'>";
	print '<option value="0">' . __('Panels ...', 'intropage') . '</option>';

	$add_panels = db_fetch_assoc_prepared('SELECT DISTINCT pd.panel_id, pd.name
		FROM plugin_intropage_panel_definition AS pd
		LEFT JOIN plugin_intropage_panel_data AS ppd
		ON pd.panel_id = ppd.panel_id
		WHERE pd.panel_id NOT IN (
			SELECT pda.panel_id
			FROM plugin_intropage_panel_data AS pda
			INNER JOIN plugin_intropage_panel_dashboard AS pd
			ON pda.id = pd.panel_id
			WHERE pd.user_id IN (0, ?)
			AND pd.dashboard_id = ?
		)
		ORDER BY pd.name',
		array($_SESSION['sess_user_id'], $dashboard_id));

	if (cacti_sizeof($add_panels)) {
		foreach ($add_panels as $panel) {
			$uniqid = db_fetch_cell_prepared('SELECT id
				FROM plugin_intropage_panel_data
				WHERE user_id IN (0, ?)
				AND panel_id = ?',
				array($_SESSION['sess_user_id'],$panel['panel_id']));

			if ($panel['panel_id'] != 'maint' && $panel['panel_id'] != 'admin_alert') {
				$allowed = is_panel_allowed($panel['panel_id']);

				$enabled = is_panel_enabled($panel['panel_id']);

				if ($uniqid > 0) {
					if ($allowed) {
						if ($enabled) {
							print "<option value='" . $uniqid . "'>" . html_escape($panel['name']) . '</option>';
						}
					} else {
						print "<option value='addpanel_" .  $uniqid . "' disabled='disabled'>" . __('%s (no permission)', $panel['name'], 'intropage') . '</option>';
					}
				} else {
					if ($allowed) {
						if ($enabled) {
							print "<option value='" . $panel['panel_id'] . "'>" . html_escape($panel['name']) . '</option>';
						}
					} else {
						print "<option value='addpanel_" .  $uniqid . "' disabled='disabled'>" . __('%s (no permission)', $panel['name'], 'intropage') . '</option>';
					}
				}
			}
		}
	}

	print '</select>';
	print '&nbsp; &nbsp;';
}

function ntp_time($host) {
	$timestamp = -1;
	$sock      = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);

	$timeout = array('sec' => 1, 'usec' => 400000);
	socket_set_option($sock, SOL_SOCKET, SO_RCVTIMEO, $timeout);
	socket_clear_error();

	socket_connect($sock, $host, 123);
	if (socket_last_error() == 0) {
		// Send request
		$msg = "\010" . str_repeat("\0", 47);
		socket_send($sock, $msg, strlen($msg), 0);
		// Receive response and close socket

		if (@socket_recv($sock, $recv, 48, MSG_WAITALL)) {
			socket_close($sock);
			// Interpret response
			$data      = unpack('N12', $recv);
			$timestamp = sprintf('%u', $data[9]);
			// NTP is number of seconds since 0000 UT on 1 January 1900
			// Unix time is seconds since 0000 UT on 1 January 1970
			$timestamp -= 2208988800;
		} else {
		    $timestamp = 'error';
		}
	} else {
	    $timestamp = 'error';
	}

	return ($timestamp);
}

function intropage_graph_button($data) {
	global $config, $login_opts;

	if (is_panel_allowed('favourite_graph')) {
		$local_graph_id = $data[1]['local_graph_id'];

		if (!isset($_SESSION['sess_current_timespan'])) {
			$_SESSION['sess_current_timespan'] = read_user_setting('default_timespan');
		}

		if ($_SESSION['sess_current_timespan'] == 0)	{	// zoom or custom timespan
			$fav = '<i class="fa fa-eye-slash" title="' . __esc('Cannot add to Dashboard. Custom timespan.', 'intropage') . '"></i>';
		} else {
			$present = db_fetch_cell_prepared('SELECT COUNT(*)
				FROM plugin_intropage_panel_data
				WHERE user_id = ?
				AND fav_graph_id = ?
				AND fav_graph_timespan = ?',
				array($_SESSION['sess_user_id'], $local_graph_id, $_SESSION['sess_current_timespan']));

			if ($present) {
				$fav = '<i class="fa fa-eye-slash" title="' . __esc('Remove from Dashboard', 'intropage') . '"></i>';
			} else {
				$fav = '<i class="fa fa-eye" title="' . __esc('Add to Dashboard', 'intropage') . '"></i>';
			}
		}

		if ($login_opts == 4) {
			print '<a class="iconLink" href="' . html_escape($config['url_path']) . 'plugins/intropage/intropage.php?intropage_action=favgraph&graph_id=' . $local_graph_id . '">' . $fav . '</a><br/>';
		} else {
			print '<a class="iconLink" href="' . html_escape($config['url_path']) . 'index.php?intropage_action=favgraph&graph_id=' . $local_graph_id . '">' . $fav . '</a><br/>';
		}
	}
}

function get_login_opts($refresh = false) {
	if (isset_request_var('intropage_action') &&
		get_nfilter_request_var('intropage_action') == 'loginopt_console') {
		$_SESSION['intropage_login_opts'] = 1;
	} elseif (isset_request_var('intropage_action') &&
		get_nfilter_request_var('intropage_action') == 'loginopt_tab') {
		$_SESSION['intropage_login_opts'] = 4;
	} elseif (isset_request_var('intropage_action') &&
		get_nfilter_request_var('intropage_action') == 'loginopt_graph') {
		$_SESSION['intropage_login_opts'] = 2;
	} elseif (empty($_SESSION['intropage_login_opts']) || $refresh) {
		$login_opts = db_fetch_cell_prepared('SELECT login_opts
			FROM user_auth
			WHERE id = ?',
			array($_SESSION['sess_user_id']));

		$_SESSION['intropage_login_opts'] = $login_opts;
	}

	return $_SESSION['intropage_login_opts'];
}

function intropage_configure_panel() {
	global $config, $login_opts, $trend_timespans, $intropage_intervals;

	$dashboards = array_rekey(
		db_fetch_assoc_prepared('SELECT dashboard_id, name
			FROM plugin_intropage_dashboard
			WHERE user_id = ?
			ORDER BY dashboard_id',
			array($_SESSION['sess_user_id'])),
		'dashboard_id', 'name'
	);

	print '<div>';

	if ($login_opts == 4) {
		$pageName = $config['url_path'] . 'plugins/intropage/intropage.php';
	} else {
		$pageName = 'index.php';
	}

	form_start($pageName);

	html_start_box(__('Dashboard Names', 'intropage'), '100%', '', '3', 'center', '');

	$class = 'odd';

	foreach($dashboards as $f => $name) {
		$class = ($class == 'odd' ? 'even':'odd');

		print '<div id="row_name_' . $f . '" class="formRow ' . $class . '">
			<div class="formColumnLeft">
				<div class="formFieldName">' . __('Dashboard %s', $f, 'intropage') . '</div>
			</div>
			<div class="formColumnRight">
				<div class="formData">';

		print '<input type="text" name="name_' . $f . '" value="' . html_escape($name)  . '"></br>';

		print '</div></div>';
	}

	html_end_box();

	$panels = db_fetch_assoc_prepared('SELECT pda.panel_id, pda.id,
		pd.name, pd.level, pd.description,
		refresh_interval, refresh AS default_refresh, pda.user_id AS user_id
		FROM plugin_intropage_panel_data AS pda
		INNER JOIN plugin_intropage_panel_definition AS pd
		ON pd.panel_id = pda.panel_id
		WHERE pda.user_id = ?
		AND pda.fav_graph_id IS NULL
		ORDER BY level, name',
		array($_SESSION['sess_user_id']));

	if (cacti_sizeof($panels))	{
		html_start_box(__('User Level Panel Update Frequencies', 'intropage'), '100%', '', '3', 'center', '');

		$class = 'odd';

		foreach ($panels as $panel)	{
			$class = ($class == 'odd' ? 'even':'odd');

			// Don't show admin pages to normal users
			if ($panel['level'] == 0 && !api_user_realm_auth('intropage_admin.php')) {
				continue;
			}

			print '<div id="row_crefresh_' . $panel['id'] . '" class="formRow ' . $class . '">
				<div class="formColumnLeft">
					<div class="formFieldName">' . $panel['name'] . '</div>
					<div class="cactiTooltipHint fa fa-question-circle">
						<span style="display:none;">' . $panel['description'] . '<span>
					</div>
				</div>
				<div class="formColumnRight">
					<div class="formData">';

			form_dropdown(
				'crefresh_' . $panel['id'],
				$intropage_intervals, '', '',
				$panel['refresh_interval'],
				__('Default', 'intropage'),
				$panel['default_refresh']
			);

			print '</div></div>';
		}

		html_end_box();
	}

	if (api_plugin_user_realm_auth('intropage_admin.php')) {
		$panels = db_fetch_assoc_prepared('SELECT DISTINCTROW pda.panel_id, pda.id,
			pd.name, pd.level, pd.description,
			refresh_interval, refresh AS default_refresh, pda.user_id AS user_id
			FROM plugin_intropage_panel_data AS pda
			INNER JOIN plugin_intropage_panel_definition AS pd
			ON pd.panel_id = pda.panel_id
			WHERE pda.user_id = 0
			AND pd.level = 0
			AND pda.fav_graph_id IS NULL
			ORDER BY level, name',
			array($_SESSION['sess_user_id']));

		if (cacti_sizeof($panels))	{
			html_start_box(__('System Panel Update Frequencies (All Authorized Users)', 'intropage'), '100%', '', '3', 'center', '');

			$class = 'odd';

			foreach ($panels as $panel)	{
				$class = ($class == 'odd' ? 'even':'odd');

				// Don't show admin pages to normal users
				if ($panel['level'] == 0 && !api_user_realm_auth('intropage_admin.php')) {
					continue;
				}

				print '<div id="row_crefresh_' . $panel['id'] . '" class="formRow ' . $class . '">
					<div class="formColumnLeft">
						<div class="formFieldName">' . $panel['name'] . '</div>
						<div class="cactiTooltipHint fa fa-question-circle">
							<span style="display:none;">' . $panel['description'] . '<span>
						</div>
					</div>
					<div class="formColumnRight">
						<div class="formData">';

				form_dropdown(
					'crefresh_' . $panel['id'],
					$intropage_intervals, '', '',
					$panel['refresh_interval'],
					__('Default', 'intropage'),
					$panel['default_refresh']
				);

				print '</div></div>';
			}

			html_end_box();
		}

		$panels = db_fetch_assoc_prepared('SELECT DISTINCTROW pda.panel_id, pda.id,
			pd.name, pd.level, pd.description,
			trend_interval, refresh AS default_refresh, pda.user_id AS user_id
			FROM plugin_intropage_panel_data AS pda
			INNER JOIN plugin_intropage_panel_definition AS pd
			ON pd.panel_id = pda.panel_id
			WHERE pda.user_id = 0
			AND pda.fav_graph_id IS NULL
			AND pd.trends_func != ""
			ORDER BY level, name',
			array($_SESSION['sess_user_id']));

		if (cacti_sizeof($panels))	{
			html_start_box(__('Trend Update Frequencies', 'intropage'), '100%', '', '3', 'center', '');

			$class = 'odd';

			foreach ($panels as $panel)	{
				$class = ($class == 'odd' ? 'even':'odd');

				print '<div id="row_crefresh_' . $panel['id'] . '" class="formRow ' . $class . '">
					<div class="formColumnLeft">
						<div class="formFieldName">' . $panel['name'] . '</div>
						<div class="cactiTooltipHint fa fa-question-circle">
							<span style="display:none;">' . $panel['description'] . '<span>
						</div>
					</div>
					<div class="formColumnRight">
						<div class="formData">';

				form_dropdown(
					'trefresh_' . $panel['id'],
					$intropage_intervals, '', '',
					$panel['trend_interval'],
					__('Default', 'intropage'),
					$panel['default_refresh']
				);

				print '</div></div>';
			}

			html_end_box();
		}
	}

	form_hidden_box('save_settings', 0, 1);

	form_save_button($pageName, 'save');

	form_end();

	print '</div>';
}

function human_readable ($bytes, $decimal = true, $precision = 2) {

	if ($decimal) {
		$factor = 1000;
	} else {
		$factor = 1024;
	}

	if ($bytes == 0) {
		return 0;
	} elseif ($bytes < 1) {
		$sizes = array(0 => '', -1 => 'm', -2 => 'µ',- 3 => 'n', -4 => 'p');
	} else {
		$sizes = array(0 => '', 1 => 'K', 2 => 'M', 3 => 'G', 4 => 'T', 5=> 'P');
	}

	$i = (int) floor(log(abs($bytes)) / log($factor));
	$d = pow($factor, $i);

	if (!array_key_exists($i, $sizes)) {
		if (function_exists('cacti_log')) {
			cacti_log('INTROPAGE WARNING: Bytes = [' . $bytes  .'], Factor = [' . $factor . '], i = [' . $i . '] d = [' . $d . ']');
			cacti_debug_backtrace('intropage-hr');
		} else {
			print 'INTROPAGE WARNING: Bytes = [' . $bytes  .'], Factor = [' . $factor . '], i = [' . $i . '] d = [' . $d . ']';
		}
		$size = '<unknown>';
		$i = 1;
	} else {
		$size = $sizes[$i];
	}

	return round(empty($d)?0:($bytes / pow($factor, $i)), $precision).' '.$size;
}

function intropage_display_graph () {
	// tady jeste bude timespan, asi
	$x = intropage_favourite_graph (2064, 1);
	print $x['data'];
}
