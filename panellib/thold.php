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
			'trefresh'     => false,
			'force'        => true,
			'width'        => 'quarter-panel',
			'priority'     => 18,
			'alarm'        => 'green',
			'requires'     => 'thold',
			'update_func'  => 'graph_thold',
			'details_func' => 'graph_thold_detail',
			'trends_func'  => false
		),
	);

	return $panels;
}

//------------------------------------ thold event -----------------------------------------------------
function thold_event($panel, $user_id) {
	global $config;

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
				LIMIT 10');
		} else {
			$data = array();
		}

		if (cacti_sizeof($data)) {
			$panel['data'] .= '<table class="tableRow">';

			foreach ($data as $row) {
				$panel['data'] .= '<tr><td style="white-space:pre">';
				$panel['data'] .= date('Y-m-d H:i:s', $row['time']) . ' - ' . html_escape($row['description']);
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
function graph_thold($panel, $user_id) {
	global $config;

	$panel['alarm'] = 'green';

	include_once($config['base_path'] . '/plugins/thold/thold_functions.php');

	if (!api_plugin_is_enabled('thold')) {
		$panel['alarm'] = 'grey';
		$panel['data']  = __('Thold plugin not installed/running', 'intropage');
	} elseif (api_plugin_user_realm_auth('thold_graph.php')) {
		$t_all  = 0;
		$t_brea = 0;
		$t_trig = 0;
		$t_disa = 0;

		$sql_where = '';

		$x = '';
		$x = get_allowed_thresholds($sql_where, 'null', 1, $t_all, $user_id);

		$sql_where = "td.thold_enabled = 'on' AND ((td.thold_alert != 0 OR td.bl_alert > 0))";
		$x = get_allowed_thresholds($sql_where, 'null', 1, $t_brea, $user_id);

		$sql_where = "td.thold_enabled = 'on' AND (((td.thold_alert != 0 AND td.thold_fail_count >= td.thold_fail_trigger)
			OR (td.bl_alert > 0 AND td.bl_fail_count >= td.bl_fail_trigger)))";
		$x = get_allowed_thresholds($sql_where, 'null', 1, $t_trig, $user_id);

		$sql_where = "td.thold_enabled = 'off'";
		$x = get_allowed_thresholds($sql_where, 'null', 1, $t_disa, $user_id);

		$url_prefix = '<a class="pic" href="' . html_escape($config['url_path'] . 'plugins/thold/thold_graph.php?tab=thold&triggered=%s') . '">';
		$url_suffix = '</a>';

		$panel['data']  = sprintf($url_prefix, '-1') . __('All', 'intropage')      . ": $t_all$url_suffix<br/>";
		$panel['data'] .= sprintf($url_prefix, '1')  . __('Breached', 'intropage') . ": $t_brea$url_suffix<br/>";
		$panel['data'] .= sprintf($url_prefix, '3')  . __('Trigged', 'intropage')  . ": $t_trig$url_suffix<br/>";
		$panel['data'] .= sprintf($url_prefix, '0')  . __('Disabled', 'intropage') . ": $t_disa$url_suffix<br/>";

		if ($t_all > 0) {
			$graph = array ('pie' => array(
				'title' => $panel['name'],
				'label' => array(
					__('OK', 'intropage'),
					__('Triggered', 'intropage'),
					__('Breached', 'intropage'),
					__('Disabled', 'intropage'),
				),
				'data' => array($t_all - $t_brea - $t_trig - $t_disa, $t_trig, $t_brea, $t_disa))
			);

			$panel['data'] = intropage_prepare_graph($graph);

			// alarms and details
			if ($t_brea > 0) {
				$panel['alarm'] = 'yellow';
			}

			if ($t_trig > 0) {
				$panel['alarm'] = 'red';
			}
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
				<td class="left">'  . sprintf($url_prefix, '1') . __('Breached', 'intropage') . '</a></td>
				<td class="right">' . number_format_i18n($t_brea, -1) . '</td></tr>';

			$panel['detail'] .= '<tr class="odd">
				<td class="left">'  . sprintf($url_prefix, '3') . __('Triggered', 'intropage') . '</a></td>
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
				<td class="right">' . number_format_i18n($t_brea, -1) . '</td></tr>';

			$panel['detail'] .= '<tr class="odd">
				<td class="left">'  . __('Triggered', 'intropage')    . '</td>
				<td class="right">' . number_format_i18n($t_trig, -1) . '</td></tr>';

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

