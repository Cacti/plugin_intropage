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

function register_system() {
	global $registry;

	$registry['system'] = array(
		'name'        => __('System Panels', 'intropage'),
		'description' => __('Panels that provide information about Cacti system performance.', 'intropage')
	);

	$panels = array(
		'info' => array(
			'name'         => __('Information', 'intropage'),
			'description'  => __('Various system information about the Cacti system itself.', 'intropage'),
			'class'        => 'system',
			'level'        => PANEL_SYSTEM,
			'refresh'      => 86400,
			'trefresh'     => false,
			'force'        => true,
			'width'        => 'quarter-panel',
			'priority'     => 5,
			'alarm'        => 'grey',
			'requires'     => false,
			'update_func'  => 'info',
			'details_func' => false,
			'trends_func'  => false
		),
		'admin_alert' => array(
			'name'         => __('Administrative Alerts', 'intropage'),
			'description'  => __('Extra admin notify panel for all users', 'intropage'),
			'class'        => 'system',
			'level'        => PANEL_SYSTEM,
			'refresh'      => 3600,
			'trefresh'     => false,
			'force'        => true,
			'width'        => 'quarter-panel',
			'priority'     => 99,
			'alarm'        => 'red',
			'requires'     => false,
			'update_func'  => 'admin_alert',
			'details_func' => false,
			'trends_func'  => false
		),
		'boost' => array(
			'name'         => __('Boost Statistics', 'intropage'),
			'description'  => __('Information about Cacti\'s performance boost process.', 'intropage'),
			'class'        => 'system',
			'level'        => PANEL_SYSTEM,
			'refresh'      => 900,
			'trefresh'     => false,
			'force'        => true,
			'width'        => 'quarter-panel',
			'priority'     => 47,
			'alarm'        => 'green',
			'requires'     => false,
			'update_func'  => 'boost',
			'details_func' => false,
			'trends_func'  => false
		),
		'extrem' => array(
			'name'         => __('24 Hour Extremes', 'intropage'),
			'description'  => __('Table with 24 hours of Polling Extremes (longest poller run, down hosts)', 'intropage'),
			'class'        => 'system',
			'level'        => PANEL_USER,
			'refresh'      => 900,
			'trefresh'     => read_config_option('poller_interval'),
			'force'        => true,
			'width'        => 'quarter-panel',
			'priority'     => 78,
			'alarm'        => 'grey',
			'requires'     => 'thold',
			'update_func'  => 'extrem',
			'details_func' => 'extrem_detail',
			'trends_func'  => 'extrem_trend'
		),
		'cpuload' => array(
			'name'         => __('CPU Utilization', 'intropage'),
			'description'  => __('CPU utilization Graph (only Linux).', 'intropage'),
			'class'        => 'system',
			'level'        => PANEL_SYSTEM,
			'refresh'      => 900,
			'trefresh'     => read_config_option('poller_interval'),
			'force'        => true,
			'width'        => 'quarter-panel',
			'priority'     => 59,
			'alarm'        => 'grey',
			'requires'     => false,
			'update_func'  => 'cpuload',
			'details_func' => false,
			'trends_func'  => 'cpuload_trend'
		)
	);

	return $panels;
}

function cpuload_trend() {
	if (!stristr(PHP_OS, 'win')) {
		$load    = sys_getloadavg();
		$load[0] = round($load[0], 2);

		db_execute_prepared("REPLACE INTO plugin_intropage_trends
			(name, value, user_id)
			VALUES ('cpuload', ?, 0)",
			array($load[0]));
	}
}

