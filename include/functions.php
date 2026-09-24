<?php
/* vim: ts=4
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group, Inc.                           |
 | Copyright (C) 2004-2025 Petr Macek                                      |
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

/**
 * Returns a comma-separated list of device ids the given user is
 * permitted to see, temporarily disabling their 'hide_disabled' user
 * setting so disabled devices aren't excluded from the scope
 * calculation. Called from intropage_device_scope() to resolve a
 * non-simple-permission user's allowed device set.
 *
 * @param int|string $user_id The Cacti user id to resolve allowed
 *                            devices for.
 *
 * @return string|false Comma-separated allowed device ids, or false if
 *                      the user has no visible devices.
 */
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

/**
 * Resolve a user's device-visibility scope for panel queries.
 *
 * Panels restrict their host queries to the devices a user may see. A user with
 * simple device permissions can see every device, so no host filter is needed;
 * otherwise the panel constrains its query to the allowed host ids. This wraps
 * the two-call permission preamble the panels would otherwise repeat. The
 * caller formats its own IN() clause from 'allowed', because the column and any
 * "AND" prefix differ between panels.
 *
 * @param int|string $user_id Cacti user id whose permissions to resolve.
 *
 * @return array{simple: bool, allowed: string|false}
 *                                                    simple  - true when the user sees every device (no filter needed).
 *                                                    allowed - comma-separated allowed host ids, or false when the user is
 *                                                    simple or has no visible devices.
 */
function intropage_device_scope($user_id): array {
	if (get_simple_device_perms($user_id)) {
		return ['simple' => true, 'allowed' => false];
	}

	return ['simple' => false, 'allowed' => intropage_get_allowed_devices($user_id)];
}

/**
 * Top-level request dispatcher for this page: sets the default action,
 * then routes to the appropriate handler based on which request
 * variables are present (adding a panel, saving dashboard/settings,
 * running a panel action, changing a panel's timespan, or one of the
 * AJAX actions: configure/autoreload/reload/details), exiting after AJAX
 * actions. Called from display_information() before rendering the
 * dashboard.
 *
 * @return void
 */
function process_page_request_variables() {
	set_default_action();

	if (isset_request_var('intropage_addpanel')) {
		intropage_action_add_panel();
	} elseif (isset_request_var('action') && isset_request_var('save_settings')) {
		intropage_action_settings();
	} elseif (isset_request_var('intropage_action')) {
		intropage_actions();
	} elseif (isset_request_var('intropage_action_timespan')) {
		intropage_actions_timespan();
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
		}

		exit;
	}
}

/**
 * Handles adding a panel to the current dashboard: creates a new
 * plugin_intropage_panel_data row for the panel if one doesn't already
 * exist (by panel_id), then associates it with the target dashboard.
 * Called from process_page_request_variables() when the
 * 'intropage_addpanel' request variable is present.
 *
 * @return void
 */
function intropage_action_add_panel() {
	$dashboard_id = get_filter_request_var('dashboard_id');

	if (is_numeric(get_nfilter_request_var('intropage_addpanel'))) {
		$addpanel = get_filter_request_var('intropage_addpanel');
	} else {
		$panel_id = get_nfilter_request_var('intropage_addpanel');

		$panel = db_fetch_row_prepared('SELECT *
			FROM plugin_intropage_panel_definition
			WHERE panel_id = ?',
			[$panel_id]);

		$save = [];

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
			[$addpanel, $_SESSION['sess_user_id'], $dashboard_id]);

		// Refresh the data if allowed
	}
}

/**
 * Validates and normalizes a raw dashboard id value (must be a
 * non-negative integer string with no leading zeros) into an integer.
 * Called from intropage_action_settings() to safely parse dashboard
 * ids embedded in dynamic POST field names.
 *
 * @param mixed $value The raw value to validate/parse.
 *
 * @return int|null The parsed dashboard id, or null if $value is not a
 *                  valid non-negative integer string.
 */
function intropage_parse_dashboard_id($value) {
	if (!is_string($value) || preg_match('/\A(?:0|[1-9][0-9]*)\z/D', $value) !== 1) {
		return null;
	}

	$id = filter_var($value, FILTER_VALIDATE_INT, [
		'options' => [
			'min_range' => 0,
			'max_range' => 2147483647,
		],
	]);

	return $id === false ? null : $id;
}

/**
 * Handles saving the dashboard settings form: persists each submitted
 * dashboard's custom name (from dynamically named 'name_{id}' POST
 * fields). Called from process_page_request_variables() when the
 * dashboard settings form is submitted.
 *
 * @return void
 */
function intropage_action_settings() {
	foreach ($_POST as $var => $value) {
		if (strpos($var, 'name_') !== false) {
			$dashboard_id = intropage_parse_dashboard_id(str_replace('name_', '', $var));

			if ($dashboard_id === null) {
				continue;
			}

			db_execute_prepared('REPLACE INTO plugin_intropage_dashboard
				(user_id, dashboard_id, name)
				VALUES (?, ?, ?)',
				[$_SESSION['sess_user_id'], $dashboard_id, $value]);
		}
	}

	// panel refresh
	if (api_user_realm_auth('intropage_admin.php')) {
		$panels = db_fetch_assoc_prepared('SELECT pda.panel_id AS panel_name, pda.id AS id
			FROM plugin_intropage_panel_data AS pda
			WHERE pda.user_id IN (0, ?)
			AND pda.fav_graph_id IS NULL',
			[$_SESSION['sess_user_id']]);
	} else {
		$panels = db_fetch_assoc_prepared('SELECT pda.panel_id AS panel_name, pda.id AS id
			FROM plugin_intropage_panel_data AS pda
			WHERE pda.user_id = ?
			AND pda.fav_graph_id IS NULL',
			[$_SESSION['sess_user_id']]);
	}

	if (cacti_sizeof($panels)) {
		foreach ($panels as $panel) {
			$interval = get_filter_request_var('crefresh_' . $panel['id'], FILTER_VALIDATE_INT);

			if ($interval >= 0 && $interval <= 999999999) {
				db_execute_prepared('UPDATE plugin_intropage_panel_data
					SET refresh_interval = ?
					WHERE id = ?',
					[$interval, $panel['id']]);
			}

			$interval = get_filter_request_var('trefresh_' . $panel['id'], FILTER_VALIDATE_INT);

			if ($interval >= 0 && $interval <= 999999999) {
				db_execute_prepared('UPDATE plugin_intropage_panel_data
					SET trend_interval = ?
					WHERE id = ?',
					[$interval, $panel['id']]);
			}
		}
	}

	raise_message(1);
}

/**
 * Handles all per-panel/dashboard user actions encoded in the
 * 'intropage_action' request variable (e.g. 'heightless'/'heightmore'
 * to resize a panel, refresh interval changes, enabling/disabling
 * panels, moving panels between dashboards, removing panels, resetting
 * layout), parsing the action name and its numeric argument(s) from the
 * value's underscore-separated segments. Called from
 * process_page_request_variables() when the 'intropage_action' request
 * variable is present.
 *
 * @return void
 *
 * @global mixed $callbackPage Reserved/declared for use by included
 *                             panel-rendering code; not set directly
 *                             here.
 * @global mixed $redirectPage Reserved/declared for use by included
 *                             panel-rendering code; not set directly
 *                             here.
 * @global array $config       Cacti global configuration array; used to
 *                             build redirect URLs.
 */
