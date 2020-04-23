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

if (isset_request_var('intropage_action') &&
	get_filter_request_var('intropage_action', FILTER_VALIDATE_REGEXP, array('options' => array('regexp' => '/^([a-z0-9_-]+)$/')))) {
	$values = explode('_', get_request_var('intropage_action'));
	// few parameters from input type select has format reset_all, refresh_180, ... first is action
	$action = array_shift($values);
//!!!! proc je tu implode?
	$value  = implode('_', $values);

	switch ($action) {

	// close panel
	case 'droppanel':
		if (get_filter_request_var('panel_id')) {
			db_execute_prepared('DELETE FROM plugin_intropage_user_setting
				WHERE user_id = ? AND id = ?',
				array($_SESSION['sess_user_id'], get_request_var('panel_id')));
		}
		break;

	// remove dashboard - only set panels to dashboard = 0
	case 'removepage':
		if (filter_var($value, FILTER_VALIDATE_INT))	{
			db_execute_prepared('UPDATE plugin_intropage_panel_data SET dashboard_id=0
				WHERE user_id = ? AND dashboard_id = ?',
				array($_SESSION['sess_user_id'], $value));
			 $x = read_user_setting('intropage_number_of_dashboards') - 1;
			 set_user_setting('intropage_number_of_dashboards',$x);
			
			$_SESSION['dashboard_id'] = 1;

		}
		break;

	case 'addpage':
		if (filter_var($value, FILTER_VALIDATE_INT))	{
			db_execute_prepared('UPDATE plugin_intropage_panel_data SET dashboard_id=0
				WHERE user_id = ? AND dashboard_id = ?',
				array($_SESSION['sess_user_id'], $value));

			 $x = read_user_setting('intropage_number_of_dashboards') + 1;
			 set_user_setting('intropage_number_of_dashboards',$x);

			 $_SESSION['dashboard_id'] = $x;


		}
		break;


	// favourite graphs
	case 'favgraph':
		if (get_filter_request_var('graph_id')) {
			// already fav?
			//!!!! tady pak pribude jeste test na casovy rozsah
			if (db_fetch_cell('SELECT COUNT(*) FROM plugin_intropage_panel_data WHERE user_id=' . $_SESSION['sess_user_id'] .
					' AND fav_graph_id=' . get_request_var('graph_id')) > 0) {
				db_execute('DELETE FROM plugin_intropage_user_setting WHERE user_id=' . $_SESSION['sess_user_id'] . ' and fav_graph_id=' .  get_request_var('graph_id'));
			} else { // add to fav
				// priority for new panel:
				$prio = db_fetch_cell('SELECT max(priority)+1 FROM plugin_intropage_panel_data 
					WHERE user_id=' . $_SESSION['sess_user_id']);

				db_execute_prepared('REPLACE INTO plugin_intropage_panel_data
					(user_id, priority, panel_id, fav_graph_id)
					VALUES (?, ?, ?, ?)',
					array(
						$_SESSION['sess_user_id'],
						$prio,
						'favourite_graph',
						get_request_var('graph_id')
					)
				);
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
            				    SET priority=? WHERE user_id=? and id=?',
            				    array ($priority, $_SESSION['sess_user_id'], $b));
            				    
            				    $priority--;
				}
			}
		}
		break;

/*
	// reset all panels
	case 'reset':
		if ($value == 'all') {
			db_execute_prepared('DELETE FROM plugin_intropage_user_setting
				WHERE user_id = ?',
				array($_SESSION['sess_user_id']));

			// default values
			set_user_setting('intropage_display_important_first', read_config_option('intropage_display_important_first'));
			set_user_setting('intropage_autorefresh', read_config_option('intropage_autorefresh'));
		}
		break;
*/
	case 'addpanel':
		if (preg_match('/^[a-z0-9\-\_]+$/i', $value)) {
			db_execute('update plugin_intropage_panel_data set dashboard_id=' . $_SESSION['dashboard_id'] . 'WHERE 
				user_id=' . $_SESSION['sess_user_id'] . ' and panel_id =' . $value);
		}
		break;

	case 'refresh':
		if (filter_var($value, FILTER_VALIDATE_INT))	{
			set_user_setting('intropage_autorefresh', $value);
		}
		break;

//!!! tohle je asi mrtve
	case 'debug':
		if ($value == 'ena') {
			set_user_setting('intropage_debug', 1);
		}
		if ($value == 'disa') {
			set_user_setting('intropage_debug', 0);
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
		}
		elseif ($value == 'console') {
			db_fetch_cell_prepared('UPDATE user_auth SET login_opts = 2 WHERE id = ?', array($_SESSION['sess_user_id']));
		}
		elseif ($value == 'tab') { 
                       db_fetch_cell_prepared('UPDATE user_auth SET login_opts = 4 WHERE id = ?', array($_SESSION['sess_user_id']));
                }

	}
}
