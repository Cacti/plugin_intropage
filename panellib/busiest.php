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

function register_busiest() {
	global $registry;

	$registry['busiest'] = array(
		'name'        => __('The busiest', 'intropage'),
		'description' => __('Panels that finds the busiest hosts.', 'intropage')
	);

	$panels = array(
		'busiest_cpu' => array(
			'name'         => __('Busiest CPU', 'intropage'),
			'description'  => __('Devices with the busiest CPU (Host MIB)', 'intropage'),
			'class'        => 'busiest',
			'level'        => PANEL_USER,
			'refresh'      => 300,
			'trefresh'     => false,
			'force'        => true,
			'width'        => 'quarter-panel',
			'priority'     => 69,
			'alarm'        => 'grey',
			'requires'     => false,
			'update_func'  => 'busiest_cpu',
			'details_func' => 'busiest_cpu_detail',
			'trends_func'  => false
		),
		'busiest_load' => array(
			'name'         => __('Busiest ucd/net - Load', 'intropage'),
			'description'  => __('Devices with the highest Load (ucd/net)', 'intropage'),
			'class'        => 'busiest',
			'level'        => PANEL_USER,
			'refresh'      => 300,
			'trefresh'     => false,
			'force'        => true,
			'width'        => 'quarter-panel',
			'priority'     => 69,
			'alarm'        => 'grey',
			'requires'     => false,
			'update_func'  => 'busiest_load',
			'details_func' => 'busiest_load_detail',
			'trends_func'  => false
		),
		'busiest_hdd' => array(
			'name'         => __('Busiest Hard Drive Space', 'intropage'),
			'description'  => __('Devices with the highest Hard Drive Space used (Host MIB)', 'intropage'),
			'class'        => 'busiest',
			'level'        => PANEL_USER,
			'refresh'      => 300,
			'trefresh'     => false,
			'force'        => true,
			'width'        => 'quarter-panel',
			'priority'     => 69,
			'alarm'        => 'grey',
			'requires'     => false,
			'update_func'  => 'busiest_hdd',
			'details_func' => 'busiest_hdd_detail',
			'trends_func'  => false
		),
		'busiest_uptime' => array(
			'name'         => __('Busiest uptime', 'intropage'),
			'description'  => __('Devices with the highest uptime', 'intropage'),
			'class'        => 'busiest',
			'level'        => PANEL_USER,
			'refresh'      => 300,
			'trefresh'     => false,
			'force'        => true,
			'width'        => 'quarter-panel',
			'priority'     => 69,
			'alarm'        => 'grey',
			'requires'     => false,
			'update_func'  => 'busiest_uptime',
			'details_func' => 'busiest_uptime_detail',
			'trends_func'  => false
		),
		'busiest_traffic' => array(
			'name'         => __('Busiest Interface in/out traffic', 'intropage'),
			'description'  => __('Devices with the highest in/out traffic (Interface)', 'intropage'),
			'class'        => 'busiest',
			'level'        => PANEL_USER,
			'refresh'      => 300,
			'trefresh'     => false,
			'force'        => true,
			'width'        => 'quarter-panel',
			'priority'     => 69,
			'alarm'        => 'grey',
			'requires'     => false,
			'update_func'  => 'busiest_traffic',
			'details_func' => 'busiest_traffic_detail',
			'trends_func'  => false
		),
		'busiest_interface_error' => array(
			'name'         => __('Busiest Interface error', 'intropage'),
			'description'  => __('Devices with the highest errors/discards (Interface)', 'intropage'),
			'class'        => 'busiest',
			'level'        => PANEL_USER,
			'refresh'      => 300,
			'trefresh'     => false,
			'force'        => true,
			'width'        => 'quarter-panel',
			'priority'     => 69,
			'alarm'        => 'grey',
			'requires'     => false,
			'update_func'  => 'busiest_interface_error',
			'details_func' => 'busiest_interface_error_detail',
			'trends_func'  => false
		),
		'busiest_interface_utilization' => array(
			'name'         => __('Busiest Interface utilization', 'intropage'),
			'description'  => __('Ports with the highest interface utilization', 'intropage'),
			'class'        => 'busiest',
			'level'        => PANEL_USER,
			'refresh'      => 300,
			'trefresh'     => false,
			'force'        => true,
			'width'        => 'quarter-panel',
			'priority'     => 68,
			'alarm'        => 'grey',
			'requires'     => false,
			'update_func'  => 'busiest_interface_util',
			'details_func' => 'busiest_interface_util_detail',
			'trends_func'  => false
		),

	);

	return $panels;
}