function intropage_actions() {
	global $callbackPage, $redirectPage, $config;

	$actionvar = get_filter_request_var('intropage_action', FILTER_VALIDATE_REGEXP, ['options' => ['regexp' => '/^([a-z0-9_-]+)$/']]);

	$values = explode('_', $actionvar);

	// few parameters from input type select has format reset_all, refresh_180, ... first is action
	$action = $values[0];

	if (isset($values[1])) {
		$value = trim($values[1]);

		if (is_numeric($value)) {
			// 0 causes issue
			$value = (int)$value;
		}
	} else {
		$value = '';
	}

	if (isset($values[2])) {
		$value_ext = trim($values[2]);
	}

	switch ($action) {
		case 'heightless':
			if (get_filter_request_var('panel_id')) {
				$actual_height = db_fetch_cell_prepared('SELECT height
				FROM plugin_intropage_panel_data
				WHERE user_id = ?
				AND id = ?',
					[$_SESSION['sess_user_id'], get_request_var('panel_id')]);

				if ($actual_height == 'double') {
					db_execute_prepared('UPDATE plugin_intropage_panel_data
				SET height = "normal"
				WHERE user_id = ?
				AND id = ?',
						[$_SESSION['sess_user_id'], get_request_var('panel_id')]);
				} elseif ($actual_height == 'triple') {
					db_execute_prepared('UPDATE plugin_intropage_panel_data
				SET height = "double"
				WHERE user_id = ?
				AND id = ?',
						[$_SESSION['sess_user_id'], get_request_var('panel_id')]);
				}
			}

			break;
		case 'heightmore':
			if (get_filter_request_var('panel_id')) {
				$actual_height = db_fetch_cell_prepared('SELECT height
				FROM plugin_intropage_panel_data
				WHERE user_id = ?
				AND id = ?',
					[$_SESSION['sess_user_id'], get_request_var('panel_id')]);

				if ($actual_height == 'double') {
					db_execute_prepared('UPDATE plugin_intropage_panel_data
				SET height = "triple"
				WHERE user_id = ?
				AND id = ?',
						[$_SESSION['sess_user_id'], get_request_var('panel_id')]);
				} elseif ($actual_height == 'normal') {
					db_execute_prepared('UPDATE plugin_intropage_panel_data
				SET height = "double"
				WHERE user_id = ?
				AND id = ?',
						[$_SESSION['sess_user_id'], get_request_var('panel_id')]);
				}
			}

			break;
		case 'addpanelselect':
			intropage_addpanel_select(get_filter_request_var('dashboard_id'));
			exit;

			break;
		case 'droppanel':
			if (get_filter_request_var('panel_id')) {
				db_execute_prepared('DELETE FROM plugin_intropage_panel_dashboard
				WHERE user_id = ?
				AND panel_id = ?',
					[$_SESSION['sess_user_id'], get_request_var('panel_id')]);

				// Delete user data, but not system user data
				db_execute_prepared('DELETE FROM plugin_intropage_panel_data
				WHERE user_id = ?
				AND id = ?',
					[$_SESSION['sess_user_id'], get_request_var('panel_id')]);
			}

			break;
		case 'removepage':
			if (filter_var($value, FILTER_VALIDATE_INT)) {
				db_execute_prepared('DELETE FROM plugin_intropage_dashboard
				WHERE user_id = ?
				AND dashboard_id = ?',
					[$_SESSION['sess_user_id'], $value]);

				db_execute_prepared('DELETE FROM plugin_intropage_panel_dashboard
				WHERE user_id = ?
				AND dashboard_id = ?',
					[$_SESSION['sess_user_id'], $value]);

				$_SESSION['dashboard_id'] = db_fetch_cell_prepared('SELECT MIN(dashboard_id)
				FROM plugin_intropage_dashboard
				WHERE user_id = ?',
					[$_SESSION['sess_user_id']]);

				raise_message('dashboard_removed', __('Dashboard has been removed', 'intropage'), MESSAGE_LEVEL_INFO);

				header('Location: ' . html_escape("$redirectPage?header=false"));

				exit;
			}

			break;
		case 'addpage':
			if (filter_var($value, FILTER_VALIDATE_INT)) {
				$dashboard_id = db_fetch_cell_prepared('SELECT MAX(dashboard_id)+1
				FROM plugin_intropage_dashboard
				WHERE user_id = ?',
					[$_SESSION['sess_user_id']]);

				$_SESSION['dashboard_id'] = $dashboard_id;

				db_execute_prepared('INSERT INTO plugin_intropage_dashboard
				(user_id, dashboard_id, name)
				VALUES (?, ?, ?)',
					[$_SESSION['sess_user_id'], $dashboard_id, __('New Dashboard', 'intropage')]);

				raise_message('dashboard_added', __('Dashboard has been added', 'intropage'), MESSAGE_LEVEL_INFO);

				header('Location: ' . html_escape("$redirectPage?header=false"));

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
					[$_SESSION['sess_user_id'], get_request_var('graph_id'), $_SESSION['sess_current_timespan']]);

				if ($exists) {
					db_execute_prepared('DELETE FROM plugin_intropage_panel_data
					WHERE user_id = ?
					AND fav_graph_id = ?
					AND fav_graph_timespan = ?',
						[$_SESSION['sess_user_id'], get_request_var('graph_id'), $_SESSION['sess_current_timespan']]);
				}

				if ($_SESSION['sess_current_timespan'] == 0) {
					raise_message('custom_error',__('Cannot add zoomed or custom timespaned graph, changing timespan to Last half hour', 'intropage'));
					$span = 1;
				} else {
					$span = $_SESSION['sess_current_timespan'];
				}

				$prio = db_fetch_cell_prepared('SELECT MAX(priority) + 1
				FROM plugin_intropage_panel_data
				WHERE user_id = ?',
					[$_SESSION['sess_user_id']]);

				db_execute_prepared('INSERT INTO plugin_intropage_panel_data
				(user_id, panel_id, height, fav_graph_id, fav_graph_timespan, priority)
				VALUES (?, "favourite_graph", "normal", ?, ?, ?)',
					[$_SESSION['sess_user_id'], get_request_var('graph_id'), $span, $prio]);

				$id = db_fetch_insert_id();

				db_execute_prepared('INSERT INTO plugin_intropage_panel_dashboard
				(panel_id, user_id, dashboard_id) VALUES ( ?, ?, ?)',
					[$id, $_SESSION['sess_user_id'], $_SESSION['dashboard_id']]);
			}

			break;
		case 'order':
			if (isset_request_var('xdata')) {
				$error    = false;
				$order    = [];
				$priority = 90; // >90 are fav. graphs

				foreach (get_nfilter_request_var('xdata') as $data) {
					[$a, $b] = explode('_', $data);

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
							[$priority, $_SESSION['sess_user_id'], $b]);

						db_execute_prepared('UPDATE plugin_intropage_panel_dashboard
						SET priority = ?
						WHERE user_id = ?
						AND panel_id = ?
						AND dashboard_id = ?',
							[$priority, $_SESSION['sess_user_id'], $b, get_filter_request_var('dashboard_id')]);

						$priority--;
					}
				}
			}

			exit;

			break;
		case 'refresh':
			if (is_int($value)) {
				set_user_setting('intropage_autorefresh', $value);
			}

			break;
		case 'lines':
			if (is_int($value)) {
				set_user_setting('intropage_number_of_lines', $value);
			}

			break;
		case 'period':
			if (filter_var($value, FILTER_VALIDATE_INT)) {
				set_user_setting('intropage_important_period', $value);
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
				[$_SESSION['sess_user_id']]);

			foreach ($panels as $panel) {
				$qpanel = get_panel($panel['panel_id'], $_SESSION['sess_user_id']);

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
				db_fetch_cell_prepared('UPDATE user_auth SET login_opts = 3 WHERE id = ?', [$_SESSION['sess_user_id']]);
			} elseif ($value == 'console') {
				db_fetch_cell_prepared('UPDATE user_auth SET login_opts = 2 WHERE id = ?', [$_SESSION['sess_user_id']]);
			} elseif ($value == 'tab') {
				db_fetch_cell_prepared('UPDATE user_auth SET login_opts = 4 WHERE id = ?', [$_SESSION['sess_user_id']]);
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
			db_execute_prepared('UPDATE plugin_intropage_dashboard
			SET shared = 1
			WHERE user_id = ? AND dashboard_id = ?',
				[$_SESSION['sess_user_id'], $_SESSION['dashboard_id']]);

			break;
		case 'unshare':
			db_execute_prepared('UPDATE plugin_intropage_dashboard
			SET shared = 0
			WHERE user_id = ? AND dashboard_id = ?',
				[$_SESSION['sess_user_id'], $_SESSION['dashboard_id']]);

			break;
		case 'useshared':
			if (filter_var($value, FILTER_VALIDATE_INT) && filter_var($value_ext, FILTER_VALIDATE_INT)) {
				$shared = db_fetch_cell_prepared('SELECT shared FROM plugin_intropage_dashboard
				WHERE dashboard_id = ? AND user_id = ?',
					[$value, $value_ext]);

				if ($shared) {
					$username = get_username($value_ext);

					$dashboard = db_fetch_row_prepared('SELECT name, user_id
					FROM plugin_intropage_dashboard
					WHERE dashboard_id = ? AND user_id = ?',
						[$value, $value_ext]);

					$new_dashboard_id = db_fetch_cell_prepared('SELECT MAX(dashboard_id)+1
					FROM plugin_intropage_dashboard
					WHERE user_id = ?',
						[$_SESSION['sess_user_id']]);

					db_execute_prepared('INSERT INTO plugin_intropage_dashboard
					(user_id, dashboard_id, name)
					VALUES (?, ?, ?)',
						[$_SESSION['sess_user_id'], $new_dashboard_id, $username . '-' . $dashboard['name']]);

					$ids_panels = db_fetch_assoc_prepared('SELECT panel_id
					FROM plugin_intropage_panel_dashboard
					WHERE dashboard_id = ? AND user_id = ?',
						[$value, $value_ext]);

					foreach ($ids_panels as $id_panel) {
						$result = db_execute_prepared('INSERT INTO plugin_intropage_panel_data
						(panel_id,user_id,data,priority,refresh_interval,trend_interval,fav_graph_id,fav_graph_timespan)
						SELECT panel_id, ? ,data,priority,refresh_interval,trend_interval,fav_graph_id,fav_graph_timespan
						FROM plugin_intropage_panel_data
						WHERE id = ?',
							[$_SESSION['sess_user_id'], $id_panel['panel_id']]);

						if ($result) {
							$last = db_fetch_insert_id();

							db_execute_prepared('INSERT INTO plugin_intropage_panel_dashboard
							(panel_id, user_id, dashboard_id)
							VALUES (?, ?, ?)',
								[$last, $_SESSION['sess_user_id'], $new_dashboard_id]);
						}
					}

					raise_message('dashboard_added', __('Dashboard has been added, please wait few poller cycle for data', 'intropage'), MESSAGE_LEVEL_INFO);

					header('Location: ' . html_escape("$redirectPage?header=false&dashboard_id=$new_dashboard_id"));

					exit;
				} else {
					raise_message('share_panel_error', __('Error - trying share non-shared dashboard', 'intropage'), MESSAGE_LEVEL_INFO);
				}
			}

			break;
	}
}

