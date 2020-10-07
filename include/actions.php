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

if (isset_request_var('intropage_addpanel') &&
	get_filter_request_var('intropage_addpanel', FILTER_VALIDATE_REGEXP, array('options' => array('regexp' => '/^([a-z0-9_-]+)$/')))) {
		db_execute_prepared('INSERT INTO plugin_intropage_panel_dashboard (panel_id,user_id,dashboard_id)
			VALUES ( ?, ?, ?)',
			array(get_request_var('intropage_addpanel'),$_SESSION['sess_user_id'],$_SESSION['dashboard_id']));
}

if (isset_request_var('intropage_action') &&
	get_filter_request_var('intropage_action', FILTER_VALIDATE_REGEXP, array('options' => array('regexp' => '/^([a-z0-9_-]+)$/')))) {
	$values = explode('_', get_request_var('intropage_action'));
	// few parameters from input type select has format reset_all, refresh_180, ... first is action
	$action = array_shift($values);
	$value  = implode('_', $values);

	switch ($action) {
	case 'droppanel':
		if (get_filter_request_var('panel_id')) {
			db_execute_prepared('DELETE FROM plugin_intropage_panel_dashboard
				WHERE user_id = ? AND panel_id = ?',
				array($_SESSION['sess_user_id'], get_request_var('panel_id')));
		}
		break;

	case 'removepage':
		if (filter_var($value, FILTER_VALIDATE_INT))	{
			db_execute_prepared('DELETE FROM plugin_intropage_panel_dashboard
				WHERE user_id = ? AND dashboard_id = ?',
				array($_SESSION['sess_user_id'], $value));
			 set_user_setting('intropage_number_of_dashboards',read_user_setting('intropage_number_of_dashboards')-1);

			$_SESSION['dashboard_id'] = 1;
		}
		break;

	case 'addpage':
		if (filter_var($value, FILTER_VALIDATE_INT))	{
			 $x = read_user_setting('intropage_number_of_dashboards') + 1;
			 set_user_setting('intropage_number_of_dashboards',$x);
			 $_SESSION['dashboard_id'] = $x;
		}
		break;

	case 'favgraph':
		if (get_filter_request_var('graph_id')) {
			// already fav?
			if (db_fetch_cell_prepared('SELECT COUNT(*) FROM plugin_intropage_panel_data WHERE user_id= ?
					AND fav_graph_id= ? AND fav_graph_timespan= ?',
					array($_SESSION['sess_user_id'],get_request_var('graph_id'),$_SESSION['sess_current_timespan'])
					) > 0) {
				db_execute_prepared('DELETE FROM plugin_intropage_panel_data
					WHERE user_id= ? AND fav_graph_id= ? AND fav_graph_timespan= ?',
					array($_SESSION['sess_user_id'],get_request_var('graph_id'),$_SESSION['sess_current_timespan']));
			} else { // add to fav
				$prio = db_fetch_cell_prepared('SELECT max(priority)+1
					FROM plugin_intropage_panel_data
					WHERE user_id = ?',
					array($_SESSION['sess_user_id']));

				db_execute_prepared('REPLACE INTO plugin_intropage_panel_data
					(user_id, panel_id, fav_graph_id, fav_graph_timespan, priority)
					VALUES (?, "favourite_graph", ?, ?, ?)',
					array($_SESSION['sess_user_id'],get_request_var('graph_id'),$_SESSION['sess_current_timespan'], $prio));

				$id = db_fetch_insert_id();
				db_execute_prepared('INSERT INTO plugin_intropage_panel_dashboard
					(panel_id, user_id, dashboard_id) VALUES ( ?, ?, ?)',
					array($id, $_SESSION['sess_user_id'], $_SESSION['dashboard_id']));
			}
		}
		break;

	// panel order
	case 'order':
		if (isset_request_var('xdata')) {
			$error = false;
			$order = array();
			$priority = 90; // >90 are fav. graphs

			foreach (get_request_var('xdata') as $data) {
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

	case 'addpanel':
		if (preg_match('/^[a-z0-9\-\_]+$/i', $value)) {
			db_execute_prepared('UPDATE plugin_intropage_panel_data
				SET dashboard_id = ?
				WHERE user_id = ?
				AND panel_id = ?',
				array($_SESSION['dashboard_id'], $_SESSION['sess_user_id'], $value));
		}
		break;

	case 'refresh':
		if (filter_var($value, FILTER_VALIDATE_INT))	{
			set_user_setting('intropage_autorefresh', $value);
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
	}
}

