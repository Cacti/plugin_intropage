<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2015-2019 Petr Macek                                      |
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

//------------------------------------ analyse_db -----------------------------------------------------
if (!function_exists('array_column')) {
    function array_column($array,$column_name) {
        return array_map(function($element) use($column_name){return $element[$column_name];}, $array);
    }
}

function intropage_analyse_db() {
	global $config;

	$result = array(
		'name' => __('Database check', 'intropage'),
		'alarm' => 'green',
		'data' => '', 
		'detail' => TRUE,
	);

	$result['alarm']  = db_fetch_cell("SELECT value FROM plugin_intropage_trends WHERE name='db_check_alarm'");
	$result['data']   = db_fetch_cell("SELECT value FROM plugin_intropage_trends WHERE name='db_check_result'");

	if (!$result['data']) {
	    $result['alarm'] = 'yellow';
	    $result['data'] = __('Waiting for data', 'intropage');
	}

	$result['data'] .= '<br/><br/>' . __('Last check', 'intropage') . ': ' . db_fetch_cell("SELECT value FROM plugin_intropage_trends WHERE name='db_check_testdate'") . '<br/>';
	$often = read_config_option('intropage_analyse_db_interval');
	if ($often == 900) {
	    $result['data'] .= __('Checked every 15 minutes', 'intropage');
	} elseif ($often == 3600) {
	    $result['data'] .= __('Checked hourly', 'intropage');
	} elseif ($often == 86400) {
	    $result['data'] .= __('Checked daily', 'intropage');
	} elseif ($often == 604800) {
	    $result['data'] .= __('Checked weekly', 'intropage');
	} elseif ($often == 2592000) {
	    $result['data'] .= __('Checked monthly', 'intropage');
	} else {
	    $result['data'] .= __('Periodic check is disabled', 'intropage');
	}

	$result['data'] .= '<br/><br/>';

	return $result;
}

//------------------------------------ analyse_log -----------------------------------------------------

function intropage_analyse_log() {
	global $config, $log;

	$result = array(
		'name' => __('Analyse cacti log', 'intropage'),
		'alarm' => 'green',
		'data' => '',
		'detail' => TRUE,
	);

	$log = array(
		'file' => read_config_option('path_cactilog'),
		'nbr_lines' => read_config_option('intropage_analyse_log_rows'),
	);

	$log['size']  = @filesize($log['file']);
	$log['lines'] = tail_log($log['file'], $log['nbr_lines']);

	if (!$log['size'] || empty($log['lines'])) {
		$result['alarm'] = 'red';
		$result['data'] .= __('Log file not accessible or empty', 'intropage');
	} else {
		$error  = 0;
		$ecount = 0;
		$warn   = 0;
		foreach ($log['lines'] as $line) {
			if (preg_match('/(WARN|ERROR|FATAL)/', $line, $matches)) {
				if (strcmp($matches[1], 'WARN') === 0) {
					$warn++;
					$ecount++;
				} elseif (strcmp($matches[1], 'ERROR') === 0 || strcmp($matches[1], 'FATAL') === 0) {
					$error++;
					$ecount++;
				}
			}
		}

		$result['data'] .= '<span class="txt_big">';
		$result['data'] .= __('Errors', 'intropage') . ': ' . $error . '</span><a href="clog.php?message_type=3&tail_lines=' . $log['nbr_lines'] . '"><i class="fa fa-external-link"></i></a><br/>';
		$result['data'] .= '<span class="txt_big">';
		$result['data'] .= __('Warnings', 'intropage') . ': ' . $warn . '</span><a href="clog.php?message_type=2&tail_lines=' . $log['nbr_lines'] . '"><i class="fa fa-external-link"></i></a><br/>';
		$result['data'] .= '</span>';

		if ($log['size'] < 0) {
			$result['alarm'] = 'red';
			$log_size_text   = __('file is larger than 2GB', 'intropage');
			$log_size_note   = '';
		} elseif ($log['size'] < 255999999) {
			$log_size_text   = human_filesize($log['size']);
			$log_size_note   = __('Log size OK');
		} else {
			$result['alarm'] = 'yellow';
			$log_size_text   = human_filesize($log['size']);
			$log_size_note   = __('Log size is quite large');
		}

		$result['data'] .= '<span class="txt_big">' . __('Log size', 'intropage') . ': ' . $log_size_text .'</span><br/>';
		if (!empty($log_size_note)) {
			$result['data'] .= '(' . $log_size_note . ')<br/>';
		}
		$result['data'] .= '<br/>' . __('(Errors and warning in last %s lines)', read_config_option('intropage_analyse_log_rows'), 'intropage');

		if ($error > 0) {
			$result['alarm'] = 'red';
		}

		if ($warn > 0 && $result['alarm'] == 'green') {
			$result['alarm'] = 'yellow';
		}
	}

	return $result;
}

//------------------------------------ analyse_login -----------------------------------------------------

