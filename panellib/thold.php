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

function register_thold() {
	global $registry;

	$registry['thold'] = array(
		'name'        => __('Threshold Panels', 'intropage'),
		'description' => __('Panels that provide information about Cacti Thresholding Plugin.', 'intropage')
	);

	$panels = array(
		'thold_event' => array(
			'name'         => __('Last Threshold Events', 'intropage'),
			'description'  => __('Threshold Plugin Latest Events', 'intropage'),
			'class'        => 'thold',
			'level'        => PANEL_USER,
			'refresh'      => 300,
			'trefresh'     => false,
			'force'        => true,
			'width'        => 'half-panel',
			'priority'     => 77,
			'alarm'        => 'green',
			'requires'     => 'thold',
			'update_func'  => 'thold_event',
			'details_func' => 'thold_event_detail',
			'trends_func'  => false
		),
		'graph_thold' => array(
			'name'         => __('Threshold', 'intropage'),
			'description'  => __('Threshold Plugin Graph (all, trigerred, ...)', 'intropage'),
			'class'        => 'thold',
			'level'        => PANEL_USER,
			'refresh'      => 300,
			'trefresh'     => read_config_option('poller_interval'),
			'force'        => true,
			'width'        => 'quarter-panel',
			'priority'     => 18,
			'alarm'        => 'green',
			'requires'     => 'thold',
			'update_func'  => 'graph_thold',
			'details_func' => 'graph_thold_detail',
			'trends_func'  => 'thold_collect'
		),
	);

	return $panels;
}