/**
 * Handles timespan-change actions encoded in the
 * 'intropage_action_timespan' request variable: stores the user's new
 * timespan preference and immediately re-runs the data-update function
 * for every trend-capable panel currently on the user's dashboards
 * using the new timespan. Called from
 * process_page_request_variables() when the
 * 'intropage_action_timespan' request variable is present.
 *
 * @return void
 *
 * @global array $config Reserved/declared for parity with other
 *                       functions in this file; not used directly
 *                       here.
 */
function intropage_actions_timespan() {
	global $config;

	$actionvar = get_filter_request_var('intropage_action_timespan', FILTER_VALIDATE_REGEXP, ['options' => ['regexp' => '/^([a-z0-9_-]+)$/']]);

	$values = explode('_', $actionvar);

	// few parameters from input type select has format reset_all, refresh_180, ... first is action
	$action = $values[0];

	if (isset($values[1])) {
		$value = trim($values[1]);
	} else {
		$value = '';
	}

	switch ($action) {
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
				[$_SESSION['sess_user_id']]);

			foreach ($panels as $panel) {
				$qpanel = get_panel($panel['panel_id'], $_SESSION['sess_user_id']);

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
	}
}

/**
 * Determines whether a panel is currently usable: it must exist in the
 * (already availability-pruned) panel library, and if it declares
 * required plugin dependencies, all of those plugins must be enabled.
 * Called throughout this plugin (e.g. panel update functions,
 * intropage_actions_timespan()) before running a panel's logic.
 *
 * @param string $panel_id The panel id to check.
 *
 * @return bool True if the panel exists and its required plugins (if
 *             any) are all enabled, false otherwise.
 */