function intropage_analyse_login() {
	global $config;

	$result = array(
		'name' => __('Analyse logins', 'intropage'),
		'alarm' => 'green',
		'data' => '',
		'detail' => TRUE,
	);

	// active users in last hour:
	$flog = db_fetch_cell('SELECT count(t.result) 
		FROM (SELECT result FROM user_auth
		         INNER JOIN user_log ON user_auth.username = user_log.username
		         ORDER BY user_log.time desc LIMIT 10) 
		as t where t.result=0;');

	if ($flog > 0) {
		$result['alarm'] = 'red';
	}

	$result['data'] = '<span class="txt_big">' . __('Failed logins', 'intropage') . ': ' . $flog . '</span><br/><br/>';

	// active users in last hour:
	$result['data'] .= '<b>Active users in last hour:</b><br/>';

	$sql_result = db_fetch_assoc('SELECT DISTINCT username
		FROM user_log
		WHERE time > adddate(now(), INTERVAL -1 HOUR)');

	foreach ($sql_result as $row) {
		$result['data'] .= $row['username'] . '<br/>';
	}

	return $result;
}

//------------------------------------ analyse_tree_host_graph  -----------------------------------------------------

function intropage_analyse_tree_host_graph() {
	global $config, $allowed_hosts;

	$result = array(
		'name' => __('Analyse tree/host/graph', 'intropage'),
		'alarm' => 'green',
		'data' => '',
		'detail' => TRUE,
	);

	$total_errors = 0;

	// hosts with same IP
	if ($allowed_hosts)	{
		$sql_result = db_fetch_assoc("SELECT COUNT(*) AS NoDups, id, hostname
		        FROM host
			WHERE id IN ($allowed_hosts)
	    		AND disabled != 'on'
			GROUP BY hostname,snmp_port
			HAVING NoDups > 1");

		$sql_count  = ($sql_result === false) ? __('N/A', 'intropage') : count($sql_result);

		if (cacti_sizeof($sql_result)) {
		        $total_errors += $sql_count;
			if (count($sql_result) > 0) {
			        $result['data'] .= __('Devices with the same IP and port: %s', $sql_count, 'intropage') . '<br/>';
				$result['alarm'] = 'red';
			}
		}
	}

	// same description
	if ($allowed_hosts)	{
		$sql_result = db_fetch_assoc("SELECT COUNT(*) AS NoDups, description
			FROM host
			WHERE id IN ($allowed_hosts)
			AND disabled != 'on'
			GROUP BY description
			HAVING NoDups > 1");

		$sql_count  = ($sql_result === false) ? __('N/A', 'intropage') : count($sql_result);

		if (cacti_sizeof($sql_result)) {
			$total_errors += $sql_count;
			if (count($sql_result) > 0) {
				$result['data'] .= __('Devices with the same description: %s', $sql_count, 'intropage') . '<br/>';
				$result['alarm'] = 'red';
			}
		}
	}

	// orphaned DS
	$sql_result = db_fetch_assoc('SELECT dtd.local_data_id, dtd.name_cache, dtd.active,
		dtd.rrd_step, dt.name AS data_template_name, dl.host_id,
		dtd.data_source_profile_id, COUNT(DISTINCT gti.local_graph_id) AS deletable
		FROM data_local AS dl
		INNER JOIN data_template_data AS dtd
		ON dl.id=dtd.local_data_id
		LEFT JOIN data_template AS dt
		ON dl.data_template_id=dt.id
		LEFT JOIN data_template_rrd AS dtr
		ON dtr.local_data_id=dtd.local_data_id
		LEFT JOIN graph_templates_item AS gti
		ON (gti.task_item_id=dtr.id)
		GROUP BY dl.id
		HAVING deletable=0
		ORDER BY `name_cache` ASC');

	$sql_count  = ($sql_result === false) ? __('N/A', 'intropage') : count($sql_result);

	if (cacti_sizeof($sql_result)) {
		$total_errors += $sql_count;
		$result['data'] .= __('Orphaned Data Sources: %s', $sql_count, 'intropage') . '<br/>';

		if ($result['alarm'] == 'green') {
			$result['alarm'] = 'yellow';
		}
	}


	// empty poller_output
	$count = db_fetch_cell("SELECT value FROM plugin_intropage_trends WHERE name = 'poller_output' ORDER BY cur_timestamp DESC LIMIT 1");

	if ($count>0) {
		$result['data'] .= __('Poller Output Items: %s', $count, 'intropage') . '<br/>';

		if ($result['alarm'] == 'green') {
			$result['alarm'] = 'yellow';
		}

		$total_errors += $count;
	}


	// DS - bad indexes
	$sql_result = db_fetch_assoc('SELECT dtd.local_data_id,dtd.name_cache
		FROM data_local AS dl
		INNER JOIN data_template_data AS dtd
		ON dl.id=dtd.local_data_id
		INNER JOIN data_template AS dt ON dt.id=dl.data_template_id
		INNER JOIN host AS h ON h.id = dl.host_id
		WHERE (dl.snmp_index = "" AND dl.snmp_query_id > 0)');

	$sql_count  = ($sql_result === false) ? __('N/A', 'intropage') : count($sql_result);

	if (cacti_sizeof($sql_result)) {
		$result['data'] .= __('Datasource - bad indexes: %s', $sql_count, 'intropage') . '<br/>';

		if ($result['alarm'] == 'green') {
			$result['alarm'] = 'yellow';
		}

		$total_errors += $sql_count;
	}
	
	// thold plugin - logonly alert and warning thold
	if (db_fetch_cell("SELECT directory FROM plugin_config WHERE directory='thold' and status=1")) {

	    $sql_result = db_fetch_assoc("SELECT td.id AS td_id, concat(h.description,'-',tt.name) AS td_name,  
		uap0.user_id AS user0, uap1.user_id AS user1, uap2.user_id AS user2
		FROM thold_data AS td
		INNER JOIN graph_local AS gl ON gl.id=td.local_graph_id
		LEFT JOIN graph_templates AS gt ON gt.id=gl.graph_template_id
		LEFT JOIN host AS h ON h.id=gl.host_id
		LEFT JOIN thold_template AS tt ON tt.id=td.thold_template_id
		LEFT JOIN data_template_data AS dtd ON dtd.local_data_id=td.local_data_id
		LEFT JOIN data_template_rrd AS dtr ON dtr.id=td.data_template_rrd_id
		LEFT JOIN user_auth_perms AS uap0 ON (gl.id=uap0.item_id AND uap0.type=1)
		LEFT JOIN user_auth_perms AS uap1 ON (gl.host_id=uap1.item_id AND uap1.type=3)
		LEFT JOIN user_auth_perms AS uap2 ON (gl.graph_template_id=uap2.item_id AND uap2.type=4)
		LEFT JOIN plugin_thold_threshold_contact as con ON (td.id = con.thold_id)
		WHERE
		    td.thold_enabled = 'on' AND
		    (td.notify_warning is NULL or td.notify_warning=0) AND
		    (td.notify_alert is NULL or td.notify_alert =0) AND
		    (td.notify_extra ='' or td.notify_extra is NULL) AND
		    (td.notify_warning_extra='' or td.notify_warning_extra is NULL)
		    AND con.contact_id IS NULL
		    HAVING (user0 IS NULL OR (user1 IS NULL OR user2 IS NULL))");

	    $sql_count  = ($sql_result === false) ? __('N/A', 'intropage') : count($sql_result);

	    if (cacti_sizeof($sql_result)) {
		$result['data'] .= __('Thold logonly alert/warning: %s', $sql_count, 'intropage') . '<br/>';

		if ($result['alarm'] == 'green') {
		    $result['alarm'] = 'yellow';
		}

		$total_errors += $sql_count;
	    }
	}

	
	// below - only information without red/yellow/green
	$result['data'] .= '<br/><b>' . __('Information only (no warn/error)') . ':</b><br/>';

	// device in more trees
	$sql_result = db_fetch_assoc('SELECT host.id, host.description, COUNT(*) AS `count`
		FROM host
		INNER JOIN graph_tree_items
		ON (host.id = graph_tree_items.host_id)
		WHERE id IN ($allowed_hosts)
		GROUP BY description
		HAVING `count` > 1');

	$sql_count  = ($sql_result === false) ? __('N/A', 'intropage') : count($sql_result);

	if (cacti_sizeof($sql_result)) {
		$result['data'] .= __('Devices in more than one tree: %s', $sql_count, 'intropage') . '<br/>';
	}

	// host without graph
	if ($allowed_hosts)	{
		$sql_result = db_fetch_assoc("SELECT id, description
			FROM host
			WHERE id IN ($allowed_hosts)
			AND disabled != 'on'
			AND id NOT IN (
				SELECT DISTINCT host_id
				FROM graph_local
			)
			AND snmp_version != 0");

		$sql_count  = ($sql_result === false) ? __('N/A', 'intropage') : count($sql_result);

		if (cacti_sizeof($sql_result)) {
			$result['data'] .= __('Hosts without graphs: %s', $sql_count, 'intropage') . '<br/>';
		}
	}

	// host without tree
	if ($allowed_hosts)	{
		$sql_result = db_fetch_assoc("SELECT id, description
			FROM host
			WHERE id IN ($allowed_hosts)
			AND disabled != 'on'
			AND id NOT IN (
				SELECT DISTINCT host_id
				FROM graph_tree_items
			)");

		$sql_count  = ($sql_result === false) ? __('N/A', 'intropage') : count($sql_result);

		if (cacti_sizeof($sql_result)) {
			$result['data'] .= __('Hosts without tree: %s', $sql_count, 'intropage') . '<br/>';
		}
	}

	// public/private community
	if ($allowed_hosts)	{
		$sql_result = db_fetch_assoc("SELECT id, description
			FROM host
			WHERE id IN ($allowed_hosts)
			AND disabled != 'on'
			AND (snmp_community ='public' OR snmp_community='private')
			ORDER BY description");

		$sql_count  = ($sql_result === false) ? __('N/A', 'intropage') : count($sql_result);

		if (cacti_sizeof($sql_result)) {
			$result['data'] .= __('Hosts with default public/private community: %s', $sql_count, 'intropage') . '<br/>';
		}
	}

	// plugin monitor - host without monitoring
	if (db_fetch_cell("SELECT directory FROM plugin_config WHERE directory='monitor'")) { // installed plugin monitor?
		if ($allowed_hosts)	{
			$sql_result = db_fetch_assoc("SELECT id, description, hostname
				FROM host
				WHERE id IN ($allowed_hosts)
				AND monitor != 'on'");

			$sql_count  = ($sql_result === false) ? __('N/A', 'intropage') : count($sql_result);

			if (cacti_sizeof($sql_result)) {
				$result['data'] .= __('Plugin Monitor - Unmonitored hosts: %s', $sql_count, 'intropage') . '</b><br/>';
			}
		}
	}

	if ($total_errors > 0) {
		$result['data'] = '<span class="txt_big">' . __('Found %s problems', $total_errors, 'intropage') . '</span><br/>' . $result['data'];
	} else {
		$result['data'] = '<span class="txt_big">' . __('Everything OK', 'intropage') . '</span><br/>' . $result['data'];
	}

	return $result;
}

//------------------------------------ boost -----------------------------------------------------

function intropage_boost() {
	global $config, $boost_refresh_interval, $boost_max_runtime;

	$result = array(
		'name' => __('Boost statistics'),
		'alarm' => 'green',
		'data' => '',
		'detail' => FALSE,
	);

	// from lib/boost.php
	$rrd_updates     = read_config_option('boost_rrd_update_enable', true);
	$last_run_time   = read_config_option('boost_last_run_time', true);
	$next_run_time   = read_config_option('boost_next_run_time', true);

	$max_records     = read_config_option('boost_rrd_update_max_records', true);
	$max_runtime     = read_config_option('boost_rrd_update_max_runtime', true);
	$update_interval = read_config_option('boost_rrd_update_interval', true);
	$peak_memory     = read_config_option('boost_peak_memory', true);
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
			$boost_status_text = __('Idle');
		} elseif (substr_count($boost_status_array[0], 'running')) {
			$boost_status_text = __('Running');
		} elseif (substr_count($boost_status_array[0], 'overrun')) {
			$boost_status_text = __('Overrun Warning');
			$result['alarm']   = 'red';
		} elseif (substr_count($boost_status_array[0], 'timeout')) {
			$boost_status_text = __('Timed Out');
			$result['alarm']   = 'red';
		} else {
			$boost_status_text = __('Other');
		}
	} else {
		$boost_status_text = __('Never Run');
		$boost_status_date = '';
	}

	if ($total_records) {
		$result['data'] .= __('Pending Boost Records: %s', number_format_i18n($pending_records, -1), 'intropage') . '<br/>';
		$result['data'] .= __('Archived Boost Records: %s', number_format_i18n($arch_records, -1), 'intropage') . '<br/>';

		if ($total_records > ($max_records - ($max_records / 10)) && $result['alarm'] == 'green') {
			$result['alarm'] = 'yellow';
			$result['data'] .= '<b>' . __('Total Boost Records: %s', number_format_i18n($total_records, -1), 'intropage') . '</b><br/>';
		} elseif ($total_records > ($max_records - ($max_records / 20)) && $result['alarm'] == 'green') {
			$result['alarm'] = 'red';
			$result['data'] .= '<b>' . __('Total Boost Records: %s', number_format_i18n($total_records, -1), 'intropage') . '</b><br/>';
		} else {
			$result['data'] .= __('Total Boost Records: %s', number_format_i18n($total_records, -1), 'intropage') . '<br/>';
		}
	}

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


	$result['data'] .= __('Boost On-demand Updating: %s', $rrd_updates == '' ? __('Disabled', 'intropage') : $boost_status_text, 'intropage') . '<br/>';

	$data_length = db_fetch_cell("SELECT data_length
		FROM INFORMATION_SCHEMA.TABLES WHERE table_schema=SCHEMA()
		AND (table_name LIKE 'poller_output_boost_arch_%' OR table_name LIKE 'poller_output_boost')");

	/* tell the user how big the table is */
	$result['data'] .= __('Current Boost Table(s) Size: %s', human_filesize($data_length), 'intropage') . '<br/>';

	/* tell the user about the average size/record */
	$result['data'] .= __('Avg Bytes/Record: %s', human_filesize($avg_row_length), 'intropage') . '<br/>';

	if (is_numeric($boost_last_run_duration)) {
		$lastduration = $boost_last_run_duration . ' s';
	} else {
		$lastduration = __('N/A');
	}
	$result['data'] .= __('Last run duration: %s', $lastduration, 'intropage') . '<br/>';

	$result['data'] .= __('RRD Updates / Max: %s / %s', $boost_rrds_updated != '' ? number_format_i18n($boost_rrds_updated, -1) : '-', number_format_i18n($max_records, -1), 'intropage')  . '<br/>';
	$result['data'] .= __('Update Frequency: %s', $rrd_updates == '' ? __('N/A') : $boost_refresh_interval[$update_interval], 'intropage') . '<br/>';
	$result['data'] .= __('Next Start Time: %s', $next_run_time, 'intropage') . '<br/>';

	return $result;
}

//------------------------------------ cpu -----------------------------------------------------

function intropage_cpu() {
	global $config;

	$result = array(
		'name' => __('CPU utilization', 'intropage'),
		'alarm' => 'grey',
		'data' => '',
		'detail' => FALSE,
		'line' => array(
			'title' => __('CPU load: ', 'intropage'),
			'label1' => array(),
			'data1' => array(),
		),
	);

	if (stristr(PHP_OS, 'win')) {
		$result['data'] = __('This function is not implemented on Windows platforms', 'intropage');
		unset($result['line']);
	} else {
		$sql = db_fetch_assoc("SELECT date_format(time(cur_timestamp),'%H:%i') AS `date`, name, value
			FROM plugin_intropage_trends
			WHERE name='cpuload'
			ORDER BY cur_timestamp desc
			LIMIT 10");

		if (cacti_sizeof($sql)) {
			$result['line']['title1'] = __('Load', 'intropage');

			foreach ($sql as $row) {
				array_push($result['line']['label1'], $row['date']);
				array_push($result['line']['data1'], $row['value']);
			}

			$result['line']['data1']  = array_reverse($result['line']['data1']);
			$result['line']['label1'] = array_reverse($result['line']['label1']);
		} else {
			unset($result['line']);

			$result['data'] = __('Waiting for data', 'intropage');
		}
	}

	return $result;
}

//------------------------------------ extrem -----------------------------------------------------

function intropage_extrem() {
	global $config, $console_access;

	$result = array(
		'name' => __('24 hour extrem', 'intropage'),
		'alarm' => 'grey',
		'data' => '',
		'detail' => TRUE,
	);

	$result['data'] .= '<table><tr><td class="rpad">';

	// long run poller
	$result['data'] .= '<strong>' . __('Long run<br/>poller', 'intropage') . ': </strong>';

	$sql_result = db_fetch_assoc("SELECT date_format(time(cur_timestamp),'%H:%i') AS `date`,
		substring(value,instr(value,':')+1) AS xvalue
		FROM plugin_intropage_trends
		WHERE name='poller'
		AND cur_timestamp > date_sub(now(),interval 1 day)
		ORDER BY xvalue desc, cur_timestamp
		LIMIT 8");

	if (cacti_sizeof($sql_result)) {
		foreach ($sql_result as $row) {
			$result['data'] .= '<br/>' . $row['date'] . ' ' . $row['xvalue'] . 's';
		}
	} 
	else {
		$result['data'] .= '<br/>' . __('Waiting<br/>for data', 'intropage');
	}

	$result['data'] .= '</td>';

	// max host down
	$result['data'] .= '<td class="rpad texalirig">';
	$result['data'] .= '<strong>Max host<br/>down: </strong>';

	$sql_result = db_fetch_assoc("SELECT date_format(time(cur_timestamp),'%H:%i') AS `date`, value
		FROM plugin_intropage_trends
		WHERE name='host'
		AND cur_timestamp > date_sub(now(),interval 1 day)
		ORDER BY value desc,cur_timestamp
		LIMIT 8");

	if (cacti_sizeof($sql_result)) {
		foreach ($sql_result as $row) {
			$result['data'] .= '<br/>' . $row['date'] . ' ' . $row['value'];
		}
	} 
	else {
		$result['data'] .= '<br/>' . __('Waiting<br/>for data', 'intropage');
	}

	$result['data'] .= '</td>';

	// max thold trig
	// extrems doesn't use user permission!
	$result['data'] .= '<td class="rpad texalirig">';
	$result['data'] .= '<strong>' . __('Max thold<br/>triggered:', 'intropage') .'</strong>';

	if (db_fetch_cell("SELECT directory FROM plugin_config WHERE directory='thold' and status=1")) {
		$sql_result = db_fetch_assoc("SELECT date_format(time(cur_timestamp),'%H:%i') AS `date`, value
			FROM plugin_intropage_trends
			WHERE name='thold'
			AND cur_timestamp > date_sub(now(),interval 1 day)
			ORDER BY value desc,cur_timestamp
			LIMIT 8");

		if (cacti_sizeof($sql_result)) {
			foreach ($sql_result as $row) {
				$result['data'] .= '<br/>' . $row['date'] . ' ' . $row['value'];
			}
		} 
		else {
			$result['data'] .= '<br/>' . __('Waiting<br/>for data', 'intropage');
		}
	} else {
		$result['data'] .= '<br/>no<br/>plugin<br/>installed<br/>or<br/>running';
	}

	$result['data'] .= '</td>';

	// poller output items
	$result['data'] .= '<td class="rpad texalirig">';
	$result['data'] .= '<strong>' . __('Poller<br/>output item:', 'intropage') . '</strong>';

	$sql_result = db_fetch_assoc("SELECT date_format(time(cur_timestamp),'%H:%i') AS `date`, value
		FROM plugin_intropage_trends
		WHERE name='poller_output'
		AND cur_timestamp > date_sub(now(),interval 1 day)
		ORDER BY value desc,cur_timestamp
		LIMIT 8");

	if (cacti_sizeof($sql_result)) {
		foreach ($sql_result as $row) {
			$result['data'] .= '<br/>' . $row['date'] . ' ' . $row['value'];
		}
	} 
	else {
		$result['data'] .= '<br/>' . __('Waiting<br/>for data', 'intropage');
	}

	$result['data'] .= '</td>';

	// poller output items
	$result['data'] .= '<td class="rpad texalirig">';
	$result['data'] .= '<strong>' . __('Failed<br/>polls:', 'intropage') . '</strong>';

	$sql_result = db_fetch_assoc("SELECT date_format(time(cur_timestamp),'%H:%i') AS `date`, value
		FROM plugin_intropage_trends
		WHERE name='failed_polls'
		AND cur_timestamp > date_sub(now(),interval 1 day)
		ORDER BY value desc,cur_timestamp
		LIMIT 8");

	if (cacti_sizeof($sql_result)) {
		foreach ($sql_result as $row) {
			$result['data'] .= '<br/>' . $row['date'] . ' ' . $row['value'];
		}
	} 
	else {
		$result['data'] .= '<br/>' . __('Waiting<br/>for data', 'intropage');
	}
	$result['data'] .= '</td>';

	$result['data'] .= '</tr></table>';

	return $result;
}

//------------------------------------ graph_datasource -----------------------------------------------------

function intropage_graph_data_source() {
	global $config, $input_types;

	$result = array(
		'name' => 'Data sources',
		'alarm' => 'grey',
		'data' => '',
		'detail' => TRUE,
		'pie' => array(
			'title' => __('Datasources: ', 'intropage'),
			'label' => array(),
			'data' => array(),
		),
	);

	$sql_ds = db_fetch_assoc('SELECT data_input.type_id, COUNT(data_input.type_id) AS total
		FROM data_local
		INNER JOIN data_template_data
		ON (data_local.id = data_template_data.local_data_id)
		LEFT JOIN data_input
		ON (data_input.id=data_template_data.data_input_id)
		LEFT JOIN data_template
		ON (data_local.data_template_id=data_template.id)
		WHERE local_data_id<>0
		GROUP BY type_id
		LIMIT 6');

	if (cacti_sizeof($sql_ds)) {
		foreach ($sql_ds as $item) {
			if (!is_null($item['type_id'])) {
				array_push($result['pie']['label'], preg_replace('/script server/', 'SS', $input_types[$item['type_id']]));
				array_push($result['pie']['data'], $item['total']);

				$result['data'] .= preg_replace('/script server/', 'SS', $input_types[$item['type_id']]) . ': ';
				$result['data'] .= $item['total'] . '<br/>';
			}
		}
	} else {
		$result['data'] = __('No untemplated datasources found');
		unset($result['pie']);
	}

	return $result;
}

//------------------------------------ graph_host -----------------------------------------------------

function intropage_graph_host() {
	global $config, $allowed_hosts, $console_access;

	$result = array(
		'name' => __('Hosts', 'intropage'),
		'alarm' => 'green',
		'data' => '',
		'detail' => TRUE,
	);

	if ($allowed_hosts)	{
		$h_all  = db_fetch_cell("SELECT count(id) FROM host WHERE id IN ($allowed_hosts)");
		$h_up   = db_fetch_cell("SELECT count(id) FROM host WHERE id IN ($allowed_hosts) AND status=3 AND disabled=''");
		$h_down = db_fetch_cell("SELECT count(id) FROM host WHERE id IN ($allowed_hosts) AND status=1 AND disabled=''");
		$h_reco = db_fetch_cell("SELECT count(id) FROM host WHERE id IN ($allowed_hosts) AND status=2 AND disabled=''");
		$h_disa = db_fetch_cell("SELECT count(id) FROM host WHERE id IN ($allowed_hosts) AND disabled='on'");

		$count = $h_all + $h_up + $h_down + $h_reco + $h_disa;
		$url_prefix = $console_access ? '<a href="' . htmlspecialchars($config['url_path']) . 'host.php?host_status=%s">' : '';
		$url_suffix = $console_access ? '</a>' : '';

		$result['data']  = sprintf($url_prefix,'-1') . __('All', 'intropage') . ": $h_all$url_suffix<br/>";
		$result['data'] .= sprintf($url_prefix,'=3') . __('Up', 'intropage') . ": $h_up$url_suffix<br/>";
		$result['data'] .= sprintf($url_prefix,'=1') . __('Down', 'intropage') . ": $h_down$url_suffix<br/>";
		$result['data'] .= sprintf($url_prefix,'=-2') . __('Disabled', 'intropage') . ": $h_disa$url_suffix<br/>";
		$result['data'] .= sprintf($url_prefix,'=2') . __('Recovering', 'intropage') . ": $h_reco$url_suffix";

		if ($count > 0) {
			$result['pie'] = array(
				'title' => __('Hosts', 'intropage'),
				'label' => array(
					__('Up', 'intropage'),
					__('Down', 'intropage'),
					__('Recovering', 'intropage'),
					__('Disabled', 'intropage'),
				),
				'data' => array($h_up, $h_down, $h_reco, $h_disa)
			);
		} else {
			unset($result['pie']);
		}

		// alarms and details
		if ($h_reco > 0) {
			$result['alarm'] = 'yellow';
		}

		if ($h_down > 0) {
			$result['alarm'] = 'red';
		}
	}
	else	{
	    $result['detail'] = FALSE;
	    $result['data'] = __('You don\'t have permissions to any hosts', 'intropage');
	}

	return $result;
}

//------------------------------------ graph host_template -----------------------------------------------------

function intropage_graph_host_template() {
	global $config, $allowed_hosts;

	$result = array(
		'name' => __('Device Templates', 'intropage'),
		'alarm' => 'grey',
		'data' => '',
		'detail' => TRUE,
		'pie' => array(
			'title' => __('Device Templates', 'intropage'),
			'label' => array(),
			'data' => array(),
		),
	);
	
	if ($allowed_hosts)	{
		$sql_ht = db_fetch_assoc("SELECT host_template.id as id, name, count(host.host_template_id) AS total
			FROM host_template
			LEFT JOIN host
			ON (host_template.id = host.host_template_id) AND host.id IN ($allowed_hosts)
			GROUP by host_template_id
			ORDER BY total desc
			LIMIT 6");

		if (cacti_sizeof($sql_ht)) {
			foreach ($sql_ht as $item) {
				array_push($result['pie']['label'], substr($item['name'],0,15));
				array_push($result['pie']['data'], $item['total']);

				$result['data'] .= $item['name'] . ': ';
				$result['data'] .= $item['total'] . '<br/>';
			}
		} else {
			$result['data'] = __('No device templates found', 'intropage');
		}
	}
	else	{
	    unset($result['pie']);
	    $result['detail'] = FALSE;
	    $result['data'] = __('You don\'t have permissions to any hosts', 'intropage');
	}

	return $result;
}

//------------------------------------ graph_thold -----------------------------------------------------

function intropage_graph_thold() {
	global $config, $sql_where;

	$result = array(
		'name' => __('Thresholds', 'intropage'),
		'data' => '',
		'alarm' => 'green',
		'detail' => TRUE,
		'pie' => array(
			'title' => __('Thresholds', 'intropage'),
			'label' => array(),
			'data' => array(),
		),
	);

	if (!db_fetch_cell("SELECT directory FROM plugin_config WHERE directory='thold' and status=1")) {
		$result['alarm'] = 'grey';
		$result['data']  = __('Thold plugin not installed/running', 'intropage');
		unset($result['pie']);
	} elseif (!db_fetch_cell('SELECT DISTINCT user_id FROM user_auth_realm WHERE user_id = ' . $_SESSION['sess_user_id'] . " AND realm_id IN (SELECT id + 100 FROM plugin_realms WHERE file LIKE '%thold%')")) {
		$result['data'] = __('You don\'t have plugin permission', 'intropage');
		unset($result['pie']);
	} else {
		// need for thold - isn't any better solution?
		$current_user  = db_fetch_row('SELECT * FROM user_auth WHERE id=' . $_SESSION['sess_user_id']);
   		$sql_where = get_graph_permissions_sql($current_user['policy_graphs'], $current_user['policy_hosts'], $current_user['policy_graph_templates']);

		$sql_join = ' LEFT JOIN host ON thold_data.host_id=host.id     LEFT JOIN user_auth_perms ON ((thold_data.graph_template_id=user_auth_perms.item_id AND user_auth_perms.type=1 AND user_auth_perms.user_id= ' . $_SESSION['sess_user_id'] . ') OR
			(thold_data.host_id=user_auth_perms.item_id AND user_auth_perms.type=3 AND user_auth_perms.user_id= ' . $_SESSION['sess_user_id'] . ') OR
			(thold_data.graph_template_id=user_auth_perms.item_id AND user_auth_perms.type=4 AND user_auth_perms.user_id= ' . $_SESSION['sess_user_id'] . '))';

		$t_all  = db_fetch_cell("SELECT COUNT(*) FROM thold_data $sql_join WHERE $sql_where");
		$t_brea = db_fetch_cell("SELECT COUNT(*) FROM thold_data $sql_join WHERE (thold_data.thold_alert!=0 OR thold_data.bl_alert>0) AND $sql_where");
		$t_trig = db_fetch_cell("SELECT COUNT(*) FROM thold_data $sql_join WHERE (thold_data.thold_alert!=0 OR thold_data.bl_fail_count >= thold_data.bl_fail_trigger) AND $sql_where");
		$t_trig = db_fetch_cell("SELECT COUNT(*) FROM thold_data $sql_join WHERE ((thold_data.thold_alert!=0 AND thold_data.thold_fail_count >= thold_data.thold_fail_trigger) OR (thold_data.bl_alert>0 AND thold_data.bl_fail_count >= thold_data.bl_fail_trigger)) AND $sql_where");

		$t_disa = db_fetch_cell("SELECT COUNT(*) FROM thold_data $sql_join WHERE thold_data.thold_enabled='off' AND $sql_where");

		$count = $t_all + $t_brea + $t_trig + $t_disa;

		$has_access = db_fetch_cell('SELECT COUNT(*) FROM user_auth_realm WHERE user_id = '.$_SESSION['sess_user_id']." AND realm_id IN (SELECT id + 100 FROM plugin_realms WHERE file LIKE '%thold_graph.php%')");
		$url_prefix = $has_access ? '<a href="' . htmlspecialchars($config['url_path']) . 'plugins/thold/thold_graph.php?tab=thold&amp;triggered=%s\">' : '';
		$url_suffix = $has_access ? '</a>' : '';

		$result['data']  = sprintf($url_prefix, '-1') . __('All', 'intropage') . ": $t_all$url_suffix<br/>";
		$result['data'] .= sprintf($url_prefix, '1') . __('Breached', 'intropage') . ": $t_brea$url_suffix<br/>";
		$result['data'] .= sprintf($url_prefix, '3') . __('Trigged', 'intropage') . ": $t_trig$url_suffix<br/>";
		$result['data'] .= sprintf($url_prefix, '0') . __('Disabled', 'intropage') . ": $t_disa$url_suffix<br/>";

		if ($count > 0) {
			$result['pie'] = array(
				'title' => __('Thresholds', 'intropage'),
				'label' => array(
					__('OK', 'intropage'),
					__('Triggered', 'intropage'),
					__('Breached', 'intropage'),
					__('Disabled', 'intropage'),
				),
				'data' => array($t_all - $t_brea - $t_trig - $t_disa, $t_brea, $t_trig, $t_disa));
		} else {
			unset($result['pie']);
		}

		// alarms and details
		if ($t_brea > 0) {
			$result['alarm'] = 'yellow';
		}

		if ($t_trig > 0) {
			$result['alarm'] = 'red';
		}
	}


	return $result;
}

//------------------------------------ info -----------------------------------------------------

function intropage_info() {
	global $config, $poller_options;

	$result = array(
		'name' => 'Info',
		'alarm' => 'grey',
		'data' => '',
		'detail' => FALSE,
	);

	$xdata = '';

	$result['data'] .= __('Cacti version: ', 'intropage') . CACTI_VERSION . '<br/>';

	if ($poller_options[read_config_option('poller_type')] == 'spine' && file_exists(read_config_option('path_spine')) && (function_exists('is_executable')) && (is_executable(read_config_option('path_spine')))) {
		$spine_version = 'SPINE';

		exec(read_config_option('path_spine') . ' --version', $out_array);

		if (sizeof($out_array)) {
			$spine_version = $out_array[0];
		}

		$result['data'] .= __('Poller type:', 'intropage') .' <a href="' . htmlspecialchars($config['url_path']) .  'settings.php?tab=poller">Spine</a><br/>';

		$result['data'] .= __('Spine version: ', 'intropage') . $spine_version . '<br/>';

		if (!strpos($spine_version, CACTI_VERSION, 0)) {
			$result['data'] .= '<span class="red">' . __('You are using incorrect spine version!', 'intropage') . '</span><br/>';
			$result['alarm'] = 'red';
		}
	} else {
		$result['data'] .= __('Poller type: ', 'intropage') . ' <a href="' . htmlspecialchars($config['url_path']) .  'settings.php?tab=poller">' . $poller_options[read_config_option('poller_type')] . '</a><br/>';
	}

	$result['data'] .= __('Running on: ', 'intropage');
	if (function_exists('php_uname')) {
		$xdata = php_uname();
	} else {
		$xdata .= PHP_OS;
	}

	$xdata2 = str_split($xdata, 50);
	$xdata  = join('<br/>', $xdata2);
	$result['data'] .= $xdata;

	return $result;
}

//------------------------------------ mactrack -----------------------------------------------------

function intropage_mactrack() {
	global $config, $console_access;

	$result = array(
		'name' => __('Mactrack', 'intropage'),
		'alarm' => 'green',
		'data' => '',
		'detail' => FALSE,
	);

	// SELECT id from plugin_realms WHERE plugin='mactrack' and display like '%view%';
	// = 329 +100

	if (!db_fetch_cell("SELECT directory FROM plugin_config WHERE directory='mactrack' AND status=1")) {		$result['alarm'] = 'grey';
		$result['data']  = __('Mactrack plugin not installed/running', 'intropage');
		
	} else {
		$mactrack_id = db_fetch_cell("SELECT id
			FROM plugin_realms
			WHERE plugin='mactrack'
			AND display LIKE '%view%'");

		if (!db_fetch_cell('SELECT DISTINCT user_id FROM user_auth_realm WHERE user_id = '.$_SESSION['sess_user_id'].' AND realm_id =' . ($mactrack_id + 100))) {
			$result['data'] =  __('You don\'t have plugin permission', 'intropage');
		} else {
			// mactrack is running and you have permission
			$m_all  = db_fetch_cell('SELECT COUNT(host_id) FROM mac_track_devices');
			$m_up   = db_fetch_cell("SELECT COUNT(host_id) FROM mac_track_devices WHERE snmp_status='3'");
			$m_down = db_fetch_cell("SELECT COUNT(host_id) FROM mac_track_devices WHERE snmp_status='1'");
			$m_disa = db_fetch_cell("SELECT COUNT(host_id) FROM mac_track_devices WHERE snmp_status='-2'");
			$m_err  = db_fetch_cell("SELECT COUNT(host_id) FROM mac_track_devices WHERE snmp_status='4'");
			$m_unkn = db_fetch_cell("SELECT COUNT(host_id) FROM mac_track_devices WHERE snmp_status='0'");

			if ($m_down > 0 || $m_err > 0 || $m_unkn > 0) {
				$result['alarm'] = 'red';
			} elseif ($m_disa > 0) {
				$result['alarm'] = 'yellow';
			}

			$result['data']  = __('All: %s', $m_all, 'intropage')       . ' | ';
			$result['data'] .= __('Up: %s', $m_up, 'intropage')         . ' | ';
			$result['data'] .= __('Down: %s', $m_down, 'intropage')     . ' | ';
			$result['data'] .= __('Error: %s', $m_err, 'intropage')     . ' | ';
			$result['data'] .= __('Unknown: %s', $m_unkn, 'intropage')  . ' | ';
			$result['data'] .= __('Disabled: %s', $m_disa, 'intropage') . ' | ';

			$result['pie'] = array(
				'title' => __('Mactrack', 'intropage'),
				'label' => array(
					__('Up', 'intropage'),
					__('Down', 'intropage'),
					__('Error', 'intropage'),
					__('Unknown', 'intropage'),
					__('Disabled', 'intropage'),
				),
				'data' => array($m_up, $m_down, $m_err, $m_unkn, $m_disa));
		}
	}

	return $result;
}

//------------------------------------ mactrack sites -----------------------------------------------------

function intropage_mactrack_sites() {
	global $config, $console_access;

	$result = array(
		'name' => __('Mactrack sites', 'intropage'),
		'alarm' => 'grey',
		'data' => '',
		'detail' => TRUE,

	);

	if (!db_fetch_cell("SELECT directory FROM plugin_config WHERE directory='mactrack' AND status=1")) {
		$result['alarm'] = 'grey';
		$result['data']  = __('Mactrack plugin not installed/running', 'intropage');
		$result['detail'] = FALSE;
	} else {
		if (!db_fetch_cell('SELECT DISTINCT user_id FROM user_auth_realm WHERE user_id = '.$_SESSION['sess_user_id'].' AND realm_id =' . ($mactrack_id + 100))) {
		    	$result['data'] =  __('You don\'t have plugin permission', 'intropage');
		}
		else	{
			$result['data'] .= '<table><tr><td class="rpad">' . __('Site', 'intropage') . '</td><td class="rpad">' . __('Devices', 'intropage') . '</td>';
			$result['data'] .= '<td class="rpad">' . __('IPs', 'intropage') . '</td><td class="rpad">' . __('Ports', 'intropage') . '</td>';
			$result['data'] .= '<td class="rpad">' . __('Ports up', 'intropage') . '</td><td class="rpad">' . __('MACs', 'intropage') . '</td>';
			$result['data'] .= '<td class="rpad">' . __('Device errors', 'intropage') . '</td></tr>';

			$sql_result = db_fetch_assoc('SELECT site_name, total_devices, total_device_errors, total_macs, total_ips, total_oper_ports, total_user_ports FROM mac_track_sites  order by total_devices desc limit 8');
			if (sizeof($sql_result) > 0) {
				foreach ($sql_result as $site) {
					$row = '<tr><td>' . $site['site_name'] . '</td><td>' . $site['total_devices'] . '</td>';
					$row .= '<td>' . $site['total_ips'] . '</td><td>' . $site['total_user_ports'] . '</td>';
					$row .= '<td>' . $site['total_oper_ports'] . '</td><td>' . $site['total_macs'] . '</td>';
					$row .= '<td>' . $site['total_device_errors'] . '</td></tr>';

                    			$result['data'] .= $row;
            			}

            			$result['data'] .= '</table>';
			} else {
				$result['data'] = __('No mactrack sites found', 'intropage');
			}
		}
	}

	return $result;
}

//------------------------------------ ntp -----------------------------------------------------

function intropage_ntp() {
	global $config;

	$result = array(
		'name' => __('Time synchronization'),
		'alarm' => 'green',
		'data' => '',
		'detail' => FALSE,
	);

	$ntp_server = read_config_option('intropage_ntp_server');

	if (!preg_match('/^(([a-z0-9]|[a-z0-9][a-z0-9\-]*[a-z0-9])\.)*([a-z0-9]|[a-z0-9][a-z0-9\-]*[a-z])$/i', $ntp_server))	{
		$result['alarm'] = 'red';
		$result['data']  = __('Wrong NTP server configured - ' . $ntp_server . '<br/>Please fix it in settings', 'intropage');
	}
    	else if (empty($ntp_server)) {
		$result['alarm'] = 'grey';
		$result['data']  = __('No NTP server configured', 'intropage');
	} else {
		$diff_time = db_fetch_cell("SELECT value FROM plugin_intropage_trends WHERE name='ntp_diff_time'");

		if ($diff_time === false) {
			$result['alarm'] = 'yellow';
			$result['data']  = __('Waiting for data', 'intropage') . '<br/>';
		} elseif ($diff_time != "error") {
			$result['data'] = '<span class="txt_big">' . date('Y-m-d') . '<br/>' . date('H:i:s') . '</span><br/><br/>';
			if ($diff_time > 1400000000)	{
				$result['alarm'] = 'red';
				$result['data'] .= __('Failed to get NTP time FROM $ntp_server', 'intropage') . '<br/>';
			} else
				if ($diff_time < -600 || $diff_time > 600) {
					$result['alarm'] = 'red';
				} elseif ($diff_time < -120 || $diff_time > 120) {
					$result['alarm'] = 'yellow';

				if ($result['alarm'] != 'green') {
					$result['data'] .= __('Please check time.<br/>It is different (more than %s seconds) FROM NTP server %s', $diff_time, $ntp_server, 'intropage') . '<br/>';
				} else {
					$result['data'] .= __('Localtime is equal to NTP server', 'intropage') . "<br/>$ntp_server<br/>";
				}
			}
		} else {
			$result['alarm'] = 'red';
			$result['data']  = __('Unable to contact the NTP server indicated.<br/>Please check your configuration.<br/>', 'intropage');
		}

		$result['data'] .= '<br/>' . __('Last check: ', 'intropage') . db_fetch_cell("SELECT value FROM plugin_intropage_trends WHERE name='ntp_testdate'") . '<br/>';
		$often = read_config_option('intropage_ntp_interval');
		if ($often == 900) {
			$result['data'] .= __('Checked every 15 minutes', 'intropage');
		} elseif ($often == 3600) {
			$result['data'] .= __('Checked hourly', 'intropage');
		} else {
			$result['data'] .= __('Checked daily', 'intropage');
		}
	}

	return $result;
}


//------------------------------------ poller_info -----------------------------------------------------

function intropage_poller_info() {
	global $config;

	$result = array(
		'name' => __('Poller info', 'intropage'),
		'alarm' => 'green',
		'data' => '',
		'detail' => TRUE,
	);

	$result['data'] = '<b>' . __('ID/Name/total time/state', 'intropage') . '</b><br/>';

	$sql_pollers = db_fetch_assoc('SELECT p.id, name, status, last_update, total_time
		FROM poller p
		LEFT JOIN poller_time pt
		ON pt.poller_id = p.id
		WHERE p.disabled = ""
		GROUP BY p.id
		ORDER BY p.id
		LIMIT 5');

	$count    = $sql_pollers === false ? __('N/A', 'intropage') : count($sql_pollers);
	$ok       = 0;
	$running  = 0;

	if (cacti_sizeof($sql_pollers)) {
		foreach ($sql_pollers as $poller) {
			if ($poller['status'] == 0 || $poller['status'] == 1 || $poller['status'] == 2 || $poller['status'] == 5) {
				$ok++;
			}

			$result['data'] .= $poller['id'] . '/' .  $poller['name'] . '/' .

			round($poller['total_time']) . 's/';

			if ($poller['status'] == 0) {
				$result['data'] .= __('New/Idle', 'intropage');
			} elseif ($poller['status'] == 1) {
				$result['data'] .= __('Running', 'intropage');
			} elseif ($poller['status'] == 2) {
				$result['data'] .= __('Idle', 'intropage');
			} elseif ($poller['status'] == 3) {
				$result['data'] .= __('Unkn/down', 'intropage');
			} elseif ($poller['status'] == 4) {
				$result['data'] .= __('Disabled', 'intropage');
			} elseif ($poller['status'] == 5) {
				$result['data'] .= __('Recovering', 'intropage');
			}

			$result['data'] .= '<br/>';
		}
	}

	$result['data'] = '<span class="txt_big">' . $ok . '</span>' . __('(ok)', 'intropage') . '<span class="txt_big">/' . $count . '</span>' . __('(all)', 'intropage') . '</span><br/>' . $result['data'];

	if ($sql_pollers === false || $count > $ok) {
		$result['alarm'] = 'red';
	} else {
		$result['alarm'] = 'green';
	}

	return $result;
}



//------------------------------------ poller_stat -----------------------------------------------------

function intropage_poller_stat() {
	global $config;


	$poller_interval = read_config_option('poller_interval');
	$result          = array(
		'name' => __('Poller stats (interval %ss)', $poller_interval, 'intropage'),
		'alarm' => 'green',
		'data' => '',
		'detail' => FALSE,
		'line' => array(
			'title1' => '',
			'label1' => array(),
			'data1' => array(),
			'title2' => '',
			'label2' => array(),
			'data2' => array(),
			'title3' => '',
			'label3' => array(),
			'data3' => array(),
			'title4' => '',
			'label4' => array(),
			'data4' => array(),
			'title5' => '',
			'label5' => array(),
			'data5' => array(),
		),
	);

	$pollers = db_fetch_assoc('SELECT p.id
		FROM poller p
		LEFT JOIN poller_time pt
		ON pt.poller_id = p.id
		WHERE p.disabled = ""
		GROUP BY p.id
		ORDER BY id
		LIMIT 5');

	if (cacti_sizeof($pollers)) {
		$new_index = 1;

		foreach ($pollers as $xpoller) {
			$poller_time = db_fetch_assoc("SELECT date_format(time(cur_timestamp),'%H:%i') AS `date`, value
				FROM plugin_intropage_trends
				WHERE name='poller'
				AND value like '" . $xpoller['id'] . ":%'
				ORDER BY cur_timestamp desc
				LIMIT 10");

			$poller_time = array_reverse($poller_time);

			foreach ($poller_time as $one_poller) {
				list($id, $time) = explode(':', $one_poller['value']);

				if ($time > ($poller_interval - 10)) {
					$result['alarm'] = 'red';
					$result['data'] .= '<b>' . $one_poller['date'] . __(' Poller ID: ', 'intropage') . $xpoller['id'] . ' ' . $time . 's</b><br/>';
				} else {
					$result['data'] .= $one_poller['date'] . __(' Poller ID: ', 'intropage') . $xpoller['id'] . ' ' . $time . 's<br/>';
				}

				// graph data
				array_push($result['line']['label' . $new_index], $one_poller['date']);
				array_push($result['line']['data' . $new_index], $time);

				$result['line']['title' . $new_index] = __('ID: ', 'intropage') . $xpoller['id'];
			}

			$new_index++;
		}
	}

	if ($pollers === false || count($result['line']['data1']) < 3) {
		$result['data'] = __('Waiting for data', 'intropage');
		unset($result['line']);
	}

	return $result;
}

//------------------------------------ thold_events -----------------------------------------------------

function intropage_thold_event() {
	global $config;

	$result = array(
		'name' => __('Last thold events'),
		'alarm' => 'green',
		'data' => '',
		'detail' => TRUE,
	);

	if (db_fetch_cell("SELECT count(*) FROM plugin_config WHERE directory='thold' AND status = 1") == 0) {
		$result['alarm'] = 'yellow';
		$result['data']  = __('Plugin Thold isn\'t installed or started', 'intropage');
		$result['detail'] = FALSE;
	} else {
		$sql_result = db_fetch_assoc('SELECT tl.description as description,tl.time as time,
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
			HAVING (user0 IS NULL OR (user1 IS NULL OR user2 IS NULL))
			ORDER BY `time` DESC
			LIMIT 10');

		if (cacti_sizeof($sql_result)) {
			foreach ($sql_result as $row) {
				$result['data'] .= date('Y-m-d H:i:s', $row['time']) . ' - ' . $row['description'] . '<br/>';
				if ($row['status'] == 1 || $row['status'] == 4 || $row['status'] == 7) {
					$result['alarm'] = 'red';
				} elseif ($result['alarm'] == 'green' && ($row['status'] == 2 || $row['status'] == 3)) {
					$result['alarm'] == 'yellow';
				}
			}
		} else {
			$result['data'] = __('Without events yet', 'intropage');
		}
	}

	return $result;
}

//------------------------------------ top5_ping -----------------------------------------------------


function intropage_top5_ping() {
	global $config, $allowed_hosts, $console_access;

	$result = array(
		'name' => __('Top5 ping (avg, current)', 'intropage'),
		'alarm' => 'green',
		'data' => '',
		'detail' => TRUE,
	);

	if ($allowed_hosts)	{
		$sql_worst_host = db_fetch_assoc("SELECT description, id, avg_time, cur_time
			FROM host
			WHERE host.id in ($allowed_hosts)
			AND disabled != 'on'
			ORDER BY avg_time desc
			LIMIT 5");

		if (cacti_sizeof($sql_worst_host)) {
			foreach ($sql_worst_host as $host) {
				if ($console_access) {
					$row = '<tr><td class="rpad"><a href="' . htmlspecialchars($config['url_path']) . 'host.php?action=edit&id=' . $host['id'] . '">' . $host['description'] . '</a>';
				} else {
					$row = '<tr><td class="rpad">' . $host['description'] . '</td>';
				}

				$row .= '<td class="rpad texalirig">' . round($host['avg_time'], 2) . 'ms</td>';

				if ($host['cur_time'] > 1000) {
					$result['alarm'] = 'yellow';
					$row .= '<td class="rpad texalirig"><b>' . round($host['cur_time'], 2) . 'ms</b></td></tr>';
				} else {
					$row .= '<td class="rpad texalirig">' . round($host['cur_time'], 2) . 'ms</td></tr>';
				}

    				$result['data'] .= $row;
			}
			$result['data'] = '<table>' . $result['data'] . '</table>';
		} 
		else {	// no data
			$result['data'] = __('Waiting for data', 'intropage');
		}
	}
	else	{
	    $result['detail'] = FALSE;
	    $result['data'] = __('You don\'t have permissions to any hosts', 'intropage');
	}

	return $result;
}

//------------------------------------ top5_availability -----------------------------------------------------

function intropage_top5_availability() {
	global $config, $allowed_hosts, $console_access;

	$result = array(
		'name' => __('Top5 worst availability', 'intropage'),
		'alarm' => 'green',
		'data' => '',
		'detail' => TRUE,
	);

	if ($allowed_hosts)	{
		$sql_worst_host = db_fetch_assoc("SELECT description, id, availability
			FROM host
			WHERE host.id IN ($allowed_hosts)
			AND disabled != 'on'
			ORDER BY availability
			LIMIT 5");

		if (cacti_sizeof($sql_worst_host)) {

			foreach ($sql_worst_host as $host) {
				if ($console_access) {
					$row = '<tr><td class="rpad"><a href="' . htmlspecialchars($config['url_path']) . 'host.php?action=edit&id=' . $host['id'] . '">' . $host['description'] . '</a>';
				} else {
					$row = '<tr><td class="rpad">' . $host['description'] . '</td>';
				}

				if ($host['availability'] < 90) {
					$result['alarm'] = 'yellow';
					$row .= '<td class="rpad texalirig"><b>' . round($host['availability'], 2) . '%</b></td></tr>';
				} else {
					$row .= '<td class="rpad texalirig">' . round($host['availability'], 2) . '%</td></tr>';
				}

    				$result['data'] .= $row;
			}
			$result['data'] = '<table>' . $result['data'] . '</table>';

		} else {	// no data
			$result['data'] = __('Waiting for data', 'intropage');
		}
	}
	else	{
	    $result['detail'] = FALSE;
	    $result['data'] = __('You don\'t have permissions to any hosts', 'intropage');
	}

	return $result;
}

//------------------------------------ top5_polltime -----------------------------------------------------

function intropage_top5_polltime() {
	global $config, $allowed_hosts, $console_access;

	$result = array(
		'name' => __('Top5 worst polling time', 'intropage'),
		'alarm' => 'green',
		'data' => '',
		'detail' => TRUE,
	);

	if ($allowed_hosts)	{
		$sql_worst_host = db_fetch_assoc("SELECT id, description, polling_time
			FROM host
			WHERE host.id in ($allowed_hosts)
			AND disabled != 'on'
			ORDER BY polling_time desc
			LIMIT 5");

		if (cacti_sizeof($sql_worst_host)) {
			foreach ($sql_worst_host as $host) {

				if ($console_access) {
					$row = '<tr><td class="rpad"><a href="' . htmlspecialchars($config['url_path']) . 'host.php?action=edit&id=' . $host['id'] . '">' . $host['description'] . '</a>';
				} else {
					$row = '<tr><td class="rpad">' . $host['description'] . '</td>';
				}

				if ($host['polling_time'] > 30) {
					$result['alarm'] = 'yellow';
					$row .= '<td class="rpad texalirig"><b>' . round($host['polling_time'], 2) . 's</b></td></tr>';
				} else {
					$row .= '<td class="rpad texalirig">' . round($host['polling_time'], 2) . 's</td></tr>';
				}

				$result['data'] .= $row;
			}
			$result['data'] = '<table>' . $result['data'] . '</table>';
		} else {	// no data
			$result['data'] = __('Waiting for data', 'intropage');
		}
	}
	else	{
	    $result['detail'] = FALSE;
	    $result['data'] = __('You don\'t have permissions to any hosts', 'intropage');
	}
	
	return $result;
}

//------------------------------------ top5_pollratio -----------------------------------------------------

function intropage_top5_pollratio() {
	global $config, $allowed_hosts, $console_access;

	$result = array(
		'name' => __('Top5 worst polling ratio (failed, total, ratio)', 'intropage'),
		'alarm' => 'grey',
		'data' => '',
		'detail' => TRUE,
	);

	if ($allowed_hosts)	{
		$sql_worst_host = db_fetch_assoc("SELECT id, description, failed_polls, total_polls, failed_polls/total_polls as ratio
			FROM host
			WHERE host.id in ($allowed_hosts)
			AND disabled != 'on'
			ORDER BY ratio desc
			LIMIT 5");

		if (cacti_sizeof($sql_worst_host)) {
			foreach ($sql_worst_host as $host) {
				if ($console_access) {
					$row = '<tr><td class="rpad"><a href="' . htmlspecialchars($config['url_path']) . 'host.php?action=edit&id=' . $host['id'] . '">' . $host['description'] . '</a>';
				} else {
					$row = '<tr><td class="rpad">' . $host['description'] . '</td>';
				}

				$row .= '<td class="rpad texalirig">' . $host['failed_polls'] . '</td>';
				$row .= '<td class="rpad texalirig">' . $host['total_polls'] . '</td>';
				$row .= '<td class="rpad texalirig">' . round($host['ratio'], 2) . '</td></tr>';

				$result['data'] .= $row;
			}
			$result['data'] = '<table>' . $result['data'] . '</table>';

		} 
		else {	// no data
			$result['data'] = __('Waiting for data', 'intropage');
		}
	}
	else	{
	    $result['detail'] = FALSE;
	    $result['data'] = __('You don\'t have permissions to any hosts', 'intropage');
	}
	

	return $result;
}

//------------------------------------ trends -----------------------------------------------------

function intropage_trend() {
	global $config;

	$result = array(
		'name' => __('Trends', 'intropage'),
		'alarm' => 'grey',
		'data' => '',
		'detail' => FALSE,
		'line' => array(
			'title' => __('Trends', 'intropage'),
			'label1' => array(),
			'data1' => array(),
			'title1' => '',
			'data2' => array(),
			'title2' => '',
		),
	);

	if (db_fetch_cell("SELECT directory FROM plugin_config WHERE directory='thold' and status=1")) {
		$sql = db_fetch_assoc("SELECT date_format(time(cur_timestamp),'%H:%i') AS `date`, name, value
			FROM plugin_intropage_trends
			WHERE name='thold'
			ORDER BY cur_timestamp desc
			LIMIT 10");

		if (cacti_sizeof($sql)) {
			$result['line']['title1'] = __('Tholds triggered', 'intropage');
			foreach ($sql as $row) {
				// no gd data
				$result['data'] .= $row['date'] . ' ' . $row['name'] . ' ' . $row['value'] . '<br/>';
				array_push($result['line']['label1'], $row['date']);
				array_push($result['line']['data1'], $row['value']);
			}
		}
	}

	$sql = db_fetch_assoc("SELECT date_format(time(cur_timestamp),'%h:%i') as `date`, name, value
		FROM plugin_intropage_trends
		WHERE name='host'
		ORDER BY cur_timestamp desc
		LIMIT 10");

	if (cacti_sizeof($sql)) {
		$result['line']['title2'] = __('Hosts down');

		foreach ($sql as $row) {
			// no gd data
			$result['data'] .= $row['date'] . ' ' . $row['name'] . ' ' . $row['value'] . '<br/>';
			array_push($result['line']['data2'], $row['value']);
		}
	}

	if ($sql === false || count($sql) < 3) {
		unset($result['line']);
		$result['data'] = 'Waiting for data';
	} else {
		$result['line']['data1'] = array_reverse($result['line']['data1']);
		$result['line']['data2'] = array_reverse($result['line']['data2']);

		$result['line']['label1'] = array_reverse($result['line']['label1']);
	}

	return $result;
}

//-----------------favourite graph----------

function intropage_favourite_graph($fav_graph_id) {
	global $config;

	if (isset($fav_graph_id)) {
		$result = array(
			'name' => __('Favourite graph', 'intropage'),
			'alarm' => 'grey',
			'data' => '',
			'detail' => FALSE,
		);

		$result['name'] .= ' ' . db_fetch_cell_prepared('SELECT title_cache
			FROM graph_templates_graph
			WHERE local_graph_id = ?',
			array($fav_graph_id));

		$result['data'] = '<img src="' . $config['url_path'] . 'graph_image.php?' .
			'local_graph_id=' . $fav_graph_id . '&' .
			'graph_height=105&' .
			'graph_width=300&' .
			'graph_nolegend=true"/>';

		return $result;
	}
}

// ----------------maint----------------------

function intropage_maint()	{
	global $config;
	
	$maint_days_before = read_config_option('intropage_maint_plugin_days_before');

	$data = '';

        $schedules = db_fetch_assoc("SELECT * FROM plugin_maint_schedules WHERE enabled='on'");
        if (cacti_sizeof($schedules)) {
                foreach ($schedules as $sc) {
                        $t = time();

                        switch ($sc['mtype']) {
                                case 1:
                                        if ($t > ($sc['stime'] - $maint_days_before) && $t < $sc['etime']) {
                                                $data .= '<b>' . date('d. m . Y  H:i', $sc['stime']) . ' - ' . date('d. m . Y  H:i', $sc['etime']) .
                                                                ' - ' . $sc['name'] . ' (One time)<br/>Affected hosts:</b> ';

                                                $hosts = db_fetch_assoc_prepared('SELECT description FROM host
                                                        INNER JOIN plugin_maint_hosts
                                                        ON host.id=plugin_maint_hosts.host
                                                        WHERE schedule = ?',
                                                        array($sc['id']));

                                                if (cacti_sizeof($hosts)) {
                                                        foreach ($hosts as $host) {
                                                                $data .= $host['description'] . ', ';
                                                        }
                                                }

                                                $data = substr($data, 0, -2) .'<br/><br/>';
                                        }
                                break;

                                case 2:
                                        while ($sc['etime'] < $t) {
                                                $sc['etime'] += $sc['minterval'];
                                                $sc['stime'] += $sc['minterval'];
                                        }

                                        if ($t > ($sc['stime'] - $maint_days_before) && $t < $sc['etime']) {
                                                $data .= '<b>' . date('d. m . Y  H:i', $sc['stime']) . ' - ' . date('d. m . Y  H:i', $sc['etime']) .
                                                                ' - ' . $sc['name'] . ' (Reoccurring)<br/>Affected hosts:</b> ';

                                                $hosts = db_fetch_assoc_prepared('SELECT description FROM host
                                                        INNER JOIN plugin_maint_hosts
                                                        ON host.id=plugin_maint_hosts.host
                                                        WHERE schedule = ?',
                                                        array($sc['id']));

                                                if (cacti_sizeof($hosts)) {
                                                        foreach ($hosts as $host) {
                                                                $data .= $host['description'] . ', ';
                                                        }
                                                }
                                                $data = substr($data, 0, -2) . '<br/><br/>';
                                        }
                                break;
                        }
                }
        }
        return ($data);
} 
