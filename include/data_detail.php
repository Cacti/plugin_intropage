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

//------------------------------------ analyse_db -----------------------------------------------------
if (!function_exists('array_column')) {
    function array_column($array,$column_name) {
        return array_map(function($element) use($column_name){return $element[$column_name];}, $array);
    }
}

function intropage_analyse_db_detail() {
	global $config;

	$result = array(
		'name' => __('Database check - detail', 'intropage'),
		'alarm' => 'green',
		'detail' => '',
	);

	$result['alarm']  = db_fetch_cell("SELECT value FROM plugin_intropage_trends WHERE name='db_check_alarm'");
	$result['detail']   = db_fetch_cell("SELECT value FROM plugin_intropage_trends WHERE name='db_check_detail'");

	if (!$result['detail']) {
	    $result['alarm'] = 'yellow';
	    $result['detail'] = __('Waiting for data', 'intropage');
	}

	$result['detail'] .= '<br/><br/>' . __('Last check', 'intropage') . ': ' . db_fetch_cell("SELECT value FROM plugin_intropage_trends WHERE name='db_check_testdate'") . '<br/>';
	$often = read_config_option('intropage_analyse_db_interval');
        if ($often == 900) {
            $result['detail'] .= __('Checked every 15 minutes', 'intropage');
        } elseif ($often == 3600) {
            $result['detail'] .= __('Checked hourly', 'intropage');
        } elseif ($often == 86400) {
            $result['detail'] .= __('Checked daily', 'intropage');
        } elseif ($often == 604800) {
            $result['detail'] .= __('Checked weekly', 'intropage');
        } elseif ($often == 2592000) {
            $result['detail'] .= __('Checked monthly', 'intropage');
        } else {
            $result['detail'] .= __('Periodic check is disabled', 'intropage');
        }

	$result['detail'] .= '<br/><br/>';

	return $result;
}

//------------------------------------ analyse_log -----------------------------------------------------