function is_panel_enabled($panel_id) {
	$panels = initialize_panel_library();

	// Panel library prunes unavailable panels
	if (!isset($panels[$panel_id])) {
		return false;
	}

	if ($panels[$panel_id]['requires'] !== false) {
		$plugins = explode(',', $panels[$panel_id]['requires']);
		$good    = true;

		foreach ($plugins as $plugin) {
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

/**
 * Determines whether a user is permitted to view a given panel, based
 * on the union of their own JSON-encoded panel permissions and those of
 * any user groups they belong to, caching the resolved permission set
 * per user for the duration of the request. Called from
 * get_allowed_panels() and other panel-visibility checks.
 *
 * @param string $panel_id The panel id to check permission for.
 * @param int    $user_id  The user id to check; 0 uses the current
 *                        session user.
 *
 * @return bool True if the user (directly or via a group) is permitted
 *             to view the panel, false otherwise.
 */
function is_panel_allowed($panel_id, $user_id = 0) {
	static $permissions = [];

	if ($user_id == 0) {
		$user_id = $_SESSION['sess_user_id'];
	}

	if (!isset($permissions[$user_id])) {
		// group permission

		// See if they have access to any group realms
		$user_groups = db_fetch_assoc_prepared('SELECT *
			FROM user_auth_group_members
			WHERE user_id = ?',
			[$user_id]);

		if (cacti_sizeof($user_groups)) {
			foreach ($user_groups as $g) {
				$g_perm = db_fetch_cell_prepared('SELECT permissions
				FROM plugin_intropage_user_group_auth
				WHERE user_group_id = ?',
					[$g['group_id']]);

				if ($g_perm) {
					$g_perm = json_decode($g_perm, true);

					foreach ($g_perm as $pid => $data) {
						if ($data == 'on') {
							$permissions[$user_id][$pid] = 'on';
						}
					}
				}
			}
		}

		$u_perm = db_fetch_cell_prepared('SELECT permissions
			FROM plugin_intropage_user_auth
			WHERE user_id = ?',
			[$user_id]);

		if ($u_perm) {
			$u_perm = json_decode($u_perm, true);

			foreach ($u_perm as $pid => $data) {
				if ($data == 'on') {
					$permissions[$user_id][$pid] = 'on';
				}
			}
		}
	}

	if (isset($permissions[$user_id][$panel_id])) {
		if ($permissions[$user_id][$panel_id] == 'on') {
			return true;
		}
	}

	return false;
}

/**
 * Returns the full set of panel ids a user is permitted to view, based
 * on the union of their own JSON-encoded panel permissions and those of
 * any user groups they belong to. Called from hmib_config_arrays()-
 * style setup code and from display.php to determine a user's panel
 * count.
 *
 * @param int $user_id The user id to resolve; 0 uses the current
 *                     session user.
 *
 * @return array|false A map of panel_id => 'on' for allowed panels, or
 *                     false if the user has no stored permissions.
 */
function get_allowed_panels($user_id = 0) {
	if ($user_id == 0) {
		$user_id = $_SESSION['sess_user_id'];
	}

	$permissions = [];

	// group permission

	// See if they have access to any group realms
	$user_groups = db_fetch_assoc_prepared('SELECT *
		FROM user_auth_group_members
		WHERE user_id = ?',
		[$user_id]);

	if (cacti_sizeof($user_groups)) {
		foreach ($user_groups as $g) {
			$g_perm = db_fetch_cell_prepared('SELECT permissions
			FROM plugin_intropage_user_group_auth
			WHERE user_group_id = ?',
				[$g['group_id']]);

			if ($g_perm) {
				$g_perm = json_decode($g_perm, true);

				foreach ($g_perm as $panel_id => $data) {
					if ($data == 'on') {
						$permissions[$panel_id] = 'on';
					}
				}
			}
		}
	}

	$u_perm = db_fetch_cell_prepared('SELECT permissions
			FROM plugin_intropage_user_auth
			WHERE user_id = ?',
		[$user_id]);

	if ($u_perm) {
		$u_perm = json_decode($u_perm, true);

		foreach ($u_perm as $panel_id => $data) {
			if ($data == 'on') {
				$permissions[$panel_id] = 'on';
			}
		}
	}

	return (cacti_sizeof($permissions) ? $permissions : false);
}

/**
 * AJAX endpoint that reloads and renders a single dashboard panel's
 * header and body markup: forces an update when the panel's stored
 * data contains a &lt;script&gt; tag or the client explicitly requested a
 * forced reload, otherwise updates only when the panel's refresh
 * interval has elapsed, then prints the panel's header controls
 * (reload/detail/resize/remove links) and its rendered data. Called
 * from process_page_request_variables() when action=reload.
 *
 * @return void This function prints its output directly and calls
 *             exit().
 *
 * @global array $panels Populated by initialize_panel_library(); used
 *                       to resolve the panel's definition and render
 *                       options.
 * @global array $config Cacti global configuration array; used to
 *                       build the disable-panel redirect URL.
 */
function intropage_reload_panel() {
	global $panels, $config;

	$login_opts = get_login_opts();

	if ($login_opts == 4) {
		$redirectPage = $config['url_path'] . 'plugins/intropage/intropage.php';
	} else {
		$redirectPage = 'index.php';
	}

	$panel_id = get_filter_request_var('panel_id');

	$forced_update = get_nfilter_request_var('force') == 'true' ? true : false;

	$panel = db_fetch_row_prepared('SELECT *
		FROM plugin_intropage_panel_data
		WHERE id = ?
		AND user_id IN (0, ?)',
		[$panel_id, $_SESSION['sess_user_id']]);

	// Close the session to allow other tabs to operate
	session_write_close();

	if (cacti_sizeof($panel)) {
		// Force update for chart data always

		if (!is_null($panel['data']) && str_contains($panel['data'], '<script')) {
			$forced_update = true;
		}

		// Source panel (not favgraph)
		if (isset($panels[$panel['panel_id']])) {
			$spanel = $panels[$panel['panel_id']];
		}

		if ($panel['fav_graph_id'] > 0) {
			$data                                       = intropage_favourite_graph($panel['fav_graph_id'], $panel['fav_graph_timespan']);
			$panels[$panel['panel_id']]['height_fixed'] = true;
		} elseif (isset($spanel) && cacti_sizeof($spanel) > 0) {
			$function = $spanel['update_func'];
			$user_id  = ($spanel['level'] == PANEL_SYSTEM ? 0 : $_SESSION['sess_user_id']);

			$qpanel = get_panel($panel['panel_id'], $user_id);

			if (function_exists($function)) {
				if ($forced_update || time() > ($qpanel['last'] + $qpanel['refresh'])) {
					// Reload the data
					$function($qpanel, $user_id);
				}

				// Return the data for display
				$data = get_panel_data($qpanel['panel_id'], $user_id);
			} else {
				$data = __('The Panel includes a render function but it does not exist.', 'intropage');
			}
		} else {
			$data = __('The Panel does not have a render function.', 'intropage');
		}

		$name   = isset($data['name']) ? html_escape($data['name']) : __esc('Not Found', 'intropage');
		$height = $panel['height'] ?? 'normal';
		$alarm  = isset($data['alarm']) && $data['alarm'] !== '' ? $data['alarm'] : 'grey';

		print '<div class="panel_header color_' . $alarm . '">';
		print '<div class="panel_name">' . $name . '</div>';

		printf("<div class='panel_actions'><a href='%s' data-panel='panel_$panel_id' class='header_link droppanel' title='" . __esc('Disable panel', 'intropage') . "'><i class='fa fa-times'></i></a>", "$redirectPage/?intropage_action=droppanel&panel_id=$panel_id&dashboard_id=" . $_SESSION['dashboard_id']);

		if (isset($panels[$panel['panel_id']]['force']) && $panels[$panel['panel_id']]['force'] === true) {
			printf("<a href='#' id='reloadid_%s' title='%s' class='header_link reload_panel_now'><i class='fa fa-retweet'></i></a>", $panel_id, __esc('Reload Panel', 'intropage'));
		}

		if (!empty($spanel['details_func'])) {
			printf("<a href='#' class='header_link maxim' detail-panel='%s' title='%s'><i class='fa fa-window-maximize'></i></a>", $panel_id, __esc('Show Details', 'intropage'));
		}

		if ($height == 'triple' && !$panels[$panel['panel_id']]['height_fixed']) {
			printf("<a href='#' id='heightless_id_%s' data-panel='panel_%s' class='header_link heightless' title='" . __esc('Less rows', 'intropage') . "'><i class='fa fa-arrow-up'></i></a>", $panel_id, $panel_id);
			printf("<a href='#' title='%s' class='header_link href_disabled'><i class='fa fa-arrow-down'></i></a>", __esc('Max height reached', 'intropage'));
		} elseif ($height == 'double' && !$panels[$panel['panel_id']]['height_fixed']) {
			printf("<a href='#' id='heightless_id_%s' data-panel='panel_%s' class='header_link heightless' title='" . __esc('Less rows', 'intropage') . "'><i class='fa fa-arrow-up'></i></a>", $panel_id, $panel_id);
			printf("<a href='#' id='heightmore_id_%s' data-panel='panel_%s' class='header_link heightmore' title='" . __esc('More rows', 'intropage') . "'><i class='fa fa-arrow-down'></i></a>", $panel_id, $panel_id);
		} elseif ($height == 'normal' && !$panels[$panel['panel_id']]['height_fixed']) {
			printf("<a href='#' title='%s' class='header_link href_disabled'><i class='fa fa-arrow-up'></i></a>", __esc('Min height reached', 'intropage'));
			printf("<a href='#' id='heightmore_id_%s' data-panel='panel_%s' class='header_link heightmore' title='" . __esc('More rows', 'intropage') . "'><i class='fa fa-arrow-down'></i></a>", $panel_id, $panel_id);
		}

		print '</div>'; // end of panel_actions
		print ' </div>'; // end of panel_header

		print "<div class='panel_data'>";

		if (isset($data['data']) && trim((string) $data['data']) != '') {
			print $data['data'];
		} else {
			print __('No Data Found.  Either wait for next check, <br/>or use the Force Reload if available.', 'intropage');
		}
	} else {
		print '<div class="panel_header color_grey">';
		print '<div class="panel_name">' . __('Panel not found', 'intropage') . '</div>';

		printf("<div class='panel_actions'></div>");
		print '</div>'; // end of header
		print "<div class='panel_data'>";

		if ($panel_id == 997) {	// exception for maint panel
			if (function_exists('maint')) {
				print maint();
			}
		} else {
			print __('Panel not found', 'intropage');
		}
	}

	print '</div>'; // end of panel_data
	exit;
}

/**
 * AJAX endpoint that renders a panel's detail view: invokes the panel
 * definition's 'details_func' and prints the resulting title/alarm/
 * detail markup. Called from process_page_request_variables() when
 * action=details.
 *
 * @return void This function prints its output directly and calls
 *             exit().
 *
 * @global array $panels Populated by initialize_panel_library();
 *                       reserved/declared for parity with other
 *                       functions in this file; not used directly here.
 */
function intropage_detail_panel() {
	global $panels;

	$panel_id = get_filter_request_var('panel_id');

	if ($panel_id != 'analyze_db') {
		$forced_update = filter_var(get_nfilter_request_var('force'), FILTER_VALIDATE_BOOLEAN);
	} else {
		$forced_update = false;
	}

	$panel = get_panel($panel_id, $_SESSION['sess_user_id']);

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

		print '<div class="cactiTableTitleRow">';
		print '<div class="cactiTableTitle">' . $data['name'] . '</div>';
		print '<div class="cactiTableButton"><i class="fas fa-circle color_' . $data['alarm'] . '_bubble"></i></div>';
		print '</div>';
		print $data['detail'];
	} else {
		print __('Panel Not Found', 'intropage');
	}

	exit;
}

/**
 * AJAX endpoint polled by the dashboard's auto-refresh JS: compares the
 * timestamp of the last completed poller cycle against the last time
 * this user's dashboard was refreshed, printing '1' (and recording a
 * new displayed-timestamp) if a refresh is warranted, or '0' otherwise.
 * Called from process_page_request_variables() when action=autoreload.
 *
 * @return void This function prints its output directly and calls
 *             exit().
 */
function intropage_autoreload() {
	$last_poller = db_fetch_cell_prepared('SELECT unix_timestamp(cur_timestamp)
		FROM plugin_intropage_trends
		WHERE name = ?',
		['ar_poller_finish']);

	$last_disp = db_fetch_cell_prepared('SELECT unix_timestamp(cur_timestamp)
		FROM plugin_intropage_trends
		WHERE name = ?',
		['ar_displayed_' . $_SESSION['sess_user_id']]);

	if (!$last_disp) {
		db_execute_prepared('INSERT INTO plugin_intropage_trends (name,value)
			VALUES (?, NOW())',
			['ar_displayed_' . $_SESSION['sess_user_id']]);

		$last_disp = $last_poller;
	}

	if ($last_poller > $last_disp) {
		db_execute_prepared('UPDATE plugin_intropage_trends
			SET cur_timestamp = NOW(), value = NOW()
			WHERE name = ?',
			['ar_displayed_' . $_SESSION['sess_user_id']]);

		print '1';
	} else {
		print '0';
	}

	exit;
}

/**
 * Resolves a panel's current stored data row (by numeric row id or by
 * panel_id) together with its static definition, creating a default
 * data row on first access if none exists yet (e.g. for a favorite
 * graph or a panel not yet added to the user's dashboard), and
 * decorates the result with a human-readable 'next update in' suffix
 * on its name. Called throughout this plugin (intropage_reload_panel(),
 * panel update functions, intropage_actions_timespan(), etc.) to fetch
 * a panel's combined data+definition.
 *
 * @param int|string $panel_id The panel's numeric row id, or its
 *                            string panel_id.
 * @param int        $user_id  The user id to scope the panel data to;
 *                            0 for system-level panels.
 *
 * @return array The combined panel data (id, panel_id, name, and other
 *              stored/derived fields) plus its 'definition' sub-array.
 */
function get_panel($panel_id, $user_id = 0) {
	// Either fetch by row id or panel_id
	if (is_numeric($panel_id)) {
		$panel = db_fetch_row_prepared('SELECT *, UNIX_TIMESTAMP(last_update) AS ts
			FROM plugin_intropage_panel_data
			WHERE id = ?
			AND user_id IN (0, ?)
			LIMIT 1',
			[$panel_id, $user_id]);

		$panel_id = $panel['panel_id'];
	} else {
		$panel = db_fetch_row_prepared('SELECT *, UNIX_TIMESTAMP(last_update) AS ts
			FROM plugin_intropage_panel_data
			WHERE panel_id = ?
			AND user_id IN(0, ?)
			LIMIT 1',
			[$panel_id, $user_id]);
	}

	$definition = db_fetch_row_prepared('SELECT *
		FROM plugin_intropage_panel_definition
		WHERE panel_id = ?',
		[$panel_id]);

	// favourite graph exception
	if (!cacti_sizeof($definition)) {
		$definition =  [];

		$definition['name']	     = '';
		$definition['refresh']	  = 300;
		$definition['trefresh']	 = false;
		$definition['level']	    = $_SESSION['sess_user_id'];
		$definition['priority']	 = 99;
		$definition['alarm']	    = 'grey';
		$definition['height']	   = 'normal';
	}

	if (cacti_sizeof($panel)) {
		$last_update      = $panel['ts'];
		$refresh_interval = $panel['refresh_interval'];
		$trend_interval   = $panel['trend_interval'];
		$next_update      = $last_update + $refresh_interval - time();
		$panel['height']  = $panel['height'] ?? 'normal';

		$panel['name']    = $definition['name'] . __(' [Upd. in %s/%s]', intropage_readable_interval($next_update), intropage_readable_interval($refresh_interval), 'intropage');
	} else {
		$last_update      = time();
		$refresh_interval = $definition['refresh'];
		$trend_interval   = $definition['trefresh'];
		$next_update      = $refresh_interval;

		$panel = [];

		$panel['id']               = 0;
		$panel['panel_id']         = $panel_id;
		$panel['user_id']          = ($definition['level'] == PANEL_SYSTEM ? 0 : $_SESSION['sess_user_id']);
		$panel['last_update']      = $last_update;
		$panel['data']             = '';
		$panel['priority']         = $definition['priority'];
		$panel['alarm']            = $definition['alarm'];
		$panel['refresh_interval'] = $definition['refresh'];
		$panel['trend_interval']   = $definition['trefresh'];
		$panel['height']           = $definition['height'];

		$panel['id']   = sql_save($panel, 'plugin_intropage_panel_data');
		$panel['name'] = $definition['name'] . __(' [Upd. in %s/%s]', intropage_readable_interval($next_update), intropage_readable_interval($definition['refresh']), 'intropage');
	}

	return [
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
		'definition' => $definition,
		'height'     => $panel['height']
	];
}

/**
 * Formats a duration in seconds as a human-readable interval string
 * (seconds/minutes/hours/days), choosing the largest applicable unit
 * and an optional abbreviated form. Called from get_panel() to build
 * each panel's 'next update in' display text.
 *
 * @param float $value The duration in seconds to format.
 * @param int   $round The number of decimal places to round the
 *                     displayed value to.
 * @param bool  $short Whether to use abbreviated unit labels (e.g.
 *                     'Sec'/'Min'/'Hrs') instead of full words.
 *
 * @return string The formatted interval string, or '-' if $value is
 *               not positive.
 */
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

/**
 * Returns the list of users whose panel data should be processed: just
 * the current session user when running interactively in a web
 * request, or every enabled user with an Intropage auth row when
 * running from the poller. Called from the poller/collection flow to
 * determine which users' panels to update.
 *
 * @return array A list of rows each containing at least an 'id' key.
 *
 * @global array $config Cacti global configuration array; used to
 *                       determine whether this is a web request.
 */
function get_user_list() {
	global $config;

	if ($config['is_web'] && $_SESSION['sess_user_id'] > 0) {
		// specific user wants his panel only
		$users = [
			[
				'id' => $_SESSION['sess_user_id']
			]
		];
	} else { // poller wants all
		$users = db_fetch_assoc_prepared('SELECT t1.id AS id
			FROM user_auth AS t1
			JOIN plugin_intropage_user_auth AS t2
			ON t1.id = t2.user_id
			WHERE t1.enabled = ?',
			['on']);
	}

	return $users;
}

/**
 * Persists a panel's freshly computed data/alarm state back to its
 * plugin_intropage_panel_data row and updates its last-update
 * timestamp. Called at the end of every panel's data-update function.
 *
 * @param array $panel   The panel's data row, including 'id', 'data',
 *                       and 'alarm'.
 * @param int   $user_id The user id to scope the update to; 0 for
 *                       system-level panels.
 *
 * @return void
 */
function save_panel_result($panel, $user_id = 0) {
	db_execute_prepared('UPDATE plugin_intropage_panel_data
		SET data = ?, alarm = ?, user_id = ?, last_update = NOW()
		WHERE id = ?',
		[$panel['data'], $panel['alarm'], $user_id, $panel['id']]);
}

/**
 * Fetches a panel's stored rendered data/alarm/last-update/height
 * fields (plus a formatted recheck interval), substituting a
 * placeholder message when no data has been collected yet. Called from
 * intropage_reload_panel() and display-rendering code to obtain a
 * panel's current display content.
 *
 * @param int|string $panel_id The panel's numeric row id or string
 *                            panel_id.
 * @param int        $user_id  The user id to scope the panel data to;
 *                            0 for system-level panels.
 *
 * @return array The panel's data row (id, data, alarm, last_update,
 *              height, recheck) plus its resolved 'name'.
 */
function get_panel_data($panel_id, $user_id = 0) {
	$panel = get_panel($panel_id, $user_id);

	$data = db_fetch_row_prepared("SELECT id, data, alarm, last_update, height,
		concat(floor(TIME_FORMAT(SEC_TO_TIME(refresh_interval), '%H') / 24), 'd ',
		MOD(TIME_FORMAT(SEC_TO_TIME(refresh_interval), '%H'), 24), 'h:',
		TIME_FORMAT(SEC_TO_TIME(refresh_interval), '%im')) AS recheck
		FROM plugin_intropage_panel_data
		WHERE panel_id = ?
		AND user_id IN (0, ?)",
		[$panel_id, $user_id]);

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

/**
 * Checks whether a user has been granted Cacti's console-access realm
 * (realm id 8). Called from include/tab.php and intropage_console_after()
 * to decide whether a user has full console access alongside Intropage.
 *
 * @param int $user_id The user id to check.
 *
 * @return bool True if the user has console-access realm permission,
 *             false otherwise.
 */
function get_console_access($user_id) {
	return (db_fetch_assoc_prepared('SELECT realm_id
		FROM user_auth_realm
		WHERE user_id = ?
		AND user_auth_realm.realm_id=8',
		[$user_id])) ? true : false;
}

/**
 * Builds (and request-caches in a static/session variable) the full
 * panel library by including every file in panellib/ and calling its
 * 'register_*' function, pruning any panel whose required plugin(s)
 * aren't installed/enabled and optionally auto-unregistering their
 * stored data when 'intropage_unregister' is enabled. Called
 * throughout this plugin (display_information(), panel action
 * handlers, is_panel_enabled(), etc.) wherever the set of available
 * panels is needed.
 *
 * @return array The available panel definitions, keyed by panel id.
 *
 * @global array $config   Cacti global configuration array; used to
 *                         locate the panellib directory.
 * @global array $registry Populated here with each panellib file's
 *                         category metadata (via its 'register_*'
 *                         function).
 */
function initialize_panel_library() {
	global $config, $registry;

	static $panel_library = [];

	if (!sizeof($panel_library)) {
		$panels    = [];
		$uninstall = [];

		$files  = glob($config['base_path'] . '/plugins/intropage/panellib/*.php');

		if (cacti_sizeof($files)) {
			foreach ($files as $file) {
				$basename = str_replace('.php', '', basename($file));

				if (basename($file) != 'index.php') {
					include_once($file);
				}

				if (function_exists('register_' . $basename)) {
					$base_panels = call_user_func('register_' . $basename);

					// Check to see if the panel should be activated
					foreach ($base_panels as $panel_id => $p) {
						if (isset($p['requires']) && $p['requires'] !== false) {
							$plugins = explode(',', $p['requires']);

							foreach ($plugins as $plugin) {
								$status = db_fetch_cell_prepared('SELECT `status`
									FROM plugin_config
									WHERE directory = ?',
									[$plugin]);

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
			foreach ($uninstall as $panel_id) {
				$id = db_fetch_cell_prepared('SELECT id
					FROM plugin_intropage_panel_data
					WHERE panel_id = ?',
					[$panel_id]);

				if ($id > 0) {
					db_execute_prepared('DELETE FROM plugin_intropage_panel_dashboard
						WHERE panel_id = ?',
						[$id]);

					db_execute_prepared('DELETE FROM plugin_intropage_panel_data
						WHERE id = ?',
						[$id]);

					db_execute_prepared('DELETE FROM plugin_intropage_definition
						WHERE panel_id = ?',
						[$panel_id]);
				}
			}
		}

		$panel_library = $panels;

		$_SESSION['intropage_panel_library'] = $panel_library;
	}

	return $panel_library;
}

/**
 * Bulk-upserts a set of panel definitions into
 * plugin_intropage_panel_definition, used to (re-)synchronize the
 * database with the panel library's in-code definitions. Intended to
 * be called after modifying registered panel definitions (e.g. by a
 * third-party plugin via intropage_add_panel()) to persist them.
 *
 * @param array $panels The panel definitions to upsert, keyed by
 *                      panel id, each with the full set of definition
 *                      fields (name, level, class, priority, alarm,
 *                      requires, update_func, details_func,
 *                      trends_func, refresh, trefresh, description,
 *                      height).
 *
 * @return void
 */
function update_registered_panels($panels) {
	$prefix = 'INSERT INTO plugin_intropage_panel_definition
		(panel_id, name, level, class, priority, alarm, requires, update_func, details_func, trends_func, refresh, trefresh, description, height) VALUES';

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
		description=VALUES(description),
		height=VALUES(height)';

	$sql    = [];
	$params = [];

	if (cacti_sizeof($panels)) {
		foreach ($panels as $panel_id => $panel) {
			$sql[]  = '(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
			$params = array_merge($params, [
				$panel_id, $panel['name'], $panel['level'], $panel['class'],
				$panel['priority'], $panel['alarm'], $panel['requires'],
				$panel['update_func'], $panel['details_func'], $panel['trends_func'],
				$panel['refresh'], $panel['trefresh'], $panel['description'], $panel['height'],
			]);
		}

		db_execute_prepared($prefix . implode(', ', $sql) . $suffix, $params);
	}
}

/**
 * Renders a user's chosen favorite graph as an embedded graph image for
 * a given timespan, sized according to the user's configured panel
 * line count. Called from intropage_reload_panel() when rendering a
 * panel backed by a favorite graph rather than a registered panel
 * definition.
 *
 * @param int $fav_graph_id       The local_graph_id of the favorite
 *                                graph to render.
 * @param int $fav_graph_timespan A graph_timeshifts key selecting the
 *                                graph's display timespan.
 *
 * @return array|null The rendered panel result ('name', 'alarm',
 *                    'data'), or null if $fav_graph_id is not set.
 *
 * @global array $config           Cacti global configuration array;
 *                                 used to build the graph image URL and
 *                                 include the time library.
 * @global array $graph_timeshifts Map of timespan key => display label,
 *                                 used to build the graph's title.
 */
function intropage_favourite_graph($fav_graph_id, $fav_graph_timespan) {
	global $config, $graph_timeshifts;

	$lines = intropage_get_lines($_SESSION['sess_user_id']);

	if ($lines == 5) {
		$graph_height = 100;
	} elseif ($lines == 10) {
		$graph_height = 170;
	} else {
		$graph_height = 250;
	}

	if (isset($fav_graph_id)) {
		$result = [
			'name'   => '', // we don't need name here
			'alarm'  => 'grey',
			'data'   => '',
		];

		include_once($config['base_path'] . '/lib/time.php');

		$result['name'] .= ' ' . db_fetch_cell_prepared('SELECT title_cache
			FROM graph_templates_graph
			WHERE local_graph_id = ?',
			[$fav_graph_id]);

		$result['name'] .= ' - ' . $graph_timeshifts[$fav_graph_timespan];

		$timespan        = [];
		$first_weekdayid = read_user_setting('first_weekdayid');

		get_timespan($timespan, time(),$fav_graph_timespan , $first_weekdayid);

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

/**
 * Renders one of this plugin's panel chart types (line, pie, or other
 * supported chart shape described by $dispdata) as embedded HTML/JS
 * (e.g. a C3/D3-based chart), sizing the chart according to the user's
 * configured panel line count. Called from nearly every panellib
 * data-update function to turn its gathered data into displayable
 * panel content.
 *
 * @param array $dispdata The chart data/config, keyed by chart type
 *                        (e.g. 'line', 'pie') with that type's
 *                        expected sub-structure (titles, labels, data
 *                        series).
 * @param int   $user_id  The id of the user the chart is being
 *                        rendered for, used to size the chart.
 *
 * @return string The rendered HTML/JS markup for the chart.
 */
function intropage_prepare_graph($dispdata, $user_id) {
	global $config;

	$lines = intropage_get_lines($user_id);

	if ($lines == 5) {
		$graph_height = 180;
	} elseif ($lines == 10) {
		$graph_height = 200;
	} else {
		$graph_height = 270;
	}

	$content = '';

	// line graph
	if (isset($dispdata['line'])) {
		$xid = 'x' . substr(md5($dispdata['line']['title1']), 0, 7);

		$columns   = [];
		$axes      = [];
		$axis      = [];
		$color_def = [];

		// Add the X Axis first
		$columns[] = array_merge(['x'], $dispdata['line']['label1']);

		// Add upto 5 Lines
		for ($i = 1; $i < 6; $i++) {
			if (isset($dispdata['line']["data$i"]) && cacti_sizeof($dispdata['line']["data$i"])) {
				// set correct color for down/triggered, ...
				if (preg_match('/(DOWN|TRIG)/', strtoupper($dispdata['line']["title$i"]))) {
					$color_def[$dispdata['line']["title$i"]] = '#ff0000';
				} elseif (preg_match('/(RECO|BREA)/', strtoupper($dispdata['line']["title$i"]))) {
					$color_def[$dispdata['line']["title$i"]] = '#dddd00';
				} elseif (preg_match('/(UP|OK)/', strtoupper($dispdata['line']["title$i"]))) {
					$color_def[$dispdata['line']["title$i"]] = '#00ff00';
				} elseif (preg_match('/(DISA)/', strtoupper($dispdata['line']["title$i"]))) {
					$color_def[$dispdata['line']["title$i"]] = '#cccccc';
				}

				$columns[] = array_merge([$dispdata['line']["title$i"]], $dispdata['line']["data$i"]);

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

		if (cacti_sizeof($color_def)) {
			$colors = '';

			foreach ($color_def as $key => $value) {
				$colors .= $key . "':'" . $value . "',";
			}
		}

		$chart = [
			'bindto' => "#line_$xid",
			'size'   => [
				'height' => 100,
				'width'  => 150
			],
			'point' => [
				'r' => 1.5
			],
			'zoom' => [
				'enabled' => 'true',
				'type'    => 'drag'
			],
			'data' => [
				'type'   => 'line',
				'x'      => 'x',
				'Format' => '%Y-%m-%d %H:%M:%S',
				'colors' => $color_def,
			]
		];

		// Setup Axes support
		if (isset($dispdata['line']['unit2']['series'])) {
			$axes = [];

			foreach ($dispdata['line']['unit2']['series'] as $series) {
				$number                                  = str_replace('data', '', $series);
				$axes[$dispdata['line']["title$number"]] = 'y2';
			}
		}

		// Setup the Axis
		$axis['x'] = [
			'type' => 'timeseries',
			'tick' => [
				'format'  => '%H:%M',
				'culling' => ['max' => 6],
			]
		];

		if (isset($dispdata['line']['unit1'])) {
			$axis['y'] = [
				'tick' => [
					'culling' => ['max' => 8]
				],
				'label' => [
					'text' => $dispdata['line']['unit1']['title'],
				],
				'show' => true
			];
		}

		if (isset($dispdata['line']['unit2'])) {
			$axis['y2'] = [
				'tick' => [
					'culling' => ['max' => 8]
				],
				'label' => [
					'text' => $dispdata['line']['unit2']['title'],
				],
				'show' => true
			];
		}

		$chart['data']['columns'] = $columns;
		$chart['data']['axes']    = $axes;
		$chart['axis']            = $axis;

		$chart_data = json_encode($chart);
		$content .= '<div style="height: ' . $graph_height . 'px;" class="chart_wrapper center" id="line_' . $xid . '"></div>';
		$content .= '<script type="text/javascript">';
		$content .= 'panels.line_' . $xid . ' = bb.generate(' . $chart_data . ');';
		$content .= '</script>';
	} // line graph end

	// bar graph - up to 8 values
	if (isset($dispdata['bar'])) {
		$xid       = 'x' . substr(md5($dispdata['bar']['title1']), 0, 7);
		$columns   = [];
		$axes      = [];
		$axis      = [];
		$color_def = [];
		$group     = [];

		// Add the X Axis first
		$columns[] = array_merge(['x'], $dispdata['bar']['label1']);

		for ($i = 0; $i < 8; $i++) {
			if (isset($dispdata['bar']["data$i"]) && cacti_sizeof($dispdata['bar']["data$i"])) {
				$columns[] = array_merge([$dispdata['bar']["title$i"]], $dispdata['bar']["data$i"]);

				$axes[$dispdata['bar']["title$i"]] = 'y';
				array_push($group, $dispdata['bar']["title$i"]);
			}
		}

		$groups = [$group];

		$chart = [
			'bindto' => "#bar_$xid",
			'size'   => [
				'height' => 100,
				'width'  => 150
			],
			'zoom' => [
				'enabled' => 'true',
				'type'    => 'drag'
			],
			'data' => [
				'type'   => 'bar',
				'x'      => 'x',
				'Format' => '%Y-%m-%d %H:%M:%S',
			],
			'bar' => [
				'width' => 7,
			]
		];
		// Setup the Axis
		$axis['x'] = [
			'type' => 'timeseries',
			'tick' => [
				'format'  => '%H:%M',
				'culling' => ['max' => 6],
			]
		];

		if (isset($dispdata['bar']['unit1'])) {
			$axis['y'] = [
				'tick' => [
					'culling' => ['max' => 8]
				],
				'label' => [
					'text' => $dispdata['bar']['unit1']['title'],
				],
				'show' => true
			];
		}

		$chart['data']['columns'] = $columns;
		$chart['data']['groups']  = $groups;

		$chart['data']['axes']    = $axes;
		$chart['axis']            = $axis;

		$chart_data = json_encode($chart);
		$content .= '<div style="height: ' . $graph_height . 'px;" class="chart_wrapper center" id="bar_' . $xid . '"></div>';
		$content .= '<script type="text/javascript">';
		$content .= 'panels.bar_' . $xid . ' = bb.generate(' . $chart_data . ');';
		$content .= '</script>';
	} // bar graph end

	if (isset($dispdata['pie'])) {
		$xid = 'x' . substr(md5($dispdata['pie']['title']), 0, 7);

		$content .= "<div class='chart_wrapper center' id=\"pie_$xid\"></div>";
		$content .= '<script type="text/javascript">';
		$content .= 'panels.pie_' . $xid . ' = bb.generate({';
		$content .= " bindto: \"#pie_$xid\",";

		$content .= ' size: {';
		$content .= "  height: $graph_height";
		$content .= ' },';

		$content .= ' data: {';
		$content .= '  columns: [';

		foreach ($dispdata['pie']['data'] as $key => $value) {
			$content .= "['" . $dispdata['pie']['label'][$key] . "', " . $value . '],';
		}

		$content .= '  ],';
		$content .= "  type: 'pie',";
		$content .= '  },';

		$content .= '  pie: {';
		$content .= '    label: {';
		$content .= '      format: function (value, ratio, id) {';
		$content .= '        return (value);';
		$content .= '      }';
		$content .= '    }';
		$content .= '  },';

		$content .= "legend: { position: 'right' },";

		$content .= '});';
		$content .= '</script>';
	}   // pie graph end

	if (isset($dispdata['treemap'])) {
		$xid = 'x' . substr(md5($dispdata['treemap']['title']), 0, 7);

		$content .= "<div class='chart_wrapper center' id=\"treemap_$xid\"></div>";
		$content .= '<script type="text/javascript">';
		$content .= 'panels.treemap_' . $xid . ' = bb.generate({';
		$content .= " bindto: \"#treemap_$xid\",";

		$content .= ' size: {';
		$content .= "  height: $graph_height";
		$content .= ' },';

		$content .= ' data: {';
		$content .= '  columns: [';

		foreach ($dispdata['treemap']['data'] as $key => $value) {
			$content .= "['" . $dispdata['treemap']['label'][$key] . "', " . $value . '],';
		}

		$content .= '  ],';
		$content .= "  type: 'treemap',";
		$content .= '  labels: {';
		$content .= "    colors: '#fff'";
		$content .= '  }';
		$content .= '  },';

		$content .= '  treemap: {';
		$content .= '    label: {';
		$content .= '      threshold: 0.03, show: true,';
		$content .= '    }';
		$content .= '  },';

		$content .= '});';
		$content .= '</script>';
	}   // treemap graph end

	return ($content);
}

/**
 * Efficiently reads the last N lines of a (potentially large) log file
 * by seeking and reading backwards in chunks, avoiding loading the
 * entire file into memory. Called from analyse_log()/analyse_log_detail()
 * to scan the Cacti log for recent error patterns.
 *
 * @param string $log_file  Path to the log file to read.
 * @param int    $nbr_lines The number of trailing lines to return.
 * @param bool   $adaptive  Whether to scale the internal read buffer
 *                         size based on $nbr_lines (more efficient for
 *                         small requests) instead of always using a
 *                         4096-byte buffer.
 *
 * @return array|false An array of the last $nbr_lines lines, or false
 *                    if the file doesn't exist/isn't readable.
 */
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

/**
 * Formats a byte count as a human-readable size string with the
 * appropriate binary unit suffix (B/kB/MB/GB/etc). Called when
 * rendering file/database sizes in panel displays.
 *
 * @param int|string $bytes    The size in bytes to format.
 * @param int        $decimals The number of decimal places to include.
 *
 * @return string The formatted size string with unit suffix.
 */
function human_filesize($bytes, $decimals = 2) {
	$size   = ['B', 'kB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];
	$factor = floor((strlen($bytes) - 1) / 3);

	return sprintf("%.{$decimals}f", $bytes / pow(1024, $factor)) . @$size[$factor];
}

/**
 * Prints an empty placeholder &lt;li&gt; grid item for a panel, sized
 * according to its width/height class, which the dashboard's client-
 * side JS then populates via an async reload call. Called while
 * rendering the dashboard's panel grid for each panel a user has
 * enabled.
 *
 * @param int $panel_id     The panel's plugin_intropage_panel_data row
 *                          id.
 * @param int $dashboard_id The dashboard this panel item belongs to.
 *
 * @return void
 *
 * @global array $config Reserved/declared for parity with other
 *                       functions in this file; not used directly
 *                       here.
 */
function intropage_create_panel($panel_id, $dashboard_id) {
	global $config;

	$panels = initialize_panel_library();
	$class  = 'panel_1_1';

	$act_param = db_fetch_row_prepared('SELECT panel_id, height
		FROM plugin_intropage_panel_data
		WHERE id = ?',
		[$panel_id]);

	$panel_type = $act_param['panel_id'];
	$act_height = $act_param['height'];

	if ($panel_type == 'favourite_graph') {
		$width  = 'quarter-panel';
		$height = 'normal';
	} else {
		$width = $panels[$panel_type]['width'];
		// we need actual height from db not from panel definition
		$height = $act_height ?? 'normal';
	}

	if ($width == 'quarter-panel') {
		if ($height == 'normal') {
			$class = 'panel_1_1';
		} elseif ($height == 'double') {
			$class = 'panel_2_1';
		} elseif ($height == 'triple') {
			$class = 'panel_3_1';
		}
	} else {	// double width
		if ($height == 'normal') {
			$class = 'panel_1_2';
		} elseif ($height == 'double') {
			$class = 'panel_2_2';
		} elseif ($height == 'triple') {
			$class = 'panel_3_2';
		}
	}

	print '<li id="panel_' . $panel_id . '" class="' . $class . ' grid_item">';
	print '<div class="panel_wrapper">';

	print '</div>'; // end of wrapper
	print '</li>';
}

/**
 * Renders the 'add panel' dropdown for a dashboard, listing panels not
 * already present on it, disabling entries the user isn't permitted to
 * view and omitting ones whose required plugin isn't enabled. Called
 * while rendering the dashboard toolbar.
 *
 * @param int $dashboard_id The dashboard to list addable panels for.
 *
 * @return void
 */
function intropage_addpanel_select($dashboard_id) {
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
		[$_SESSION['sess_user_id'], $dashboard_id]);

	// display always, even if it is empty
	print "<select id='intropage_addpanel'>";
	print '<option value="0">' . __('Panels ...', 'intropage') . '</option>';

	if (cacti_sizeof($add_panels)) {
		foreach ($add_panels as $panel) {
			$uniqid = db_fetch_cell_prepared('SELECT id
				FROM plugin_intropage_panel_data
				WHERE user_id IN (0, ?)
				AND panel_id = ?',
				[$_SESSION['sess_user_id'], $panel['panel_id']]);

//			if ($panel['panel_id'] != 'maint' && $panel['panel_id'] != 'admin_alert') {
			if ($panel['panel_id'] != 'admin_alert') {
				$allowed = is_panel_allowed($panel['panel_id']);

				$enabled = is_panel_enabled($panel['panel_id']);

				if ($uniqid > 0) {
					if ($allowed) {
						if ($enabled) {
							print "<option value='" . $uniqid . "'>" . html_escape($panel['name']) . '</option>';
						}
					} else {
						print "<option value='addpanel_" . $uniqid . "' disabled='disabled'>" . __('%s (no permission)', $panel['name'], 'intropage') . '</option>';
					}
				} else {
					if ($allowed) {
						if ($enabled) {
							print "<option value='" . $panel['panel_id'] . "'>" . html_escape($panel['name']) . '</option>';
						}
					} else {
						print "<option value='addpanel_" . $uniqid . "' disabled='disabled'>" . __('%s (no permission)', $panel['name'], 'intropage') . '</option>';
					}
				}
			}
		}
	}
	print '</select>';
	print '&nbsp; &nbsp;';
}

/**
 * Queries a remote NTP server via a raw UDP NTP request and returns the
 * server's reported Unix timestamp, used to detect local clock drift.
 * Called from ntp_dns() (panellib/misc.php) for the 'NTP/DNS Status'
 * panel.
 *
 * @param string $host The NTP server hostname/IP to query.
 *
 * @return int|string The remote Unix timestamp, or an 'error: ...'
 *                    string describing a socket failure.
 *
 * @global array $config Cacti global configuration array; used to
 *                       select the correct socket receive mode on
 *                       Windows vs. other platforms.
 */
function ntp_time($host) {
	global $config;

	$timestamp = -1;
	// create a UDP socket
	$sock      = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);

	if (!$sock) {
		return 'error: socket_create failed:' . socket_strerror(socket_last_error($sock));
	}

	// set a timeout of 5 second
	$timeout = ['sec' => 5, 'usec' => 0];
	socket_set_option($sock, SOL_SOCKET, SO_RCVTIMEO, $timeout);

	// clear any existing error
	socket_clear_error();

	// send an NTP request to the server
	$request = "\x1b" . str_repeat("\x00", 47);

	if (socket_sendto($sock, $request, strlen($request), 0, $host, 123) === false) {
		$error = socket_strerror(socket_last_error($sock));
		socket_close($sock);

		return 'error: socket_sendto failed:' . $error;
	}

	// receive the NTP response from the server
	$recv = '';

	if ($config['cacti_server_os'] == 'win32') {
		if (socket_recv($sock, $recv, 48, MSG_PEEK) === false) {
			$error = socket_strerror(socket_last_error($sock));
			socket_close($sock);

			return 'error: socket_recv failed: ' . $error;
		}
	} else {
		if (socket_recv($sock, $recv, 48, MSG_WAITALL) === false) {
			$error = socket_strerror(socket_last_error($sock));
			socket_close($sock);

			return 'error: socket_recv failed: ' . $error;
		}
	}
	// extract the timestamp from the received data
	$data      = unpack('N12', $recv);
	$timestamp = sprintf('%u', $data[9]);

	// close the socket
	socket_close($sock);

	// NTP is number of seconds since 0000 UT on 1 January 1900
	// Unix time is seconds since 0000 UT on 1 January 1970
	$timestamp -= 2208988800;

	return ($timestamp);
}
/**
 * Graph_buttons/graph_buttons_thumbnails hook: adds an 'Add to
 * Favorites/Intropage' button to Cacti's graph view toolbar, letting
 * the user pin the current graph as a favorite-graph panel on their
 * dashboard. Called by Cacti's graph rendering via the
 * 'graph_buttons'/'graph_buttons_thumbnails' hooks.
 *
 * @param array $data The graph button context data supplied by Cacti
 *                    (e.g. the current graph's local_graph_id).
 *
 * @return void
 *
 * @global array $config       Cacti global configuration array; used
 *                             to build URLs.
 * @global mixed $callbackPage Reserved/declared for use by included
 *                             code; not set directly here.
 * @global mixed $redirectPage Populated here with the page to redirect
 *                             to based on the user's login options.
 */
function intropage_graph_button($data) {
	global $config, $callbackPage, $redirectPage;

	$login_opts = get_login_opts();

	if ($login_opts == 4) {
		$redirectPage = $config['url_path'] . 'plugins/intropage/intropage.php';
	} else {
		$redirectPage = 'index.php';
	}

	if (is_panel_allowed('favourite_graph')) {
		$local_graph_id = $data[1]['local_graph_id'];

		$_SESSION['sess_current_timespan'] ??= read_user_setting('default_timespan');

		if ($_SESSION['sess_current_timespan'] == 0) { // zoom or custom timespan
			$fav = '<i class="fa fa-eye-slash" title="' . __esc('Cannot add to Dashboard. Custom timespan.', 'intropage') . '"></i>';
		} else {
			$present = db_fetch_cell_prepared('SELECT COUNT(*)
				FROM plugin_intropage_panel_data
				WHERE user_id = ?
				AND fav_graph_id = ?
				AND fav_graph_timespan = ?',
				[$_SESSION['sess_user_id'], $local_graph_id, $_SESSION['sess_current_timespan']]);

			if ($present) {
				$fav = '<i class="fa fa-eye-slash" title="' . __esc('Remove from Dashboard', 'intropage') . '"></i>';
			} else {
				$fav = '<i class="fa fa-eye" title="' . __esc('Add to Dashboard', 'intropage') . '"></i>';
			}
		}

		print '<a class="iconLink" href="' . html_escape("$redirectPage?intropage_action=favgraph&graph_id=$local_graph_id") . '">' . $fav . '</a><br/>';
	}
}

/**
 * Resolves (and caches in the session) the current user's effective
 * login-options value, handling explicit 'loginopt_console'/
 * 'loginopt_tab'/'loginopt_graph' action requests as session overrides,
 * otherwise reading the user's stored login_opts from the database.
 * Called from intropage_login_options_navigate(), include/tab.php, and
 * display_information() to decide navigation/rendering behavior.
 *
 * @param bool $refresh Whether to force re-reading the value from the
 *                      database even if already cached in the session.
 *
 * @return int The resolved login_opts value.
 */
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
			[$_SESSION['sess_user_id']]);

		$_SESSION['intropage_login_opts'] = $login_opts;
	}

	return $_SESSION['intropage_login_opts'];
}

/**
 * AJAX/form endpoint rendering the panel configuration dialog: lets the
 * user rename their dashboards and override each panel's (and, for
 * admins, each system panel's and trend collector's) refresh interval
 * away from its default. Called from process_page_request_variables()
 * when action=configure.
 *
 * @return void
 *
 * @global array $config              Reserved/declared for parity with
 *                                    other functions in this file; not
 *                                    used directly here.
 * @global mixed $callbackPage        Reserved/declared for use by
 *                                    included code; not set directly
 *                                    here.
 * @global mixed $redirectPage        The form's target action URL,
 *                                    expected to already be set by the
 *                                    caller.
 * @global array $trend_timespans     Reserved/declared for parity with
 *                                    other functions in this file; not
 *                                    used directly here.
 * @global array $intropage_intervals The refresh-interval option list
 *                                    used to populate each panel's
 *                                    interval dropdown.
 */
function intropage_configure_panel() {
	global $config, $callbackPage, $redirectPage, $trend_timespans, $intropage_intervals;

	$dashboards = array_rekey(
		db_fetch_assoc_prepared('SELECT dashboard_id, name
			FROM plugin_intropage_dashboard
			WHERE user_id = ?
			ORDER BY dashboard_id',
			[$_SESSION['sess_user_id']]),
		'dashboard_id', 'name'
	);

	print '<div>';

	form_start($redirectPage);

	html_start_box(__('Dashboard Names', 'intropage'), '100%', '', '3', 'center', '');

	$class = 'odd';

	foreach ($dashboards as $f => $name) {
		$class = ($class == 'odd' ? 'even' : 'odd');

		print '<div id="row_name_' . $f . '" class="formRow ' . $class . '">
			<div class="formColumnLeft">
				<div class="formFieldName">' . __('Dashboard %s', $f, 'intropage') . '</div>
			</div>
			<div class="formColumnRight">
				<div class="formData">';

		print '<input type="text" name="name_' . $f . '" value="' . html_escape($name) . '"></br>';

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
		[$_SESSION['sess_user_id']]);

	if (cacti_sizeof($panels)) {
		html_start_box(__('User Level Panel Update Frequencies', 'intropage'), '100%', '', '3', 'center', '');

		$class = 'odd';

		foreach ($panels as $panel) {
			$class = ($class == 'odd' ? 'even' : 'odd');

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
			ORDER BY level, name');

		if (cacti_sizeof($panels)) {
			html_start_box(__('System Panel Update Frequencies (All Authorized Users)', 'intropage'), '100%', '', '3', 'center', '');

			$class = 'odd';

			foreach ($panels as $panel) {
				$class = ($class == 'odd' ? 'even' : 'odd');

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
			WHERE pda.user_id = ?
			AND pda.fav_graph_id IS NULL
			AND pd.trends_func != ""
			ORDER BY level, name',
			[$_SESSION['sess_user_id']]);

		if (cacti_sizeof($panels)) {
			html_start_box(__('Trend Update Frequencies', 'intropage'), '100%', '', '3', 'center', '');

			$class = 'odd';

			foreach ($panels as $panel) {
				$class = ($class == 'odd' ? 'even' : 'odd');

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

	form_save_button($redirectPage, 'save');

	form_end();

	print '</div>';
}

/**
 * Formats a numeric value (bytes or otherwise) as a human-readable
 * string with a metric (decimal, factor 1000) or binary (factor 1024)
 * unit suffix, supporting both large (K/M/G/T/P) and small
 * (m/µ/n/p) magnitudes. Called throughout panel rendering wherever a
 * general numeric metric needs unit-scaled display.
 *
 * @param float $bytes     The value to format.
 * @param bool  $decimal   Whether to scale by powers of 1000 (true) or
 *                        1024 (false).
 * @param int   $precision The number of decimal places to round the
 *                        displayed value to.
 *
 * @return string The formatted value with its unit suffix, or 0 if
 *               $bytes is 0.
 */
function human_readable($bytes, $decimal = true, $precision = 2) {
	if ($decimal) {
		$factor = 1000;
	} else {
		$factor = 1024;
	}

	if ($bytes == 0) {
		return 0;
	}

	if ($bytes < 1) {
		$sizes = [0 => '', -1 => 'm', -2 => 'µ', - 3 => 'n', -4 => 'p'];
	} else {
		$sizes = [0 => '', 1 => 'K', 2 => 'M', 3 => 'G', 4 => 'T', 5=> 'P'];
	}

	$i = (int) floor(log(abs($bytes)) / log($factor));
	$d = pow($factor, $i);

	if (!array_key_exists($i, $sizes)) {
		if (function_exists('cacti_log')) {
			cacti_log('INTROPAGE WARNING: Bytes = [' . $bytes . '], Factor = [' . $factor . '], i = [' . $i . '] d = [' . $d . ']');
			cacti_debug_backtrace('intropage-hr');
		} else {
			print 'INTROPAGE WARNING: Bytes = [' . $bytes . '], Factor = [' . $factor . '], i = [' . $i . '] d = [' . $d . ']';
		}
		$size = '<unknown>';
		$i    = 1;
	} else {
		$size = $sizes[$i];
	}

	return round(empty($d) ? 0 : ($bytes / pow($factor, $i)), $precision) . ' ' . $size;
}

/**
 * Computes the number of table rows a panel should display, scaling
 * the user's configured base line count by the panel's height class
 * (normal/double/triple). Called from list-rendering panel update
 * functions to determine their SQL LIMIT.
 *
 * @param string $height  The panel's height class ('normal', 'double',
 *                       or 'triple').
 * @param int    $user_id The user id to resolve the base line count
 *                       for.
 *
 * @return int The number of rows to display.
 */
function get_panel_lines_count($height, $user_id) {
	$lines = intropage_get_lines($user_id);

	if (!is_numeric($lines)) {
		$lines = 5;
	} elseif ($height == 'double') {
		$lines *= 2;
	} elseif ($height == 'triple') {
		$lines *= 3;
	}

	return $lines;
}

/**
 * Reads a user's configured base panel line count (falling back to the
 * system default, then to 5 if unset/invalid). Called from
 * get_panel_lines_count() and intropage_favourite_graph() to determine
 * how many rows/what size to render.
 *
 * @param int $user_id The user id to resolve the setting for.
 *
 * @return int The resolved line count (at least 1, defaulting to 5).
 */
function intropage_get_lines($user_id) {
	$lines = read_user_setting('intropage_number_of_lines', read_config_option('intropage_number_of_lines'), false, $user_id);

	if (!is_numeric($lines) || $lines <= 0) {
		$lines = 5;
	}

	return $lines;
}