//------------------------------------ thold event -----------------------------------------------------
function thold_event($panel, $user_id) {
	global $config;

	$lines = read_user_setting('intropage_number_of_lines', read_config_option('intropage_number_of_lines'), false, $user_id);
	$important_period = read_user_setting('intropage_important_period', read_config_option('intropage_important_period'), false, $user_id);
	if ($important_period == -1) {
		$important_period = time();
	}

	$panel['alarm'] = 'green';

	if (!api_plugin_is_enabled('thold')) {
		$panel['alarm'] = 'yellow';
		$panel['data']  = __('Plugin Thold isn\'t installed or started', 'intropage');
		$panel['detail'] = FALSE;
	} else {
		$allowed_devices = intropage_get_allowed_devices($user_id);

		if ($allowed_devices !== false) {
			$data = db_fetch_assoc('SELECT tl.description AS description,tl.time AS time,
				tl.status AS status, uap0.user_id AS user0, uap1.user_id AS user1, uap2.user_id AS user2
				FROM plugin_thold_log AS tl
				INNER JOIN thold_data AS td
				ON tl.threshold_id=td.id
				INNER JOIN graph_local AS gl
				ON gl.id=td.local_graph_id
				LEFT JOIN graph_templates AS gt
				ON gt.id=gl.graph_template_id
				LEFT JOIN graph_templates_graph AS gtg
				ON gtg.local_graph_id=gl.id
				LEFT JOIN host AS h
				ON h.id=gl.host_id
				LEFT JOIN user_auth_perms AS uap0
				ON (gl.id=uap0.item_id AND uap0.type=1)
				LEFT JOIN user_auth_perms AS uap1
				ON (gl.host_id=uap1.item_id AND uap1.type=3)
				LEFT JOIN user_auth_perms AS uap2
				ON (gl.graph_template_id=uap2.item_id AND uap2.type=4)
				WHERE td.host_id IN (' . $allowed_devices . ')
				HAVING (user0 IS NULL OR (user1 IS NULL OR user2 IS NULL))
				ORDER BY `time` DESC
				LIMIT ' . $lines);
		} else {
			$data = array();
		}

		if (cacti_sizeof($data)) {
			
			$panel['data'] .= '<table class="cactiTable inpa_fixed">';

			foreach ($data as $row) {
				$panel['data'] .= '<tr><td class="inpa_first inpa_loglines" title="' . html_escape($row['description']) . '">';
				// zkousim
				
				$panel['data'] .= date('y-m-d H:i:s', $row['time']);
				
				$color = 'grey';

				if ($row['time'] > (time()-($important_period))) { 
					if (preg_match('/(NORMAL)/i', $row['description'])) {
						$color = 'green';
					} elseif (preg_match('/(ALERT|ERROR)/i', $row['description'])) {
						$color = 'red';
					} elseif (preg_match('/(WARNING)/i', $row['description'])) {
						$color = 'yellow';
					}
				}

                                if ($panel['alarm'] == 'grey' && $color == 'green') {
                                        $panel['alarm'] = 'green';
                                }

                                if ($panel['alarm'] == 'green' && $color == 'yellow') {
                                        $panel['alarm'] = 'yellow';
                                }

                                if ($panel['alarm'] == 'yellow' && $color == 'red') {
                                        $panel['alarm'] = 'red';
                                }

				
				$panel['data'] .= '<span class="inpa_sq color_' . $color . '"></span>';
				
				$panel['data'] .= html_escape($row['description']);
				$panel['data'] .= '</td></tr>';

				if ($row['status'] == 1 || $row['status'] == 4 || $row['status'] == 7) {
					$panel['alarm'] = 'red';
				} elseif ($panel['alarm'] == 'green' && ($row['status'] == 2 || $row['status'] == 3)) {
					$panel['alarm'] == 'yellow';
				}
			}
			
			$panel['data'] .= '</table>';
		} else {
			$panel['data'] = __('Without events yet', 'intropage');
		}
	}

	save_panel_result($panel, $user_id);
}

//------------------------------------ graph_thold -----------------------------------------------------
function graph_thold($panel, $user_id, $timespan = 0) {
	global $config;

	$panel['alarm'] = 'green';

	include_once($config['base_path'] . '/plugins/thold/thold_functions.php');

	$graph = array (
        	'line' => array(
			'title'  => __('Thresholds: ', 'intropage'),
			'label1' => array(),
			'data1'  => array(),
			'label2' => array(),
			'data2'  => array(),
			'label3' => array(),
			'data3'  => array(),
		),
	);

	if (!api_plugin_is_enabled('thold')) {
		$panel['alarm'] = 'grey';
		$panel['data']  = __('Thold plugin not installed/running', 'intropage');
	} elseif (api_plugin_user_realm_auth('thold_graph.php')) {

		$first = true;
       
		if ($timespan == 0) {
                	if (isset($_SESSION['sess_user_id'])) {
				$timespan = read_user_setting('intropage_timespan', read_config_option('intropage_timespan'), $_SESSION['sess_user_id']);
			} else {
				$timespan = $panel['refresh'];
			}
		}

		if (!isset($panel['refresh_interval'])) {
			$refresh = db_fetch_cell_prepared('SELECT refresh_interval
				FROM plugin_intropage_panel_data
				WHERE id = ?',
				array($panel['id']));
		} else {
			$refresh = $panel['refresh'];
		}

		$rows = db_fetch_assoc_prepared("SELECT cur_timestamp AS `date`, value
			FROM plugin_intropage_trends
			WHERE cur_timestamp > date_sub(NOW(), INTERVAL ? SECOND)
			AND name = 'thold_trig'
			ORDER BY cur_timestamp ASC",
			array($timespan));

                if (cacti_sizeof($rows)) {
                
                        $graph['line']['title1'] = __('Trigerred', 'intropage');
                        $graph['line']['unit1']['title'] = 'Triggered';

			foreach ($rows as $row) {
				if ($first) {
					if ($row['value'] > 0) {
						$panel['alarm'] = 'red';
					}
					$first = false;
				}

				$graph['line']['label1'][] = $row['date'];
				$graph['line']['data1'][]  = $row['value'];
                        }
		} else {
			unset($graph['line']['label1']);
			unset($graph['line']['data1']);
		}
		
		$rows = db_fetch_assoc_prepared("SELECT cur_timestamp AS `date`, value
			FROM plugin_intropage_trends
			WHERE cur_timestamp > date_sub(NOW(), INTERVAL ? SECOND)
			AND name = 'thold_brea'
			ORDER BY cur_timestamp ASC",
			array($timespan));

                if (cacti_sizeof($rows)) {
                
                        $graph['line']['title2'] = __('Breached', 'intropage');
                        $graph['line']['unit2']['title'] = 'Breached';

			foreach ($rows as $row) {
				if ($first) {
					if ($row['value'] > 0) {
						$panel['alarm'] = 'yellow';
					}
					$first = false;
				}

				$graph['line']['label2'][] = $row['date'];
				$graph['line']['data2'][]  = $row['value'];
                        }
		} else {
			unset($graph['line']['label2']);
			unset($graph['line']['data2']);
		}

		$rows = db_fetch_assoc_prepared("SELECT cur_timestamp AS `date`, value
			FROM plugin_intropage_trends
			WHERE cur_timestamp > date_sub(NOW(), INTERVAL ? SECOND)
			AND name = 'thold_disa'
			ORDER BY cur_timestamp ASC",
			array($timespan));

                if (cacti_sizeof($rows)) {
                
                        $graph['line']['title3'] = __('Disabled', 'intropage');
                        $graph['line']['unit3']['title'] = 'Disabled';

			foreach ($rows as $row) {
				$graph['line']['label3'][] = $row['date'];
				$graph['line']['data3'][]  = $row['value'];
                        }
		} else {
			unset($graph['line']['label3']);
			unset($graph['line']['data3']);
		}

		if (isset($graph['line']['data1']) || isset($graph['line']['data2']) || isset($graph['line']['data3'])) {
	                $panel['data'] = intropage_prepare_graph($graph, $user_id);
                } else {
                        unset($graph);
                        $panel['data'] = __('Waiting for data', 'intropage');
		}
	} else {
		$panel['data'] = __('You don\'t have plugin permission', 'intropage');
	}

	save_panel_result($panel, $user_id);
}

//------------------------------------ graph_thold -----------------------------------------------------
function graph_thold_detail() {
	global $config, $sql_where;

	include_once($config['base_path'] . '/plugins/thold/thold_functions.php');

	$panel = array(
		'name'   => __('Threshold Details', 'intropage'),
		'alarm'  => 'green',
		'detail' => '',
	);

	if (!api_plugin_is_enabled('thold')) {
		$panel['alarm'] = 'grey';
		$panel['detail']  = __('Thold plugin not installed/running', 'intropage');
		unset($panel['pie']);
	} elseif (api_plugin_user_realm_auth('thold_graph.php')) {
		$t_all  = 0;
		$t_brea = 0;
		$t_trig = 0;
		$t_disa = 0;

		$sql_where = '';
		$x = get_allowed_thresholds($sql_where, 'null', 1, $t_all, $_SESSION['sess_user_id']);

		$sql_where     = "td.thold_enabled = 'on' AND ((td.thold_alert != 0 OR td.bl_alert > 0))";
		$t_brea_result = get_allowed_thresholds($sql_where, 'null', '', $t_brea, $_SESSION['sess_user_id']);

		$sql_where = "td.thold_enabled = 'on' AND (((td.thold_alert != 0 AND td.thold_fail_count >= td.thold_fail_trigger)
			OR (td.bl_alert > 0 AND td.bl_fail_count >= td.bl_fail_trigger)))";
		$t_trig_result = get_allowed_thresholds($sql_where, 'null', '', $t_trig, $_SESSION['sess_user_id']);

		$sql_where = "td.thold_enabled = 'off'";
		$x = get_allowed_thresholds($sql_where, 'null', 1, $t_disa, $_SESSION['sess_user_id']);

		$count = $t_all + $t_brea + $t_trig + $t_disa;

		$panel['detail'] = '<table class="cactiTable">';
		$panel['detail'] .= '<tr class="tableHeader"><th class="left">' . __('Status', 'intropage') . '</th><th class="right">' . __('Thresholds', 'intropage') . '</th></tr>';

		$url_suffix = '</a>';

		if (api_plugin_user_realm_auth('thold_graph.php')) {
			$url_prefix = '<a class="linkEditMain" href="' . html_escape($config['url_path'] . 'plugins/thold/thold_graph.php?tab=thold&triggered=%s') . '">';

			$panel['detail'] .= '<tr class="odd">
				<td class="left">'  . sprintf($url_prefix, '-1') . __('All', 'intropage') . '</a></td>
				<td class="right">' . number_format_i18n($t_all, -1) . '</td></tr>';

			$panel['detail'] .= '<tr class="even">
				<td class="left">'  . sprintf($url_prefix, '1') . __('Breached', 'intropage') . '</a><span class="inpa_sq color_yellow"></span></td>
				<td class="right">' . number_format_i18n($t_brea, -1) . '</td></tr>';

			$panel['detail'] .= '<tr class="odd">
				<td class="left">'  . sprintf($url_prefix, '3') . __('Triggered', 'intropage') . '</a><span class="inpa_sq color_red"></span></td>
				<td class="right">' . number_format_i18n($t_trig, -1) . '</td></tr>';

			$panel['detail'] .= '<tr class="even">
				<td class="left">'  . sprintf($url_prefix, '0') . __('Disabled', 'intropage') . '</a></td>
				<td class="right">' . number_format_i18n($t_disa, -1) . '</td></tr>';
		} else {
			$panel['detail'] .= '<tr class="odd">
				<td class="left">'  . __('All', 'intropage')         . '</td>
				<td class="right">' . number_format_i18n($t_all, -1) . '</td></tr>';

			$panel['detail'] .= '<tr class="even">
				<td class="left">'  . __('Breached', 'intropage')     . '</td>
				<td class="right"></span>' . number_format_i18n($t_brea, -1) . '<span class="inpa_sq color_yellow"></td></tr>';

			$panel['detail'] .= '<tr class="odd">
				<td class="left">'  . __('Triggered', 'intropage')    . '</td>
				<td class="right">' . number_format_i18n($t_trig, -1) . '<span class="inpa_sq color_red"></span></td></tr>';

			$panel['detail'] .= '<tr class="even">
				<td class="left">'  . __('Disabled', 'intropage')     . '</td>
				<td class="right">' . number_format_i18n($t_disa, -1) . '</td></tr>';
		}

		$panel['detail'] .= '</table>';

		// alarms and details
		if ($t_brea > 0) {
			$panel['alarm']   = 'yellow';
			$panel['detail'] .= '<b>' . __('Breached: ', 'intropage') . '</b><br/>';

			foreach ($t_brea_result as $host) {
				$panel['detail'] .= html_escape($host['name_cache']) . '<br/>';
			}

			$panel['detail'] .= '<br/><br/>';
		}

		if ($t_trig > 0) {
			$panel['alarm']   = 'red';
			$panel['detail'] .= '<b>' . __('Triggered: ', 'intropage') .'</b><br/>';

			foreach ($t_trig_result as $host) {
				$panel['detail'] .= html_escape($host['name_cache']) . '<br/>';
			}
			$panel['detail'] .= '<br/><br/>';
		}
	} else {
		$panel['detail'] = __('You don\'t have permission to Thresholds', 'intropage');
	}

	return $panel;
}