//------------------------------------ cpuload -----------------------------------------------------
function cpuload($panel, $user_id, $timespan = 0) {
	global $config;

	$panel['alarm'] = 'green';

	$graph = array (
		'line' => array(
			'title'  => __('CPU Load: ', 'intropage'),
			'label1' => array(),
			'data1'  => array(),
		),
	);

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

	if (stristr(PHP_OS, 'win')) {
		$panel['data'] = __('This function is not implemented on Windows platforms', 'intropage');
		unset($graph);
	} else {
		$rows = db_fetch_assoc_prepared("SELECT cur_timestamp AS `date`, AVG(value) AS average, MAX(value) AS max
			FROM plugin_intropage_trends
			WHERE cur_timestamp > date_sub(NOW(), INTERVAL ? SECOND)
			AND name = 'cpuload'
			GROUP BY UNIX_TIMESTAMP(cur_timestamp) DIV $refresh
			ORDER BY cur_timestamp ASC",
			array($timespan));

		if (cacti_sizeof($rows)) {
			$graph['line']['title1'] = __('Avg CPU', 'intropage');
			$graph['line']['title2'] = __('Max CPU', 'intropage');
			$graph['line']['unit1']['title'] = '%';

			foreach ($rows as $row) {
				$graph['line']['label1'][] = $row['date'];
				$graph['line']['data1'][]  = round($row['average'], 2);
				$graph['line']['data2'][]  = round($row['max'], 2);
			}

			$panel['data'] = intropage_prepare_graph($graph, $user_id);
		} else {
			unset($graph);

			$panel['data'] = __('Waiting for data', 'intropage');
		}
	}

	save_panel_result($panel, $user_id);
}

//------------------------- info-------------------------
function info($panel, $user_id) {
	global $config, $poller_options;

	$xdata = '';

	$panel['alarm'] = 'green';

	$panel['data'] .= '<table class="cactiTable inpa_fixed">';

	$panel['data'] .= '<tr><td>' . __('Cacti Version: ', 'intropage') . CACTI_VERSION . '</td></tr>';

	if ($poller_options[read_config_option('poller_type')] == 'spine' && file_exists(read_config_option('path_spine')) && (function_exists('is_executable')) && (is_executable(read_config_option('path_spine')))) {
		$spine_version = 'SPINE';

		exec(read_config_option('path_spine') . ' --version', $out_array);

		if (sizeof($out_array)) {
			$spine_version = $out_array[0];
		}

		$panel['data'] .= '<tr><td>' . __('Poller Type:', 'intropage') .' <a class="linkEditMain" href="' . html_escape($config['url_path'] .  'settings.php?tab=poller') . '">' . __('Spine', 'intropage') . '</a></td></tr>';

		$panel['data'] .= '<tr><td>' . __('Spine version: ', 'intropage') . $spine_version . '<br/></td></tr>';

		if (!strpos($spine_version, CACTI_VERSION, 0)) {
			$panel['data'] .= '<tr><td>' . __('You are using incorrect spine version!', 'intropage') . '<span class="inpa_sq color_red"></span></td></tr>';
			$panel['alarm'] = 'red';
		}

	} else {
		$panel['data'] .= '<tr><td>' . __('Poller Type: ', 'intropage') . ' <a class="linkEditMain" href="' . html_escape($config['url_path'] .  'settings.php?tab=poller') . '">' . $poller_options[read_config_option('poller_type')] . '</a><br/></td></tr>';
	}

	if (function_exists('php_uname')) {
		$xdata = php_uname();
	} else {
		$xdata .= PHP_OS;
	}

	$panel['data'] .= '<tr><td class="inpa_loglines" title="' . $xdata . '"><br/>' . __('Running on: ', 'intropage') . $xdata . '</td></tr>';

	$panel['data'] .= '</table>';
	
	save_panel_result($panel, $user_id);
}

//---------------------------admin alert--------------------
function admin_alert($panel, $user_id) {
	global $config;

	$panel['data'] .= '<span class="inpa_sq color_red"></span><div title="' . read_config_option('intropage_admin_alert') . '">' . read_config_option('intropage_admin_alert') . '</div>';

	save_panel_result($panel, $user_id);
}