//------------------------------------ busiest cpu -----------------------------------------------------
function busiest_cpu($panel, $user_id) {
	global $config;

	$lines = read_user_setting('intropage_number_of_lines', read_config_option('intropage_number_of_lines'), false, $user_id);

	$panel['alarm'] = 'grey';

	$console_access = get_console_access($user_id);

	if (read_config_option('dsstats_enable') != 'on') {
		$panel['data'] = __('Panel needs DS stats enabled.', 'intropage') . '<br/>';

		if ($console_access) {
			$panel['data'] .=  '<a class="pic" href="' . $config['url_path'] .'settings.php?tab=data">' . __('Please enable and configure DS stats', 'intropage') . '</a>';
		} else {
			$panel['data'] .=  __('Ask admin to enable DS stats', 'intropage') . '</a>';
		}

		save_panel_result($panel, $user_id);
	}

	$allowed_devices = intropage_get_allowed_devices($user_id);

	$ds = db_fetch_row("SELECT id,name
		FROM data_template
		WHERE hash='f6e7d21c19434666bbdac00ccef9932f'");

	if ($allowed_devices && $ds) {

		$columns = " t1.local_data_id AS ldid, concat(t1.name_cache,' - ', t2.rrd_name) AS name, t2.average AS xvalue, t2.peak AS xpeak ";

		$query = ' FROM data_template_data AS t1
			LEFT JOIN data_source_stats_hourly AS t2 ON t1.local_data_id = t2.local_data_id
			LEFT JOIN data_local AS t3 on t3.id=t1.local_data_id
			WHERE t3.host_id IN (' . $allowed_devices . ') AND
			t2.average IS NOT  NULL AND
			t1.data_template_id = ' . $ds['id'] . '
			ORDER BY t2.average DESC
			LIMIT ' . $lines;

		$avg = db_fetch_cell ('SELECT avg(average)' . $query);
		$result = db_fetch_assoc("SELECT $columns $query");

		if (cacti_sizeof($result)) {
			$panel['data'] = '<table class="cactiTable inpa_fixed">' .

				'<tr class="tableHeader">' .
					'<th class="left inpa_first">'  . $ds['name'] . '</th>' .
					'<th class="right">' . __('Average', 'intropage') . '</th>' .
					'<th class="right">' . __('Peak', 'intropage') . '</th>' .
				'</tr>';

			$i = 0;

			foreach ($result as $row) {
				$graph_id = db_fetch_cell_prepared('SELECT DISTINCT(local_graph_id) FROM graph_templates_item
					LEFT JOIN data_template_rrd ON (graph_templates_item.task_item_id=data_template_rrd.id)
					LEFT JOIN data_local ON (data_template_rrd.local_data_id=data_local.id)
					LEFT JOIN data_template_data ON (data_local.id=data_template_data.local_data_id)
					WHERE data_template_data.local_data_id = ?',
					array($row['ldid']));

				$panel['data'] .= '<tr class="' . ($i % 2 == 0 ? 'even':'odd') . '">';
				$panel['data'] .= '<td class="left inpa_loglines" title="' . $row['name'] . '"><i class="fas fa-chart-area bus_graph" bus_id="' . $graph_id . '"></i>' . html_escape($row['name']) . '</td>';

				$panel['data'] .= "<td class='right intropage_1'>" . round($row['xvalue'], 2) . ' %</td>';
				$panel['data'] .= "<td class='right intropage_1'>" . round($row['xpeak'], 2) . ' %</td></tr>';

				if ($row['xvalue'] > 100 || $row['xpeak'] > 100) {
					$host_id = db_fetch_cell_prepared('SELECT host_id
						FROM data_local
						WHERE id = ?',
						array($row['ldid']));

					cacti_log("WARNING: Problem with DSSTAT data for Device[$host_id] and DS[{$row['ldid']}.  Please investigate or clear DSSTAT data.", false, 'INTROPAGE');
				}

				$i++;
			}

			$panel['data'] .= '<tr><td colspan="2">' . __('Average of all allowed DS') . '</td><td>' . round($avg, 2) . ' %</td></tr>';
			$panel['data'] .= '</table>';

		} else {
			$panel['data'] = __('Waiting for data or you don\'t have permission for any device with this template.', 'intropage');
		}

	} else {
		$panel['data'] = __('You don\'t have permissions to any hosts or there isn\'t any host with this template', 'intropage');
	}

	save_panel_result($panel, $user_id);
}


//------------------------------------ busiest load -----------------------------------------------------
function busiest_load($panel, $user_id) {
	global $config;

	$lines = read_user_setting('intropage_number_of_lines', read_config_option('intropage_number_of_lines'), false, $user_id);

	$panel['alarm'] = 'grey';

	$console_access = get_console_access($user_id);

	if (read_config_option('dsstats_enable') != 'on') {
		$panel['data'] = __('Panel needs DS stats enabled.', 'intropage') . '<br/>';

		if ($console_access) {
			$panel['data'] .=  '<a class="pic" href="' . $config['url_path'] .'settings.php?tab=data">' . __('Please enable and configure DS stats', 'intropage') . '</a>';
		} else {
			$panel['data'] .=  __('Ask admin to enable DS stats', 'intropage') . '</a>';
		}

		save_panel_result($panel, $user_id);
	}

	$allowed_devices = intropage_get_allowed_devices($user_id);

	$ds = db_fetch_row("SELECT id,name
		FROM data_template
		WHERE hash='9b82d44eb563027659683765f92c9757'");

	if ($allowed_devices && $ds) {

		$columns = " t1.local_data_id AS ldid, concat(t1.name_cache,' - ', t2.rrd_name) AS name, t2.average AS xvalue, t2.peak AS xpeak ";

		$query = ' FROM data_template_data AS t1
			LEFT JOIN data_source_stats_hourly AS t2 ON t1.local_data_id = t2.local_data_id
			LEFT JOIN data_local AS t3 on t3.id=t1.local_data_id
			WHERE t3.host_id IN (' . $allowed_devices . ') AND
			t2.average IS NOT  NULL AND
			t1.data_template_id = ' . $ds['id'] . '
			ORDER BY t2.average DESC
			LIMIT ' . $lines;

		$avg = db_fetch_cell ('SELECT avg(average)' . $query);
		$result = db_fetch_assoc("SELECT $columns $query");

		if (cacti_sizeof($result)) {

			$panel['data'] = '<table class="cactiTable inpa_fixed">' .
				'<tr class="tableHeader">' .
					'<th class="left inpa_first">'  . $ds['name'] . '</th>' .
					'<th class="right">' . __('Average', 'intropage') . '</th>' .
					'<th class="right">' . __('Peak', 'intropage') . '</th>' .
				'</tr>';

			$i = 0;

			foreach ($result as $row) {

				$graph_id = db_fetch_cell ('SELECT DISTINCT(local_graph_id) FROM graph_templates_item
					LEFT JOIN data_template_rrd ON (graph_templates_item.task_item_id=data_template_rrd.id)
					LEFT JOIN data_local ON (data_template_rrd.local_data_id=data_local.id)
					LEFT JOIN data_template_data ON (data_local.id=data_template_data.local_data_id)
					WHERE data_template_data.local_data_id=' . $row['ldid']);

				$panel['data'] .= '<tr class="' . ($i % 2 == 0 ? 'even':'odd') . '">';
				$panel['data'] .= '<td class="left inpa_loglines" title="' . $row['name'] . '"><i class="fas fa-chart-area bus_graph" bus_id="' . $graph_id . '"></i>' . html_escape($row['name']) . '</td>';

				$panel['data'] .= "<td class='right'>" . round($row['xvalue'], 2) . '</td>';
				$panel['data'] .= "<td class='right'>" . round($row['xpeak'], 2) . '</td></tr>';

				$i++;
			}

			$panel['data'] .= '<tr><td colspan="2">' . __('Average of all allowed DS') . '</td><td>' . round($avg, 2) . '</td></tr>';
			$panel['data'] .= '</table>';

		} else {
			$panel['data'] = __('Waiting for data or you don\'t have permission for any device with this template.', 'intropage');
		}

	} else {
		$panel['data'] = __('You don\'t have permissions to any hosts or there isn\'t any host with this template', 'intropage');
	}

	save_panel_result($panel, $user_id);
}


//------------------------------------ busiest_hdd  -----------------------------------------------------
function busiest_hdd($panel, $user_id) {
	global $config;

	$lines = read_user_setting('intropage_number_of_lines', read_config_option('intropage_number_of_lines'), false, $user_id);

	$panel['alarm'] = 'grey';

	$console_access = get_console_access($user_id);

	if (read_config_option('dsstats_enable') != 'on') {
		$panel['data'] = __('Panel needs DS stats enabled.', 'intropage') . '<br/>';

		if ($console_access) {
			$panel['data'] .=  '<a class="pic" href="' . $config['url_path'] .'settings.php?tab=data">' . __('Please enable and configure DS stats', 'intropage') . '</a>';
		} else {
			$panel['data'] .=  __('Ask admin to enable DS stats', 'intropage') . '</a>';
		}

		save_panel_result($panel, $user_id);
	}

	$allowed_devices = intropage_get_allowed_devices($user_id);

	$ds = db_fetch_row("SELECT id,name
		FROM data_template
		WHERE hash='d814fa3b79bd0f8933b6e0834d3f16d0'");

	if ($allowed_devices && $ds) {

		$columns = " name_cache AS name, t2.local_data_id AS ldid,
			100*average/(SELECT average FROM data_source_stats_hourly WHERE local_data_id = ldid AND rrd_name='hdd_total' ) AS xvalue,
			100*peak/(SELECT peak FROM data_source_stats_hourly WHERE local_data_id = ldid AND rrd_name='hdd_total') AS xpeak ";

		$query = ' FROM data_template_data AS t1
			LEFT JOIN data_source_stats_hourly AS t2 ON t1.local_data_id = t2.local_data_id
			LEFT JOIN data_local AS t3 on t3.id=t1.local_data_id
			WHERE t3.host_id IN (' . $allowed_devices . ') AND
			t2.rrd_name=\'hdd_used\' AND
			t1.data_template_id = ' . $ds['id'] . '
			ORDER BY xvalue DESC
			LIMIT ' . $lines;

		$result = db_fetch_assoc("SELECT $columns $query");

		// avg
		$columns = " t1.local_data_id AS ldid,100*average/(SELECT average FROM data_source_stats_hourly WHERE local_data_id = ldid AND rrd_name='hdd_total' ) AS xvalue ";

		$query = ' FROM data_template_data AS t1
			LEFT JOIN data_source_stats_hourly AS t2 ON t1.local_data_id = t2.local_data_id
			LEFT JOIN data_local AS t3 on t3.id=t1.local_data_id
			WHERE t3.host_id IN (' . $allowed_devices . ') AND
			t2.rrd_name=\'hdd_used\' AND
			t1.data_template_id = ' . $ds['id'] . '
			AND t2.rrd_name=\'hdd_used\'';

		$xavg = db_fetch_assoc ('SELECT ' . $columns . ' ' . $query);
		$avg = 0;

		if ($xavg) {
			foreach ($xavg as $row) {
				$avg+=$row['xvalue'];
			}
			$avg = $avg/count($xavg);
		}

		if (cacti_sizeof($result)) {
			$panel['data'] = '<table class="cactiTable inpa_fixed">' .
				'<tr class="tableHeader">' .
					'<th class="left inpa_first">'  . $ds['name'] . '</th>' .
					'<th class="right">' . __('Average', 'intropage') . '</th>' .
					'<th class="right">' . __('Peak', 'intropage') . '</th>' .
				'</tr>';

			$i = 0;

			foreach ($result as $row) {

				$graph_id = db_fetch_cell ('SELECT DISTINCT(local_graph_id) FROM graph_templates_item
					LEFT JOIN data_template_rrd ON (graph_templates_item.task_item_id=data_template_rrd.id)
					LEFT JOIN data_local ON (data_template_rrd.local_data_id=data_local.id)
					LEFT JOIN data_template_data ON (data_local.id=data_template_data.local_data_id)
					WHERE data_template_data.local_data_id=' . $row['ldid']);

				$panel['data'] .= '<tr class="' . ($i % 2 == 0 ? 'even':'odd') . '">';
				$panel['data'] .= '<td class="left inpa_loglines" title="' . $row['name'] . '"><i class="fas fa-chart-area bus_graph" bus_id="' . $graph_id . '"></i>' . html_escape($row['name']) . '</td>';

				$panel['data'] .= "<td class='right'>" . round($row['xvalue'], 2) . ' %</td>';
				$panel['data'] .= "<td class='right'>" . round($row['xpeak'], 2) . ' %</td></tr>';

				if ($row['xvalue'] > 100 || $row['xpeak'] > 100) {
					$host_id = db_fetch_cell_prepared('SELECT host_id
						FROM data_local
						WHERE id = ?',
						array($row['ldid']));

					cacti_log("WARNING: Problem with DSSTAT data for Device[$host_id] and DS[{$row['ldid']}.  Please investigate or clear DSSTAT data.", false, 'INTROPAGE');
				}

				$i++;
			}

			$panel['data'] .= '<tr><td colspan="2">' . __('Average of all allowed DS') . '</td><td>' . round($avg, 2) . ' %</td></tr>';
			$panel['data'] .= '</table>';

		} else {
			$panel['data'] = __('Waiting for data or you don\'t have permission for any device with this template.', 'intropage');
		}

	} else {
		$panel['data'] = __('You don\'t have permissions to any hosts or there isn\'t any host with this template', 'intropage');
	}

	save_panel_result($panel, $user_id);
}


//------------------------------------ busiest uptime -----------------------------------------------------
function busiest_uptime($panel, $user_id) {
	global $config;

	$lines = read_user_setting('intropage_number_of_lines', read_config_option('intropage_number_of_lines'), false, $user_id);

	$panel['alarm'] = 'grey';

	$console_access = get_console_access($user_id);

	$allowed_devices = intropage_get_allowed_devices($user_id);

	if ($allowed_devices) {

		$columns = " id, description, snmp_sysUpTimeInstance";
		$query = ' FROM host
			WHERE id IN (' . $allowed_devices . ')
			ORDER BY snmp_sysUpTimeInstance DESC
			LIMIT ' . $lines;

		$avg = db_fetch_cell ('SELECT avg(snmp_sysUpTimeInstance)' . $query);
		$result = db_fetch_assoc("SELECT $columns $query");

		if (cacti_sizeof($result)) {

			$panel['data'] = '<table class="cactiTable inpa_fixed">' .
				'<tr class="tableHeader">' .
					'<th class="left inpa_first">'  . __('Host', 'intropage') . '</th>' .
					'<th class="right">' . __('Uptime', 'intropage') . '</th>' .
				'</tr>';

			$i = 0;

			foreach ($result as $row) {

				if ($console_access) {
					$panel['data'] .= '<tr class="' . ($i % 2 == 0 ? 'even':'odd') . '"><td class="left inpa_loglines"><a class="linkEditMain" href="' . html_escape($config['url_path'] . 'host.php?action=edit&id=' . $row['id']) . '">' . html_escape($row['description']) . '</a></td>';
				} else {
					$panel['data'] .= '<tr class="' . ($i % 2 == 0 ? 'even':'odd') . '"><td class="left inpa_loglines">' . html_escape($row['description']) . '</td>';
				}

				$panel['data'] .= "<td class='right'>" . get_daysfromtime($row['snmp_sysUpTimeInstance']/100) . '</td>';

				$i++;
			}

			$panel['data'] .= '<tr><td>' . __('Average of all allowed hosts') . '</td><td>' . get_daysfromtime($avg/100) . '</td></tr>';
			$panel['data'] .= '</table>';

		} else {
			$panel['data'] = __('Waiting for data or you don\'t have permission for any device', 'intropage');
		}

	} else {
		$panel['data'] = __('You don\'t have permissions to any hosts', 'intropage');
	}

	save_panel_result($panel, $user_id);
}


//------------------------------------ busiest_traffic  -----------------------------------------------------
function busiest_traffic($panel, $user_id) {
	global $config;

	$lines = read_user_setting('intropage_number_of_lines', read_config_option('intropage_number_of_lines'), false, $user_id);

	$panel['alarm'] = 'grey';

	$console_access = get_console_access($user_id);

	$intropage_mb = read_user_setting('intropage_mb', read_config_option('intropage_mb'), $_SESSION['sess_user_id']);

	if (read_config_option('dsstats_enable') != 'on') {
		$panel['data'] = __('Panel needs DS stats enabled.', 'intropage') . '<br/>';

		if ($console_access) {
			$panel['data'] .=  '<a class="pic" href="' . $config['url_path'] .'settings.php?tab=data">' . __('Please enable and configure DS stats', 'intropage') . '</a>';
		} else {
			$panel['data'] .=  __('Ask admin to enable DS stats', 'intropage') . '</a>';
		}

		save_panel_result($panel, $user_id);
	}

	$allowed_devices = intropage_get_allowed_devices($user_id);

	$ds = db_fetch_row("SELECT id,name
		FROM data_template
		WHERE hash='6632e1e0b58a565c135d7ff90440c335'");

	if ($allowed_devices && $ds) {

		$columns = " name_cache AS name, t2.local_data_id AS ldid,
			average + (SELECT average FROM data_source_stats_hourly WHERE local_data_id = ldid AND rrd_name='traffic_in' ) AS xvalue,
			peak + (SELECT peak FROM data_source_stats_hourly WHERE local_data_id = ldid AND rrd_name='traffic_in') AS xpeak ";

		$query = ' FROM data_template_data AS t1
			LEFT JOIN data_source_stats_hourly AS t2 ON t1.local_data_id = t2.local_data_id
			LEFT JOIN data_local AS t3 on t3.id=t1.local_data_id
			WHERE t1.data_template_id = ' . $ds['id'] . ' AND
			t3.host_id IN (' . $allowed_devices . ') AND
			rrd_name=\'traffic_out\'
			ORDER BY xvalue DESC
			LIMIT ' . $lines;

		$result = db_fetch_assoc("SELECT $columns $query");

		$columns = " name_cache AS name, t1.local_data_id AS ldid,
			average/(SELECT average FROM data_source_stats_hourly WHERE local_data_id = ldid AND rrd_name='traffic_in' ) AS xvalue,
			peak + (SELECT peak FROM data_source_stats_hourly WHERE local_data_id = ldid AND rrd_name='traffic_in') AS xpeak ";

		$query = ' FROM data_template_data AS t1 LEFT JOIN data_source_stats_hourly AS t2 ON t1.local_data_id = t2.local_data_id
			WHERE t1.data_template_id = ' . $ds['id'] . '
			AND rrd_name=\'traffic_out\' ';

		$xavg = db_fetch_assoc ('SELECT ' . $columns . ' ' . $query);
		$avg = 0;

		if ($xavg) {
			foreach ($xavg as $row) {
				$avg+=$row['xvalue'];
			}
			$avg = $avg/count($xavg);
		}

		if (cacti_sizeof($result)) {

			$panel['data'] = '<table class="cactiTable inpa_fixed">' .
				'<tr class="tableHeader">' .
					'<th class="left inpa_first">'  . $ds['name'] . '</th>' .
					'<th class="right">' . __('Average', 'intropage') . '</th>' .
					'<th class="right">' . __('Peak', 'intropage') . '</th>' .
				'</tr>';

			$i = 0;

			foreach ($result as $row) {

				$graph_id = db_fetch_cell ('SELECT DISTINCT(local_graph_id) FROM graph_templates_item
					LEFT JOIN data_template_rrd ON (graph_templates_item.task_item_id=data_template_rrd.id)
					LEFT JOIN data_local ON (data_template_rrd.local_data_id=data_local.id)
					LEFT JOIN data_template_data ON (data_local.id=data_template_data.local_data_id)
					WHERE data_template_data.local_data_id=' . $row['ldid']);

				$panel['data'] .= '<tr class="' . ($i % 2 == 0 ? 'even':'odd') . '">';
				$panel['data'] .= '<td class="left inpa_loglines" title="' . $row['name'] . '"><i class="fas fa-chart-area bus_graph" bus_id="' . $graph_id . '"></i>' . html_escape($row['name']) . '</td>';

				if ($intropage_mb == 'b') {
					$row['xvalue'] *= 8;
					$row['xpeak'] *= 8;
					$units = 'b';
				} else {
					$units = 'B';
				}

				$panel['data'] .= "<td class='right'>" . human_readable($row['xvalue'], false,1) . $units . '</td>';
				$panel['data'] .= "<td class='right'>" . human_readable($row['xpeak'], false,1) . $units .'</td></tr>';

				$i++;
			}

			if ($intropage_mb == 'b') {
				$avg *= 8;
			}

			$panel['data'] .= '<tr><td>' . __('Average of all allowed DS') . '</td><td colspan="2">' . human_readable($avg, false,1) . $units . '</td></tr>';
			$panel['data'] .= '</table>';

		} else {
			$panel['data'] = __('Waiting for data or you don\'t have permission for any device with this template.', 'intropage');
		}

	} else {
		$panel['data'] = __('You don\'t have permissions to any hosts or there isn\'t any host with this template', 'intropage');
	}

	save_panel_result($panel, $user_id);
}


//------------------------------------ busiest_traffic_error  -----------------------------------------------------
function busiest_interface_error($panel, $user_id) {
	global $config;

	$lines = read_user_setting('intropage_number_of_lines', read_config_option('intropage_number_of_lines'), false, $user_id);

	$panel['alarm'] = 'grey';

	$console_access = get_console_access($user_id);

	if (read_config_option('dsstats_enable') != 'on') {
		$panel['data'] = __('Panel needs DS stats enabled.', 'intropage') . '<br/>';

		if ($console_access) {
			$panel['data'] .=  '<a class="pic" href="' . $config['url_path'] .'settings.php?tab=data">' . __('Please enable and configure DS stats', 'intropage') . '</a>';
		} else {
			$panel['data'] .=  __('Ask admin to enable DS stats', 'intropage') . '</a>';
		}

		save_panel_result($panel, $user_id);
	}

	$allowed_devices = intropage_get_allowed_devices($user_id);

	$ds = db_fetch_row("SELECT id,name
		FROM data_template
		WHERE hash='36335cd98633963a575b70639cd2fdad'");

	if ($allowed_devices && $ds) {

		$columns = " t1.local_data_id AS ldid, concat(t1.name_cache,' - ', t2.rrd_name) AS name, t2.average AS xvalue, t2.peak AS xpeak ";

		$query = ' FROM data_template_data AS t1
			LEFT JOIN data_source_stats_hourly AS t2 ON t1.local_data_id = t2.local_data_id
			LEFT JOIN data_local AS t3 on t3.id=t1.local_data_id
			WHERE t1.data_template_id = ' . $ds['id'] . ' AND
			t3.host_id IN (' . $allowed_devices . ') AND
			t2.average IS NOT NULL
			ORDER BY t2.average DESC
			LIMIT ' . $lines;

		$result = db_fetch_assoc("SELECT $columns $query");

		$query = ' FROM data_template_data AS t1
			LEFT JOIN data_source_stats_hourly AS t2 ON t1.local_data_id = t2.local_data_id
			WHERE t1.data_template_id = ' . $ds['id'] . ' AND
			t2.average IS NOT NULL';

		$avg = db_fetch_cell ('SELECT avg(average)' . $query);

		if (cacti_sizeof($result)) {

			$panel['data'] = '<table class="cactiTable inpa_fixed">' .
				'<tr class="tableHeader">' .
					'<th class="left inpa_first">'  . $ds['name'] . '</th>' .
					'<th class="right">' . __('Average', 'intropage') . '</th>' .
					'<th class="right">' . __('Peak', 'intropage') . '</th>' .
				'</tr>';

			$i = 0;

			foreach ($result as $row) {

				$graph_id = db_fetch_cell ('SELECT DISTINCT(local_graph_id) FROM graph_templates_item
					LEFT JOIN data_template_rrd ON (graph_templates_item.task_item_id=data_template_rrd.id)
					LEFT JOIN data_local ON (data_template_rrd.local_data_id=data_local.id)
					LEFT JOIN data_template_data ON (data_local.id=data_template_data.local_data_id)
					WHERE data_template_data.local_data_id=' . $row['ldid']);

				$panel['data'] .= '<tr class="' . ($i % 2 == 0 ? 'even':'odd') . '">';
				$panel['data'] .= '<td class="left inpa_loglines" title="' . $row['name'] . '"><i class="fas fa-chart-area bus_graph" bus_id="' . $graph_id . '"></i>' . html_escape($row['name']) . '</td>';

				$panel['data'] .= "<td class='right'>" . human_readable($row['xvalue']) . '</td>';
				$panel['data'] .= "<td class='right'>" . human_readable($row['xpeak']) . '</td></tr>';

				$i++;
			}

			$panel['data'] .= '<tr><td>' . __('Average of all allowed DS') . '</td><td colspan="2">' . human_readable($avg) . ' Err/Discard</td></tr>';
			$panel['data'] .= '</table>';

		} else {
			$panel['data'] = __('Waiting for data or you don\'t have permission for any device with this template.', 'intropage');
		}

	} else {
		$panel['data'] = __('You don\'t have permissions to any hosts or there isn\'t any host with this template', 'intropage');
	}

	save_panel_result($panel, $user_id);
}


//------------------------------------ busiest_traffic_utilization -----------------------------------------------------
function busiest_interface_util($panel, $user_id) {
	global $config;

	$lines = read_user_setting('intropage_number_of_lines', read_config_option('intropage_number_of_lines'), false, $user_id);

	include_once($config['base_path'] . '/lib/api_data_source.php');

	$panel['alarm'] = 'grey';

	$console_access = get_console_access($user_id);

	if (read_config_option('dsstats_enable') != 'on') {
		$panel['data'] = __('Panel needs DS stats enabled.', 'intropage') . '<br/>';

		if ($console_access) {
			$panel['data'] .=  '<a class="pic" href="' . $config['url_path'] .'settings.php?tab=data">' . __('Please enable and configure DS stats', 'intropage') . '</a>';
		} else {
			$panel['data'] .=  __('Ask admin to enable DS stats', 'intropage') . '</a>';
		}

		save_panel_result($panel, $user_id);
	}

	$allowed_devices = intropage_get_allowed_devices($user_id);

	$ds = db_fetch_row("SELECT id,name
		FROM data_template
		WHERE hash='6632e1e0b58a565c135d7ff90440c335'");

	if ($allowed_devices && $ds) {

		$perc = array();

		$result = db_fetch_assoc("SELECT t2.local_data_id, rrd_name, value,
			t3.host_id AS `host_id`, t3.snmp_query_id AS `snmp_query_id`, t3.snmp_index AS `snmp_index`
			FROM data_source_stats_hourly_cache AS t2
			LEFT JOIN data_local AS t3 on t3.id=t2.local_data_id
			LEFT JOIN data_template_data AS t4 ON t4.local_data_id = t2.local_data_id
			WHERE t3.host_id IN (" . $allowed_devices . ") AND
			t4.data_template_id = " . $ds['id'] . " AND value > 0 AND
			time > date_sub(now(), INTERVAL 5 MINUTE) ORDER BY value DESC");

		foreach ($result as $row) {

			$speed = api_data_source_get_interface_speed ($row)/8;

			$key = $row['local_data_id'] . '-' . $row['rrd_name'];
			$perc[$key] = round(100 * $row['value'] / $speed, 2);
		}

		if (cacti_sizeof($perc)) {

			arsort($perc, SORT_NUMERIC);

			$panel['data'] = '<table class="cactiTable inpa_fixed">' .
				'<tr class="tableHeader">' .
					'<th class="left inpa_first">'  . $ds['name'] . '</th>' .
					'<th class="right">' . __('Direction', 'intropage') . '</th>' .
					'<th class="right">%</th>' .
				'</tr>';

			$i = 0;

			foreach ($perc as $key=>$value) {
				list($real_key,$direction) = explode ('-', $key);

				$gdata = db_fetch_row ('SELECT DISTINCT(local_graph_id) AS graph_id,name_cache FROM graph_templates_item
					LEFT JOIN data_template_rrd ON (graph_templates_item.task_item_id=data_template_rrd.id)
					LEFT JOIN data_local ON (data_template_rrd.local_data_id=data_local.id)
					LEFT JOIN data_template_data ON (data_local.id=data_template_data.local_data_id)
					WHERE data_template_data.local_data_id=' . $real_key);

				$panel['data'] .= '<tr class="' . ($i % 2 == 0 ? 'even':'odd') . '">';
				$panel['data'] .= '<td class="left inpa_loglines" title="' . $gdata['name_cache'] . '"><i class="fas fa-chart-area bus_graph" bus_id="' . html_escape($gdata['graph_id']) . '"></i>';
				$panel['data'] .= html_escape($gdata['name_cache']) . '</td>';
				$panel['data'] .= '<td>' . ($direction == 'traffic_in' ? 'In':'Out') . '</td>';
				$panel['data'] .= "<td class='right'>" . $value . '</td>';

				$i++;

				if ($i >= $lines) {
					break;
				}
			}

			$panel['data'] .= '<tr><td colspan="2">' . __('Time interval last 5 minues') . '</td></tr>';
			$panel['data'] .= '</table>';

		} else {
			$panel['data'] = __('Waiting for data or you don\'t have permission for any device with this template.', 'intropage');
		}

	} else {
		$panel['data'] = __('You don\'t have permissions to any hosts or there isn\'t any host with this template', 'intropage');
	}

	save_panel_result($panel, $user_id);
}


//------------------------------------ busiest_cpu_detail  -----------------------------------------------------
function busiest_cpu_detail() {
	global $config;

	$panel = array(
		'name'   => __('Busiest 30 Host MIB CPU utilization (last hour)', 'intropage'),
		'alarm'  => 'grey',
		'detail' => '',
	);

	$console_access = get_console_access($_SESSION['sess_user_id']);

	if (read_config_option('dsstats_enable') != 'on') {
		$panel['detail'] = __('Panel needs DS stats enabled.', 'intropage') . '<br/>';

		if ($console_access) {
			$panel['detail'] .=  '<a class="pic" href="' . $config['url_path'] .'settings.php?tab=data">' . __('Please enable and configure DS stats', 'intropage') . '</a>';
		} else {
			$panel['detail'] .=  __('Ask admin to enable DS stats', 'intropage') . '</a>';
		}

		save_panel_result($panel, $user_id);
	}

	$allowed_devices = intropage_get_allowed_devices($_SESSION['sess_user_id']);

	$ds = db_fetch_row("SELECT id,name
		FROM data_template
		WHERE hash='f6e7d21c19434666bbdac00ccef9932f'");

	if ($allowed_devices && $ds) {

		$columns = " t1.local_data_id AS ldid, concat(t1.name_cache,' - ', t2.rrd_name) AS name, t2.average AS xvalue, t2.peak AS xpeak ";

		$query = ' FROM data_template_data AS t1
			LEFT JOIN data_source_stats_hourly AS t2 ON t1.local_data_id = t2.local_data_id
			LEFT JOIN data_local AS t3 on t3.id=t1.local_data_id
			WHERE t3.host_id IN (' . $allowed_devices . ') AND
			t2.average IS NOT  NULL AND
			t1.data_template_id = ' . $ds['id'] . '
			ORDER BY t2.average DESC
			LIMIT 30';

		$avg = db_fetch_cell ('SELECT avg(average)' . $query);
		$result = db_fetch_assoc("SELECT $columns $query");

		if (cacti_sizeof($result)) {

			$panel['detail'] = '<table class="cactiTable">' .
				'<tr class="tableHeader">' .
					'<th class="left">'  . $ds['name'] . '</th>' .
					'<th class="right">' . __('Average', 'intropage') . '</th>' .
					'<th class="right">' . __('Peak', 'intropage') . '</th>' .
				'</tr>';

			$i = 0;

			foreach ($result as $row) {

				if ($console_access) {
					$graph_id = db_fetch_cell ('SELECT DISTINCT(local_graph_id) FROM graph_templates_item
						LEFT JOIN data_template_rrd ON (graph_templates_item.task_item_id=data_template_rrd.id)
						LEFT JOIN data_local ON (data_template_rrd.local_data_id=data_local.id)
						LEFT JOIN data_template_data ON (data_local.id=data_template_data.local_data_id)
						WHERE data_template_data.local_data_id=' . $row['ldid']);

					$panel['detail'] .= '<tr class="' . ($i % 2 == 0 ? 'even':'odd') . '"><td class="left"><a class="linkEditMain bus_graph" bus_id="' . $graph_id . '" href="' . html_escape($config['url_path'] . 'graphs.php?action=graph_edit&id=' . $graph_id) . '">' . html_escape($row['name']) . '</a></td>';
				} else {
					$panel['detail'] .= '<tr class="' . ($i % 2 == 0 ? 'even':'odd') . '"><td class="left">' . html_escape($row['name']) . '</td>';
				}

				$panel['detail'] .= "<td class='right'>" . round($row['xvalue'], 2) . ' %</td>';
				$panel['detail'] .= "<td class='right'>" . round($row['xpeak'], 2) . ' %</td></tr>';

				$i++;
			}

			$panel['detail'] .= '<tr><td>' . __('Average of all allowed DS') . '</td><td colspan="2">' . round($avg, 2) . ' %</td></tr>';
			$panel['detail'] .= '</table><br/>';
			$panel['detail'] .= __('Install TopX plugin for more DS statistics');

		} else {
			$panel['detail'] = __('Waiting for data or you don\'t have permission for any device with this template.', 'intropage');
		}

	} else {
		$panel['detail'] = __('You don\'t have permissions to any hosts or there isn\'t any host with this template', 'intropage');
	}

	return $panel;
}


//------------------------------------ busiest_load_detail  -----------------------------------------------------
function busiest_load_detail() {
	global $config;

	$panel = array(
		'name'   => __('Busiest 30 ucd/net Load (last hour)', 'intropage'),
		'alarm'  => 'grey',
		'detail' => '',
	);

	$console_access = get_console_access($_SESSION['sess_user_id']);

	if (read_config_option('dsstats_enable') != 'on') {
		$panel['detail'] = __('Panel needs DS stats enabled.', 'intropage') . '<br/>';

		if ($console_access) {
			$panel['detail'] .=  '<a class="pic" href="' . $config['url_path'] .'settings.php?tab=data">' . __('Please enable and configure DS stats', 'intropage') . '</a>';
		} else {
			$panel['detail'] .=  __('Ask admin to enable DS stats', 'intropage') . '</a>';
		}

		save_panel_result($panel, $user_id);
	}

	$allowed_devices = intropage_get_allowed_devices($_SESSION['sess_user_id']);

	$ds = db_fetch_row("SELECT id,name
		FROM data_template
		WHERE hash='9b82d44eb563027659683765f92c9757'");

	if ($allowed_devices && $ds) {

		$columns = " t1.local_data_id AS ldid, concat(t1.name_cache,' - ', t2.rrd_name) AS name, t2.average AS xvalue, t2.peak AS xpeak ";

		$query = ' FROM data_template_data AS t1
			LEFT JOIN data_source_stats_hourly AS t2 ON t1.local_data_id = t2.local_data_id
			LEFT JOIN data_local AS t3 on t3.id=t1.local_data_id
			WHERE t3.host_id IN (' . $allowed_devices . ') AND
			t2.average IS NOT  NULL AND
			t1.data_template_id = ' . $ds['id'] . '
			ORDER BY t2.average DESC
			LIMIT 30';

		$avg = db_fetch_cell ('SELECT avg(average)' . $query);
		$result = db_fetch_assoc("SELECT $columns $query");

		if (cacti_sizeof($result)) {

			$panel['detail'] = '<table class="cactiTable">' .
				'<tr class="tableHeader">' .
					'<th class="left">'  . $ds['name'] . '</th>' .
					'<th class="right">' . __('Average', 'intropage') . '</th>' .
					'<th class="right">' . __('Peak', 'intropage') . '</th>' .
				'</tr>';

			$i = 0;

			foreach ($result as $row) {

				if ($console_access) {
					$graph_id = db_fetch_cell ('SELECT DISTINCT(local_graph_id) FROM graph_templates_item
						LEFT JOIN data_template_rrd ON (graph_templates_item.task_item_id=data_template_rrd.id)
						LEFT JOIN data_local ON (data_template_rrd.local_data_id=data_local.id)
						LEFT JOIN data_template_data ON (data_local.id=data_template_data.local_data_id)
						WHERE data_template_data.local_data_id=' . $row['ldid']);

					$panel['detail'] .= '<tr class="' . ($i % 2 == 0 ? 'even':'odd') . '"><td class="left"><a class="linkEditMain" href="' . html_escape($config['url_path'] . 'graphs.php?action=graph_edit&id=' . $graph_id) . '">' . html_escape($row['name']) . '</a></td>';
				} else {
					$panel['detail'] .= '<tr class="' . ($i % 2 == 0 ? 'even':'odd') . '"><td class="left">' . html_escape($row['name']) . '</td>';
				}

				$panel['detail'] .= "<td class='right'>" . round($row['xvalue'], 2) . '</td>';
				$panel['detail'] .= "<td class='right'>" . round($row['xpeak'], 2) . '</td></tr>';

				$i++;
			}

			$panel['detail'] .= '<tr><td>' . __('Average of all allowed DS') . '</td><td colspan="2">' . round($avg, 2) . '</td></tr>';
			$panel['detail'] .= '</table><br/>';
			$panel['detail'] .= __('Install TopX plugin for more DS statistics');

		} else {
			$panel['detail'] = __('Waiting for data or you don\'t have permission for any device with this template.', 'intropage');
		}

	} else {
		$panel['detail'] = __('You don\'t have permissions to any hosts or there isn\'t any host with this template', 'intropage');
	}

	return $panel;
}



//------------------------------------ busiest hdd detail  -----------------------------------------------------
function busiest_hdd_detail() {
	global $config;

	$panel = array(
		'name'   => __('Busiest 30 Host MIB Hard Drive space (last hour)', 'intropage'),
		'alarm'  => 'grey',
		'detail' => '',
	);

	$console_access = get_console_access($_SESSION['sess_user_id']);

	if (read_config_option('dsstats_enable') != 'on') {
		$panel['detail'] = __('Panel needs DS stats enabled.', 'intropage') . '<br/>';

		if ($console_access) {
			$panel['detail'] .=  '<a class="pic" href="' . $config['url_path'] .'settings.php?tab=data">' . __('Please enable and configure DS stats', 'intropage') . '</a>';
		} else {
			$panel['detail'] .=  __('Ask admin to enable DS stats', 'intropage') . '</a>';
		}

		save_panel_result($panel, $user_id);
	}

	$allowed_devices = intropage_get_allowed_devices($_SESSION['sess_user_id']);

	$ds = db_fetch_row("SELECT id,name
		FROM data_template
		WHERE hash='d814fa3b79bd0f8933b6e0834d3f16d0'");

	if ($allowed_devices && $ds) {

		$columns = " name_cache AS name, t2.local_data_id AS ldid,
			100*average/(SELECT average FROM data_source_stats_hourly WHERE local_data_id = ldid AND rrd_name='hdd_total' ) AS xvalue,
			100*peak/(SELECT peak FROM data_source_stats_hourly WHERE local_data_id = ldid AND rrd_name='hdd_total') AS xpeak ";

		$query = ' FROM data_template_data AS t1
			LEFT JOIN data_source_stats_hourly AS t2 ON t1.local_data_id = t2.local_data_id
			LEFT JOIN data_local AS t3 on t3.id=t1.local_data_id
			WHERE t3.host_id IN (' . $allowed_devices . ') AND
			t2.rrd_name=\'hdd_used\' AND
			t1.data_template_id = ' . $ds['id'] . '
			ORDER BY xvalue DESC
			LIMIT 30';

		$result = db_fetch_assoc("SELECT $columns $query");

		// avg
		$columns = " t1.local_data_id AS ldid,100*average/(SELECT average FROM data_source_stats_hourly WHERE local_data_id = ldid AND rrd_name='hdd_total' ) AS xvalue ";

		$query = ' FROM data_template_data AS t1
			LEFT JOIN data_source_stats_hourly AS t2 ON t1.local_data_id = t2.local_data_id
			LEFT JOIN data_local AS t3 on t3.id=t1.local_data_id
			WHERE t3.host_id IN (' . $allowed_devices . ') AND
			t2.rrd_name=\'hdd_used\' AND
			t1.data_template_id = ' . $ds['id'] . '
			AND t2.rrd_name=\'hdd_used\'';

		$xavg = db_fetch_assoc ('SELECT ' . $columns . ' ' . $query);
		$avg = 0;

		if ($xavg) {
			foreach ($xavg as $row) {
				$avg+=$row['xvalue'];
			}
			$avg = $avg/count($xavg);
		}

		if (cacti_sizeof($result)) {

			$panel['detail'] = '<table class="cactiTable">' .
				'<tr class="tableHeader">' .
					'<th class="left">'  . $ds['name'] . '</th>' .
					'<th class="right">' . __('Average', 'intropage') . '</th>' .
					'<th class="right">' . __('Peak', 'intropage') . '</th>' .
				'</tr>';

			$i = 0;

			foreach ($result as $row) {

				if ($console_access) {
					$graph_id = db_fetch_cell ('SELECT DISTINCT(local_graph_id) FROM graph_templates_item
						LEFT JOIN data_template_rrd ON (graph_templates_item.task_item_id=data_template_rrd.id)
						LEFT JOIN data_local ON (data_template_rrd.local_data_id=data_local.id)
						LEFT JOIN data_template_data ON (data_local.id=data_template_data.local_data_id)
						WHERE data_template_data.local_data_id=' . $row['ldid']);

					$panel['detail'] .= '<tr class="' . ($i % 2 == 0 ? 'even':'odd') . '"><td class="left"><a style="white-space: overflow" class="linkEditMain" href="' . html_escape($config['url_path'] . 'graphs.php?action=graph_edit&id=' . $graph_id) . '">' . html_escape($row['name']) . '</a></td>';
				} else {
					$panel['detail'] .= '<tr class="' . ($i % 2 == 0 ? 'even':'odd') . '"><td class="left">' . html_escape($row['name']) . '</td>';
				}

				$panel['detail'] .= "<td class='right'>" . round($row['xvalue'], 2) . ' %</td>';
				$panel['detail'] .= "<td class='right'>" . round($row['xpeak'], 2) . ' %</td></tr>';

				$i++;
			}

			$panel['detail'] .= '<tr><td>' . __('Average of all allowed DS') . '</td><td colspan="2">' . round($avg, 2) . ' %</td></tr>';
			$panel['detail'] .= '</table><br/>';
			$panel['detail'] .= __('Install TopX plugin for more DS statistics');

		} else {
			$panel['detail'] = __('Waiting for data or you don\'t have permission for any device with this template.', 'intropage');
		}

	} else {
		$panel['detail'] = __('You don\'t have permissions to any hosts or there isn\'t any host with this template', 'intropage');
	}

	return $panel;
}


//------------------------------------ busiest uptime detail -----------------------------------------------------
function busiest_uptime_detail() {
	global $config;

	$panel = array(
		'name'   => __('Busiest uptime', 'intropage'),
		'alarm'  => 'grey',
		'detail' => '',
	);

	$console_access = get_console_access($_SESSION['sess_user_id']);

	$allowed_devices = intropage_get_allowed_devices($_SESSION['sess_user_id']);

	if ($allowed_devices) {

		$columns = " id, description, snmp_sysUpTimeInstance";

		$query = ' FROM host
			WHERE id IN (' . $allowed_devices . ')
			ORDER BY snmp_sysUpTimeInstance DESC
			LIMIT 30';

		$avg = db_fetch_cell ('SELECT avg(snmp_sysUpTimeInstance)' . $query);
		$result = db_fetch_assoc("SELECT $columns $query");

		if (cacti_sizeof($result)) {

			$panel['detail'] = '<table class="cactiTable">' .
				'<tr class="tableHeader">' .
					'<th class="left">'  . __('Host', 'intropage') . '</th>' .
					'<th class="right">' . __('Uptime', 'intropage') . '</th>' .
				'</tr>';

			$i = 0;

			foreach ($result as $row) {

				if ($console_access) {
					$panel['detail'] .= '<tr class="' . ($i % 2 == 0 ? 'even':'odd') . '"><td class="left"><a class="linkEditMain" href="' . html_escape($config['url_path'] . 'host.php?action=edit&id=' . $row['id']) . '">' . html_escape($row['description']) . '</a></td>';
				} else {
					$panel['detail'] .= '<tr class="' . ($i % 2 == 0 ? 'even':'odd') . '"><td class="left">' . html_escape($row['description']) . '</td>';
				}

				$panel['detail'] .= "<td class='right'>" . get_daysfromtime($row['snmp_sysUpTimeInstance']/100) . '</td>';

				$i++;
			}

			$panel['detail'] .= '<tr><td>' . __('Average of all allowed hosts') . '</td><td colspan="2">' . get_daysfromtime($avg/100) . '</td></tr>';
			$panel['detail'] .= '</table>';

		} else {
			$panel['detail'] = __('Waiting for data or you don\'t have permission for any device', 'intropage');
		}

	} else {
		$panel['detail'] = __('You don\'t have permissions to any hosts', 'intropage');
	}

	return ($panel);
}


//------------------------------------ busiest_traffic_detail  -----------------------------------------------------
function busiest_traffic_detail() {
	global $config;

	$panel = array(
		'name'   => __('Busiest traffic (in+out)', 'intropage'),
		'alarm'  => 'grey',
		'detail' => '',
	);

	$console_access = get_console_access($_SESSION['sess_user_id']);
	$intropage_mb = read_user_setting('intropage_mb', read_config_option('intropage_mb'), $_SESSION['sess_user_id']);

	$allowed_devices = intropage_get_allowed_devices($_SESSION['sess_user_id']);

	$ds = db_fetch_row("SELECT id,name
		FROM data_template
		WHERE hash='6632e1e0b58a565c135d7ff90440c335'");

	if ($allowed_devices && $ds) {

		$columns = " name_cache AS name, t2.local_data_id AS ldid,
			average + (SELECT average FROM data_source_stats_hourly WHERE local_data_id = ldid AND rrd_name='traffic_in' ) AS xvalue,
			peak + (SELECT peak FROM data_source_stats_hourly WHERE local_data_id = ldid AND rrd_name='traffic_in') AS xpeak ";

		$query = ' FROM data_template_data AS t1
			LEFT JOIN data_source_stats_hourly  AS t2 ON t1.local_data_id = t2.local_data_id
			LEFT JOIN data_local AS t3 on t3.id=t1.local_data_id
			WHERE t1.data_template_id = ' . $ds['id'] . ' AND
			t3.host_id IN (' . $allowed_devices . ') AND
			rrd_name=\'traffic_out\'
			ORDER BY xvalue DESC
			LIMIT 30';

		$result = db_fetch_assoc("SELECT $columns $query");

		$columns = " t1.local_data_id AS ldid, average/(SELECT average FROM data_source_stats_hourly
			WHERE local_data_id = ldid AND rrd_name='traffic_in' ) AS xvalue ";

		$query = ' FROM data_template_data AS t1 LEFT JOIN data_source_stats_hourly AS t2 ON t1.local_data_id = t2.local_data_id
			WHERE t1.data_template_id = ' . $ds['id'] . '
			AND rrd_name=\'traffic_out\' ';

		$xavg = db_fetch_assoc ('SELECT ' . $columns . ' ' . $query);
		$avg = 0;

		if ($xavg) {
			foreach ($xavg as $row) {
				$avg+=$row['xvalue'];
			}
			$avg = $avg/count($xavg);
		}

		if (cacti_sizeof($result)) {

			$panel['detail'] = '<table class="cactiTable">' .
				'<tr class="tableHeader">' .
					'<th class="left">'  . $ds['name'] . '</th>' .
					'<th class="right">' . __('Average', 'intropage') . '</th>' .
					'<th class="right">' . __('Peak', 'intropage') . '</th>' .
				'</tr>';

			$i = 0;

			foreach ($result as $row) {

				if ($console_access) {
					$graph_id = db_fetch_cell ('SELECT DISTINCT(local_graph_id) FROM graph_templates_item
						LEFT JOIN data_template_rrd ON (graph_templates_item.task_item_id=data_template_rrd.id)
						LEFT JOIN data_local ON (data_template_rrd.local_data_id=data_local.id)
						LEFT JOIN data_template_data ON (data_local.id=data_template_data.local_data_id)
						WHERE data_template_data.local_data_id=' . $row['ldid']);

					$panel['detail'] .= '<tr class="' . ($i % 2 == 0 ? 'even':'odd') . '"><td class="left"><a class="linkEditMain" href="' . html_escape($config['url_path'] . 'graphs.php?action=graph_edit&id=' . $graph_id) . '">' . html_escape($row['name']) . '</a></td>';
				} else {
					$panel['detail'] .= '<tr class="' . ($i % 2 == 0 ? 'even':'odd') . '"><td class="left">' . html_escape($row['name']) . '</td>';
				}

				if ($intropage_mb == 'b') {
					$row['xvalue'] *= 8;
					$row['xpeak'] *= 8;
					$units = 'b';
				} else {
					$units = 'B';
				}

				$panel['detail'] .= "<td class='right'>" . human_readable($row['xvalue'], false) . $units . '</td>';
				$panel['detail'] .= "<td class='right'>" . human_readable($row['xpeak'], false) . $units . '</td></tr>';

				$i++;
			}

			if ($intropage_mb == 'b') {
				$avg *= 8;
			}

			$panel['detail'] .= '<tr><td>' . __('Average of all allowed DS') . '</td><td colspan="2">' . human_readable($avg, false) . $units . '</td></tr>';
			$panel['detail'] .= '</table>';

		} else {
			$panel['detail'] = __('Waiting for data or you don\'t have permission for any device with this template.', 'intropage');
		}

	} else {
		$panel['detail'] = __('You don\'t have permissions to any hosts or there isn\'t any host with this template', 'intropage');
	}

	return ($panel);
}


//------------------------------------ busiest_traffic_error_detail  -----------------------------------------------------
function busiest_interface_error_detail() {
	global $config;

	$panel = array(
		'name'   => __('Busiest traffic (in+out)', 'intropage'),
		'alarm'  => 'grey',
		'detail' => '',
	);

	$console_access = get_console_access($_SESSION['sess_user_id']);

	$allowed_devices = intropage_get_allowed_devices($_SESSION['sess_user_id']);

	$ds = db_fetch_row("SELECT id,name
		FROM data_template
		WHERE hash='36335cd98633963a575b70639cd2fdad'");

	if ($allowed_devices && $ds) {

		$columns = " t1.local_data_id AS ldid, concat(t1.name_cache,' - ', t2.rrd_name) AS name, t2.average AS xvalue, t2.peak AS xpeak ";

		$query = ' FROM data_template_data AS t1
			LEFT JOIN data_source_stats_hourly AS t2 ON t1.local_data_id = t2.local_data_id
			LEFT JOIN data_local AS t3 on t3.id=t1.local_data_id
			WHERE t1.data_template_id = ' . $ds['id'] . ' AND
			t3.host_id IN (' . $allowed_devices . ') AND
			t2.average IS NOT  NULL
			ORDER BY t2.average DESC
			LIMIT 30';

		$result = db_fetch_assoc("SELECT $columns $query");

		$query = ' FROM data_template_data AS t1 LEFT JOIN data_source_stats_hourly AS t2 ON t1.local_data_id = t2.local_data_id
			WHERE t1.data_template_id = ' . $ds['id'] . ' AND
			t2.average IS NOT NULL';

		$avg = db_fetch_cell ('SELECT avg(average)' . $query);

		if (cacti_sizeof($result)) {

			$panel['detail'] = '<table class="cactiTable">' .
				'<tr class="tableHeader">' .
					'<th class="left">'  . $ds['name'] . '</th>' .
					'<th class="right">' . __('Average', 'intropage') . '</th>' .
					'<th class="right">' . __('Peak', 'intropage') . '</th>' .
				'</tr>';

			$i = 0;

			foreach ($result as $row) {

				if ($console_access) {
					$graph_id = db_fetch_cell ('SELECT DISTINCT(local_graph_id) FROM graph_templates_item
						LEFT JOIN data_template_rrd ON (graph_templates_item.task_item_id=data_template_rrd.id)
						LEFT JOIN data_local ON (data_template_rrd.local_data_id=data_local.id)
						LEFT JOIN data_template_data ON (data_local.id=data_template_data.local_data_id)
						WHERE data_template_data.local_data_id=' . $row['ldid']);

					$panel['detail'] .= '<tr class="' . ($i % 2 == 0 ? 'even':'odd') . '"><td class="left"><a class="linkEditMain" href="' . html_escape($config['url_path'] . 'graphs.php?action=graph_edit&id=' . $graph_id) . '">' . html_escape($row['name']) . '</a></td>';
				} else {
					$panel['detail'] .= '<tr class="' . ($i % 2 == 0 ? 'even':'odd') . '"><td class="left">' . html_escape($row['name']) . '</td>';
				}

				$panel['detail'] .= "<td class='right'>" . human_readable($row['xvalue']) . ' Err/Discard</td>';
				$panel['detail'] .= "<td class='right'>" . human_readable($row['xpeak']) . ' Err/Discard</td></tr>';

				$i++;
			}

			$panel['detail'] .= '<tr><td>' . __('Average of all allowed DS') . '</td><td colspan="2">' . human_readable($avg) . ' Err/Discard</td></tr>';
			$panel['detail'] .= '</table>';

		} else {
			$panel['detail'] = __('Waiting for data or you don\'t have permission for any device with this template.', 'intropage');
		}

	} else {
		$panel['detail'] = __('You don\'t have permissions to any hosts or there isn\'t any host with this template', 'intropage');
	}

	return ($panel);
}

//------------------------------------ busiest_traffic_utilization_detail-----------------------------------------------
function busiest_interface_util_detail() {
	global $config;

	$panel = array(
		'name'   => __('Busiest interface utilization', 'intropage'),
		'alarm'  => 'grey',
		'detail' => '',
	);

	include_once($config['base_path'] . '/lib/api_data_source.php');

	$console_access = get_console_access($_SESSION['sess_user_id']);

	$allowed_devices = intropage_get_allowed_devices($_SESSION['sess_user_id']);

	$ds = db_fetch_row("SELECT id,name
		FROM data_template
		WHERE hash='6632e1e0b58a565c135d7ff90440c335'");

	if ($allowed_devices && $ds) {

		$perc = array();

		$result = db_fetch_assoc("SELECT t2.local_data_id, rrd_name, value,
			t3.host_id AS `host_id`, t3.snmp_query_id AS `snmp_query_id`, t3.snmp_index AS `snmp_index`
			FROM data_source_stats_hourly_cache AS t2
			LEFT JOIN data_local AS t3 on t3.id=t2.local_data_id
			LEFT JOIN data_template_data AS t4 ON t4.local_data_id = t2.local_data_id
			WHERE t3.host_id IN (" . $allowed_devices . ") AND
			t4.data_template_id = " . $ds['id'] . " AND value > 0 AND
			time > date_sub(now(), INTERVAL 5 MINUTE) ORDER BY value DESC");

		foreach ($result as $row) {

			$speed = api_data_source_get_interface_speed ($row)/8;

			$key = $row['local_data_id'] . '-' . $row['rrd_name'];
			$perc[$key] = round(100 * $row['value'] / $speed,2);
		}

		if (cacti_sizeof($perc)) {

			arsort($perc, SORT_NUMERIC);

			$panel['detail'] = '<table class="cactiTable">' .
				'<tr class="tableHeader">' .
					'<th class="left">'  . $ds['name'] . '</th>' .
					'<th class="right">' . __('Direction', 'intropage') . '</th>' .
					'<th class="right">%</th>' .
				'</tr>';

			$i = 0;

			foreach ($perc as $key=>$value) {
				list($real_key,$direction) = explode ('-', $key);

				$gdata = db_fetch_row ('SELECT DISTINCT(local_graph_id) AS graph_id,name_cache FROM graph_templates_item
					LEFT JOIN data_template_rrd ON (graph_templates_item.task_item_id=data_template_rrd.id)
					LEFT JOIN data_local ON (data_template_rrd.local_data_id=data_local.id)
					LEFT JOIN data_template_data ON (data_local.id=data_template_data.local_data_id)
					WHERE data_template_data.local_data_id=' . $real_key);

				$panel['detail'] .= '<tr class="' . ($i % 2 == 0 ? 'even':'odd') . '"><td class="left"><i class="fas fa-chart-area bus_graph" bus_id="' . $gdata['graph_id'] . '"></i>';
				$panel['detail'] .= html_escape($gdata['name_cache']) . '</td>';
				$panel['detail'] .= '<td>' . ($direction == 'traffic_in' ? 'In':'Out') . '</td>';
				$panel['detail'] .= "<td class='right'>" . $value . '</td>';

				$i++;

				if ($i > 30) {
					break;
				}
			}

			$panel['detail'] .= '<tr><td colspan="2">' . __('Time interval last 5 minues', 'intropage') . '</td></tr>';
			$panel['detail'] .= '</table>';

		} else {
			$panel['detail'] = __('Waiting for data or you don\'t have permission for any device with this template.', 'intropage');
		}

	} else {
		$panel['detail'] = __('You don\'t have permissions to any hosts or there isn\'t any host with this template', 'intropage');
	}

	return($panel);
}
