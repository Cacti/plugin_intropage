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

function register_analyze() {
	global $registry;

	$registry['analyze'] = array(
		'name'        => __('Analysis Panels', 'intropage'),
		'description' => __('Panels that analyze the current behavior of Cacti and it\'s plugins.', 'intropage')
	);

	$panels = array(
		'analyse_login' => array(
			'name'         => __('Analyze Logins', 'intropage'),
			'description'  => __('Analyze the last several Cacti logins for trends and errors.', 'intropage'),
			'class'        => 'analyze',
			'level'        => PANEL_SYSTEM,
			'refresh'      => 300,
			'trefresh'     => false,
			'force'        => true,
			'width'        => 'quarter-panel',
			'priority'     => 51,
			'alarm'        => 'green',
			'requires'     => false,
			'update_func'  => 'analyse_login',
			'details_func' => 'analyse_login_detail',
			'trends_func'  => false
		),
		'analyse_log' => array(
			'name'         => __('Analyze Logs', 'intropage'),
			'description'  => __('Look for common errors in Cacti\'s log file that should be a cause for concern.', 'intropage'),
			'class'        => 'analyze',
			'level'        => PANEL_SYSTEM,
			'refresh'      => 300,
			'trefresh'     => false,
			'force'        => true,
			'width'        => 'half-panel',
			'priority'     => 50,
			'alarm'        => 'green',
			'requires'     => false,
			'update_func'  => 'analyse_log',
			'details_func' => 'analyse_log_detail',
			'trends_func'  => false
		),
		'analyse_db' => array(
			'name'         => __('Database Checks', 'intropage'),
			'description'  => __('Analyze MySQL/MariaDB database for common errors.  Note that this process may take a long time on very large systems.', 'intropage'),
			'class'        => 'analyze',
			'level'        => PANEL_SYSTEM,
			'refresh'      => 86400,
			'trefresh'     => false,
			'force'        => false,
			'width'        => 'quarter-panel',
			'priority'     => 7,
			'alarm'        => 'green',
			'requires'     => false,
			'update_func'  => 'analyse_db',
			'details_func' => false,
			'trends_func'  => false
		),
		'analyse_tree_host_graph' => array(
			'name'         => __('Analyze Cacti Objects', 'intropage'),
			'description'  => __('Analyze Trees, Graphs, Hosts, ...', 'intropage'),
			'class'        => 'analyze',
			'level'        => PANEL_USER,
			'refresh'      => 1800,
			'trefresh'     => false,
			'force'        => true,
			'width'        => 'quarter-panel',
			'priority'     => 33,
			'alarm'        => 'green',
			'requires'     => false,
			'update_func'  => 'analyse_tree_host_graph',
			'details_func' => 'analyse_tree_host_graph_detail',
			'trends_func'  => false
		),
		'analyse_ds_stat' => array(
			'name'         => __('Analyze DS stats', 'intropage'),
			'description'  => __('Analyze data source stats', 'intropage'),
			'class'        => 'analyze',
			'level'        => PANEL_SYSTEM,
			'refresh'      => 300,
			'trefresh'     => true,
			'force'        => true,
			'width'        => 'quarter-panel',
			'priority'     => 44,
			'alarm'        => 'green',
			'requires'     => false,
			'update_func'  => 'analyse_ds_stats',
			'details_func' => false,
			'trends_func'  => 'ds_stats_trend'
		),
	);

	return $panels;
}