//--------------------------------boost--------------------------------
function boost($panel, $user_id) {
	global $config, $boost_refresh_interval, $boost_max_runtime;

	$panel['alarm'] = 'green';

	// from lib/boost.php
	$rrd_updates     = read_config_option('boost_rrd_update_enable', true);
	$last_run_time   = read_config_option('boost_last_run_time', true);
	$next_run_time   = read_config_option('boost_next_run_time', true);
	$max_records     = read_config_option('boost_rrd_update_max_records', true);
	$max_runtime     = read_config_option('boost_rrd_update_max_runtime', true);
	$update_int      = read_config_option('boost_rrd_update_interval', true);
	$peak_memory     = read_config_option('boost_peak_memory', true);
	$parallel        = read_config_option('boost_parallel', true);
	$detail_stats    = read_config_option('stats_detail_boost', true);

	/* get the boost table status */
	$boost_table_status = db_fetch_assoc("SELECT *
		FROM INFORMATION_SCHEMA.TABLES WHERE table_schema=SCHEMA()
		AND (table_name LIKE 'poller_output_boost_arch_%' OR table_name LIKE 'poller_output_boost')");

	$pending_records = 0;
	$arch_records    = 0;
	$data_length     = 0;
	$engine          = '';
	$max_data_length = 0;

	if (cacti_sizeof($boost_table_status)) {
		foreach ($boost_table_status as $table) {
			if ($table['TABLE_NAME'] == 'poller_output_boost') {
				$pending_records += $table['TABLE_ROWS'];
			} else {
				$arch_records += $table['TABLE_ROWS'];
			}

			$data_length    += $table['DATA_LENGTH'];
			$data_length    += $table['INDEX_LENGTH'];
			$engine          = $table['ENGINE'];
			$max_data_length = $table['MAX_DATA_LENGTH'];
		}
	}

	$total_records  = $pending_records + $arch_records;
	$avg_row_length = ($total_records ? intval($data_length / $total_records) : 0);

	$boost_status = read_config_option('boost_poller_status', true);

	if ($boost_status != '') {
		$boost_status_array = explode(':', $boost_status);

		$boost_status_date  = $boost_status_array[1];

		if (substr_count($boost_status_array[0], 'complete')) {
			$boost_status_text = __('Idle', 'intropage');
		} elseif (substr_count($boost_status_array[0], 'running')) {
			$boost_status_text = __('Running', 'intropage');
		} elseif (substr_count($boost_status_array[0], 'overrun')) {
			$boost_status_text = __('Overrun Warning', 'intropage');
			$panel['alarm']   = 'red';
		} elseif (substr_count($boost_status_array[0], 'timeout')) {
			$boost_status_text = __('Timed Out', 'intropage');
			$panel['alarm']   = 'red';
		} else {
			$boost_status_text = __('Other');
		}
	} else {
		$boost_status_text = __('Never Run', 'intropage');
		$boost_status_date = '';
	}

	$panel['data'] = '<table class="cactiTable">';

	$stats_boost = read_config_option('stats_boost', true);

	if ($stats_boost != '') {
		$stats_boost_array = explode(' ', $stats_boost);

		$stats_duration          = explode(':', $stats_boost_array[0]);
		$boost_last_run_duration = $stats_duration[1];

		$stats_rrds         = explode(':', $stats_boost_array[1]);
		$boost_rrds_updated = $stats_rrds[1];
	} else {
		$boost_last_run_duration = '';
		$boost_rrds_updated      = '';
	}

	$panel['data'] .= '<tr><td>' . __('Boost Status is: %s', $rrd_updates == '' ? __('Disabled', 'intropage') : $boost_status_text, 'intropage') . '</td></tr>';

	$panel['data'] .= '<tr><td><hr></td></tr>';

	$panel['data'] .= '<tr><td>' . __('Processes/Frequency: %s / %s', number_format_i18n($parallel, -1), $rrd_updates == '' ? __('N/A') : $boost_refresh_interval[$update_int], 'intropage') . '</td></tr>';

	$panel['data'] .= '<tr><td>' . __('Pending Records Threshold: %s', number_format_i18n($max_records, -1), 'intropage') . '</td></tr>';

	if (is_numeric($next_run_time)) {
		$next_run_time = date('Y-m-d H-i:s', $next_run_time);
	}

	$panel['data'] .= '<tr><td>' . __('Approximate Next Start Time: %s', $next_run_time, 'intropage') . '</td></tr>';

	if ($total_records) {
		$panel['data'] .= '<tr><td>' . __('Pending/Archived Records: %s / %s', number_format_i18n($pending_records, -1), number_format_i18n($arch_records, -1), 'intropage') . '</td></tr>';

		if ($total_records > ($max_records - ($max_records / 10)) && $panel['alarm'] == 'green') {
			$panel['alarm'] = 'yellow';
		} elseif ($total_records > ($max_records - ($max_records / 20)) && $panel['alarm'] == 'green') {
			$panel['alarm'] = 'red';
		}
	}

	$data_length = db_fetch_cell("SELECT data_length
		FROM INFORMATION_SCHEMA.TABLES WHERE table_schema=SCHEMA()
		AND (table_name LIKE 'poller_output_boost_arch_%' OR table_name LIKE 'poller_output_boost')");

	$panel['data'] .= '<tr><td><hr></td></tr>';

	/* tell the user how big the table is */
	$panel['data'] .= '<tr><td>' . __('Current Boost Table(s) Size: %s', human_filesize($data_length), 'intropage') . '</td></tr>';

	/* tell the user about the average size/record */
	$panel['data'] .= '<tr><td>' . __('Avg Bytes/Record: %s', human_filesize($avg_row_length), 'intropage') . '</td></tr>';

	if (is_numeric($boost_last_run_duration)) {
		$lastduration = $boost_last_run_duration . ' s';
	} else {
		$lastduration = __('N/A');
	}

	$panel['data'] .= '<tr><td><hr></td></tr>';

	$panel['data'] .= '<tr><td>' . __('Last run duration/updates: %s / %s', $lastduration, $boost_rrds_updated != '' ? number_format_i18n($boost_rrds_updated, -1) : '-', 'intropage') . '</td></tr>';

	$panel['data'] .= '</table>';

	save_panel_result($panel, $user_id);
}

function extrem_trend() {
	// update in poller
	$users = get_user_list();

	foreach ($users as $user) {
		if (is_panel_allowed('trend', $user['id'])) {
			$allowed_devices = intropage_get_allowed_devices($user['id']);

			if ($allowed_devices !== false) {
				$count = db_fetch_cell('SELECT SUM(failed_polls)
					FROM host
					WHERE id IN (' . $allowed_devices . ')');

				db_execute_prepared('REPLACE INTO plugin_intropage_trends
					(name, value, user_id)
					VALUES (?, ?, ?)',
					array('failed_polls', $count, $user['id']));
			}
		}
	}
}

//------------------------------------ extrem -----------------------------------------------------
function extrem($panel, $user_id) {
	global $config;
	
	$lines = read_user_setting('intropage_number_of_lines', read_config_option('intropage_number_of_lines'), false, $user_id);
	$poller_interval = read_config_option('poller_interval');

	$panel['alarm'] = 'grey';

	$colums = array();
	$data   = array();
	$fin_data   = array();

	$console_access = get_console_access($user_id);

	if ($console_access) {
		$columns['poller'] = __('Poller Run', 'intropage');

		$data = db_fetch_assoc("SELECT date_format(time(cur_timestamp),'%H:%i') AS `date`,
			substring(value,instr(value,':')+1) AS xvalue
			FROM plugin_intropage_trends
			WHERE name='poller'
			AND cur_timestamp > date_sub(now(),interval 1 day)			
			ORDER BY xvalue desc, cur_timestamp
			LIMIT $lines");

		if (cacti_sizeof($data)) {
			foreach ($data as $key => $row) {
	                        if (($row['xvalue']/$poller_interval) > 0.9) {
        	                        $color = 'red';
                	        } elseif (($row['xvalue']/$poller_interval) > 0.7) {
                        	        $color = 'yellow';
                        	} else {
                        		$color = 'green';
                        	}
			
				$fin_data[$key]['poller'] = $row['date'] . ' ' . $row['xvalue'] . 's <span class="inpa_sq color_' . $color . '"></span>';
			}
		}
	}

	// max host down
	$columns['down'] = __('Down Hosts', 'intropage');

	$data = db_fetch_assoc_prepared("SELECT date_format(time(cur_timestamp),'%H:%i') AS `date`, value
		FROM plugin_intropage_trends
		WHERE name='host_down'
		AND user_id = ?
		AND cur_timestamp > date_sub(now(),interval 1 day)
		ORDER BY value desc,cur_timestamp
		LIMIT $lines",
		array($user_id));

	if (cacti_sizeof($data)) {
		foreach ($data as $key => $row) {

			if ($row['value'] > 0) {
				$fin_data[$key]['down'] = $row['date'] . ' ' . $row['value'] . ' <span class="inpa_sq color_red"></span>';
			} else {
				$fin_data[$key]['down'] = $row['date'] . ' ' . $row['value'];			
			}
		}
	}

	// max thold trig
	if (api_plugin_is_enabled('thold')) {
		$columns['thold'] = __('Triggered', 'intropage');

		$data = db_fetch_assoc_prepared("SELECT date_format(time(cur_timestamp),'%H:%i') AS `date`, value
			FROM plugin_intropage_trends
			WHERE name='thold_trig'
			AND user_id = ?
			AND cur_timestamp > date_sub(now(),interval 1 day)
			ORDER BY value desc,cur_timestamp
			LIMIT $lines",
			array($user_id));

		if (cacti_sizeof($data)) {
			foreach ($data as $key => $row) {
				if ($row['value'] > 0) {			
					$fin_data[$key]['thold'] = $row['date'] . ' ' . $row['value'] . ' <span class="inpa_sq color_red"></span>';
				} else {			
					$fin_data[$key]['thold'] = $row['date'] . ' ' . $row['value'] . ' <span class="inpa_sq color_green"></span>';
				}
			}
		}
	}

	// poller output items
	if ($console_access) {
		$columns['pout'] = __('Poller out. itms', 'intropage');

		$data = db_fetch_assoc("SELECT date_format(time(cur_timestamp),'%H:%i') AS `date`, value
			FROM plugin_intropage_trends
			WHERE name='poller_output'
			AND cur_timestamp > date_sub(now(),interval 1 day)
			ORDER BY value desc,cur_timestamp
			LIMIT $lines");

		if (cacti_sizeof($data)) {
			foreach ($data as $key => $row) {
				if ($row['value'] > 0) {
					$fin_data[$key]['pout'] = $row['date'] . ' ' . $row['value'] . ' <span class="inpa_sq color_red"></span>';
				} else {
					$fin_data[$key]['pout'] = $row['date'] . ' ' . $row['value'] . ' <span class="inpa_sq color_green"></span>';
				}
			}
		}
	}

	// failed polls
	if ($console_access) {
		$columns['failed'] = __('Failed', 'intropage');

		$data = db_fetch_assoc("SELECT date_format(time(cur_timestamp),'%H:%i') AS `date`, value
			FROM plugin_intropage_trends
			WHERE name = 'failed_polls'
			AND cur_timestamp > date_sub(now(), interval 1 day)
			ORDER BY value desc, cur_timestamp
			LIMIT $lines");

		if (cacti_sizeof($data)) {
			foreach ($data as $key => $row) {
				$fin_data[$key]['failed'] = $row['date'] . ' ' . $row['value'];
			}
		}
	}

	if (cacti_sizeof($fin_data)) {
		// Create table from data
		$panel['data'] = '<table class="cactiTable"><tr class="tableHeader">';

		foreach($columns as $col) {
			$panel['data'] .= '<th class="right">' . $col . '</th>';
		}

		$panel['data'] .= '</tr>';

		$i = 0;
		foreach($fin_data as $key => $rdata) {
			$panel['data'] .= '<tr class="' . ($i % 2 == 0 ? 'even':'odd') . '">';

			foreach($columns as $index => $col) {
				$panel['data'] .= '<td class="right">' . (isset($rdata[$index]) ? $rdata[$index]:'-') . '</td>';
			}

			$panel['data'] .= '</tr>';

			$i++;
		}

		$panel['data'] .= '</table>';
	} else {
		$panel['data'] .=  __('Waiting for data', 'intropage');
	}

	save_panel_result($panel, $user_id);
}

//------------------------------------ extrem -----------------------------------------------------
function extrem_detail() {
	global $config, $console_access;

	$lines = read_user_setting('intropage_number_of_lines', read_config_option('intropage_number_of_lines'));
	$poller_interval = read_config_option('poller_interval');
	
	$panel = array(
		'name'   => __('48 Hour Extreme Polling', 'intropage'),
		'alarm'  => 'grey',
		'detail' => '',
	);

	$trows   = array();
	$header  = array();

	$panel['detail'] .= '<table class="cactiTable">' .
		'<tr class="tableHeader">';

	// long run poller
	if (is_realm_allowed(8)) {
		$data = db_fetch_assoc("SELECT date_format(cur_timestamp,'%d.%m. %H:%i') AS `date`,
			substring(value,instr(value,':')+1) AS xvalue
			FROM plugin_intropage_trends
			WHERE name='poller'
			AND cur_timestamp > date_sub(now(), interval 2 day)
			ORDER BY xvalue desc, cur_timestamp
			LIMIT 25");

		if (cacti_sizeof($data)) {
			$j = 0;
			$header[] = __('Long Running Poller', 'intropage');

			$i = 0;
			foreach ($data as $row) {
	                        if (($row['xvalue']/$poller_interval) > 0.9) {
        	                        $color = 'red';
                	        } elseif (($row['xvalue']/$poller_interval) > 0.7) {
                        	        $color = 'yellow';
                        	} else {
                        		$color = 'green';
                        	}

				$trows[$i][$j] = $row['date'] . ' ' . $row['xvalue'] . 's <span class="inpa_sq color_' . $color . '"></span>';
				$i++;
			}
		}
	}

	// max host down
	$data = db_fetch_assoc_prepared ("SELECT date_format(cur_timestamp,'%d.%m. %H:%i') AS `date`, value
		FROM plugin_intropage_trends
		WHERE name='host_down'
		AND user_id =  ?
		AND cur_timestamp > date_sub(now(),interval 2 day)
		ORDER BY value desc,cur_timestamp
		LIMIT 25",
		array($_SESSION['sess_user_id']));

	if (cacti_sizeof($data)) {
		$j++;

		$header[] = __('Max Device Down', 'intropage');

		$i = 0;
		foreach ($data as $row) {
			if ($row['value'] > 0) {
				$trows[$i][$j]  = $row['date'] . ' ' . $row['value'] . ' <span class="inpa_sq color_red"></span>';
			} else {
				$trows[$i][$j]  = $row['date'] . ' ' . $row['value'];			
			}

			$i++;
		}
	}

	if (api_plugin_is_enabled('thold')) {

		$data = db_fetch_assoc_prepared("SELECT date_format(cur_timestamp,'%d.%m. %H:%i') AS `date`, value
			FROM plugin_intropage_trends
			WHERE name='thold_trig'
			AND user_id = ?
			AND cur_timestamp > date_sub(now(), interval 2 day)
			ORDER BY value desc,cur_timestamp
			LIMIT 25",
			array($_SESSION['sess_user_id']));

		if (cacti_sizeof($data)) {
			$j++;

			$header[] = __('Max Thold Triggered', 'intropage');

			$i = 0;
			foreach ($data as $row) {
				if ($row['value'] > 0) {			
					$trows[$i][$j] = $row['date'] . ' ' . $row['value'] . ' <span class="inpa_sq color_red"></span>';
				} else {			
					$trows[$i][$j] = $row['date'] . ' ' . $row['value'];
				}

				$i++;
			}
		}
	}

	if (is_realm_allowed(8)) {
		// poller output items
		$data = db_fetch_assoc("SELECT date_format(cur_timestamp,'%d.%m. %H:%i') AS `date`, value
			FROM plugin_intropage_trends
			WHERE name='poller_output'
			AND cur_timestamp > date_sub(now(),interval 2 day)
			ORDER BY value desc,cur_timestamp
			LIMIT 25");

		if (cacti_sizeof($data)) {
			$j++;

			$header[] = __('Poller Output Item', 'intropage');

			$i = 0;
			foreach ($data as $row) {
				if ($row['value'] > 0) {
					$trows[$i][$j] = $row['date'] . ' ' . $row['value'] . ' <span class="inpa_sq color_red"></span>';
				} else {
					$trows[$i][$j] = $row['date'] . ' ' . $row['value'];
				}

				$i++;
			}
		}

		// failed polls
		$data = db_fetch_assoc_prepared("SELECT date_format(cur_timestamp,'%d.%m. %H:%i') AS `date`, value
			FROM plugin_intropage_trends
			WHERE name='failed_polls'
			AND user_id=?
			AND cur_timestamp > date_sub(now(),interval 2 day)
			ORDER BY value desc,cur_timestamp
			LIMIT 25",
			array($_SESSION['sess_user_id']));

		if (cacti_sizeof($data)) {
			$j++;

			$header[] = __('Failed Polls', 'intropage');

			$i = 0;
			foreach ($data as $row) {
				$trows[$i][$j] = $row['date'] . ' ' . $row['value'];
				$i++;
			}
		}
	}

	foreach($header as $h) {
		$panel['detail'] .= '<th class="left">' . $h . '</th>';
	}

	$panel['detail'] .= '</tr>';

	for($k = 0; $k < $i; $k++) {
		$panel['detail'] .= '<tr>';
		for($l = 0; $l <= $j; $l++) {
			$panel['detail'] .= '<td class="left">' . (isset($trows[$k][$l]) ? $trows[$k][$l]:__('N/A', 'intropage')) . '</td>';
		}

		$panel['detail'] .= '</tr>';
	}

	$panel['detail'] .= '</table>';

	return $panel;
}