function intropage_analyse_log_detail() {
	global $config, $log;

	$result = array(
		'name' => __('Analyse cacti log - detail', 'intropage'),
		'alarm' => 'green',
		'detail' => '',
	);

	$log = array(
		'file' => read_config_option('path_cactilog'),
		'nbr_lines' => read_config_option('intropage_analyse_log_rows'),
	);

	$log['size']  = @filesize($log['file']);
	$log['lines'] = tail_log($log['file'], $log['nbr_lines']*2);

	if (!$log['size'] || empty($log['lines'])) {
		$result['alarm'] = 'red';
		$result['detail'] .= __('Log file not accessible or empty', 'intropage');
	} else {
		$error  = 0;
		$ecount = 0;
		$warn   = 0;
		foreach ($log['lines'] as $line) {
			if (preg_match('/(WARN|ERROR|FATAL)/', $line, $matches)) {
				if (strcmp($matches[1], 'WARN') === 0) {
					$warn++;
					$ecount++;
					$result['detail'] .= '<b>' . $line . '</b><br/>';
				} elseif (strcmp($matches[1], 'ERROR') === 0 || strcmp($matches[1], 'FATAL') === 0) {
					$error++;
					$ecount++;
					$result['detail'] .= '<b>' . $line .'</b><br/>';
				}
			}
		}

		$result['detail'] .= '<span class="txt_big">';
		$result['detail'] .= __('Errors', 'intropage') . ': ' . $error . '</span><a href="clog.php?message_type=3&tail_lines=' . $log['nbr_lines'] . '"><i class="fa fa-external-link"></i></a><br/>';
		$result['detail'] .= '<span class="txt_big">';
		$result['detail'] .= __('Warnings', 'intropage') . ': ' . $warn . '</span><a href="clog.php?message_type=2&tail_lines=' . $log['nbr_lines'] . '"><i class="fa fa-external-link"></i></a><br/>';
		$result['detail'] .= '</span>';

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

		$result['detail'] .= '<span class="txt_big">' . __('Log size', 'intropage') . ': ' . $log_size_text .'</span><br/>';
		if (!empty($log_size_note)) {
			$result['detail'] .= '(' . $log_size_note . ')<br/>';
		}
		$result['detail'] .= '<br/>' . __('(Errors and warning in last %s lines)', read_config_option('intropage_analyse_log_rows')*2, 'intropage');

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

function intropage_analyse_login_detail() {
	global $config;

	$result = array(
		'name' => __('Analyse logins - detail', 'intropage'),
		'alarm' => 'green',
		'detail' => '',
	);

	$sql_result = db_fetch_assoc('SELECT user_log.username, user_auth.full_name, user_log.time, user_log.result, user_log.ip
		FROM user_auth
		INNER JOIN user_log
		ON user_auth.username = user_log.username
		ORDER BY user_log.time desc
		LIMIT 10');

	if (cacti_sizeof($sql_result)) {
		$result['detail'] .= '<table>';
		foreach ($sql_result as $row) {
			if ($row['result'] == 0) {
				$result['alarm'] = 'red';
			}

			$result['detail'] .= sprintf('<tr><td class="rpad">%s </td><td class="rpad">%s </td><td class="rpad">%s </td><td>%s</td></tr>', $row['time'], $row['ip'], $row['username'], ($row['result'] == 0) ? __('failed', 'intropage') : __('success', 'intropage'));

		}
		$result['detail'] .= '</table>';
	}

	// active users in last hour:
	$result['detail'] .= '<br/><b>Active users in last 2 hours:</b><br/>';

	$sql_result = db_fetch_assoc('SELECT DISTINCT username
		FROM user_log
		WHERE time > adddate(now(), INTERVAL -2 HOUR)');

	foreach ($sql_result as $row) {
		$result['detail'] .= $row['username'] . '<br/>';
	}

	$loggin_access = (db_fetch_assoc("SELECT realm_id
		FROM user_auth_realm
		WHERE user_id='" . $_SESSION['sess_user_id'] . "'
		AND user_auth_realm.realm_id=19")) ? true : false;

	if ($result['detail'] && $loggin_access) {
		$result['detail'] .= '<br/><br/><a href="' . html_escape($config['url_path'] . 'utilities.php?action=view_user_log') . '">' . __('Full log') . '</a><br/>';
	}

	return $result;
}

//------------------------------------ analyse_tree_host_graph  -----------------------------------------------------

function intropage_analyse_tree_host_graph_detail() {
	global $config;

	$result = array(
		'name' => __('Analyse tree/host/graph', 'intropage'),
		'alarm' => 'green',
		'detail' => '',
	);

	$total_errors = 0;

	// hosts with same IP
	if ($_SESSION['allowed_hosts'])	{
		$sql_result = db_fetch_assoc("SELECT COUNT(*) AS NoDups, id, hostname, snmp_port
			FROM host
			WHERE id IN (" . $_SESSION['allowed_hosts'] . ")
			AND disabled != 'on'
			GROUP BY hostname,snmp_port
			HAVING NoDups > 1");

		$sql_count  = ($sql_result === false) ? __('N/A', 'intropage') : count($sql_result);

		$result['detail'] .= '<br/><b>' . __('Devices with the same IP and port - %s', $sql_count, 'intropage') . ':</b><br/>';

		if (cacti_sizeof($sql_result)) {
			$total_errors += $sql_count;
			if (count($sql_result) > 0) {
				$result['alarm'] = 'red';
				foreach ($sql_result as $row) {

					$sql_hosts = db_fetch_assoc("SELECT id, description, hostname
						FROM host
						WHERE hostname = " . db_qstr($row['hostname']) . " AND snmp_port=" . $row['snmp_port']);

					if (cacti_sizeof($sql_hosts)) {
						foreach ($sql_hosts as $row2) {
							$result['detail'] .= sprintf('<a href="%shost.php?action=edit&amp;id=%d">%s %s (ID: %d)</a><br/>', html_escape($config['url_path']), $row['id'], html_escape($row2['description']), html_escape($row2['hostname']), $row2['id']);
						}
					}
				}
			}
		}
	}

	// same description
	if ($_SESSION['allowed_hosts'])	{
		$sql_result = db_fetch_assoc("SELECT COUNT(*) AS NoDups, id, description
			FROM host
			WHERE id IN (" . $_SESSION['allowed_hosts'] . ")
			AND disabled != 'on'
			GROUP BY description
			HAVING NoDups > 1");

		$sql_count  = ($sql_result === false) ? __('N/A', 'intropage') : count($sql_result);

		$result['detail'] .= '<br/><b>' . __('Devices with the same description - %s', $sql_count, 'intropage') . ':</b><br/>';

		if (cacti_sizeof($sql_result)) {
			$total_errors += $sql_count;
			if (count($sql_result) > 0) {
				$result['alarm'] = 'red';
				foreach ($sql_result as $row) {
					$sql_hosts = db_fetch_assoc("SELECT id, description, hostname
						FROM host
						WHERE description = " . db_qstr($row['description']));

					if (cacti_sizeof($sql_hosts)) {
						foreach ($sql_hosts as $row2) {
							$result['detail'] .= sprintf('<a href="%shost.php?action=edit&amp;id=%d">%s (ID: %d)</a><br/>', html_escape($config['url_path']), $row2['id'], html_escape($row2['description']), $row2['id']);
						}
					}
				}
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

	$result['detail'] .= '<br/><b>' . __('Orphaned Data Sources - %s', $sql_count, 'intropage') . ':</b><br/>';

	if (cacti_sizeof($sql_result)) {
		$total_errors += $sql_count;

		if ($result['alarm'] == 'green') {
			$result['alarm'] = 'yellow';
		}

		foreach ($sql_result as $row) {
			$result['detail'] .= '<a href="' . html_escape($config['url_path'] . 'data_sources.php?action=ds_edit&id=' . $row['local_data_id']) . '">' .
			html_escape($row['name_cache']) . '</a><br/>';
		}
	}

	// empty poller_output
	$sql_result = db_fetch_assoc('SELECT local_data_id,rrd_name FROM poller_output');

	$sql_count  = ($sql_result === false) ? __('N/A', 'intropage') : count($sql_result);

	$result['detail'] .= '<br/><b>' . __('Poller output items - %s:', $sql_count, 'intropage') . '</b><br/>';

	if (cacti_sizeof($sql_result)) {

		if ($result['alarm'] == 'green') {
			$result['alarm'] = 'yellow';
		}

		foreach ($sql_result as $row) {
			$result['detail'] .= '<a href="' . html_escape($config['url_path'] . 'data_sources.php?action=ds_edit&id=' . $row['local_data_id']) . '">' .
			html_escape($row['rrd_name']) . '</a><br/>';

		}
		$total_errors += $sql_count;
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

	$result['detail'] .= '<br/><b>' . __('Datasources with bad indexes - %s:', $sql_count, 'intropage') . '</b><br/>';

	if (cacti_sizeof($sql_result)) {

		if ($result['alarm'] == 'green') {
			$result['alarm'] = 'yellow';
		}

		foreach ($sql_result as $row) {
			$result['detail'] .= '<a href="' . html_escape($config['url_path'] . 'data_sources.php?action=ds_edit&id=' . $row['local_data_id']) . '">' .
			html_escape($row['name_cache']) . '</a><br/>';

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

	    $result['detail'] .= '<br/><b>' . __('Thold logonly alert/warning - %s:', $sql_count, 'intropage') . '</b><br/>';

	    if (cacti_sizeof($sql_result)) {
			if ($result['alarm'] == 'green') {
				$result['alarm'] = 'yellow';
			}

			foreach ($sql_result as $row) {
				$result['detail'] .= '<a href="' . html_escape($config['url_path'] . 'plugins/thold/thold.php?action=edit&id=' . $row['td_id']) . '">' .
				html_escape($row['td_name']) . '</a><br/>';
			}

			$total_errors += $sql_count;
	    }
	}


	// below - only information without red/yellow/green
	$result['detail'] .= '<br/><b>' . __('Information only (no warn/error)') . ':</b><br/>';

	// device in more trees
	if ($_SESSION['allowed_hosts'])	{
		$sql_result = db_fetch_assoc('SELECT host.id, host.description, COUNT(*) AS `count`
			FROM host
			INNER JOIN graph_tree_items
			ON (host.id = graph_tree_items.host_id)
			WHERE host.id IN (' . $_SESSION['allowed_hosts'] . ')
			GROUP BY description
			HAVING `count` > 1');

		$sql_count  = ($sql_result === false) ? __('N/A', 'intropage') : count($sql_result);

		$result['detail'] .= '<br/><b>' . __('Devices in more than one tree - %s', $sql_count, 'intropage') . ':</b><br/>';

		if (cacti_sizeof($sql_result)) {

			foreach ($sql_result as $row) {
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

						$result['detail'] .= sprintf('<a href="%stree.php?action=edit&id=%d">Node: %s | Tree: %s</a><br/>', html_escape($config['url_path']), $host['gtid'], html_escape($host['description']), $tree);
					}
				}
			}
		}
	}

	// host without graph
	if ($_SESSION['allowed_hosts'])	{
		$sql_result = db_fetch_assoc("SELECT id, description
			FROM host
			WHERE id IN (" . $_SESSION['allowed_hosts'] . ")
			AND disabled != 'on'
			AND id NOT IN (
				SELECT DISTINCT host_id
				FROM graph_local
			)
			AND snmp_version != 0");

		$sql_count  = ($sql_result === false) ? __('N/A', 'intropage') : count($sql_result);

		$result['detail'] .= '<br/><b>' . __('Hosts without graphs - %s', $sql_count, 'intropage') . ':</b><br/>';

		if (cacti_sizeof($sql_result)) {
			foreach ($sql_result as $row) {
				$result['detail'] .= sprintf('<a href="%shost.php?action=edit&amp;id=%d">%s (ID: %d)</a><br/>', html_escape($config['url_path']), $row['id'], html_escape($row['description']), $row['id']);
			}
		}
	}

	// host without tree
	if ($_SESSION['allowed_hosts'])	{
		$sql_result = db_fetch_assoc("SELECT id, description
			FROM host
			WHERE id IN (" . $_SESSION['allowed_hosts'] . ")
			AND disabled != 'on'
			AND id NOT IN (
				SELECT DISTINCT host_id
				FROM graph_tree_items)
			");

		$sql_count  = ($sql_result === false) ? __('N/A', 'intropage') : count($sql_result);

		$result['detail'] .= '<br/><b>' . __('Hosts without tree - %s', $sql_count, 'intropage') . ':</b><br/>';

		if (cacti_sizeof($sql_result)) {

			foreach ($sql_result as $row) {
				$result['detail'] .= sprintf('<a href="%shost.php?action=edit&amp;id=%d">%s (ID: %d)</a><br/>', html_escape($config['url_path']), $row['id'], html_escape($row['description']), $row['id']);
			}
		}
	}

	// public/private community
	if ($_SESSION['allowed_hosts'])	{
		$sql_result = db_fetch_assoc("SELECT id, description
			FROM host
			WHERE id IN (" . $_SESSION['allowed_hosts'] . ")
			AND disabled != 'on'
			AND (snmp_community ='public' OR snmp_community='private')
			ORDER BY description");

		$sql_count  = ($sql_result === false) ? __('N/A', 'intropage') : count($sql_result);

		$result['detail'] .= '<br/><b>' . __('Hosts with default public/private community - %s', $sql_count, 'intropage') . ':</b><br/>';

		if (cacti_sizeof($sql_result)) {

			foreach ($sql_result as $row) {
				$result['detail'] .= sprintf('<a href="%shost.php?action=edit&amp;id=%d">%s (ID: %d)</a><br/>', html_escape($config['url_path']), $row['id'], html_escape($row['description']), $row['id']);
			}
		}
	}

	// plugin monitor - host without monitoring
	if (db_fetch_cell("SELECT directory FROM plugin_config WHERE directory='monitor'")) { // installed plugin monitor?
		if ($_SESSION['allowed_hosts'])	{
			$sql_result = db_fetch_assoc("SELECT id, description, hostname
				FROM host
				WHERE id IN (" . $_SESSION['allowed_hosts'] . ")
				AND monitor != 'on'");

			$sql_count  = ($sql_result === false) ? __('N/A', 'intropage') : count($sql_result);

			$result['detail'] .= '<br/><b>' . __('Plugin Monitor - Unmonitored hosts - %s', $sql_count, 'intropage') . ':</b><br/>';

			if (cacti_sizeof($sql_result)) {

				foreach ($sql_result as $row) {
					$result['detail'] .= sprintf('<a href="%shost.php?action=edit&amp;id=%d">%s %s (ID: %d)</a><br/>', html_escape($config['url_path']), $row['id'], html_escape($row['description']), html_escape($row['hostname']), $row['id']);
				}
			}
		}
	}

	if ($total_errors > 0) {
		$result['detail'] = '<span class="txt_big">' . __('Found %s problems', $total_errors, 'intropage') . '</span><br/>' . $result['detail'];
	} else {
		$result['detail'] = '<span class="txt_big">' . __('Everything OK', 'intropage') . '</span><br/>' . $result['detail'];
	}

	return $result;
}


//------------------------------------ extrem -----------------------------------------------------

function intropage_extrem_detail() {
	global $config, $console_access;

	$result = array(
		'name' => __('48 hour extrem', 'intropage'),
		'alarm' => 'grey',
		'detail' => '',
	);

	$result['detail'] .= '<table><tr><td class="rpad">';

	// long run poller
	$result['detail'] .= '<strong>' . __('Long run<br/>poller', 'intropage') . ': </strong>';

	$sql_result = db_fetch_assoc("SELECT date_format(cur_timestamp,'%d.%m. %H:%i') AS `date`,
		substring(value,instr(value,':')+1) AS xvalue
		FROM plugin_intropage_trends
		WHERE name='poller'
		AND cur_timestamp > date_sub(now(),interval 2 day)
		ORDER BY xvalue desc, cur_timestamp
		LIMIT 10");

	if (cacti_sizeof($sql_result)) {
		foreach ($sql_result as $row) {
			$result['detail'] .= '<br/>' . $row['date'] . ' ' . $row['xvalue'] . 's';
		}
	} else {
		$result['detail'] .= '<br/>' . __('Waiting<br/>for data', 'intropage');
	}

	$result['detail'] .= '</td>';

	// max host down
	$result['detail'] .= '<td class="rpad texalirig">';
	$result['detail'] .= '<strong>Max host<br/>down: </strong>';

	$sql_result = db_fetch_assoc("SELECT date_format(cur_timestamp,'%d.%m. %H:%i') AS `date`, value
		FROM plugin_intropage_trends
		WHERE name='host'
		AND cur_timestamp > date_sub(now(),interval 2 day)
		ORDER BY value desc,cur_timestamp
		LIMIT 10");

	if (cacti_sizeof($sql_result)) {
		foreach ($sql_result as $row) {
			$result['detail'] .= '<br/>' . $row['date'] . ' ' . $row['value'];
		}
	} else {
		$result['detail'] .= '<br/>' . __('Waiting<br/>for data', 'intropage');
	}

	$result['detail'] .= '</td>';

	// max thold trig
	// extrems doesn't use user permission!
	$result['detail'] .= '<td class="rpad texalirig">';
	$result['detail'] .= '<strong>' . __('Max thold<br/>triggered:', 'intropage') .'</strong>';

	if (db_fetch_cell("SELECT directory FROM plugin_config WHERE directory='thold' and status=1")) {
		$sql_result = db_fetch_assoc("SELECT date_format(cur_timestamp,'%d.%m. %H:%i') AS `date`, value
			FROM plugin_intropage_trends
			WHERE name='thold'
			AND cur_timestamp > date_sub(now(),interval 2 day)
			ORDER BY value desc,cur_timestamp
			LIMIT 10");

		if (cacti_sizeof($sql_result)) {
			foreach ($sql_result as $row) {
				$result['detail'] .= '<br/>' . $row['date'] . ' ' . $row['value'];
			}
		} else {
			$result['detail'] .= '<br/>Waiting<br/>for data';
		}
	} else {
		$result['detail'] .= '<br/>no<br/>plugin<br/>installed<br/>or<br/> running';
	}

	$result['detail'] .= '</td>';

	// poller output items
	$result['detail'] .= '<td class="rpad texalirig">';
	$result['detail'] .= '<strong>' . __('Poller<br/>output item:', 'intropage') . '</strong>';

	$sql_result = db_fetch_assoc("SELECT date_format(cur_timestamp,'%d.%m. %H:%i') AS `date`, value
		FROM plugin_intropage_trends
		WHERE name='poller_output'
		AND cur_timestamp > date_sub(now(),interval 2 day)
		ORDER BY value desc,cur_timestamp
		LIMIT 10");

	if (cacti_sizeof($sql_result)) {
		foreach ($sql_result as $row) {
			$result['detail'] .= '<br/>' . $row['date'] . ' ' . $row['value'];
		}
	} else {
		$result['detail'] .= '<br/>Waiting<br/>for data';
	}

	$result['detail'] .= '</td>';

	// poller output items
	$result['detail'] .= '<td class="rpad texalirig">';
	$result['detail'] .= '<strong>' . __('Failed<br/>polls:', 'intropage') . '</strong>';

	$sql_result = db_fetch_assoc("SELECT date_format(cur_timestamp,'%d.%m. %H:%i') AS `date`, value
		FROM plugin_intropage_trends
		WHERE name='failed_polls'
		AND cur_timestamp > date_sub(now(),interval 2 day)
		ORDER BY value desc,cur_timestamp
		LIMIT 10");

	if (cacti_sizeof($sql_result)) {
		foreach ($sql_result as $row) {
			$result['detail'] .= '<br/>' . $row['date'] . ' ' . $row['value'];
		}
	} else {
		$result['detail'] .= '<br/>Waiting<br/>for data';
	}
	$result['detail'] .= '</td>';

	$result['detail'] .= '</tr></table>';

	return $result;
}

//------------------------------------ graph_datasource -----------------------------------------------------

function intropage_graph_data_source_detail() {
	global $config, $input_types;

	$result = array(
		'name' => 'Data sources',
		'alarm' => 'grey',
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
		GROUP BY type_id');

	$total = 0;

	if (cacti_sizeof($sql_ds)) {
		foreach ($sql_ds as $item) {
			if (!is_null($item['type_id'])) {
				$result['detail'] .= preg_replace('/script server/', 'SS', $input_types[$item['type_id']]) . ': ';
				$result['detail'] .= $item['total'] . '<br/>';
				$total += $item['total'];
			}
		}
		$result['detail'] .= '<br/><b> Total: ' . $total . '</b><br/>';
	} else {
		$result['detail'] = __('No untemplated datasources found');
	}

	return $result;
}

//------------------------------------ graph_host -----------------------------------------------------

function intropage_graph_host_detail() {
	global $config, $console_access;

	$result = array(
		'name' => __('Hosts', 'intropage'),
		'alarm' => 'green',
		'detail' => '',
	);

	$h_all  = db_fetch_cell("SELECT count(id) FROM host WHERE id IN (" . $_SESSION['allowed_hosts'] . ")");
	$h_up   = db_fetch_cell("SELECT count(id) FROM host WHERE id IN (" . $_SESSION['allowed_hosts'] . ") AND status=3 AND disabled=''");
	$h_down = db_fetch_cell("SELECT count(id) FROM host WHERE id IN (" . $_SESSION['allowed_hosts'] . ") AND status=1 AND disabled=''");
	$h_reco = db_fetch_cell("SELECT count(id) FROM host WHERE id IN (" . $_SESSION['allowed_hosts'] . ") AND status=2 AND disabled=''");
	$h_disa = db_fetch_cell("SELECT count(id) FROM host WHERE id IN (" . $_SESSION['allowed_hosts'] . ") AND disabled='on'");

	$count = $h_all + $h_up + $h_down + $h_reco + $h_disa;
	$url_prefix = $console_access ? '<a href="' . html_escape($config['url_path'] . 'host.php?host_status=%s') . '">' : '';
	$url_suffix = $console_access ? '</a>' : '';

	$result['detail']  = sprintf($url_prefix,'-1') . __('All', 'intropage') . ": $h_all$url_suffix<br/>";
	$result['detail'] .= sprintf($url_prefix,'=3') . __('Up', 'intropage') . ": $h_up$url_suffix<br/>";
	$result['detail'] .= sprintf($url_prefix,'=1') . __('Down', 'intropage') . ": $h_down$url_suffix<br/>";
	$result['detail'] .= sprintf($url_prefix,'=-2') . __('Disabled', 'intropage') . ": $h_disa$url_suffix<br/>";
	$result['detail'] .= sprintf($url_prefix,'=2') . __('Recovering', 'intropage') . ": $h_reco$url_suffix";

	// alarms and details
	if ($h_reco > 0) {
		$result['alarm'] = 'yellow';

		$hosts = db_fetch_assoc("SELECT description FROM host WHERE id IN (" . $_SESSION['allowed_hosts'] . ") AND status=2 AND disabled=''");

		$result['detail'] .= '<b>' . __('RECOVERING', 'intropage') . ':</b><br/>';

		if (cacti_sizeof($hosts)) {
			foreach ($hosts as $host) {
				$result['detail'] .= html_escape($host['description']) . '<br/>';
			}
		}

		$result['detail'] .= '<br/><br/>';
	}

	if ($h_down > 0) {
		$result['alarm'] = 'red';

		$hosts = db_fetch_assoc("SELECT description FROM host WHERE id IN (" . $_SESSION['allowed_hosts'] . ") AND status=1 AND disabled=''");

		$result['detail'] .= '<b>' . __('DOWN', 'intropage') . ':</b><br/>';

		if (cacti_sizeof($hosts)) {
			foreach ($hosts as $host) {
				$result['detail'] .= html_escape($host['description']) . '<br/>';
			}
		}

		$result['detail'] .= '<br/><br/>';
	}

	return $result;
}

//------------------------------------ graph host_template -----------------------------------------------------

function intropage_graph_host_template_detail() {
	global $config;

	$result = array(
		'name' => __('Device Templates', 'intropage'),
		'alarm' => 'grey',
		'detail' => '',
	);

	$sql_ht = db_fetch_assoc("SELECT host_template.id as id, name, count(host.host_template_id) AS total
		FROM host_template
		LEFT JOIN host
		ON (host_template.id = host.host_template_id) AND host.id IN (" . $_SESSION['allowed_hosts'] . ")
		GROUP by host_template_id
		ORDER BY total desc");

	$total = 0;

	if (cacti_sizeof($sql_ht)) {
		foreach ($sql_ht as $item) {
			$result['detail'] .= $item['name'] . ': ';
			$result['detail'] .= $item['total'] . '<br/>';
			$total += $item['total'];
		}

		$result['detail'] .= '<br/><b> Total: ' . $total . '</b><br/>';

	} else {
		$result['detail'] = __('No device templates found', 'intropage');
	}

	return $result;
}

//------------------------------------ graph_thold -----------------------------------------------------

function intropage_graph_thold_detail() {
	global $config, $sql_where;

	$result = array(
		'name' => __('Thresholds', 'intropage'),
		'alarm' => 'green',
		'detail' => '',
	);

	if (!db_fetch_cell("SELECT directory FROM plugin_config WHERE directory='thold' and status=1")) {
		$result['alarm'] = 'grey';
		$result['detail']  = __('Thold plugin not installed/running', 'intropage');
		unset($result['pie']);
	} elseif (!db_fetch_cell('SELECT DISTINCT user_id FROM user_auth_realm WHERE user_id = ' . $_SESSION['sess_user_id'] . " AND realm_id IN (SELECT id + 100 FROM plugin_realms WHERE file LIKE '%thold%')")) {
		$result['detail'] = __('You don\'t have permission', 'intropage');
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
		$url_prefix = $has_access ? '<a href="' . html_escape($config['url_path'] . 'plugins/thold/thold_graph.php?tab=thold&triggered=%s') . '">' : '';
		$url_suffix = $has_access ? '</a>' : '';

		$result['detail']  = sprintf($url_prefix, '-1') . __('All', 'intropage') . ": $t_all$url_suffix<br/>";
		$result['detail'] .= sprintf($url_prefix, '1') . __('Breached', 'intropage') . ": $t_brea$url_suffix<br/>";
		$result['detail'] .= sprintf($url_prefix, '3') . __('Trigged', 'intropage') . ": $t_trig$url_suffix<br/>";
		$result['detail'] .= sprintf($url_prefix, '0') . __('Disabled', 'intropage') . ": $t_disa$url_suffix<br/><br/>";

		// alarms and details
		if ($t_brea > 0) {
			$result['alarm'] = 'yellow';
			$hosts           = db_fetch_assoc("SELECT description FROM thold_data $sql_join WHERE (thold_data.thold_alert!=0 OR thold_data.bl_alert>0) AND $sql_where");
			$result['detail'] .= '<b>' . __('BREACHED', 'intropage') . ':</b><br/>';
			foreach ($hosts as $host) {
				$result['detail'] .= html_escape($host['description']) . '<br/>';
			}
			$result['detail'] .= '<br/><br/>';
		}

		if ($t_trig > 0) {
			$result['alarm'] = 'red';
			$hosts           = db_fetch_assoc("SELECT description FROM thold_data $sql_join WHERE (thold_data.thold_alert!=0 OR thold_data.bl_fail_count >= thold_data.bl_fail_trigger) AND $sql_where");
			$result['detail'] .= '<b>' . __('TRIGGERED', 'intropage') .':</b><br/>';
			foreach ($hosts as $host) {
				$result['detail'] .= html_escape($host['description']) . '<br/>';
			}
			$result['detail'] .= '<br/><br/>';
		}
	}

	return $result;
}


//------------------------------------ mactrack sites -----------------------------------------------------

function intropage_mactrack_sites_detail() {
	global $config, $console_access;

	$result = array(
		'name' => __('Mactrack sites', 'intropage'),
		'alarm' => 'grey',
		'detail' => '',

	);

	$result['detail'] .= '<table><tr><td class="rpad">' . __('Site', 'intropage') . '</td><td class="rpad">' . __('Devices', 'intropage') . '</td>';
	$result['detail'] .= '<td class="rpad">' . __('IPs', 'intropage') . '</td><td class="rpad">' . __('Ports', 'intropage') . '</td>';
	$result['detail'] .= '<td class="rpad">' . __('Ports up', 'intropage') . '</td><td class="rpad">' . __('MACs', 'intropage') . '</td>';
	$result['detail'] .= '<td class="rpad">' . __('Device errors', 'intropage') . '</td></tr>';

	$sql_result = db_fetch_assoc('SELECT site_name, total_devices, total_device_errors, total_macs, total_ips, total_oper_ports, total_user_ports FROM mac_track_sites  order by total_devices desc');
	if (sizeof($sql_result) > 0) {
		foreach ($sql_result as $site) {
			$row = '<tr><td>' . html_escape($site['site_name']) . '</td><td>' . $site['total_devices'] . '</td>';
			$row .= '<td>' . $site['total_ips'] . '</td><td>' . $site['total_user_ports'] . '</td>';
			$row .= '<td>' . $site['total_oper_ports'] . '</td><td>' . $site['total_macs'] . '</td>';
			$row .= '<td>' . $site['total_device_errors'] . '</td></tr>';
				$result['detail'] .= $row;
		}

		$result['detail'] .= '</table>';
	} else {
	    $result['detail'] = __('No mactrack sites found', 'intropage');
	}

	return $result;
}


//------------------------------------ poller_info -----------------------------------------------------

function intropage_poller_info_detail() {
	global $config;

	$result = array(
		'name' => __('Poller info', 'intropage'),
		'alarm' => 'green',
		'detail' => '',
	);

	$row = '<tr><td class="rpad">' . __('ID', 'intropage') . '</td><td class="rpad">' . __('Name', 'intropage') . '</td>' .
		'<td class="rpad">' . __('Total time', 'intropage') . '</td><td class="rpad">' . __('State', 'intropage') . '</td></tr>';

	$sql_pollers = db_fetch_assoc('SELECT p.id, name, status, last_update, total_time
		FROM poller p
		LEFT JOIN poller_time pt
		ON pt.poller_id = p.id
		WHERE p.disabled = ""
		GROUP BY p.id
		ORDER BY p.id');

	$count    = $sql_pollers === false ? __('N/A', 'intropage') : count($sql_pollers);
	$ok       = 0;
	$running  = 0;

	if (cacti_sizeof($sql_pollers)) {
		foreach ($sql_pollers as $poller) {
			if ($poller['status'] == 0 || $poller['status'] == 1 || $poller['status'] == 2 || $poller['status'] == 5) {
				$ok++;
			}

			$row .= '<td class="rpad texalirig">' . $poller['id'] . '</td>';
			$row .= '<td class="rpad texalirig">' . $poller['name'] . '</td>';
			$row .= '<td class="rpad texalirig">' . round($poller['total_time']) . 's</td>';

			if ($poller['status'] == 0) {
				$row .= '<td class="rpad texalirig">' . __('New/Idle', 'intropage') . '</td>';
			} elseif ($poller['status'] == 1) {
				$row .= '<td class="rpad texalirig">' . __('Running', 'intropage') . '</td>';
			} elseif ($poller['status'] == 2) {
				$row .= '<td class="rpad texalirig">' . __('Idle', 'intropage') . '</td>';
			} elseif ($poller['status'] == 3) {
				$row .= '<td class="rpad texalirig">' . __('Unkn/down', 'intropage') . '</td>';
			} elseif ($poller['status'] == 4) {
				$row .= '<td class="rpad texalirig">' . __('Disabled', 'intropage') . '</td>';
			} elseif ($poller['status'] == 5) {
				$row .= '<td class="rpad texalirig">' . __('Recovering', 'intropage') . '</td>';
			}

			$row .= '</tr>';
		}
	}

	$result['detail'] = '<span class="txt_big">' . $ok . '</span>' . __('(ok)', 'intropage') . '<span class="txt_big">/' . $count . '</span>' . __('(all)', 'intropage') . '</span><br/>' . $result['detail'];

	$result['detail'] = '<br/><br/><table>' . $row . '</table>';


	if ($sql_pollers === false || $count > $ok) {
		$result['alarm'] = 'red';
	} else {
		$result['alarm'] = 'green';
	}

	return $result;
}


//------------------------------------ thold_events -----------------------------------------------------

function intropage_thold_event_detail() {
	global $config;

	$result = array(
		'name' => __('Last thold events'),
		'alarm' => 'green',
		'detail' => '',
	);

	if (db_fetch_cell("SELECT count(*) FROM plugin_config WHERE directory='thold' AND status = 1") == 0) {
		$result['alarm'] = 'yellow';
		$result['detail']  = __('Plugin Thold isn\'t installed or started', 'intropage');
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
			LIMIT 20');

		if (cacti_sizeof($sql_result)) {
			foreach ($sql_result as $row) {
				$result['detail'] .= date('Y-m-d H:i:s', $row['time']) . ' - ' . html_escape($row['description']) . '<br/>';
				if ($row['status'] == 1 || $row['status'] == 4 || $row['status'] == 7) {
					$result['alarm'] = 'red';
				} elseif ($result['alarm'] == 'green' && ($row['status'] == 2 || $row['status'] == 3)) {
					$result['alarm'] == 'yellow';
				}
			}
		} else {
			$result['detail'] = __('Without events yet', 'intropage');
		}
	}

	return $result;
}


//------------------------------------ top5_ping -----------------------------------------------------

function intropage_top5_ping_detail() {
	global $config, $console_access;

	$result = array(
		'name' => __('Top 20 hosts with the worst ping (avg, current)', 'intropage'),
		'alarm' => 'green',
		'detail' => '',
	);

	$sql_worst_host = db_fetch_assoc("SELECT description, id, avg_time, cur_time
		FROM host
		WHERE host.id in (" . $_SESSION['allowed_hosts'] . ")
		AND disabled != 'on'
		ORDER BY avg_time desc
		LIMIT 20");

	if (cacti_sizeof($sql_worst_host)) {
		foreach ($sql_worst_host as $host) {
			if ($console_access) {
				$row = '<tr><td class="rpad"><a href="' . html_escape($config['url_path'] . 'host.php?action=edit&id=' . $host['id']) . '">' . html_escape($host['description']) . '</a>';
			} else {
				$row = '<tr><td class="rpad">' . html_escape($host['description']) . '</td>';
			}

			$row .= '<td class="rpad texalirig">' . round($host['avg_time'], 2) . 'ms</td>';

			if ($host['cur_time'] > 1000) {
				$result['alarm'] = 'yellow';
				$row .= '<td class="rpad texalirig"><b>' . round($host['cur_time'], 2) . 'ms</b></td></tr>';
			} else {
				$row .= '<td class="rpad texalirig">' . round($host['cur_time'], 2) . 'ms</td></tr>';
			}

			$result['detail'] .= $row;

		}
		$result['detail'] = '<table>' . $result['detail'] . '</table>';

	} else {	// no data
		$result['detail'] = __('Waiting for data', 'intropage');
	}

	return $result;
}


//------------------------------------ top5_availability -----------------------------------------------------

function intropage_top5_availability_detail() {
	global $config, $console_access;

	$result = array(
		'name' => __('Top 20 hosts with the worst availability', 'intropage'),
		'alarm' => 'green',
		'detail' => '',
	);

	$sql_worst_host = db_fetch_assoc("SELECT description, id, availability
		FROM host
		WHERE host.id IN (" . $_SESSION['allowed_hosts'] . ")
		AND disabled != 'on'
		ORDER BY availability
		LIMIT 20");

	if (cacti_sizeof($sql_worst_host)) {

		foreach ($sql_worst_host as $host) {
			if ($console_access) {
				$row = '<tr><td class="rpad"><a href="' . html_escape($config['url_path'] . 'host.php?action=edit&id=' . $host['id']) . '">' . html_escape($host['description']) . '</a>';
			} else {
				$row = '<tr><td class="rpad">' . html_escape($host['description']) . '</td>';
			}

			if ($host['availability'] < 90) {
				$result['alarm'] = 'yellow';
				$row .= '<td class="rpad texalirig"><b>' . round($host['availability'], 2) . '%</b></td></tr>';
			} else {
				$row .= '<td class="rpad texalirig">' . round($host['availability'], 2) . '%</td></tr>';
			}

			$result['detail'] .= $row;

		}
		$result['detail'] = '<table>' . $result['detail'] . '</table>';

	} else {	// no data
		$result['detail'] = __('Waiting for data', 'intropage');
	}

	return $result;
}


//------------------------------------ top5_polltime -----------------------------------------------------

function intropage_top5_polltime_detail() {
	global $config, $console_access;

	$result = array(
		'name' => __('Top 20 hosts worst polling time', 'intropage'),
		'alarm' => 'green',
		'detail' => '',
	);

	$sql_worst_host = db_fetch_assoc("SELECT id, description, polling_time
		FROM host
		WHERE host.id in (" . $_SESSION['allowed_hosts'] . ")
		AND disabled != 'on'
		ORDER BY polling_time desc
		LIMIT 20");

	if (cacti_sizeof($sql_worst_host)) {
		foreach ($sql_worst_host as $host) {

			if ($console_access) {
				$row = '<tr><td class="rpad"><a href="' . html_escape($config['url_path'] . 'host.php?action=edit&id=' . $host['id']) . '">' . html_escape($host['description']) . '</a>';
			} else {
				$row = '<tr><td class="rpad">' . html_escape($host['description']) . '</td>';
			}

			if ($host['polling_time'] > 30) {
				$result['alarm'] = 'yellow';
				$row .= '<td class="rpad texalirig"><b>' . round($host['polling_time'], 2) . 's</b></td></tr>';
			} else {
				$row .= '<td class="rpad texalirig">' . round($host['polling_time'], 2) . 's</td></tr>';
			}

			$result['detail'] .= $row;
		}
		$result['detail'] = '<table>' . $result['detail'] . '</table>';

	} else {	// no data
		$result['detail'] = __('Waiting for data', 'intropage');
	}

	return $result;
}


//------------------------------------ top5_pollratio -----------------------------------------------------

function intropage_top5_pollratio_detail() {
	global $config, $console_access;

	$result = array(
		'name' => __('Top 20 hosts with the  worst polling ratio (failed, total, ratio)', 'intropage'),
		'alarm' => 'grey',
		'detail' => '',
	);

	$sql_worst_host = db_fetch_assoc("SELECT id, description, failed_polls, total_polls, failed_polls/total_polls as ratio
		FROM host
		WHERE host.id in (" . $_SESSION['allowed_hosts'] . ")
		AND disabled != 'on'
		ORDER BY ratio desc
		LIMIT 20");

	if (cacti_sizeof($sql_worst_host)) {
		foreach ($sql_worst_host as $host) {
			if ($console_access) {
				$row = '<tr><td class="rpad"><a href="' . html_escape($config['url_path'] . 'host.php?action=edit&id=' . $host['id']) . '">' . html_escape($host['description']) . '</a>';
			} else {
				$row = '<tr><td class="rpad">' . html_escape($host['description']) . '</td>';
			}

			$row .= '<td class="rpad texalirig">' . $host['failed_polls'] . '</td>';
			$row .= '<td class="rpad texalirig">' . $host['total_polls'] . '</td>';
			$row .= '<td class="rpad texalirig">' . round($host['ratio'], 2) . '</td></tr>';

			$result['detail'] .= $row;
		}
		$result['detail'] = '<table>' . $result['detail'] . '</table>';

	} else {	// no data
		$result['detail'] = __('Waiting for data', 'intropage');
	}

	return $result;
}