//------------------------------------ thold_events -----------------------------------------------------
function thold_event_detail() {
	global $config;

	include_once($config['base_path'] . '/plugins/thold/thold_functions.php');

	$panel = array(
		'name'   => __('Last Threshold Events', 'intropage'),
		'alarm'  => 'green',
		'detail' => '',
	);

	if (!api_plugin_is_enabled('thold')) {
		$panel['alarm']  = 'yellow';
		$panel['detail'] = __('Plugin Thold isn\'t installed or started', 'intropage');
	} else {
		$allowed_devices = intropage_get_allowed_devices($_SESSION['sess_user_id']);

		$data = db_fetch_assoc('SELECT tl.description as description,tl.time as time,
			tl.status as status, uap0.user_id AS user0, uap1.user_id AS user1, uap2.user_id AS user2
			FROM plugin_thold_log AS tl
			INNER JOIN thold_data AS td
			ON tl.threshold_id=td.id
			INNER JOIN graph_local AS gl
			ON gl.id=td.local_graph_id
			LEFT JOIN graph_templates AS gt
			ON gt.id=gl.graph_template_id
			LEFT JOIN graph_templates_graph AS gtg
			ON gtg.local_graph_id=gl.id
			LEFT JOIN host AS h
			ON h.id=gl.host_id
			LEFT JOIN user_auth_perms AS uap0
			ON (gl.id=uap0.item_id AND uap0.type=1)
			LEFT JOIN user_auth_perms AS uap1
			ON (gl.host_id=uap1.item_id AND uap1.type=3)
			LEFT JOIN user_auth_perms AS uap2
			ON (gl.graph_template_id=uap2.item_id AND uap2.type=4)
			WHERE td.host_id in (' . $allowed_devices . ')
			HAVING (user0 IS NULL OR (user1 IS NULL OR user2 IS NULL))
			ORDER BY `time` DESC
			LIMIT 30');

		if (cacti_sizeof($data)) {
			$panel['detail'] .= '<table class="cactiTable">';
			$panel['detail'] .= '<tr class="tableHeader"><th class="left">' . __('Description', 'intropage') . '</th>
				<th class="right">' . __('Date', 'intropage') . '</th></tr>';

			$i = 0;
			foreach ($data as $row) {
				$class = ($i % 2 == 0 ? 'odd':'even');
				$panel['detail'] .= '<tr class="' . $class . '">
					<td class="left">'  . html_escape($row['description'])  . '</td>
					<td class="right">' . date('Y-m-d H:i:s', $row['time']) . '</td>
				</tr>';

				if ($row['status'] == 1 || $row['status'] == 4 || $row['status'] == 7) {
					$panel['alarm'] = 'red';
				} elseif ($panel['alarm'] == 'green' && ($row['status'] == 2 || $row['status'] == 3)) {
					$panel['alarm'] == 'yellow';
				}

				$i++;
			}

			$panel['detail'] .= '</table>';
		} else {
			$panel['detail'] = __('Without events yet', 'intropage');
		}
	}

	return $panel;
}

function thold_collect() {
	global $config;

	// update in poller
	$users = get_user_list();

	include_once($config['base_path'] . '/plugins/thold/thold_functions.php');

	foreach ($users as $user) {
		$t_brea = 0;
		$t_trig = 0;
		$t_disa = 0;

		$allowed_devices = intropage_get_allowed_devices($user['id']);

		if ($allowed_devices !== false) {

			$x      = '';
			$sql_where = '';

			$sql_where = "td.thold_enabled = 'on' AND ((td.thold_alert != 0 OR td.bl_alert > 0))";
			$x = get_allowed_thresholds($sql_where, 'null', 1, $t_brea, $user['id']);

			$sql_where = "td.thold_enabled = 'off'";
			$x = get_allowed_thresholds($sql_where, 'null', 1, $t_disa, $user['id']);

			$sql_where = "td.thold_enabled = 'on'
				AND (((td.thold_alert != 0
				AND td.thold_fail_count >= td.thold_fail_trigger)
				OR (td.bl_alert > 0 AND td.bl_fail_count >= td.bl_fail_trigger)))";

			$x = get_allowed_thresholds($sql_where, 'null', 1, $t_trig, $user['id']);

			db_execute_prepared("REPLACE INTO plugin_intropage_trends
				(name,value,user_id)
				VALUES ('thold_brea', ?, ?)",
				array($t_brea, $user['id']));

			db_execute_prepared("REPLACE INTO plugin_intropage_trends
				(name,value,user_id)
				VALUES ('thold_disa', ?, ?)",
				array($t_disa, $user['id']));

			db_execute_prepared("REPLACE INTO plugin_intropage_trends
				(name,value,user_id)
				VALUES ('thold_trig', ?, ?)",
				array($t_trig, $user['id']));

		} else {
			db_execute_prepared("REPLACE INTO plugin_intropage_trends
				(name,value,user_id)
				VALUES ('thold_brea', 0, ?)",
				array($user['id']));

			db_execute_prepared("REPLACE INTO plugin_intropage_trends
				(name,value,user_id)
				VALUES ('thold_disa', 0, ?)",
				array($user['id']));

			db_execute_prepared("REPLACE INTO plugin_intropage_trends
				(name,value,user_id)
				VALUES ('thold_trig', 0, ?)",
				array($user['id']));
		}
	}
}