//------------------------------------ analyse_login -----------------------------------------------------
function analyse_login($panel, $user_id) {
	global $config;

	$lines = read_user_setting('intropage_number_of_lines', read_config_option('intropage_number_of_lines'), false, $user_id);
        $important_period = read_user_setting('intropage_important_period', read_config_option('intropage_important_period'), false, $user_id);
        if ($important_period == -1) {
                $important_period = time();
        }

	$flog = db_fetch_cell('SELECT COUNT(*)
		FROM user_log
		WHERE result = 0');

	$panel['alarm'] = 'green';

	if ($flog > 0) {
		$panel['alarm'] = 'red';
	}

	$panel['data']  = '<table class="cactiTable">';

	$rows = db_fetch_assoc('SELECT user_log.username, user_auth.full_name,
		user_log.time, user_log.result, user_log.ip, UNIX_TIMESTAMP(user_log.time) as secs
		FROM user_auth
		INNER JOIN user_log
		ON user_auth.username = user_log.username
		ORDER BY user_log.time desc
		LIMIT ' . ($lines-3));

	if (cacti_sizeof($rows)) {
		$panel['data'] .=
			'<tr class="tableHeader">' .
				'<th class="left">' . __('Date', 'intropage') . '</td>' .
				'<th class="left">' . __('Username', 'intropage') . '</td>' .
				'<th class="left">' . __('IP Address', 'intropage') . '</td>' .
				'<th class="left">' . __('Status', 'intropage') . '</td>' .
			'</tr>';

		$i = 0;

		foreach ($rows as $row) {

			$color = 'grey';

			if ($row['result'] == 0) {
				$status = __('Failed', 'intropage');

				if ($row['secs'] > (time()-($important_period))) {
					$color = 'red';
				}

			} elseif ($row['result'] == 1) {
				$status = __('Success - Login', 'intropage');

				if ($row['secs'] > (time()-($important_period))) {
					$color = 'green';
				}

			} else {
				$status = __('Success - Token', 'intropage');

				if ($row['secs'] > (time()-($important_period))) {
					$color = 'green';
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

			$panel['data'] .= sprintf('<tr class="%s">' .
				'<td class="left">%s</td>' .
				'<td class="left">%s</td>' .
				'<td class="left">%s</td>' .
				'<td><span class="inpa_sq color_' . $color . '"></span>%s</td>' .
			'</tr>', $i % 2 == 0 ? 'even':'odd', substr($row['time'], 5), $row['username'], $row['ip'], $status);

			$i++;
		}
	}

	$panel['data'] .= '</table><br/>';

	$panel['data'] .= '<table class="cactiTable inpa_fixed">';

	$panel['data'] .= '<tr><td>' . __('Total Failed Logins: %s', number_format_i18n($flog), 'intropage') . '</td></tr>';

	$data = db_fetch_assoc('SELECT DISTINCT username
		FROM user_log
		WHERE time > adddate(now(), INTERVAL -1 HOUR)');

	if (cacti_sizeof($data)) {
		$text = implode (', ', array_column($data,'username'));
	} else {
		$text = __('None', 'intropage');
	}

	$panel['data'] .= '<tr><td class="inpa_first inpa_loglines" title="' . $text . '">' . __('Active Users in Last Hour: ', 'intropage');

	$panel['data'] .= $text . '</td></tr></table>';

	save_panel_result($panel, $user_id);
}

//------------------------------------ analyse_log -----------------------------------------------------
function analyse_log($panel, $user_id) {
	global $config;

	$lines = read_user_setting('intropage_number_of_lines', read_config_option('intropage_number_of_lines'), false, $user_id);

	if (isset($_SESSION['sess_user_id'])) {
		$important_period = read_user_setting('intropage_important_period', read_config_option('intropage_important_period'), false, $_SESSION['sess_user_id']);
	} else {
		$admin_user       = read_config_option('admin_user');
		$important_period = read_user_setting('intropage_important_period', read_config_option('intropage_important_period'), false, $admin_user);
	}

	if ($important_period == -1) {
		$important_period = time();
	}

	$panel['data']  = '';
	$panel['alarm'] = 'green';

	$log = array(
		'file' => read_config_option('path_cactilog'),
		'nbr_lines' => read_config_option('intropage_analyse_log_rows'),
	);

	$log['size']  = @filesize($log['file']);
	$log['lines'] = tail_log($log['file'], $log['nbr_lines']);

	if (!$log['size'] || empty($log['lines'])) {
		$panel['alarm'] = 'red';
		$panel['data'] = __('Log file not accessible or empty', 'intropage');
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

		$panel['data'] .= '<table class="cactiTable inpa_fixed">';

		$panel['data'] .= '<tr><td colspan="3">' . __('Analyze last %s log lines:', read_config_option('intropage_analyse_log_rows'), 'intropage') . '</td></tr>';

		$panel['data'] .= '<tr><td class="bold">' . __('Errors: ', 'intropage') . $error .
			'<a class="linkEditMain" href="clog.php?message_type=3&tail_lines=' . $log['nbr_lines'] . '">' .
				'<i class="fa fa-external-link"></i>' .
			'</a></td>';

		$panel['data'] .= '<td class="bold">' . __('Warnings: ', 'intropage') . $warn .
			'<a class="linkEditMain" href="clog.php?message_type=2&tail_lines=' . $log['nbr_lines'] . '">' .
				'<i class="fa fa-external-link"></i>' .
			'</a></td>';

		if ($log['size'] < 0) {
			$panel['alarm'] = 'red';
			$log_size_text   = __('Log Size: Larger than 2GB', 'intropage');
			$log_size_note   = '';
		} elseif ($log['size'] < 255999999) {
			$log_size_text   = human_filesize($log['size']);
			$log_size_note   = __('Log Size: OK', 'intropage');
		} else {
			$panel['alarm'] = 'yellow';
			$log_size_text   = human_filesize($log['size']);
			$log_size_note   = __('Log Size: Quite Large', 'intropage');
		}

		$panel['data'] .= '<td class="bold">' . __('Log Size: ', 'intropage') . $log_size_text;

		if (!empty($log_size_note)) {
			$panel['data'] .= ' (' . $log_size_note . ')</td></tr>';
		}

		$panel['data'] .= '<tr><td class="inpa_loglines" colspan=3><br/>';
		$panel['data'] .= __('Last log lines:', read_config_option('intropage_analyse_log_rows'), 'intropage') . '<br/>';
		$panel['data'] .= '</td></tr>';

		$log['lines'] = array_reverse(tail_log($log['file'], $lines - 3));

	        $datechar = array(
        	        GDC_HYPHEN => '-',
                	GDC_SLASH  => '/',
                	GDC_DOT    => '.'
        	);

        	$date_fmt        = read_config_option('default_date_format');
        	$dateCharSetting = read_config_option('default_datechar');

        	if (!isset($datechar[$dateCharSetting])) {
                	$dateCharSetting = GDC_SLASH;
        	}

        	$datecharacter = $datechar[$dateCharSetting];

        	switch ($date_fmt) {
			case GD_MO_D_Y:
				$format = 'm' . $datecharacter . 'd' . $datecharacter . 'Y H:i:s';
			break;
			case GD_MN_D_Y:
				$format = 'M' . $datecharacter . 'd' . $datecharacter . 'Y H:i:s';
			break;
			case GD_D_MO_Y:
                        	$format = 'd' . $datecharacter . 'm' . $datecharacter . 'Y H:i:s';
			break;
			case GD_D_MN_Y:
                        	$format = 'd' . $datecharacter . 'M' . $datecharacter . 'Y H:i:s';
			break;
			case GD_Y_MO_D:
                        	$format = 'Y' . $datecharacter . 'm' . $datecharacter . 'd H:i:s';
			break;
			case GD_Y_MN_D:
                        	$format = 'Y' . $datecharacter . 'M' . $datecharacter . 'd H:i:s';
			break;
			default:
                        	$format = 'Y' . $datecharacter . 'm' . $datecharacter . 'd H:i:s';
			break;
        	}

		foreach ($log['lines'] as $line) {

			$color = 'grey';

			if (strlen($line) > 3) {

				$date = explode(' - ', $line);

				$d_p = date_parse_from_format($format, $date[0]);
				$timestamp = mktime ($d_p['hour'], $d_p['minute'], $d_p['second'], $d_p['month'], $d_p['day'], $d_p['year']);

				if ($timestamp > (time()-($important_period))) {
                                        if (preg_match('/( ERROR|FATAL)/', $line)) {
                                                $color = 'red';
                                        } elseif (preg_match('/( WARNING)/', $line)) {
                                                $color = 'yellow';
                                        } else {
                                        	$color = 'green';
                                        }
				}

				$panel['data'] .= '<tr><td class="inpa_loglines" colspan="3" title="' . $line . '"><span class="inpa_sq color_' . $color . '"></span>';

				$panel['data'] .= $line;
				$panel['data'] .= '</td></tr>';
			}
		}

		$panel['data'] .= '</table>';

		if ($error > 0) {
			$panel['alarm'] = 'red';
		} elseif ($warn > 0) {
			$panel['alarm'] = 'yellow';
		}
	}

	save_panel_result($panel, $user_id);
}


// -------------------------------------analyse db-------------------------------------------
function analyse_db($panel, $user_id) {
	global $config;

	$damaged   = 0;
	$memtables = 0;

	$panel['alarm'] = 'green';
	$panel['data']  = '';

	db_execute_prepared('UPDATE plugin_intropage_panel_data
		SET last_update = NOW()
		WHERE panel_id = ?
		AND user_id = 0',
		array($panel['panel_id']));

	$db_check_level = read_config_option('intropage_analyse_db_level');

	$tables = db_fetch_assoc('SHOW TABLES');

	foreach ($tables as $key => $val) {
		$row = db_fetch_row('check table ' . current($val) . ' ' . $db_check_level);

		if (preg_match('/^note$/i', $row['Msg_type']) && preg_match('/doesn\'t support/i', $row['Msg_text'])) {
			$memtables++;
		} elseif (!preg_match('/OK/i', $row['Msg_text']) && !preg_match('/Table is already up to date/i', $row['Msg_text'])) {
			$damaged++;
			$panel['data'] .= '<tr><td>' . __('Table %s status %s', $row['Table'], $row['Msg_text'], 'intropage') . '</td></tr>';
		}
	}

	if ($damaged > 0) {
		$panel['alarm'] = 'red';
		$panel['data']  = '<table class="cactiTable">
			<tr>
				<td><span class="txt_big">' . __('DB: Problems', 'intropage') . '</span></td>
			</tr>
			<tr><td><hr></td></tr>
			<tr>
				<td></td>
			</tr>' . $panel['data'];
	} else {
		$panel['data'] = '<table class="cactiTable">
			<tr>
				<td><span class="txt_big">' . __('DB: OK', 'intropage') . '</span></td>
			</tr>
			<tr>
				<td></td>
			</tr>' . $panel['data'];
	}

	// connection errors
	$cerrors = 0;
	$color   = read_config_option('intropage_alert_db_abort');

	$con_err = db_fetch_assoc("SHOW GLOBAL STATUS LIKE '%Connection_errors%'");

	foreach ($con_err as $key => $val) {
		$cerrors = $cerrors + $val['Value'];
	}

	if ($cerrors > 0) {

		if ($color == 'red') {
			$panel['alarm'] = 'red';
		} elseif ($panel['alarm'] == 'green' && $color == 'yellow') {
			$panel['alarm'] = 'yellow';
		}

		$panel['data'] .= __('Connection errors: %s - try to restart SQL service, check SQL log, ...', $cerrors, 'intropage') . '<span class="inpa_sq color_' . $color . '"></span><br/>';
	}

	// aborted problems
	$aerrors = 0;
	$con_err = db_fetch_assoc("SHOW GLOBAL STATUS LIKE '%Aborted_c%'");

	foreach ($con_err as $key => $val) {
		$aerrors = $aerrors + $val['Value'];
	}

	if ($aerrors > 0) {

		if ($color == 'red') {
			$panel['alarm'] = 'red';
		} elseif ($panel['alarm'] == 'green' && $color == 'yellow') {
			$panel['alarm'] = 'yellow';
		}

		$panel['data'] .= '<tr><td>' . __('Aborted clients/connects: %s', $aerrors, 'intropage') . '<span class="inpa_sq color_' . $color . '"></span></td></tr>';

	}

	$panel['data'] .= '<tr><td>' . __('Connection errors: %s', $cerrors, 'intropage') . '</td></tr>';

	$panel['data'] .=
		'<tr><td>' . __('Damaged tables: %s', $damaged, 'intropage')   . '</td></tr>' .
		'<tr><td>' . __('Memory tables: %s', $memtables, 'intropage')  . '</td></tr>' .
		'<tr><td>' . __('All tables: %s', count($tables), 'intropage') . '</td></tr>';

	if ($aerrors > 0) {
		$panel['data'] .= '<tr><td><br/><br/>' . __('Aborted clients/connects - Run \'SET GLOBAL log_warnings = 1;\' or \' log_error_verbosity = 1;\' (depends on your MySQL/MariaDB version) from the mysql CLI and set in server.cnf to silence.', 'intropage') . '</td></tr>';
	}

	save_panel_result($panel, $user_id);

	if (true == false) {
		$data = db_fetch_row_prepared('SELECT *
			FROM plugin_intropage_panel_data
			WHERE panel_id = ?',
			array($panel_id));

		// exception - refresh is in intropage settings
		if ($data['refresh_interval'] == 0) {
			$data['recheck'] = __('Scheduled db check disabled','intropage');
		} elseif ($data['refresh_interval'] == 3600) {
			$data['recheck'] = __('hour', 'intropage');
		} elseif ($data['refresh_interval'] == 86400) {
			$data['recheck'] = __('day', 'intropage');
		} elseif ($data['refresh_interval'] == 604800) {
			$data['recheck'] = __('week', 'intropage');
		} elseif ($data['refresh_interval'] == 2592000) {
			$data['recheck'] = __('month', 'intropage');
		}

		$data['name'] = $panel['name'];

		return $data;
	}
}



// --------------------------------analyse_tree_host_graph
function analyse_tree_host_graph($panel, $user_id) {
	global $config;

	$panel['alarm'] = 'green';

	$console_access = get_console_access($user_id);

	$total_errors = 0;

	$allowed_devices = intropage_get_allowed_devices($user_id);

	if ($allowed_devices != '') {
		$data = db_fetch_assoc("SELECT COUNT(*) AS NoDups, id, hostname
			FROM host
			WHERE id IN (" . $allowed_devices . ")
			AND disabled != 'on'
			GROUP BY hostname,snmp_port
			HAVING NoDups > 1");

		$sql_count  = ($data === false) ? __('N/A', 'intropage') : count($data);

		$color = read_config_option('intropage_alert_same_ip');

		if (cacti_sizeof($data)) {
			$total_errors += $sql_count;

			if (count($data) > 0) {
				if ($color == 'red') {
					$panel['alarm'] = 'red';
				} elseif ($panel['alarm'] == 'green' && $color == "yellow") {
					$panel['alarm'] = 'yellow';
				}

				$panel['data'] .= '<span class="inpa_sq color_' . $color . '"></span>' . __('Devices with the same IP and port: %s', $sql_count, 'intropage') . '<br/>';
			}
		}
	}

	if ($allowed_devices != '') {
		$data = db_fetch_assoc("SELECT COUNT(*) AS NoDups, description
			FROM host
			WHERE id IN (" . $allowed_devices . ")
			AND disabled != 'on'
			GROUP BY description
			HAVING NoDups > 1");

		$sql_count  = ($data === false) ? __('N/A', 'intropage') : count($data);

		if (cacti_sizeof($data)) {
			$total_errors += $sql_count;

			$color = read_config_option('intropage_alert_same_description');

			if (count($data) > 0) {
				if ($color == 'red') {
					$panel['alarm'] = 'red';
				} elseif ($panel['alarm'] == 'green' && $color == "yellow") {
					$panel['alarm'] = 'yellow';
				}

				$panel['data'] .= '<span class="inpa_sq color_' . $color . '"></span>' . __('Devices with the same description: %s', $sql_count, 'intropage') . '<br/>';
			}
		}
	}

	if ($allowed_devices != '') {

		$data = db_fetch_assoc("SELECT
    		dtr.local_graph_id, dtd.local_data_id, dtd.name_cache, dtd.active, dtd.rrd_step,
    		dt.name AS data_template_name, dl.host_id, dtd.data_source_profile_id
		FROM data_local AS dl
    		INNER JOIN data_template_data AS dtd ON dl.id = dtd.local_data_id
    		INNER JOIN data_template AS dt ON dt.id = dl.data_template_id
    		LEFT JOIN host AS h ON h.id = dl.host_id
		INNER JOIN (
		SELECT DISTINCT dtr.local_data_id, task_item_id, local_graph_id FROM graph_templates_item AS gti
        	INNER JOIN graph_local AS gl ON gl.id = gti.local_graph_id
        	LEFT JOIN data_template_rrd AS dtr ON dtr.id = gti.task_item_id
        	LEFT JOIN host AS h ON h.id = gl.host_id
		WHERE graph_type_id IN (4,5,6,7,8,20) AND
          	task_item_id IS NULL AND cdef_id NOT IN (
              	SELECT c.id FROM cdef AS c
		INNER JOIN cdef_items AS ci ON c.id = ci.cdef_id
		WHERE (ci.type = 4 OR (ci.type = 6 AND value LIKE '%DATA_SOURCE%'))
          	)) AS dtr ON dl.id = dtr.local_data_id
		WHERE dl.host_id IN (" . $allowed_devices . ") AND
		((dl.snmp_index = '' AND dl.snmp_query_id > 0) OR dtr.local_graph_id IS NULL)
		ORDER BY `name_cache` ASC");
	} else {
		$data = array();
	}

	$sql_count  = ($data === false) ? __('N/A', 'intropage') : count($data);

	if (cacti_sizeof($data)) {
		$total_errors += $sql_count;

		$color = read_config_option('intropage_alert_orphaned_ds');

		if ($color == 'red') {
			$panel['alarm'] = 'red';
		} elseif ($panel['alarm'] == 'green' && $color == "yellow") {
			$panel['alarm'] = 'yellow';
		}

		$panel['data'] .= '<span class="inpa_sq color_' . $color . '"></span>' . __('Orphaned Data Sources: %s', $sql_count, 'intropage') . '<br/>';

	}

	if ($allowed_devices != '') {
		$data = db_fetch_assoc('SELECT dtd.local_data_id,dtd.name_cache
			FROM data_local AS dl
			INNER JOIN data_template_data AS dtd
			ON dl.id=dtd.local_data_id
			INNER JOIN data_template AS dt ON dt.id=dl.data_template_id
			INNER JOIN host AS h ON h.id = dl.host_id
			WHERE (dl.snmp_index = "" AND dl.snmp_query_id > 0)
			AND dl.host_id in (' . $allowed_devices . ')');
	} else {
		$datas = array();
	}

	$sql_count  = ($data === false) ? __('N/A', 'intropage') : count($data);

	if (cacti_sizeof($data)) {
		$color = read_config_option('intropage_alert_bad_indexes');

		if ($color == 'red') {
			$panel['alarm'] = 'red';
		} elseif ($panel['alarm'] == 'green' && $color == "yellow") {
			$panel['alarm'] = 'yellow';
		}
		$panel['data'] .= '<span class="inpa_sq color_' . $color . '"></span>' . __('Datasource - bad indexes: %s', $sql_count, 'intropage') . '<br/>';


		$total_errors += $sql_count;
	}

	// thold plugin - logonly alert and warning thold
	// I don't use thold get_allowed_thold because of join plugin_thold_threshold_contact

	if (api_plugin_is_enabled('thold')) {
		$allowed_devices = intropage_get_allowed_devices($user_id);

		if ($allowed_devices != '') {
			$data = db_fetch_assoc("SELECT td.id AS td_id, concat(h.description,'-',tt.name) AS td_name,
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
					AND gl.host_id IN (" . $allowed_devices . ")
					HAVING (user0 IS NULL OR (user1 IS NULL OR user2 IS NULL))");
		} else {
			$data = array();
		}

		$sql_count  = ($data === false) ? __('N/A', 'intropage') : count($data);

		if (cacti_sizeof($data)) {

			$color = read_config_option('intropage_alert_thold_logonly');

			if ($color == 'red') {
				$panel['alarm'] = 'red';
			} elseif ($panel['alarm'] == 'green' && $color == "yellow") {
				$panel['alarm'] = 'yellow';
			}

			$panel['data'] .= '<span class="inpa_sq color_' . $color . '"></span>' . __('Thold logonly alert/warning: %s', $sql_count, 'intropage') . '<br/>';

			$total_errors += $sql_count;
		}
	}

	if ($allowed_devices != '') {
		$data = db_fetch_assoc('SELECT host.id, host.description, COUNT(*) AS `count`
			FROM host
			INNER JOIN graph_tree_items
			ON (host.id = graph_tree_items.host_id)
			WHERE host.id IN (' . $allowed_devices . ')
			GROUP BY description
			HAVING `count` > 1');

		$sql_count  = ($data === false) ? __('N/A', 'intropage') : count($data);

		if (cacti_sizeof($data)) {
			$color = read_config_option('intropage_alert_more_trees');

			if ($color == 'red') {
				$panel['alarm'] = 'red';
			} elseif ($panel['alarm'] == 'green' && $color == "yellow") {
				$panel['alarm'] = 'yellow';
			}

			$panel['data'] .= '<span class="inpa_sq color_' . $color . '"></span>' . __('Devices in more than one tree: %s', $sql_count, 'intropage') . '<br/>';
		}
	}

	if ($allowed_devices != '') {
		$data = db_fetch_assoc("SELECT id, description
			FROM host
			WHERE id IN (" . $allowed_devices . ")
			AND disabled != 'on'
			AND id NOT IN (
				SELECT DISTINCT host_id
				FROM graph_local
			)
			AND snmp_version != 0");

		$sql_count  = ($data === false) ? __('N/A', 'intropage') : count($data);

		if (cacti_sizeof($data)) {
			$color = read_config_option('intropage_alert_without_graph');

			if ($color == 'red') {
				$panel['alarm'] = 'red';
			} elseif ($panel['alarm'] == 'green' && $color == "yellow") {
				$panel['alarm'] = 'yellow';
			}

			$panel['data'] .= '<span class="inpa_sq color_' . $color . '"></span>' . __('Hosts without graphs: %s', $sql_count, 'intropage') . '<br/>';
		}
	}

	if ($allowed_devices != '') {
		$data = db_fetch_assoc("SELECT id, description
			FROM host
			WHERE id IN (" . $allowed_devices . ")
			AND disabled != 'on'
			AND id NOT IN (
				SELECT DISTINCT host_id
				FROM graph_tree_items
			)");

		$sql_count  = ($data === false) ? __('N/A', 'intropage') : count($data);

		if (cacti_sizeof($data)) {
			$color = read_config_option('intropage_alert_without_tree');

			if ($color == 'red') {
				$panel['alarm'] = 'red';
			} elseif ($panel['alarm'] == 'green' && $color == "yellow") {
				$panel['alarm'] = 'yellow';
			}

			$panel['data'] .= '<span class="inpa_sq color_' . $color . '"></span>' . __('Hosts without tree: %s', $sql_count, 'intropage') . '<br/>';
		}
	}

	if ($allowed_devices != '') {
		$data = db_fetch_assoc("SELECT id, description
			FROM host
			WHERE id IN (" . $allowed_devices . ")
			AND disabled != 'on'
			AND (snmp_community ='public' OR snmp_community='private')
			AND snmp_version IN (1,2)
			ORDER BY description");

		$sql_count  = ($data === false) ? __('N/A', 'intropage') : count($data);

		if (cacti_sizeof($data)) {
			$color = read_config_option('intropage_alert_default_community');

			if ($color == 'red') {
				$panel['alarm'] = 'red';
			} elseif ($panel['alarm'] == 'green' && $color == "yellow") {
				$panel['alarm'] = 'yellow';
			}

			$panel['data'] .= '<span class="inpa_sq color_' . $color . '"></span>' . __('Hosts with default public/private community: %s', $sql_count, 'intropage') . '<br/>';
		}
	}

	if (api_plugin_is_enabled('monitor')) {
		if ($allowed_devices != '') {
			$data = db_fetch_assoc("SELECT id, description, hostname
				FROM host
				WHERE id IN (" . $allowed_devices . ")
				AND monitor != 'on'");

			$sql_count  = ($data === false) ? __('N/A', 'intropage') : count($data);

			if (cacti_sizeof($data)) {
				$color = read_config_option('intropage_alert_without_monitoring');

				if ($color == 'red') {
					$panel['alarm'] = 'red';
				} elseif ($panel['alarm'] == 'green' && $color == "yellow") {
					$panel['alarm'] = 'yellow';
				}

				$panel['data'] .= '<span class="inpa_sq color_' . $color . '"></span>' . __('Plugin Monitor - Unmonitored hosts: %s', $sql_count, 'intropage') . '<br/>';
			}
		}
	}

	if ($total_errors > 0) {
		$panel['data'] = '<table class="cactiTable">
			<tr>
				<td><span class="txt_med">' . __('Found %s problems', $total_errors, 'intropage') . '</span><br/><br/>' . $panel['data'] . '</td>
			</tr>
		</table>';
	} else {
		$panel['data'] = '<table class="cactiTable">
			<tr>
				<td><span class="txt_med">' . __('Everything OK', 'intropage') . '</span><br/>' . $panel['data'] . '</td>
			</tr>
		</table>';;
	}

	save_panel_result($panel, $user_id);
}

//------------------------------------ analyse_ds_stats -----------------------------------------------------
function analyse_ds_stats($panel, $user_id, $timespan = 0) {
	global $config;

	$panel['alarm'] = 'green';

	$graph = array (
		'line' => array(
			'title'  => __('DS stats: ', 'intropage'),
			'label1' => array(),
			'data1'  => array(),
			'label2' => array(),
			'data2'  => array(),
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

	if (read_config_option('dsstats_enable') != 'on') {
		$panel['data'] = __('Panel needs DS stats enabled.', 'intropage') . '<br/>';
		$panel['alarm'] = 'grey';
		unset($graph);
	} else {
		$rows = db_fetch_assoc_prepared("SELECT cur_timestamp AS `date`, value
			FROM plugin_intropage_trends
			WHERE cur_timestamp > date_sub(NOW(), INTERVAL ? SECOND)
			AND name = 'dsstats_all'
			ORDER BY cur_timestamp ASC",
			array($timespan));

		if (cacti_sizeof($rows)) {
			$graph['line']['title1'] = __('DS all records ', 'intropage');
			$graph['line']['unit1']['title'] = 'All';

			foreach ($rows as $row) {
				$graph['line']['label1'][] = $row['date'];
				$graph['line']['data1'][]  = $row['value'];
			}

			$rows = db_fetch_assoc_prepared("SELECT cur_timestamp AS `date`, value
				FROM plugin_intropage_trends
				WHERE cur_timestamp > date_sub(NOW(), INTERVAL ? SECOND)
				AND name = 'dsstats_null'
				ORDER BY cur_timestamp ASC",
				array($timespan));

			if (cacti_sizeof($rows)) {
				$graph['line']['title2'] = __('DS null records ', 'intropage');
				$graph['line']['unit2']['title'] = 'Null';

				foreach ($rows as $row) {
					$graph['line']['label2'][] = $row['date'];
					$graph['line']['data2'][]  = $row['value'];
				}
			} else {
				unset($graph['line']['label2']);
				unset($graph['line']['data2']);
				unset($graph['line']['title2']);
				unset($graph['line']['unit2']);
			}

			$panel['data'] = intropage_prepare_graph($graph, $user_id);
		} else {
			unset($graph);
			$panel['data'] = __('Waiting for data', 'intropage');
		}
	}

	save_panel_result($panel, $user_id);
}


function ds_stats_trend () {

	$count = db_fetch_cell("SELECT count(*) FROM data_source_stats_hourly_last");

	db_execute_prepared('REPLACE INTO plugin_intropage_trends
		(name, value, user_id)
		VALUES (?, ?, 0)',
		array('dsstats_all', $count));

	$count = db_fetch_cell("SELECT count(*) FROM data_source_stats_hourly_last WHERE value IS NULL");

	db_execute_prepared('REPLACE INTO plugin_intropage_trends
		(name, value, user_id)
		VALUES (?, ?, 0)',
		array('dsstats_null', $count));
}


//------------------------------------ analyse_log -----------------------------------------------------
function analyse_log_detail() {
	global $log;

	if (isset($_SESSION['sess_user_id'])) {
		$important_period = read_user_setting('intropage_important_period', read_config_option('intropage_important_period'), false, $_SESSION['sess_user_id']);
	} else {
		$admin_user       = read_config_option('admin_user');
		$important_period = read_user_setting('intropage_important_period', read_config_option('intropage_important_period'), false, $admin_user);
	}

	if ($important_period == -1) {
		$important_period = time();
	}

	$panel = array(
		'name'   => __('Analyze Cacti Log Details [ Warnings/Errors ]', 'intropage'),
		'alarm'  => 'green',
		'detail' => '',
	);

	$log = array(
		'file'      => read_config_option('path_cactilog'),
		'nbr_lines' => read_config_option('intropage_analyse_log_rows'),
	);

	$log['size']  = @filesize($log['file']);
	$log['lines'] = tail_log($log['file'], $log['nbr_lines']*2);

	$panel['detail'] .= '<table class="cactiTable">';

	if (!$log['size'] || empty($log['lines'])) {
		$panel['alarm'] = 'red';
		$panel['detail'] .= '<tr><td>' . __('Log file not accessible or empty', 'intropage') . '</td></tr>';
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

		$panel['detail'] .= '<tr><td>' .
			'<a class="linkEditMain txt_med" href="clog.php?message_type=3&tail_lines=' . $log['nbr_lines'] . '">' .
				__('Errors', 'intropage') . ': ' . $error . '<i class="fas fa-link"></i>' .
			'</a></td></tr>';

		$panel['detail'] .= '<tr><td>' .
			'<a class="linkEditMain txt_med" href="clog.php?message_type=2&tail_lines=' . $log['nbr_lines'] . '">' .
				__('Warnings', 'intropage') . ': ' . $warn . '<i class="fas fa-link"></i>' .
			'</a></td></tr>';

		if ($log['size'] < 0) {
			$panel['alarm'] = 'red';
			$log_size_text   = __('WARNING: File is Larger than 2GB', 'intropage');
			$log_size_note   = '';
		} elseif ($log['size'] < 255999999) {
			$log_size_text   = human_filesize($log['size']);
			$log_size_note   = __('Log Size: OK', 'intropage');
		} else {
			$panel['alarm'] = 'yellow';
			$log_size_text   = human_filesize($log['size']);
			$log_size_note   = __('Log Size: Quite Large', 'intropage');
		}

		$panel['detail'] .= '<tr><td class="txt_med">' . __('Log Size', 'intropage') . ': ' . $log_size_text . '</td></tr>';

		if (!empty($log_size_note)) {
			$panel['detail'] .= '<tr><td class="txt_med">' . $log_size_note . '<hr></td></tr>';
		}

	        $datechar = array(
        	        GDC_HYPHEN => '-',
                	GDC_SLASH  => '/',
                	GDC_DOT    => '.'
        	);

        	$date_fmt        = read_config_option('default_date_format');
        	$dateCharSetting = read_config_option('default_datechar');

        	if (!isset($datechar[$dateCharSetting])) {
                	$dateCharSetting = GDC_SLASH;
        	}

        	$datecharacter = $datechar[$dateCharSetting];

        	switch ($date_fmt) {
			case GD_MO_D_Y:
				$format = 'm' . $datecharacter . 'd' . $datecharacter . 'Y H:i:s';
			break;
			case GD_MN_D_Y:
				$format = 'M' . $datecharacter . 'd' . $datecharacter . 'Y H:i:s';
			break;
			case GD_D_MO_Y:
                        	$format = 'd' . $datecharacter . 'm' . $datecharacter . 'Y H:i:s';
			break;
			case GD_D_MN_Y:
                        	$format = 'd' . $datecharacter . 'M' . $datecharacter . 'Y H:i:s';
			break;
			case GD_Y_MO_D:
                        	$format = 'Y' . $datecharacter . 'm' . $datecharacter . 'd H:i:s';
			break;
			case GD_Y_MN_D:
                        	$format = 'Y' . $datecharacter . 'M' . $datecharacter . 'd H:i:s';
			break;
			default:
                        	$format = 'Y' . $datecharacter . 'm' . $datecharacter . 'd H:i:s';
			break;
        	}

		$count = 0;

		foreach ($log['lines'] as $line) {

			if ($count > 99) {
				break;
			}

			$color = 'grey';

			if (strlen($line) > 3) {

				$date = explode(' - ', $line);

				$d_p = date_parse_from_format($format, $date[0]);
				$timestamp = mktime ($d_p['hour'], $d_p['minute'], $d_p['second'], $d_p['month'], $d_p['day'], $d_p['year']);

				if ($timestamp > (time()-($important_period))) {
                                        if (preg_match('/( ERROR)/', $line)) {
                                                $color = 'red';
                                        } elseif (preg_match('/( WARNING)/', $line)) {
                                                $color = 'yellow';
                                        } else {
                                        	$color = 'green';
                                        }
				}

				$panel['detail'] .= '<tr><td class="inpa_loglines"><span class="inpa_sq color_' . $color . '"></span>' . $line . '</td></tr>';
			}

			$count++;
		}

		if ($error > 0) {
			$panel['alarm'] = 'red';
		} elseif ($warn > 0) {
			$panel['alarm'] = 'yellow';
		}
	}

	return $panel;
}

//------------------------------------ analyse_login -----------------------------------------------------
function analyse_login_detail() {
	global $config;

	if (isset($_SESSION['sess_user_id'])) {
		$important_period = read_user_setting('intropage_important_period', read_config_option('intropage_important_period'), false, $_SESSION['sess_user_id']);
	} else {
		$admin_user       = read_config_option('admin_user');
		$important_period = read_user_setting('intropage_important_period', read_config_option('intropage_important_period'), false, $admin_user);
	}

	if ($important_period == -1) {
		$important_period = time();
	}

	$lines = 20;

	$panel = array(
		'name'   => __('Analyze Logins Detail', 'intropage'),
		'alarm'  => 'green',
		'detail' => '',
	);

	$data = db_fetch_assoc('SELECT user_log.username, user_auth.full_name, user_log.time,
		user_log.result, user_log.ip, UNIX_TIMESTAMP(user_log.time) AS secs
		FROM user_auth
		INNER JOIN user_log
		ON user_auth.username = user_log.username
		ORDER BY user_log.time desc
		LIMIT ' . $lines);

	if (cacti_sizeof($data)) {
		$panel['detail'] .= '<table class="cactiTable">' .
			'<tr class="tableHeader">' .
				'<th>' . __('Date', 'intropage')       . '</th>' .
				'<th>' . __('IP Address', 'intropage') . '</th>' .
				'<th>' . __('User', 'intropage')       . '</th>' .
				'<th>' . __('Result', 'intropage')     . '</th>' .
			'</tr>';

		foreach ($data as $row) {

			$color = 'grey';

			if ($row['result'] == 0) {
				$status = __('Failed', 'intropage');

				if ($row['secs'] > (time()-($important_period))) {
					$color = 'red';
				}

			} elseif ($row['result'] == 1) {
				$status = __('Success - Login', 'intropage');

				if ($row['secs'] > (time()-($important_period))) {
					$color = 'green';
				}

			} else {
				$status = __('Success - Token', 'intropage');

				if ($row['secs'] > (time()-($important_period))) {
					$color = 'green';
				}
			}

			$panel['detail'] .= sprintf('<tr>' .
				'<td class="left">%s </td>' .
				'<td class="left">%s </td>' .
				'<td class="left">%s </td>' .
				'<td class="left"><span class="inpa_sq color_' . $color . '"></span>%s</td>' .
			'</tr>', $row['time'], $row['ip'], $row['username'], $status);

		}

		$panel['detail'] .= '</table>';
	}

	// active users in last hour:
	$panel['detail'] .= '<h4>' . __('Active Users in Last 2 Hours:', 'intropage') . '</h4>';

	$users = db_fetch_assoc('SELECT DISTINCT username
		FROM user_log
		WHERE time > adddate(now(), INTERVAL -2 HOUR)');

	if (cacti_sizeof($users)) {
		foreach ($users as $row) {
			$panel['detail'] .= html_escape($row['username']) . '<br/>';
		}
	} else {
		$panel['detail'] .= __('No Active Users', 'intropage') . '<br/>';
	}

	$login_access = is_realm_allowed(19);

	if ($panel['detail'] && $login_access) {
		$panel['detail'] .= '<br/><a class="linkEditMain" href="' . html_escape($config['url_path'] . 'utilities.php?action=view_user_log') . '">' . __('View Full User Log', 'intropage') . '</a>';
	}

	return $panel;
}

//------------------------------------ analyse_tree_host_graph  -----------------------------------------------------
function analyse_tree_host_graph_detail() {
	global $config, $console_access;

	if (isset($_SESSION['sess_user_id'])) {
		$allowed_devices = intropage_get_allowed_devices($_SESSION['sess_user_id']);
	} else {
		$admin_user      = read_config_option('admin_user');
		$allowed_devices = intropage_get_allowed_devices($admin_user);
	}

	$panel = array(
		'name'   => __('Analyze Tree, Graphs, Hosts', 'intropage'),
		'alarm'  => 'green',
		'detail' => '',
	);

	$total_errors = 0;

	$data = db_fetch_assoc("SELECT COUNT(*) AS NoDups, id, hostname, snmp_port
		FROM host
		WHERE disabled != 'on'
		GROUP BY hostname,snmp_port
		HAVING NoDups > 1");

	$sql_count  = ($data === false) ? __('N/A', 'intropage') : count($data);

	$color = read_config_option('intropage_alert_same_ip');

	$panel['detail'] .= '<h4>' . __('Devices with the same IP and port - %s', $sql_count, 'intropage') . '<span class="inpa_sq color_' . $color . '"></span></h4>';

	if (cacti_sizeof($data)) {
		$total_errors += $sql_count;

		if (count($data) > 0) {

			if ($color == 'red')    {
				$panel['alarm'] = 'red';
			} elseif ($panel['alarm'] == 'green' && $color == "yellow") {
				$panel['alarm'] = 'yellow';
			}

			foreach ($data as $row) {
				$sql_hosts = db_fetch_assoc("SELECT id, description, hostname
					FROM host
					WHERE hostname = " . db_qstr($row['hostname']) . " AND snmp_port=" . $row['snmp_port']);

				if (cacti_sizeof($sql_hosts)) {
					foreach ($sql_hosts as $row2) {
						$panel['detail'] .= sprintf('<a class="linkEditMain" href="%shost.php?action=edit&amp;id=%d">%s %s (ID: %d)</a><br/>', html_escape($config['url_path']), $row['id'], html_escape($row2['description']), html_escape($row2['hostname']), $row2['id']);
					}
				}
			}
		}
	}

	$data = db_fetch_assoc("SELECT COUNT(*) AS NoDups, id, description
		FROM host
		WHERE disabled != 'on'
		GROUP BY description
		HAVING NoDups > 1");

	$sql_count  = ($data === false) ? __('N/A', 'intropage') : count($data);

	$color = read_config_option('intropage_alert_same_description');

	$panel['detail'] .= '<h4>' . __('Devices with the same description - %s', $sql_count, 'intropage') . '<span class="inpa_sq color_' . $color . '"></span></h4>';

	if (cacti_sizeof($data)) {
		$total_errors += $sql_count;
		if (count($data) > 0) {

			if ($color == 'red')    {
				$panel['alarm'] = 'red';
			} elseif ($panel['alarm'] == 'green' && $color == "yellow") {
				$panel['alarm'] = 'yellow';
			}

			foreach ($data as $row) {
				$sql_hosts = db_fetch_assoc("SELECT id, description, hostname
					FROM host
					WHERE description = " . db_qstr($row['description']));

				if (cacti_sizeof($sql_hosts)) {
					foreach ($sql_hosts as $row2) {
						$panel['detail'] .= sprintf('<a class="linkEditMain" href="%shost.php?action=edit&amp;id=%d">%s (ID: %d)</a><br/>', html_escape($config['url_path']), $row2['id'], html_escape($row2['description']), $row2['id']);
					}
				}
			}
		}
	}

	$data = db_fetch_assoc("SELECT
    		dtr.local_graph_id, dtd.local_data_id, dtd.name_cache, dtd.active, dtd.rrd_step,
    		dt.name AS data_template_name, dl.host_id, dtd.data_source_profile_id
		FROM data_local AS dl
    		INNER JOIN data_template_data AS dtd ON dl.id = dtd.local_data_id
    		INNER JOIN data_template AS dt ON dt.id = dl.data_template_id
    		LEFT JOIN host AS h ON h.id = dl.host_id
		INNER JOIN (
    		SELECT DISTINCT dtr.local_data_id, task_item_id, local_graph_id FROM graph_templates_item AS gti
        	INNER JOIN graph_local AS gl ON gl.id = gti.local_graph_id
        	LEFT JOIN data_template_rrd AS dtr ON dtr.id = gti.task_item_id
        	LEFT JOIN host AS h ON h.id = gl.host_id
		WHERE graph_type_id IN (4,5,6,7,8,20) AND
          	task_item_id IS NULL AND cdef_id NOT IN (
              	SELECT c.id FROM cdef AS c
		INNER JOIN cdef_items AS ci ON c.id = ci.cdef_id
		WHERE (ci.type = 4 OR (ci.type = 6 AND value LIKE '%DATA_SOURCE%'))
		)) AS dtr ON dl.id = dtr.local_data_id
		WHERE dl.host_id IN (" . $allowed_devices . ") AND
		((dl.snmp_index = '' AND dl.snmp_query_id > 0) OR dtr.local_graph_id IS NULL)
		ORDER BY `name_cache` ASC");

	$sql_count  = ($data === false) ? __('N/A', 'intropage') : count($data);

	$color = read_config_option('intropage_alert_orphaned_ds');

	$panel['detail'] .= '<h4>' . __('Orphaned Data Sources - %s', $sql_count, 'intropage') . '<span class="inpa_sq color_' . $color . '"></span></h4>';

	if (cacti_sizeof($data)) {
		$total_errors += $sql_count;

		if ($color == 'red') {
			$panel['alarm'] = 'red';
		} elseif ($panel['alarm'] == 'green' && $color == "yellow") {
			$panel['alarm'] = 'yellow';
		}

		foreach ($data as $row) {
			$panel['detail'] .= '<a class="linkEditMain" href="' . html_escape($config['url_path'] . 'data_sources.php?action=ds_edit&id=' . $row['local_data_id']) . '">' .
			html_escape($row['name_cache']) . '</a><br/>';
		}
	}

	// DS - bad indexes
	$data = db_fetch_assoc('SELECT dtd.local_data_id,dtd.name_cache
		FROM data_local AS dl
		INNER JOIN data_template_data AS dtd
		ON dl.id=dtd.local_data_id
		INNER JOIN data_template AS dt
		ON dt.id=dl.data_template_id
		INNER  JOIN host AS h
		ON h.id = dl.host_id
		WHERE dl.snmp_index = "" AND dl.snmp_query_id > 0');

	$sql_count  = ($data === false) ? __('N/A', 'intropage') : count($data);

	$color = read_config_option('intropage_alert_bad_indexes');

	$panel['detail'] .= '<h4>' . __('Datasources with bad indexes - %s', $sql_count, 'intropage') . '<span class="inpa_sq color_' . $color . '"></span></h4>';

	if (cacti_sizeof($data)) {

		if ($color == 'red')    {
			$panel['alarm'] = 'red';
		} elseif ($panel['alarm'] == 'green' && $color == "yellow") {
			$panel['alarm'] = 'yellow';
		}

		foreach ($data as $row) {
			$panel['detail'] .= '<a class="linkEditMain" href="' . html_escape($config['url_path'] . 'data_sources.php?action=ds_edit&id=' . $row['local_data_id']) . '">' .
			html_escape($row['name_cache']) . '</a><br/>';

		}
		$total_errors += $sql_count;
	}

	// thold plugin - logonly alert and warning thold
	if (api_plugin_is_enabled('thold')) {
		$data = db_fetch_assoc("SELECT td.id AS td_id, concat(h.description,'-',tt.name) AS td_name,
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

		$sql_count  = ($data === false) ? __('N/A', 'intropage') : count($data);

		$color = read_config_option('intropage_alert_thold_logonly');

		$panel['detail'] .= '<h4>' . __('Thold logonly alert/warning - %s', $sql_count, 'intropage') . '<span class="inpa_sq color_' . $color . '"></span></h4>';

		if (cacti_sizeof($data)) {

			if ($color == 'red') {
				$panel['alarm'] = 'red';
			} elseif ($panel['alarm'] == 'green' && $color == "yellow") {
				$panel['alarm'] = 'yellow';
			}

			foreach ($data as $row) {
				$panel['detail'] .= '<a class="linkEditMain" href="' . html_escape($config['url_path'] . 'plugins/thold/thold.php?action=edit&id=' . $row['td_id']) . '">' .
				html_escape($row['td_name']) . '</a><br/>';
			}

			$total_errors += $sql_count;
		}
	}

	$data = db_fetch_assoc('SELECT host.id, host.description, COUNT(*) AS `count`
		FROM host
		INNER JOIN graph_tree_items
		ON host.id = graph_tree_items.host_id
		GROUP BY description
		HAVING `count` > 1');

	$sql_count  = ($data === false) ? __('N/A', 'intropage') : count($data);

	$color = read_config_option('intropage_alert_more_trees');

	$panel['detail'] .= '<h4>' . __('Devices in more than one tree - %s', $sql_count, 'intropage') . '<span class="inpa_sq color_' . $color . '"></span></h4>';

	if (cacti_sizeof($data)) {

		if ($color == 'red') {
			$panel['alarm'] = 'red';
		} elseif ($panel['alarm'] == 'green' && $color == "yellow") {
			$panel['alarm'] = 'yellow';
		}

		foreach ($data as $row) {
			$sql_hosts = db_fetch_assoc_prepared('SELECT graph_tree.id as gtid, host.description,
				graph_tree_items.title, graph_tree_items.parent, graph_tree.name
				FROM host
				INNER JOIN graph_tree_items
				ON (host.id = graph_tree_items.host_id)
				INNER JOIN graph_tree
				ON (graph_tree_items.graph_tree_id = graph_tree.id)
				WHERE host.id = ?',
				array($row['id']));

			if (cacti_sizeof($sql_hosts)) {
				foreach ($sql_hosts as $host) {
					$parent = $host['parent'];
					$tree   = $host['name'] . ' / ';
					while ($parent != 0) {
						$sql_parent = db_fetch_row('SELECT parent, title FROM graph_tree_items WHERE id = ' . $parent);
						$parent     = $sql_parent['parent'];
						$tree .= $sql_parent['title'] . ' / ';
					}

					$panel['detail'] .= sprintf('<a class="linkEditMain" href="%stree.php?action=edit&id=%d">Node: %s | Tree: %s</a><br/>', html_escape($config['url_path']), $host['gtid'], html_escape($host['description']), $tree);
				}
			}
		}
	}

	$data = db_fetch_assoc("SELECT id, description
		FROM host
		WHERE disabled != 'on'
		AND id NOT IN (
			SELECT DISTINCT host_id
			FROM graph_local
		)
		AND snmp_version != 0");

	$sql_count  = ($data === false) ? __('N/A', 'intropage') : count($data);

	$color = read_config_option('intropage_alert_without_graph');

	$panel['detail'] .= '<h4>' . __('Devices without Graphs - %s', $sql_count, 'intropage') . '<span class="inpa_sq color_' . $color . '"></span></h4>';

	if (cacti_sizeof($data)) {

		if ($color == 'red') {
			$panel['alarm'] = 'red';
		} elseif ($panel['alarm'] == 'green' && $color == "yellow") {
			$panel['alarm'] = 'yellow';
		}

		foreach ($data as $row) {
			$panel['detail'] .= sprintf('<a class="linkEditMain" href="%shost.php?action=edit&amp;id=%d">%s (ID: %d)</a><br/>', html_escape($config['url_path']), $row['id'], html_escape($row['description']), $row['id']);
		}
	}

	$data = db_fetch_assoc("SELECT id, description
		FROM host
		WHERE disabled != 'on'
		AND id NOT IN (
			SELECT DISTINCT host_id
			FROM graph_tree_items
		)");

	$sql_count  = ($data === false) ? __('N/A', 'intropage') : count($data);

	$color = read_config_option('intropage_alert_without_tree');

	$panel['detail'] .= '<h4>' . __('Devices without tree - %s', $sql_count, 'intropage') . '<span class="inpa_sq color_' . $color . '"></span></h4>';

	if (cacti_sizeof($data)) {

		if ($color == 'red')    {
			$panel['alarm'] = 'red';
		} elseif ($panel['alarm'] == 'green' && $color == "yellow") {
			$panel['alarm'] = 'yellow';
		}

		foreach ($data as $row) {
			$panel['detail'] .= sprintf('<a class="linkEditMain" href="%shost.php?action=edit&amp;id=%d">%s (ID: %d)</a><br/>', html_escape($config['url_path']), $row['id'], html_escape($row['description']), $row['id']);
		}
	}

	$data = db_fetch_assoc("SELECT id, description
		FROM host
		WHERE disabled != 'on'
		AND (snmp_community ='public' OR snmp_community='private')
		AND snmp_version IN (1,2)
		ORDER BY description");

	$sql_count  = ($data === false) ? __('N/A', 'intropage') : count($data);

	$color = read_config_option('intropage_alert_default_community');

	$panel['detail'] .= '<h4>' . __('Hosts with default public/private community - %s', $sql_count, 'intropage') . '<span class="inpa_sq color_' . $color . '"></span></h4>';

	if (cacti_sizeof($data)) {

		if ($color == 'red')    {
			$panel['alarm'] = 'red';
		} elseif ($panel['alarm'] == 'green' && $color == "yellow") {
			$panel['alarm'] = 'yellow';
		}

		foreach ($data as $row) {
			$panel['detail'] .= sprintf('<a class="linkEditMain" href="%shost.php?action=edit&amp;id=%d">%s (ID: %d)</a><br/>', html_escape($config['url_path']), $row['id'], html_escape($row['description']), $row['id']);
		}
	}

	// plugin monitor - host without monitoring
	if (api_plugin_is_enabled('monitor')) {
		$data = db_fetch_assoc("SELECT id, description, hostname
			FROM host
			WHERE monitor != 'on'");

		$sql_count  = ($data === false) ? __('N/A', 'intropage') : count($data);

		$color = read_config_option('intropage_alert_without_monitoring');

		$panel['detail'] .= '<h4>' . __('Plugin Monitor - Unmonitored hosts - %s', $sql_count, 'intropage') . '<span class="inpa_sq color_' . $color . '"></span></h4>';

		if (cacti_sizeof($data)) {

			if ($color == 'red')    {
				$panel['alarm'] = 'red';
			} elseif ($panel['alarm'] == 'green' && $color == "yellow") {
				$panel['alarm'] = 'yellow';
			}

			foreach ($data as $row) {
				$panel['detail'] .= sprintf('<a class="linkEditMain" href="%shost.php?action=edit&amp;id=%d">%s %s (ID: %d)</a><br/>', html_escape($config['url_path']), $row['id'], html_escape($row['description']), html_escape($row['hostname']), $row['id']);
			}
		}
	}

	if ($total_errors > 0) {
		$panel['detail'] = '<span class="txt_big">' . __('Found %s problems', $total_errors, 'intropage') . '</span><br/>' . $panel['detail'];
	} else {
		$panel['detail'] = '<span class="txt_big">' . __('Everything OK', 'intropage') . '</span><br/>' . $panel['detail'];
	}

	return $panel;
}


