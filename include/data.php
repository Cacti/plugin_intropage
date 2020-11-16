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

if (!function_exists('array_column')) {
    function array_column($array,$column_name) {
        return array_map(function($element) use($column_name){return $element[$column_name];}, $array);
    }
}

if (isset($run_from_poller))	{
	$_SESSION['sess_user_id'] = 0;
}


$users = db_fetch_assoc("SELECT t1.id AS id FROM user_auth AS t1 JOIN plugin_intropage_user_auth AS t2
                         ON t1.id=t2.user_id WHERE t1.enabled='on'");
    
$x = 0;
$_SESSION['allowed_hosts'] = array();
foreach ($users as $user)       {
	$us = read_user_setting('hide_disabled',false,false,$user['id']);
	if ($us == 'on') {
        	set_user_setting('hide_disabled','',$user['id']);
	}
       
        $allowed = get_allowed_devices('','null',-1,$x,$user['id']);

	if ($us == 'on') {
        	set_user_setting('hide_disabled','on',$user['id']);
	}

        if (count($allowed) > 0) {
        	$_SESSION['allowed_hosts'][$user['id']] = implode(',', array_column($allowed, 'id'));
        	$_SESSION['allowed_hosts_count'][$user['id']] = count($allowed);
	} else {
        	$_SESSION['allowed_hosts'][$user['id']] = -1;
        	$_SESSION['allowed_hosts_count'][$user['id']] = 0;
        }
}

include_once($config['base_path'] . '/plugins/intropage/include/functions.php');

//------------------------------------ analyse_login -----------------------------------------------------
function analyse_login($display=false, $update=false, $force_update=false) {
	global $config;

	$panel_id = 'analyse_login';
	$panel_name = __('Analyze logins', 'intropage');

	$result = array(
		'name' => $panel_name,
		'alarm' => 'green',
		'data' => '',
		'last_update' =>  NULL,
	);

	$id = db_fetch_cell_prepared('SELECT id FROM plugin_intropage_panel_data WHERE
				panel_id=? AND last_update IS NOT NULL',
				array($panel_id));

	if (!$id) {
		db_execute_prepared('REPLACE INTO plugin_intropage_panel_data (panel_id,user_id,data,alarm,last_update)
			    VALUES (?, ?, ?,"gray",1000)',
			    array($panel_id, 0,__('Waiting for data', 'intropage')));

		$id = db_fetch_insert_id();
	}

	$last_update = db_fetch_cell_prepared('SELECT unix_timestamp(last_update) FROM plugin_intropage_panel_data
					WHERE user_id=0 and panel_id= ?',
					array($panel_id));

	$update_interval = db_fetch_cell_prepared('SELECT refresh_interval FROM plugin_intropage_panel_definition
					WHERE panel_id= ?',
					array($panel_id));

	if ( $force_update || time() > ($last_update + $update_interval))	{

    $flog = db_fetch_cell('SELECT count(t.result)
			FROM ( 
				SELECT result FROM user_auth
				INNER JOIN user_log ON user_auth.username = user_log.username
				ORDER BY user_log.time DESC LIMIT 10
			) AS t
			WHERE t.result=0;');

		if ($flog > 0) {
			$result['alarm'] = 'red';
		}

		$result['data'] = '<span class="txt_big">' . __('Failed logins', 'intropage') . ': ' . $flog . '</span><br/><br/>';

		$result['data'] .= '<b>Active users in last hour:</b><br/>';

		$sql_result = db_fetch_assoc('SELECT DISTINCT username
			FROM user_log
			WHERE time > adddate(now(), INTERVAL -1 HOUR)
			LIMIT 10');

		if (cacti_sizeof($sql_result)) {
			foreach ($sql_result as $row) {
				$result['data'] .= $row['username'] . '<br/>';
			}
   	}
		$result['data'] .= '<br/><b>' . __('Last logins', 'intropage') . ':</b><br/>';

		$sql_result = db_fetch_assoc('SELECT user_log.username, user_auth.full_name, user_log.time, user_log.result, user_log.ip
                	FROM user_auth
                	INNER JOIN user_log
                	ON user_auth.username = user_log.username
                	ORDER BY user_log.time desc
                	LIMIT 3');

        	if (cacti_sizeof($sql_result)) {
                	$result['data'] .= '<table>';
                	foreach ($sql_result as $row) {
                        	$result['data'] .= sprintf('<tr><td class="rpad">%s </td><td class="rpad">%s</td><td>%s</td></tr>', $row['time'], $row['ip'], ($row['result'] == 0)? __('failed', 'intropage') : __('success', 'intropage'));
                	}
                	$result['data'] .= '</table>';
        	}

	    	db_execute_prepared('REPLACE INTO plugin_intropage_panel_data (id,panel_id,user_id,data,alarm) 
			    VALUES ( ?, ?, ?, ?, ?)',
			    array($id,$panel_id,0,$result['data'],$result['alarm']));
	}

	if ($display)    {
		$result = db_fetch_row_prepared('SELECT id, data, alarm, last_update
			FROM plugin_intropage_panel_data
			WHERE panel_id= ?',
			array($panel_id));

		$result['recheck'] = db_fetch_cell_prepared("SELECT concat(
			floor(TIME_FORMAT(SEC_TO_TIME(refresh_interval), '%H') / 24), 'd ',
			MOD(TIME_FORMAT(SEC_TO_TIME(refresh_interval), '%H'), 24), 'h:',
			TIME_FORMAT(SEC_TO_TIME(refresh_interval), '%im'))
			FROM plugin_intropage_panel_definition
			WHERE panel_id= ?",
			array($panel_id));

		$result['name'] = $panel_name;
	        return $result;
	}
}


//------------------------------------ analyse_log -----------------------------------------------------
function analyse_log($display=false, $update=false, $force_update=false) {
	global $config;

	$panel_id = 'analyse_log';
	$panel_name = __('Analyze log', 'intropage');

	$result = array(
		'name' => $panel_name,
		'alarm' => 'green',
		'data' => '',
		'last_update' =>  NULL,
	);

	$id = db_fetch_cell_prepared('SELECT id FROM plugin_intropage_panel_data WHERE
				panel_id= ? AND last_update IS NOT NULL',
				array($panel_id));

	if (!$id) {
	    db_execute_prepared('REPLACE INTO plugin_intropage_panel_data (panel_id,user_id,data,alarm,last_update)
			    VALUES ( ?, ?, ?, "gray", 1000)',
			    array($panel_id, 0, __('Waiting for data', 'intropage')));

	    $id = db_fetch_insert_id();
	}

	$last_update = db_fetch_cell_prepared('SELECT unix_timestamp(last_update) FROM plugin_intropage_panel_data
					WHERE user_id=0 and panel_id= ?',
					array($panel_id));

	$update_interval = db_fetch_cell_prepared('SELECT refresh_interval FROM plugin_intropage_panel_definition
					WHERE panel_id= ?',
					array($panel_id));

	if ( $force_update || time() > ($last_update + $update_interval)) {
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
			$result['data'] .= '<br/>' . __('(Errors and warning in last %s lines)', read_config_option('intropage_analyse_log_rows'), 'intropage') . '<br/>';

			if ($error > 0) {
				$result['alarm'] = 'red';
			}

			if ($warn > 0 && $result['alarm'] == 'green') {
				$result['alarm'] = 'yellow';
			}
		}

		db_execute_prepared('REPLACE INTO plugin_intropage_panel_data (id,panel_id,user_id,data,alarm)
			VALUES ( ?, ?, ?, ?, ?)',
			array($id,$panel_id,0,$result['data'],$result['alarm']));
	}

	if ($display)    {
		$result = db_fetch_row_prepared('SELECT id, data, alarm, last_update
			FROM plugin_intropage_panel_data
			WHERE panel_id= ?',
			array($panel_id));

		$result['recheck'] = db_fetch_cell_prepared("SELECT concat(
			floor(TIME_FORMAT(SEC_TO_TIME(refresh_interval), '%H') / 24), 'd ',
			MOD(TIME_FORMAT(SEC_TO_TIME(refresh_interval), '%H'), 24), 'h:',
			TIME_FORMAT(SEC_TO_TIME(refresh_interval), '%im'))
			FROM plugin_intropage_panel_definition
			WHERE panel_id= ?",
			array($panel_id));

		$result['name'] = $panel_name;
	        return $result;
	}
}

//------------------------------------ top5_worst_ping -----------------------------------------------------
function top5_ping($display=false, $update=false, $force_update=false) {
	global $config;

	$panel_id = 'top5_ping';
	$panel_name = __('Top5 ping', 'intropage');

	$update_interval = db_fetch_cell_prepared('SELECT refresh_interval FROM plugin_intropage_panel_definition
					WHERE panel_id= ?',
					array($panel_id));

	if ($_SESSION['sess_user_id'] > 0)	{ // specific user wants his panel only	
		$users = array(array('id'=>$_SESSION['sess_user_id']));
	}
	else	{ // poller wants all
		$users = db_fetch_assoc("SELECT t1.id AS id FROM user_auth AS t1 JOIN plugin_intropage_user_auth AS t2
				 ON t1.id=t2.user_id WHERE t1.enabled='on'");
	}

	foreach ($users as $user)	{
		$id = db_fetch_cell_prepared('SELECT id FROM plugin_intropage_panel_data WHERE
				panel_id= ? AND user_id= ? AND last_update IS NOT NULL',
				array($panel_id,$user['id']));

		if (!$id) {
			db_execute_prepared('REPLACE INTO plugin_intropage_panel_data
				(panel_id,user_id,data,alarm,last_update)
				VALUES ( ?, ?, ?, "gray", 1000)',
				array($panel_id, $user['id'],__('Waiting for data', 'intropage')));

			$id = db_fetch_insert_id();
		}

		$last_update = db_fetch_cell_prepared('SELECT unix_timestamp(last_update)
			FROM plugin_intropage_panel_data
			WHERE user_id= ? AND panel_id= ?',
			array($user['id'],$panel_id));

		if ( $force_update || time() > ($last_update + $update_interval)) {
			$result = array(
				'name' => $panel_name,
				'alarm' => 'green',
				'data' => '',
				'last_update' =>  NULL,
			);

	    		if ($_SESSION['allowed_hosts_count'][$user['id']] > 0)	{
				$console_access = (db_fetch_assoc_prepared('SELECT realm_id FROM user_auth_realm
					WHERE user_id = ?
				    	AND user_auth_realm.realm_id=8',
				    	array($user['id']))) ? true : false;

				$sql_worst_host = db_fetch_assoc("SELECT description, id, avg_time, cur_time
					FROM host
					WHERE host.id in (" . $_SESSION['allowed_hosts'][$user['id']]	 . ")
					AND disabled != 'on'
					ORDER BY cur_time desc
					LIMIT 5"
					);

				if (cacti_sizeof($sql_worst_host)) {
					$color = read_config_option('intropage_alert_worst_ping');
					list($red,$yellow) = explode ('/',$color);

					foreach ($sql_worst_host as $host) {

                                		if ($host['cur_time'] > $red) {
                                			$result['alarm'] = 'red';
						}
						elseif ($result['alarm'] == 'green' && $host['cur_time'] > $yellow)	{
							$result['alarm'] = 'yellow';
						}
					
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

						$result['data'] .= $row;
					}

					$result['data'] = '<table>' . 
								'<tr><td>' . __('Host', 'intropage') . '</td>' . 
								'<td>' . __('Average', 'intropage') . '</td>' .
								'<td>' . __('Current', 'intropage') . '</td></tr>' . 
								$result['data'] . '</table>';
				} else {	// no data
					$result['data'] = __('Waiting for data', 'intropage');
				}
	    		} else {
            			$result['data'] = __('You don\'t have permissions to any hosts', 'intropage');
			}

			db_execute_prepared('REPLACE INTO plugin_intropage_panel_data
				(id,panel_id,user_id,data,alarm)
				VALUES (?,?,?,?,?)',
				array($id,$panel_id,$user['id'],$result['data'],$result['alarm']));
		}
	}

	if ($display) {
		$result = db_fetch_row_prepared('SELECT id, data, alarm, last_update
			FROM plugin_intropage_panel_data
			WHERE panel_id= ?',
			array($panel_id));

		$result['recheck'] = db_fetch_cell_prepared("SELECT concat(
			floor(TIME_FORMAT(SEC_TO_TIME(refresh_interval), '%H') / 24), 'd ',
			MOD(TIME_FORMAT(SEC_TO_TIME(refresh_interval), '%H'), 24), 'h:',
			TIME_FORMAT(SEC_TO_TIME(refresh_interval), '%im'))
			FROM plugin_intropage_panel_definition
			WHERE panel_id= ?",
			array($panel_id));

		$result['name'] = $panel_name;

        return $result;
	}
}


//------------------------------------ cpuload -----------------------------------------------------
function cpuload($display=false, $update=false, $force_update=false) {
        global $config, $run_from_poller;

	$panel_id = 'cpuload';
	$panel_name = __('CPU utilization', 'intropage');

	$result = array(
		'name' => $panel_name,
		'alarm' => 'gray',
		'data' => '',
		'last_update' =>  NULL,
	);

	$graph = array (
		'line' => array(
			'title' => __('CPU load: ', 'intropage'),
			'label1' => array(),
			'data1' => array(),
		),
	);

	if (isset($run_from_poller))	{ // update in poller
        	if (!stristr(PHP_OS, 'win')) {
                	$load    = sys_getloadavg();
                	$load[0] = round($load[0], 2);

                	db_execute_prepared("REPLACE INTO plugin_intropage_trends (name, value, user_id)
                		VALUES ('cpuload', ?, 0)",
                		array($load[0]));
		}
	}

	$id = db_fetch_cell_prepared('SELECT id FROM plugin_intropage_panel_data WHERE
				panel_id= ? AND last_update IS NOT NULL',
				array($panel_id));

	if (!$id) {
		db_execute_prepared('REPLACE INTO plugin_intropage_panel_data (panel_id,user_id,data,alarm,last_update)
			    VALUES ( ?, ?, ?, "gray", 1000)',
			    array($panel_id, 0, __('Waiting for data', 'intropage')));

	    	$id = db_fetch_insert_id();
	}

	$last_update = db_fetch_cell_prepared('SELECT unix_timestamp(last_update) FROM plugin_intropage_panel_data
					WHERE user_id=0 and panel_id= ?',
					array($panel_id));

	$update_interval = db_fetch_cell_prepared('SELECT refresh_interval FROM plugin_intropage_panel_definition
					WHERE panel_id= ?',
					array($panel_id));

        if ( $force_update || time() > ($last_update + $update_interval))       {

        	if (stristr(PHP_OS, 'win')) {
                	$result['data'] = __('This function is not implemented on Windows platforms', 'intropage');
                	unset($graph);
        	} else {

                	$sql = db_fetch_assoc("SELECT date_format(time(cur_timestamp),'%H:%i') AS `date`, name, value
                        	FROM plugin_intropage_trends
                        	WHERE name='cpuload'
                        	ORDER BY cur_timestamp desc
                        	LIMIT 10");

                	if (cacti_sizeof($sql)) {
                        	$graph['line']['title1'] = __('Load', 'intropage');

                        	foreach ($sql as $row) {
                                	array_push($graph['line']['label1'], $row['date']);
                                	array_push($graph['line']['data1'], $row['value']);
                        	}

                        	$graph['line']['data1']  = array_reverse($graph['line']['data1']);
                        	$graph['line']['label1'] = array_reverse($graph['line']['label1']);
                        	$result['data'] = intropage_prepare_graph($graph);
                	} else {
                        	unset($graph);
                        	$result['data'] = __('Waiting for data', 'intropage');
                	}
        	}

		db_execute_prepared('REPLACE INTO plugin_intropage_panel_data (id,panel_id,user_id,data,alarm)
			VALUES ( ?, ?, ?, ?, ?)',
			array($id,$panel_id,0,$result['data'],$result['alarm']));
        }

        if ($display)    {
	        $result = db_fetch_row_prepared('SELECT id, data, alarm, last_update FROM plugin_intropage_panel_data
	    				    WHERE panel_id= ?',
	    				    array($panel_id));

		$result['recheck'] = db_fetch_cell_prepared("SELECT concat(
			floor(TIME_FORMAT(SEC_TO_TIME(refresh_interval), '%H') / 24), 'd ',
			MOD(TIME_FORMAT(SEC_TO_TIME(refresh_interval), '%H'), 24), 'h:',
			TIME_FORMAT(SEC_TO_TIME(refresh_interval), '%im'))
			FROM plugin_intropage_panel_definition
			WHERE panel_id= ?",
			array($panel_id));

                $result['name'] = $panel_name;
                return $result;
        }
}


// -------------------------------------ntp-------------------------------------------
function ntp($display=false, $update=false, $force_update=false) {
	global $config;

	$panel_id = 'ntp';
	$panel_name = __('NTP', 'intropage');

	$result = array(
		'name' => $panel_name,
		'alarm' => 'green',
		'data' => '',
		'last_update' =>  NULL,
	);

	$id = db_fetch_cell_prepared('SELECT id FROM plugin_intropage_panel_data WHERE
				panel_id= ? AND last_update IS NOT NULL',
				array($panel_id));

	if (!$id) {
		db_execute_prepared('REPLACE INTO plugin_intropage_panel_data (panel_id,user_id,data,alarm,last_update)
			VALUES ( ?, ?, ?, "gray", 1000)',
			array($panel_id, 0, __('Waiting for data', 'intropage')));

	    	$id = db_fetch_insert_id();
	}

	$last_update = db_fetch_cell_prepared('SELECT unix_timestamp(last_update) FROM plugin_intropage_panel_data
					WHERE user_id=0 and panel_id= ?',
					array($panel_id));

	$update_interval = db_fetch_cell_prepared('SELECT refresh_interval FROM plugin_intropage_panel_definition
					WHERE panel_id= ?',
					array($panel_id));

        if ( $force_update || time() > ($last_update + $update_interval))       {

  		$ntp_server = read_config_option('intropage_ntp_server');

        	if (empty($ntp_server)) {
                	$result['alarm'] = 'gray';
                	$result['data']  = __('No NTP server configured', 'intropage');
		} elseif (!filter_var(trim($ntp_server), FILTER_VALIDATE_IP) && !filter_var(trim($ntp_server), FILTER_VALIDATE_DOMAIN))    {
                	$result['alarm'] = 'red';
                	$result['data']  = __('Wrong NTP server configured - ' . $ntp_server . '<br/>Please fix it in settings', 'intropage');
        	} else {

                	$timestamp = ntp_time($ntp_server);

                	if ($timestamp != "error") {
                		$diff_time = date('U') - $timestamp;

                        	$result['data'] = '<span class="txt_big">' . date('Y-m-d') . '<br/>' . date('H:i:s') . '</span><br/><br/>';
                        	if ($diff_time > 1400000000)    {

                                	$result['alarm'] = 'red';
                                	$result['data'] .= __('Failed to get NTP time FROM $ntp_server', 'intropage') . '<br/>';
                        	} elseif ($diff_time < -600 || $diff_time > 600) {
                                        $result['alarm'] = 'red';
                                } elseif ($diff_time < -120 || $diff_time > 120) {
                                        $result['alarm'] = 'yellow';
				}

                                if ($result['alarm'] != 'green') {
                                       	$result['data'] .= __('Please check time.<br/>It is different (more than %s seconds) FROM NTP server %s', $diff_time, $ntp_server, 'intropage') . '<br/>';
                                } else {
                                       	$result['data'] .= __('Localtime is equal to NTP server', 'intropage') . "<br/>$ntp_server<br/>";
                                }

                	} else {
                        	$result['alarm'] = 'red';
                        	$result['data']  = __('Unable to contact the NTP server indicated.<br/>Please check your configuration.<br/>', 'intropage');
                	}

        	}

	    	db_execute_prepared('REPLACE INTO plugin_intropage_panel_data (id,panel_id,user_id,data,alarm)
			VALUES (?,?,?,?,?)',
			array($id,$panel_id,0,$result['data'],$result['alarm']));
        }

        if ($display)    {
	        $result = db_fetch_row_prepared('SELECT id, data, alarm, last_update FROM plugin_intropage_panel_data
			    WHERE panel_id= ?',
			    array($panel_id));

		$result['recheck'] = db_fetch_cell_prepared("SELECT concat(
			floor(TIME_FORMAT(SEC_TO_TIME(refresh_interval), '%H') / 24), 'd ',
			MOD(TIME_FORMAT(SEC_TO_TIME(refresh_interval), '%H'), 24), 'h:',
			TIME_FORMAT(SEC_TO_TIME(refresh_interval), '%im'))
			FROM plugin_intropage_panel_definition
			WHERE panel_id= ?",
			array($panel_id));

                $result['name'] = $panel_name;
                return $result;
        }
}


// ------------------------- graph data source---------------------
function graph_data_source($display=false, $update=false, $force_update=false) {
        global $config, $input_types, $run_from_poller;

	$panel_id = 'graph_data_source';
	$panel_name = __('Data sources', 'intropage');

        $result = array(
                'name' => $panel_name,
                'alarm' => 'gray',
                'data' => '',
		'last_update' => NULL,
        );

	$update_interval = db_fetch_cell_prepared('SELECT refresh_interval FROM plugin_intropage_panel_definition
					WHERE panel_id= ?',
					array($panel_id));

	if ($_SESSION['sess_user_id'] > 0)	{ // specific user wants his panel only	
		$users = array(array('id'=>$_SESSION['sess_user_id']));
	}
	else	{ // poller wants all
		$users = db_fetch_assoc("SELECT t1.id AS id FROM user_auth AS t1 JOIN plugin_intropage_user_auth AS t2
				 ON t1.id=t2.user_id WHERE t1.enabled='on'");
	}

	foreach ($users as $user)	{

		$id = db_fetch_cell_prepared('SELECT id FROM plugin_intropage_panel_data WHERE 
				panel_id= ? AND user_id= ? AND last_update IS NOT NULL',
				array($panel_id,$user['id']));

		if (!$id) {				
	    		db_execute_prepared('REPLACE INTO plugin_intropage_panel_data (panel_id,user_id,data,alarm,last_update) 
				VALUES ( ?, ?, ?, "gray", 1000)',
			    	array($panel_id, $user['id'],__('Waiting for data', 'intropage')));

	    		$id = db_fetch_insert_id();
		}

		$last_update = db_fetch_cell_prepared('SELECT unix_timestamp(last_update) FROM plugin_intropage_panel_data
					WHERE user_id= ? AND panel_id= ?',
					array($user['id'],$panel_id));

        	if ( $force_update || time() > ($last_update + $update_interval))       {

	    		if ($_SESSION['allowed_hosts_count'][$user['id']] > 0)	{

			        $graph = array ('pie' => array(
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
                        	        WHERE local_data_id<>0 AND data_local.host_id in (' . $_SESSION['allowed_hosts'][$user['id']] . ' )
                                	GROUP BY type_id LIMIT 6');

        			if (cacti_sizeof($sql_ds)) {
                			foreach ($sql_ds as $item) {
                       			 	if (!is_null($item['type_id'])) {
                                			array_push($graph['pie']['label'], preg_replace('/script server/', 'SS', $input_types[$item['type_id']]));
                                			array_push($graph['pie']['data'], $item['total']);

	                                		$result['data'] .= preg_replace('/script server/', 'SS', $input_types[$item['type_id']]) . ': ';
        	                        		$result['data'] .= $item['total'] . '<br/>';
                	        		}
                			}
                       			$result['data'] = intropage_prepare_graph($graph);
	        			unset($graph);
  
        			}
			} else {
            			$result['data'] = __('You don\'t have permissions to any hosts', 'intropage');
			}
	

			db_execute_prepared('REPLACE INTO plugin_intropage_panel_data (id,panel_id,user_id,data,alarm) 
				VALUES ( ?, ?, ?, ?, ?)',
				array($id,$panel_id,$user['id'],$result['data'],$result['alarm']));
		}
        }

	if ($display)    {
                $result = db_fetch_row_prepared('SELECT id, data, alarm, last_update FROM plugin_intropage_panel_data 
                                            WHERE panel_id= ? AND user_id= ?',
                                            array($panel_id, $_SESSION['sess_user_id'])); 

                $result['recheck'] = db_fetch_cell_prepared("SELECT concat(
                        floor(TIME_FORMAT(SEC_TO_TIME(refresh_interval), '%H') / 24), 'd ',
                        MOD(TIME_FORMAT(SEC_TO_TIME(refresh_interval), '%H'), 24), 'h:',
                        TIME_FORMAT(SEC_TO_TIME(refresh_interval), '%im'))
                        FROM plugin_intropage_panel_definition
                        WHERE panel_id= ?",
                        array($panel_id));

                $result['name'] = $panel_name;
                return $result;
        }
}


// -----------------------graph_host template--------------------
function graph_host_template($display=false, $update=false, $force_update=false) {
	global $config;

	$panel_id = 'graph_host_template';
	$panel_name = __('Host templates', 'intropage');

	$update_interval = db_fetch_cell_prepared('SELECT refresh_interval FROM plugin_intropage_panel_definition
					WHERE panel_id= ?',
					array($panel_id));


	if ($_SESSION['sess_user_id'] > 0)	{ // specific user wants his panel only
	    	$users = array(array('id'=>$_SESSION['sess_user_id']));
	}
	else	{ // poller wants all
		$users = db_fetch_assoc("SELECT t1.id AS id FROM user_auth AS t1 JOIN plugin_intropage_user_auth AS t2
				 ON t1.id=t2.user_id WHERE t1.enabled='on'");
	}	

	foreach ($users as $user)	{

		$id = db_fetch_cell_prepared('SELECT id FROM plugin_intropage_panel_data WHERE
				panel_id= ? AND user_id= ? AND last_update IS NOT NULL',
				array($panel_id,$user['id']));

		if (!$id) {
	    		db_execute_prepared('REPLACE INTO plugin_intropage_panel_data (panel_id,user_id,data,alarm,last_update)
				VALUES ( ?, ?, ?, "gray", 1000)',
			    	array($panel_id, $user['id'],__('Waiting for data', 'intropage')));

	    		$id = db_fetch_insert_id();
		}

		$last_update = db_fetch_cell_prepared('SELECT unix_timestamp(last_update) FROM plugin_intropage_panel_data
					WHERE user_id= ? AND panel_id= ?',
					array($user['id'],$panel_id));

        	if ( $force_update || time() > ($last_update + $update_interval))       {
			$result = array(
				'name' => $panel_name,
				'alarm' => 'gray',
				'data' => '',
				'last_update' =>  NULL,
			);

	    		if ($_SESSION['allowed_hosts_count'][$user['id']] > 0)	{

        			$graph = array ('pie' => array(
                        		'title' => __('Host templates: ', 'intropage'),
                        		'label' => array(),
                        		'data' => array(),
                			),
				);

                		$sql_ht = db_fetch_assoc("SELECT host_template.id as id, name,
                			count(host.host_template_id) AS total
                        		FROM host_template LEFT JOIN host
                        		ON (host_template.id = host.host_template_id) AND host.id IN ( " . $_SESSION['allowed_hosts'][$user['id']] . ")
                        		GROUP by host_template_id
                        		ORDER BY total desc LIMIT 6");

                		if (cacti_sizeof($sql_ht)) {

                        		foreach ($sql_ht as $item) {

                                		array_push($graph['pie']['label'], substr($item['name'],0,15));
                                		array_push($graph['pie']['data'], $item['total']);

                                		$result['data'] .= $item['name'] . ': ';
                                		$result['data'] .= $item['total'] . '<br/>';
        				}
                        		$result['data'] = intropage_prepare_graph($graph);
            				unset($graph);
                        	}
        		} else {
            			$result['data'] = __('You don\'t have permissions to any hosts', 'intropage');
			}

	    		db_execute_prepared('REPLACE INTO plugin_intropage_panel_data (id,panel_id,user_id,data,alarm)
				VALUES ( ?, ?, ?, ?, ?)',
			    	array($id,$panel_id,$user['id'],$result['data'],$result['alarm']));
	    	}
	}

	if ($display)    {
	        $result = db_fetch_row_prepared('SELECT id, data, alarm, last_update FROM plugin_intropage_panel_data 
	    				    WHERE panel_id= ? AND user_id= ?',
	    				    array($panel_id, $_SESSION['sess_user_id'])); 

		$result['recheck'] = db_fetch_cell_prepared("SELECT concat(
			floor(TIME_FORMAT(SEC_TO_TIME(refresh_interval), '%H') / 24), 'd ',
			MOD(TIME_FORMAT(SEC_TO_TIME(refresh_interval), '%H'), 24), 'h:',
			TIME_FORMAT(SEC_TO_TIME(refresh_interval), '%im'))
			FROM plugin_intropage_panel_definition
			WHERE panel_id= ?",
			array($panel_id));

		$result['name'] = $panel_name;

	        return $result;
	}
}


//--------------------------------------- graph host-----------------------------

function graph_host($display=false, $update=false, $force_update=false) {
        global $config;

	$panel_id = 'graph_host';
	$panel_name = __('Hosts', 'intropage');

	$update_interval = db_fetch_cell_prepared('SELECT refresh_interval FROM plugin_intropage_panel_definition
					WHERE panel_id= ?',
					array($panel_id));


	if ($_SESSION['sess_user_id'] > 0)	{ // specific user wants his panel only
		$users = array(array('id'=>$_SESSION['sess_user_id']));
	}
	else	{ // poller wants all
//	    	$users = db_fetch_assoc("SELECT id FROM user_auth WHERE enabled='on'");
		$users = db_fetch_assoc("SELECT t1.id AS id FROM user_auth AS t1 JOIN plugin_intropage_user_auth AS t2
				 ON t1.id=t2.user_id WHERE t1.enabled='on'");

	}

	foreach ($users as $user)	{

		$id = db_fetch_cell_prepared('SELECT id FROM plugin_intropage_panel_data WHERE
				panel_id= ? AND user_id= ? AND last_update IS NOT NULL',
				array($panel_id,$user['id']));

		if (!$id) {
	    		db_execute_prepared('REPLACE INTO plugin_intropage_panel_data (panel_id,user_id,data,alarm,last_update)
				VALUES ( ?, ?, ?, "gray", 1000)',
			    	array($panel_id, $user['id'],__('Waiting for data', 'intropage')));

	    		$id = db_fetch_insert_id();
		}

		$last_update = db_fetch_cell_prepared('SELECT unix_timestamp(last_update) FROM plugin_intropage_panel_data
					WHERE user_id= ? AND panel_id= ?',
					array($user['id'],$panel_id));

        	if ( $force_update || time() > ($last_update + $update_interval))       {
			$result = array(
				'name' => $panel_name,
				'alarm' => 'green',
				'data' => '',
				'last_update' =>  NULL,
			);

	    		if ($_SESSION['allowed_hosts_count'][$user['id']] > 0)	{

        			$graph = array ('pie' => array(
                        		'title' => __('Hosts: ', 'intropage'),
                        		'label' => array(),
                        		'data' => array(),
                			),
				);

				$console_access = (db_fetch_assoc_prepared('SELECT realm_id FROM user_auth_realm
					WHERE user_id = ?
	    				AND user_auth_realm.realm_id=8',
	    				array($user['id']))) ? true : false;


                		$h_all  = db_fetch_cell('SELECT count(id) FROM host WHERE id IN (' . $_SESSION['allowed_hosts'][$user['id']] . ')');
                		$h_up   = db_fetch_cell('SELECT count(id) FROM host WHERE id IN (' . $_SESSION['allowed_hosts'][$user['id']] . ') AND status=3 AND disabled=""');
                		$h_down = db_fetch_cell('SELECT count(id) FROM host WHERE id IN (' . $_SESSION['allowed_hosts'][$user['id']] . ') AND status=1 AND disabled=""');
                		$h_reco = db_fetch_cell('SELECT count(id) FROM host WHERE id IN (' . $_SESSION['allowed_hosts'][$user['id']] . ') AND status=2 AND disabled=""');
                		$h_disa = db_fetch_cell('SELECT count(id) FROM host WHERE id IN (' . $_SESSION['allowed_hosts'][$user['id']] . ') AND disabled="on"');

                		$count = $h_all + $h_up + $h_down + $h_reco + $h_disa;
                		$url_prefix = $console_access ? '<a href="' . html_escape($config['url_path']) . 'host.php?host_status=%s">' : '';
                		$url_suffix = $console_access ? '</a>' : '';

                		$result['data']  = sprintf($url_prefix,'-1') . __('All', 'intropage') . ": $h_all$url_suffix<br/>";
                		$result['data'] .= sprintf($url_prefix,'=3') . __('Up', 'intropage') . ": $h_up$url_suffix<br/>";
                		$result['data'] .= sprintf($url_prefix,'=1') . __('Down', 'intropage') . ": $h_down$url_suffix<br/>";
                		$result['data'] .= sprintf($url_prefix,'=-2') . __('Disabled', 'intropage') . ": $h_disa$url_suffix<br/>";
                		$result['data'] .= sprintf($url_prefix,'=2') . __('Recovering', 'intropage') . ": $h_reco$url_suffix";

                		if ($count > 0) {
                        		$graph['pie'] = array(
                                		'title' => __('Hosts', 'intropage'),
                                		'label' => array(
                                        		__('Up', 'intropage'),
                                        		__('Down', 'intropage'),
                                       			__('Recovering', 'intropage'),
                                        		__('Disabled', 'intropage'),
                                		),
                                		'data' => array($h_up, $h_down, $h_reco, $h_disa)
                        		);

                        		$result['data'] = intropage_prepare_graph($graph);
        				unset($graph);
				}

                		// alarms and details
                		if ($h_reco > 0) {
                        		$result['alarm'] = 'yellow';
                		}

                		if ($h_down > 0) {
                        		$result['alarm'] = 'red';
                		}
			} else { // no allowed hosts
            			$result['data'] = __('You don\'t have permissions to any hosts', 'intropage');
        		}

	    		db_execute_prepared('REPLACE INTO plugin_intropage_panel_data (id,panel_id,user_id,data,alarm)
				VALUES ( ?, ?, ?, ?, ?)',
			    	array($id,$panel_id,$user['id'],$result['data'],$result['alarm']));
		}
	}

	if ($display)    {
	        $result = db_fetch_row_prepared('SELECT id, data, alarm, last_update FROM plugin_intropage_panel_data 
	    				    WHERE panel_id= ? AND user_id= ?',
	    				    array($panel_id, $_SESSION['sess_user_id'])); 

		$result['recheck'] = db_fetch_cell_prepared("SELECT concat(
			floor(TIME_FORMAT(SEC_TO_TIME(refresh_interval), '%H') / 24), 'd ',
			MOD(TIME_FORMAT(SEC_TO_TIME(refresh_interval), '%H'), 24), 'h:',
			TIME_FORMAT(SEC_TO_TIME(refresh_interval), '%im'))
			FROM plugin_intropage_panel_definition
			WHERE panel_id= ?",
			array($panel_id));

		$result['name'] = $panel_name;

	        return $result;
	}
}


//------------------------- info-------------------------
function info($display=false, $update=false, $force_update=false) {
        global $config, $poller_options;

	$panel_id = 'info';
	$panel_name = __('Info', 'intropage');

	$result = array(
		'name' => $panel_name,
		'alarm' => 'gray',
		'data' => '',
		'last_update' =>  NULL,
	);

        $id = db_fetch_cell_prepared('SELECT id FROM plugin_intropage_panel_data WHERE
                                panel_id= ? AND last_update IS NOT NULL',
                                array($panel_id));

        if (!$id) {
		db_execute_prepared('REPLACE INTO plugin_intropage_panel_data (panel_id,user_id,data,alarm,last_update)
                            VALUES ( ?, ?, ?, "gray", 1000)',
                            array($panel_id, 0, __('Waiting for data', 'intropage')));

            	$id = db_fetch_insert_id();
        }

        $last_update = db_fetch_cell_prepared('SELECT unix_timestamp(last_update) FROM plugin_intropage_panel_data
                                        WHERE user_id=0 and panel_id= ?',
                                        array($panel_id));

        $update_interval = db_fetch_cell_prepared('SELECT refresh_interval FROM plugin_intropage_panel_definition
                                        WHERE panel_id= ?',
                                        array($panel_id));

        if ( $force_update || time() > ($last_update + $update_interval))       {

        	$xdata = '';

        	$result['data'] .= __('Cacti version: ', 'intropage') . CACTI_VERSION . '<br/>';

        	if ($poller_options[read_config_option('poller_type')] == 'spine' && file_exists(read_config_option('path_spine')) && (function_exists('is_executable')) && (is_executable(read_config_option('path_spine')))) {
                	$spine_version = 'SPINE';

                	exec(read_config_option('path_spine') . ' --version', $out_array);

                	if (sizeof($out_array)) {
                        	$spine_version = $out_array[0];
                	}

                	$result['data'] .= __('Poller type:', 'intropage') .' <a href="' . html_escape($config['url_path'] .  'settings.php?tab=poller') . '">' . __('Spine', 'intropage') . '</a><br/>';

                	$result['data'] .= __('Spine version: ', 'intropage') . $spine_version . '<br/>';

                	if (!strpos($spine_version, CACTI_VERSION, 0)) {
                        	$result['data'] .= '<span class="red">' . __('You are using incorrect spine version!', 'intropage') . '</span><br/>';
                        	$result['alarm'] = 'red';
                	}
        	} else {
                	$result['data'] .= __('Poller type: ', 'intropage') . ' <a href="' . html_escape($config['url_path'] .  'settings.php?tab=poller') . '">' . $poller_options[read_config_option('poller_type')] . '</a><br/>';
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

		db_execute_prepared('REPLACE INTO plugin_intropage_panel_data (id,panel_id,user_id,data,alarm)
			VALUES ( ?, ?, ?, ?, ?)',
			array($id,$panel_id,0,$result['data'],$result['alarm']));
        }

	if ($display)    {
                $result = db_fetch_row_prepared('SELECT id, data, alarm, last_update FROM plugin_intropage_panel_data
                                            WHERE panel_id= ?',
                                            array($panel_id));

                $result['recheck'] = db_fetch_cell_prepared("SELECT concat(
                        floor(TIME_FORMAT(SEC_TO_TIME(refresh_interval), '%H') / 24), 'd ',
                        MOD(TIME_FORMAT(SEC_TO_TIME(refresh_interval), '%H'), 24), 'h:',
                        TIME_FORMAT(SEC_TO_TIME(refresh_interval), '%im'))
                        FROM plugin_intropage_panel_definition
                        WHERE panel_id= ?",
                        array($panel_id));

                $result['name'] = $panel_name;
                return $result;
        }
}


// -------------------------------------analyse db-------------------------------------------
function analyse_db($display=false, $update=false, $force_update=false) {
	global $config;

	$panel_id = 'analyse_db';
	$panel_name = __('Database check', 'intropage');

	$result = array(
		'name' => $panel_name,
		'alarm' => 'green',
		'data' => '',
		'last_update' =>  NULL,
	);

	$id = db_fetch_cell_prepared('SELECT id FROM plugin_intropage_panel_data WHERE
				panel_id= ? AND last_update IS NOT NULL',
				array($panel_id));

	if (!$id) {
		db_execute_prepared('REPLACE INTO plugin_intropage_panel_data (panel_id,user_id,data,alarm,last_update)
			    VALUES ( ?, ?, ?, "gray", 1000)',
			    array($panel_id, 0, __('Waiting for data', 'intropage')));

	    	$id = db_fetch_insert_id();
	}

	$last_update = db_fetch_cell_prepared('SELECT unix_timestamp(last_update) FROM plugin_intropage_panel_data
					WHERE user_id=0 and panel_id= ?',
					array($panel_id));

	$update_interval = read_config_option('intropage_analyse_db_interval');

        if ($force_update || time() > ($last_update + $update_interval))       {

       		$damaged   = 0;
       		$memtables = 0;

       		$db_check_level = read_config_option('intropage_analyse_db_level');

       		$tables = db_fetch_assoc('SHOW TABLES');

       		foreach ($tables as $key => $val) {
               		$row = db_fetch_row('check table ' . current($val) . ' ' . $db_check_level);

               		if (preg_match('/^note$/i', $row['Msg_type']) && preg_match('/doesn\'t support/i', $row['Msg_text'])) {
               			$memtables++;
               		} elseif (!preg_match('/OK/i', $row['Msg_text']) && !preg_match('/Table is already up to date/i', $row['Msg_text'])) {
               			$damaged++;
               			$result['data'] .= 'Table ' . $row['Table'] . ' status ' . $row['Msg_text'] . '<br/>';
               		}
       		}

       		if ($damaged > 0) {
               		$result['alarm'] = 'red';
               		$result['data'] = '<span class="txt_big">' . __('DB problem', 'intropage') . '</span><br/><br/>' . $result['data'];
       		} else {
               		$result['data'] = '<span class="txt_big">' . __('DB OK', 'intropage') . '</span><br/><br/>' . $result['data'];
       		}

       		// connection errors
       		$cerrors = 0;
		$color = read_config_option('intropage_alert_db_abort');

       		$con_err = db_fetch_assoc("SHOW GLOBAL STATUS LIKE '%Connection_errors%'");

       		foreach ($con_err as $key => $val) {
               		$cerrors = $cerrors + $val['Value'];
       		}

       		if ($cerrors > 0) {
               		$result['data'] .= __('Connection errors: %s - try to restart SQL service, check SQL log, ...', $cerrors, 'intropage') . '<br/>';

			if ($color == 'red')	{
                       		$result['alarm'] = 'red';
			}
               		elseif ($result['alarm'] == 'green' && $color == "yellow") {
                       		$result['alarm'] = 'yellow';
               		}
       		}

       		// aborted problems
       		$aerrors = 0;
       		$con_err = db_fetch_assoc("SHOW GLOBAL STATUS LIKE '%Aborted_c%'");

       		foreach ($con_err as $key => $val) {
               		$aerrors = $aerrors + $val['Value'];
       		}

       		if ($aerrors > 0) {    
               		$result['data'] .= __('Aborted clients/connects: %s - check logs.', $aerrors, 'intropage') . '<br/>';

			if ($color == 'red')	{
                       		$result['alarm'] = 'red';
			}
               		elseif ($result['alarm'] == 'green' && $color == "yellow") {
                       		$result['alarm'] = 'yellow';
               		}
       		}

       		$result['data'] .= __('Connection errors: %s', $cerrors, 'intropage') . '<br/>';
       		$result['data'] .= __('Damaged tables: %s', $damaged, 'intropage') . '<br/>' .
               		__('Memory tables: %s', $memtables, 'intropage') . '<br/>' .
       			__('All tables: %s', count($tables), 'intropage') . '<br/>';

    		db_execute_prepared('REPLACE INTO plugin_intropage_panel_data (id,panel_id,user_id,data,alarm)
		    	VALUES (?,?,?,?,?)',
		    	array($id,$panel_id,0,$result['data'],$result['alarm']));
	}

        if ($display)    {
	        $result = db_fetch_row_prepared('SELECT id, data, alarm, last_update FROM plugin_intropage_panel_data
	    				    WHERE panel_id= ?',
	    				    array($panel_id));

		if ($update_interval == 0)	{
			$result['recheck'] = __('Scheduled db check disabled','intropage');
		} elseif ($update_interval == 3600) {
            		$result['recheck'] = __('hour', 'intropage');
       	 	} elseif ($update_interval == 86400) {
            		$result['recheck'] = __('day', 'intropage');
        	} elseif ($update_interval == 604800) {
            		$result['recheck'] = __('week', 'intropage');
        	} elseif ($update_interval == 2592000) {
            		$result['recheck'] = __('month', 'intropage');
		}

                $result['name'] = $panel_name;
                return $result;
        }
}


//---------------------------maint plugin--------------------
function maint($display=false, $update=false, $force_update=false) {
	global $config;

	$panel_id = 'maint';
	$panel_name = __('Maint plugin', 'intropage');

	$result = array(
		'name' => $panel_name,
		'alarm' => 'red',
		'data' => '',
		'last_update' =>  NULL,
	);

	$update_interval = db_fetch_cell_prepared('SELECT refresh_interval FROM plugin_intropage_panel_definition
					WHERE panel_id= ?',
					array($panel_id));

	if ($_SESSION['sess_user_id'] > 0)	{ // specific user wants his panel only	
	    	$users = array(array('id'=>$_SESSION['sess_user_id']));
	}
	else	{ // poller wants all
		$users = db_fetch_assoc("SELECT t1.id AS id FROM user_auth AS t1 JOIN plugin_intropage_user_auth AS t2
				 ON t1.id=t2.user_id WHERE t1.enabled='on'");
	}

	foreach ($users as $user)	{

		$id = db_fetch_cell_prepared('SELECT id FROM plugin_intropage_panel_data WHERE 
				panel_id= ? AND user_id= ? AND last_update IS NOT NULL',
				array($panel_id,$user['id']));

		if (!$id) {				
	    		db_execute_prepared('REPLACE INTO plugin_intropage_panel_data (panel_id,user_id,data,alarm,last_update) 
				VALUES ( ?, ?, ?, "gray", 1000)',
			    	array($panel_id, $user['id'],__('Waiting for data', 'intropage')));

	    		$id = db_fetch_insert_id();
		}

		$last_update = db_fetch_cell_prepared('SELECT unix_timestamp(last_update) FROM plugin_intropage_panel_data
					WHERE user_id= ? AND panel_id= ?',
					array($user['id'],$panel_id));

        	if ( $force_update || time() > ($last_update + $update_interval))       {

        		$maint_days_before = read_config_option('intropage_maint_plugin_days_before');

			if (db_fetch_cell("SELECT directory FROM plugin_config WHERE directory='maint' and status=1")) {

        			$schedules = db_fetch_assoc("SELECT * FROM plugin_maint_schedules WHERE enabled='on'");
        			if (cacti_sizeof($schedules)) {
                			foreach ($schedules as $sc) {
                        			$t = time();

                        			switch ($sc['mtype']) {
                                			case 1:
                                        			if ($t > ($sc['stime'] - $maint_days_before) && $t < $sc['etime']) {
									$hosts = db_fetch_assoc_prepared('SELECT description FROM host
                                        					      INNER JOIN plugin_maint_hosts
					                                              ON host.id=plugin_maint_hosts.host
					                                              WHERE host.id in (' . $_SESSION['allowed_hosts'][$user['id']] . ') AND schedule = ?',
					                                              array($sc['id']));

					                                if (cacti_sizeof($hosts)) {
					                                	$result['data'] .= '<b>' . date('d. m . Y  H:i', $sc['stime']) . ' - ' . date('d. m . Y  H:i', $sc['etime']) .
					                                                      ' - ' . $sc['name'] . ' (One time)<br/>Affected hosts:</b> ';

					                                        foreach ($hosts as $host) {
                                        						$result['data'] .= $host['description'] . ', ';
										}
									}
					                                $result['data'] = substr($result['data'], 0, -2) .'<br/><br/>';
								}
                                       			break;

                                			case 2:
                                        			while ($sc['etime'] < $t) {
                                                			$sc['etime'] += $sc['minterval'];
                                                			$sc['stime'] += $sc['minterval'];
	                                        		}

                	                        		if ($t > ($sc['stime'] - $maint_days_before) && $t < $sc['etime']) {

                                	                		$hosts = db_fetch_assoc_prepared('SELECT description FROM host
                                        	                		INNER JOIN plugin_maint_hosts
                                                	        		ON host.id=plugin_maint_hosts.host
                                                        			WHERE host.id in (' . $_SESSION['allowed_hosts'][$user['id']] . ') AND schedule = ?',
                                                        			array($sc['id']));

	                                                		if (cacti_sizeof($hosts)) {
	        	                                        		$result['data'] .= '<b>' . date('d. m . Y  H:i', $sc['stime']) . ' - ' . date('d. m . Y  H:i', $sc['etime']) .
                        		                                		' - ' . $sc['name'] . ' (Reoccurring)<br/>Affected hosts:</b> ';
			                                                		
        	                                                		foreach ($hosts as $host) {
                	                                                		$result['data'] .= $host['description'] . ', ';
                        	                                		}
                                	                		}

                                        	        		$result['data'] = substr($result['data'], 0, -2) . '<br/><br/>';
                                        			}
							break;
						}
					}
				}
        		}
        		else {
       				$result['data'] = __('Maint plugin is not installed/enabled', 'intropage');
        		}

    			db_execute_prepared('REPLACE INTO plugin_intropage_panel_data (id,panel_id,user_id,data,alarm) 
			    	VALUES (?,?,?,?,?)',
			    	array($id,$panel_id,$user['id'],$result['data'],$result['alarm']));
		}
	}

	if ($display)    {
                $result = db_fetch_row_prepared('SELECT id, data, alarm, last_update FROM plugin_intropage_panel_data 
                                            WHERE panel_id= ? AND user_id= ?',
                                            array($panel_id, $_SESSION['sess_user_id'])); 

                $result['recheck'] = db_fetch_cell_prepared("SELECT concat(
                        floor(TIME_FORMAT(SEC_TO_TIME(refresh_interval), '%H') / 24), 'd ',
                        MOD(TIME_FORMAT(SEC_TO_TIME(refresh_interval), '%H'), 24), 'h:',
                        TIME_FORMAT(SEC_TO_TIME(refresh_interval), '%im'))
                        FROM plugin_intropage_panel_definition
                        WHERE panel_id= ?",
                        array($panel_id));

                $result['name'] = $panel_name;
                return $result;
        }
}


//---------------------------admin alert--------------------
function admin_alert($display=false, $update=false, $force_update=false) {
	global $config;

	$panel_id = 'admin_alert';
	$panel_name = __('Admin alert', 'intropage');

	$result = array(
		'name' => $panel_name,
		'alarm' => 'red',
		'data' => '',
		'last_update' =>  NULL,
	);

	$id = db_fetch_cell_prepared('SELECT id FROM plugin_intropage_panel_data WHERE
				panel_id= ? AND last_update IS NOT NULL',
				array($panel_id));

	if (!$id) {
		db_execute_prepared('REPLACE INTO plugin_intropage_panel_data (panel_id,user_id,data,alarm,last_update)
			    VALUES ( ?, ?, ?, "gray", 1000)',
			    array($panel_id, 0,__('Waiting for data', 'intropage')));

		$id = db_fetch_insert_id();
	}

	$last_update = db_fetch_cell_prepared('SELECT unix_timestamp(last_update) FROM plugin_intropage_panel_data
					WHERE user_id=0 and panel_id= ?',
					array($panel_id));

	$update_interval = read_config_option('intropage_analyse_db_interval');

        if ($force_update || time() > ($last_update + $update_interval))       {

        	$result['data'] = read_config_option('intropage_admin_alert') . '<br/><br/>';

    		db_execute_prepared('REPLACE INTO plugin_intropage_panel_data (id,panel_id,user_id,data,alarm)
			    	VALUES (?,?,?,?,?)',
			    	array($id,$panel_id,0,$result['data'],$result['alarm']));
	}

	if ($display)    {
                $result = db_fetch_row_prepared('SELECT id, data, alarm, last_update FROM plugin_intropage_panel_data
                                            WHERE panel_id= ?',
                                            array($panel_id));

                $result['recheck'] = db_fetch_cell_prepared("SELECT concat(
                        floor(TIME_FORMAT(SEC_TO_TIME(refresh_interval), '%H') / 24), 'd ',
                        MOD(TIME_FORMAT(SEC_TO_TIME(refresh_interval), '%H'), 24), 'h:',
                        TIME_FORMAT(SEC_TO_TIME(refresh_interval), '%im'))
                        FROM plugin_intropage_panel_definition
                        WHERE panel_id= ?",
                        array($panel_id));

                $result['name'] = $panel_name;
                return $result;
        }
}


//------------------------------------ trends -----------------------------------------------------
function trend($display=false, $update=false, $force_update=false) {
	global $config, $run_from_poller;

	$panel_id = 'trend';
	$panel_name = __('Trends', 'intropage');

	$result = array(
		'name' => $panel_name,
		'alarm' => 'green',
		'data' => '',
		'last_update' =>  NULL,
	);

	if (isset($run_from_poller))	{ // update in poller
	
		$users = db_fetch_assoc("SELECT t1.id AS id FROM user_auth AS t1 JOIN plugin_intropage_user_auth AS t2
				 ON t1.id=t2.user_id WHERE t1.enabled='on' AND t2.trend='on'");
		foreach ($users as $user)	{
			if ($_SESSION['allowed_hosts'][$user['id']])	{

/*	
old fast code		


                		db_execute_prepared("REPLACE INTO plugin_intropage_trends
                        		(name,value,user_id)
                        		SELECT 'thold', COUNT(*),?
                        		FROM thold_data
                        		WHERE thold_data.host_id in (" . $_SESSION['allowed_hosts'][$user['id']] . ") 
                        		AND thold_data.thold_enabled = 'on' 
                        		AND (((thold_data.thold_alert != 0 AND thold_data.thold_fail_count >= thold_data.thold_fail_trigger) 
					OR (thold_data.bl_alert > 0 AND thold_data.bl_fail_count >= thold_data.bl_fail_trigger)))",
                        		array($user['id']));
                        		
                        		
new code from thold plugin - it is slower but correct count
*/

				include_once($config['base_path'] . '/plugins/thold/thold_functions.php');

				// right way but it is slow
				$t_trig = 0; 
				$x = '';
				$sql_where = "td.thold_enabled = 'on' AND (((td.thold_alert != 0 AND td.thold_fail_count >= td.thold_fail_trigger) 
				OR (td.bl_alert > 0 AND td.bl_fail_count >= td.bl_fail_trigger)))";
				$x = get_allowed_thresholds($sql_where, 'null', 1, $t_trig, $user['id']);

                		db_execute_prepared("REPLACE INTO plugin_intropage_trends
                        		(name,value,user_id)
                        		VALUES ('thold', ?,?)",
                        		array($t_trig,$user['id']));

                		db_execute_prepared("REPLACE INTO plugin_intropage_trends
                        		(name,value,user_id)
                        		SELECT 'host', COUNT(*),?
                        		FROM host
                        		WHERE id in (" . $_SESSION['allowed_hosts'][$user['id']] . ") AND  status='1' AND disabled=''",
                        		array($user['id']));

			}
			else	{
                		db_execute_prepared("REPLACE INTO plugin_intropage_trends
                        		(name,value,user_id) values ('thold,0,?)",
                        		array($user['id']));
                		db_execute_prepared("REPLACE INTO plugin_intropage_trends
                        		(name,value,user_id) values ('host,0,?)",
                        		array($user['id']));
			}
			
                }
	}

	$update_interval = db_fetch_cell_prepared('SELECT refresh_interval FROM plugin_intropage_panel_definition
					WHERE panel_id= ?',
					array($panel_id));

	if ($_SESSION['sess_user_id'] > 0)	{ // specific user wants his panel only	
	    	$users = array(array('id'=>$_SESSION['sess_user_id']));
	}
	else	{ // poller wants all
		$users = db_fetch_assoc("SELECT t1.id AS id FROM user_auth AS t1 JOIN plugin_intropage_user_auth AS t2
				 ON t1.id=t2.user_id WHERE t1.enabled='on'");
	}

	foreach ($users as $user)	{

		$id = db_fetch_cell_prepared('SELECT id FROM plugin_intropage_panel_data WHERE 
				panel_id= ? AND user_id= ? AND last_update IS NOT NULL',
				array($panel_id,$user['id']));

		if (!$id) {				
	    		db_execute_prepared('REPLACE INTO plugin_intropage_panel_data (panel_id,user_id,data,alarm,last_update) 
				VALUES ( ?, ?, ?, "gray", 1000)',
			    	array($panel_id, $user['id'],__('Waiting for data', 'intropage')));

	    		$id = db_fetch_insert_id();
		}

		$last_update = db_fetch_cell_prepared('SELECT unix_timestamp(last_update) FROM plugin_intropage_panel_data
					WHERE user_id= ? AND panel_id= ?',
					array($user['id'],$panel_id));

        	if ( $force_update || time() > ($last_update + $update_interval))       {

	        	$graph = array ('line' => array(
        		               	'title' => $panel_name,
                        		'label1' => array(),
                        		'data1' => array(),
	                        	'title1' => '',
        	                	'data2' => array(),
                	        	'title2' => '',	
	                	),
			);

	        	if (db_fetch_cell("SELECT directory FROM plugin_config WHERE directory='thold' and status=1")) {
        	        	$sql = db_fetch_assoc_prepared("SELECT date_format(time(cur_timestamp),'%H:%i') AS `date`, name, value
                	        	FROM plugin_intropage_trends
                        		WHERE name='thold' AND user_id = ?
                        		ORDER BY cur_timestamp desc
                        		LIMIT 10",
                        		array($user['id']));

	                	if (cacti_sizeof($sql)) {
        	                	$graph['line']['title1'] = __('Tholds triggered', 'intropage');
                	        	foreach ($sql as $row) {
                        	        	array_push($graph['line']['label1'], $row['date']);
                                		array_push($graph['line']['data1'], $row['value']);
                        		}
                		}
        		}

	        	$sql = db_fetch_assoc_prepared("SELECT date_format(time(cur_timestamp),'%h:%i') as `date`, name, value
        	        	FROM plugin_intropage_trends
                		WHERE name='host' AND user_id = ?
                		ORDER BY cur_timestamp desc
	                	LIMIT 10",
	                	array($user['id']));

	        	if (cacti_sizeof($sql)) {
        	        	$graph['line']['title2'] = __('Hosts down');

                		foreach ($sql as $row) {
                        		array_push($graph['line']['data2'], $row['value']);
                		}
        		}

	        	if (count($graph['line']) < 3) {
        	        	unset($graph);
                		$result['data'] = __('Waiting for data','intropage');
	        	} else {
        	        	$graph['line']['data1'] = array_reverse($graph['line']['data1']);
                		$graph['line']['data2'] = array_reverse($graph['line']['data2']);
                		$graph['line']['label1'] = array_reverse($graph['line']['label1']);
	              		$result['data'] = intropage_prepare_graph($graph);
				unset($graph);
        		}
    			db_execute_prepared('REPLACE INTO plugin_intropage_panel_data (id,panel_id,user_id,data,alarm) 
			    	VALUES (?,?,?,?,?)',
			    	array($id,$panel_id,$user['id'],$result['data'],$result['alarm']));
		}
	}

	if ($display)    {
                $result = db_fetch_row_prepared('SELECT id, data, alarm, last_update FROM plugin_intropage_panel_data 
                                            WHERE panel_id= ? AND user_id= ?',
                                            array($panel_id, $_SESSION['sess_user_id'])); 

                $result['recheck'] = db_fetch_cell_prepared("SELECT concat(
                        floor(TIME_FORMAT(SEC_TO_TIME(refresh_interval), '%H') / 24), 'd ',
                        MOD(TIME_FORMAT(SEC_TO_TIME(refresh_interval), '%H'), 24), 'h:',
                        TIME_FORMAT(SEC_TO_TIME(refresh_interval), '%im'))
                        FROM plugin_intropage_panel_definition
                        WHERE panel_id= ?",
                        array($panel_id));

                $result['name'] = $panel_name;
                return $result;
	}
}

//------------------------------------ poller info -----------------------------------------------------
function poller_info($display=false, $update=false, $force_update=false) {
	global $config, $run_from_poller;

	$panel_id = 'poller_info';
	$panel_name = __('Poller info', 'intropage');

	$result = array(
		'name' => $panel_name,
		'alarm' => 'green',
		'data' => '',
		'last_update' =>  NULL,
	);

	if (isset($run_from_poller))	{ // update in poller

	}


	$id = db_fetch_cell_prepared('SELECT id FROM plugin_intropage_panel_data WHERE
				panel_id=? AND last_update IS NOT NULL',
				array($panel_id));

	if (!$id) {
		db_execute_prepared('REPLACE INTO plugin_intropage_panel_data (panel_id,user_id,data,alarm,last_update)
			    VALUES (?, ?, ?,"gray",1000)',
			    array($panel_id, 0, __('Waiting for data', 'intropage')));

		$id = db_fetch_insert_id();
	}

	$last_update = db_fetch_cell_prepared('SELECT unix_timestamp(last_update) FROM plugin_intropage_panel_data
					WHERE user_id=0 and panel_id= ?',
					array($panel_id));

	$update_interval = db_fetch_cell_prepared('SELECT refresh_interval FROM plugin_intropage_panel_definition
					WHERE panel_id= ?',
					array($panel_id));

	if ( $force_update || time() > ($last_update + $update_interval))	{

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

    		db_execute_prepared('REPLACE INTO plugin_intropage_panel_data (id,panel_id,user_id,data,alarm)
			    	VALUES (?,?,?,?,?)',
			    	array($id,$panel_id,0,$result['data'],$result['alarm']));
	}

	if ($display)    {
                $result = db_fetch_row_prepared('SELECT id, data, alarm, last_update FROM plugin_intropage_panel_data
                                            WHERE panel_id= ?',
                                            array($panel_id));

                $result['recheck'] = db_fetch_cell_prepared("SELECT concat(
                        floor(TIME_FORMAT(SEC_TO_TIME(refresh_interval), '%H') / 24), 'd ',
                        MOD(TIME_FORMAT(SEC_TO_TIME(refresh_interval), '%H'), 24), 'h:',
                        TIME_FORMAT(SEC_TO_TIME(refresh_interval), '%im'))
                        FROM plugin_intropage_panel_definition
                        WHERE panel_id= ?",
                        array($panel_id));

                $result['name'] = $panel_name;
                return $result;
	}
}


//------------------------------------ poller stat -----------------------------------------------------
function poller_stat($display=false, $update=false, $force_update=false) {
	global $config, $run_from_poller;

	$panel_id = 'poller_stat';
	$panel_name = __('Poller stats', 'intropage');

	$result = array(
		'name' => $panel_name,
		'alarm' => 'green',
		'data' => '',
		'last_update' =>  NULL,
	);

	$poller_interval = read_config_option('poller_interval');

	if (isset($run_from_poller))	{ // update in poller
        	$stats = db_fetch_assoc('SELECT id, total_time, date_sub(last_update, interval round(total_time) second) AS start
                	FROM poller ORDER BY id LIMIT 5');

        	foreach ($stats as $stat) {
                	db_execute_prepared("REPLACE INTO plugin_intropage_trends
                        	(name, cur_timestamp, value, user_id) VALUES
                        	('poller', ?, ?, ?)",
                        	array($stat['start'], $stat['id'] . ':' . round($stat['total_time']),0));
        	}
	}

	$id = db_fetch_cell_prepared('SELECT id FROM plugin_intropage_panel_data WHERE
				panel_id=? AND last_update IS NOT NULL',
				array($panel_id));

	if (!$id) {
		db_execute_prepared('REPLACE INTO plugin_intropage_panel_data (panel_id,user_id,data,alarm,last_update)
			    VALUES (?, ?, ?,"gray",1000)',
			    array($panel_id, 0, __('Waiting for data', 'intropage')));

		$id = db_fetch_insert_id();
	}

	$last_update = db_fetch_cell_prepared('SELECT unix_timestamp(last_update) FROM plugin_intropage_panel_data
					WHERE user_id=0 and panel_id= ?',
					array($panel_id));

	$update_interval = db_fetch_cell_prepared('SELECT refresh_interval FROM plugin_intropage_panel_definition
					WHERE panel_id= ?',
					array($panel_id));

	if ( $force_update || time() > ($last_update + $update_interval))	{

        	$graph = array ('line' => array(
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
                                	array_push($graph['line']['label' . $new_index], $one_poller['date']);
                                	array_push($graph['line']['data' . $new_index], $time);

                                	$graph['line']['title' . $new_index] = __('ID: ', 'intropage') . $xpoller['id'];
                        	}
                        	$new_index++;
                	}
        	}

        	if (count($graph['line']['data1']) < 3) {
                	$result['data'] = __('Waiting for data', 'intropage');
                	unset($graph);
        	}
        	else {
              		$result['data'] = intropage_prepare_graph($graph);
			unset($graph);
        	}

    		db_execute_prepared('REPLACE INTO plugin_intropage_panel_data (id,panel_id,user_id,data,alarm)
			    	VALUES (?,?,?,?,?)',
			    	array($id,$panel_id,0,$result['data'],$result['alarm']));
	}

	if ($display)    {
                $result = db_fetch_row_prepared('SELECT id, data, alarm, last_update FROM plugin_intropage_panel_data
                                            WHERE panel_id= ?',
                                            array($panel_id));

                $result['recheck'] = db_fetch_cell_prepared("SELECT concat(
                        floor(TIME_FORMAT(SEC_TO_TIME(refresh_interval), '%H') / 24), 'd ',
                        MOD(TIME_FORMAT(SEC_TO_TIME(refresh_interval), '%H'), 24), 'h:',
                        TIME_FORMAT(SEC_TO_TIME(refresh_interval), '%im'))
                        FROM plugin_intropage_panel_definition
                        WHERE panel_id= ?",
                        array($panel_id));

                $result['name'] = $panel_name;
                return $result;
	}
}


// --------------------------------analyse_tree_host_graph
function analyse_tree_host_graph($display=false, $update=false, $force_update=false) {
	global $config, $run_from_poller;

	$panel_id = 'analyse_tree_host_graph';
	$panel_name = __('Analyze tree/host/graph', 'intropage');

	if (isset($run_from_poller))	{ // update in poller
	}

	$update_interval = db_fetch_cell_prepared('SELECT refresh_interval FROM plugin_intropage_panel_definition
					WHERE panel_id= ?',
					array($panel_id));


	if ($_SESSION['sess_user_id'] > 0)	{ // specific user wants his panel only
	    	$users = array(array('id'=>$_SESSION['sess_user_id']));
	}
	else	{ // poller wants all
		$users = db_fetch_assoc("SELECT t1.id AS id FROM user_auth AS t1 JOIN plugin_intropage_user_auth AS t2
				 ON t1.id=t2.user_id WHERE t1.enabled='on'");
	}

	foreach ($users as $user)	{

		$id = db_fetch_cell_prepared('SELECT id FROM plugin_intropage_panel_data WHERE
				panel_id= ? AND user_id= ? AND last_update IS NOT NULL',
				array($panel_id,$user['id']));

		if (!$id) {
	    		db_execute_prepared('REPLACE INTO plugin_intropage_panel_data (panel_id,user_id,data,alarm,last_update)
				VALUES ( ?, ?, ?, "gray", 1000)',
			    	array($panel_id, $user['id'],__('Waiting for data', 'intropage')));

	    		$id = db_fetch_insert_id();
		}

		$last_update = db_fetch_cell_prepared('SELECT unix_timestamp(last_update) FROM plugin_intropage_panel_data
					WHERE user_id= ? AND panel_id= ?',
					array($user['id'],$panel_id));

        	if ( $force_update || time() > ($last_update + $update_interval))       {

			$result = array(
				'name' => $panel_name,
				'alarm' => 'green',
				'data' => '',
				'last_update' =>  NULL,
			);

        		 $console_access = (db_fetch_assoc_prepared('SELECT realm_id FROM user_auth_realm
                                        WHERE user_id = ?
                                        AND user_auth_realm.realm_id=8',
                                        array($user['id']))) ? true : false;
        		$total_errors = 0;

        		// hosts with same IP
        		if ($_SESSION['allowed_hosts_count'][$user['id']] > 0) {
                		$sql_result = db_fetch_assoc("SELECT COUNT(*) AS NoDups, id, hostname
                        		FROM host
                        		WHERE id IN (" . $_SESSION['allowed_hosts'][$user['id']] . ")
                        		AND disabled != 'on'
                        		GROUP BY hostname,snmp_port
                        		HAVING NoDups > 1");

                		$sql_count  = ($sql_result === false) ? __('N/A', 'intropage') : count($sql_result);

                		if (cacti_sizeof($sql_result)) {
                        		$total_errors += $sql_count;
                        		if (count($sql_result) > 0) {
                                		$result['data'] .= __('Devices with the same IP and port: %s', $sql_count, 'intropage') . '<br/>';

						$color = read_config_option('intropage_alert_same_ip');
                                		if ($color == 'red')	{
                       					$result['alarm'] = 'red';
						}
               					elseif ($result['alarm'] == 'green' && $color == "yellow") {
                       					$result['alarm'] = 'yellow';
               					}
                        		}
                		}
        		}

        		// same description
        		if ($_SESSION['allowed_hosts_count'][$user['id']] > 0) {
                		$sql_result = db_fetch_assoc("SELECT COUNT(*) AS NoDups, description
                        		FROM host
					WHERE id IN (" . $_SESSION['allowed_hosts'][$user['id']] . ")
                        		AND disabled != 'on'
                        		GROUP BY description
                        		HAVING NoDups > 1");

                		$sql_count  = ($sql_result === false) ? __('N/A', 'intropage') : count($sql_result);


                		if (cacti_sizeof($sql_result)) {
                        		$total_errors += $sql_count;
                        		if (count($sql_result) > 0) {
                                		$result['data'] .= __('Devices with the same description: %s', $sql_count, 'intropage') . '<br/>';
						$color = read_config_option('intropage_alert_same_description');
                                		if ($color == 'red')	{
                       					$result['alarm'] = 'red';
						}
               					elseif ($result['alarm'] == 'green' && $color == "yellow") {
                       					$result['alarm'] = 'yellow';
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
				WHERE dl.host_id IN (' . $_SESSION['allowed_hosts'][$user['id']] . ') 
                		GROUP BY dl.id
                		HAVING deletable=0
                		ORDER BY `name_cache` ASC');

        		$sql_count  = ($sql_result === false) ? __('N/A', 'intropage') : count($sql_result);

        		if (cacti_sizeof($sql_result)) {
                		$total_errors += $sql_count;
                		$result['data'] .= __('Orphaned Data Sources: %s', $sql_count, 'intropage') . '<br/>';

				$color = read_config_option('intropage_alert_orphaned_ds');

                       		if ($color == 'red')	{
      					$result['alarm'] = 'red';
				}
				elseif ($result['alarm'] == 'green' && $color == "yellow") {
       					$result['alarm'] = 'yellow';
				}
        		}

        		// empty poller_output
			if ($console_access)	{
	        		$count = db_fetch_cell("SELECT value FROM plugin_intropage_trends WHERE name = 'poller_output' ORDER BY cur_timestamp DESC LIMIT 1");

        			if ($count>0) {
                			$result['data'] .= __('Poller Output Items: %s', $count, 'intropage') . '<br/>';
	
					$color = read_config_option('intropage_alert_poller_output');
	                       		if ($color == 'red')	{
      						$result['alarm'] = 'red';
					}
					elseif ($result['alarm'] == 'green' && $color == "yellow") {
       						$result['alarm'] = 'yellow';
					}

                			$total_errors += $count;
        			}
			}

        		// DS - bad indexes
        		$sql_result = db_fetch_assoc('SELECT dtd.local_data_id,dtd.name_cache
                		FROM data_local AS dl
                		INNER JOIN data_template_data AS dtd
                		ON dl.id=dtd.local_data_id
                		INNER JOIN data_template AS dt ON dt.id=dl.data_template_id
                		INNER JOIN host AS h ON h.id = dl.host_id
                		WHERE (dl.snmp_index = "" AND dl.snmp_query_id > 0)
                		AND dl.host_id in (' . $_SESSION['allowed_hosts'][$user['id']] . ')');

        		$sql_count  = ($sql_result === false) ? __('N/A', 'intropage') : count($sql_result);

        		if (cacti_sizeof($sql_result)) {
        		
                		$result['data'] .= __('Datasource - bad indexes: %s', $sql_count, 'intropage') . '<br/>';

				$color = read_config_option('intropage_alert_bad_indexes');
                       		if ($color == 'red')	{
					$result['alarm'] = 'red';
				}
				elseif ($result['alarm'] == 'green' && $color == "yellow") {
					$result['alarm'] = 'yellow';
				}

                		$total_errors += $sql_count;
        		}

        		// thold plugin - logonly alert and warning thold
        		// I don't use thold get_allowed_thold because of join plugin_thold_threshold_contact

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
                            		AND gl.host_id IN (" . $_SESSION['allowed_hosts'][$user['id']] . ")
                            		HAVING (user0 IS NULL OR (user1 IS NULL OR user2 IS NULL))");

            			$sql_count  = ($sql_result === false) ? __('N/A', 'intropage') : count($sql_result);

            			if (cacti_sizeof($sql_result)) {

                        		$result['data'] .= __('Thold logonly alert/warning: %s', $sql_count, 'intropage') . '<br/>';
	
					$color = read_config_option('intropage_alert_thold_logonly');
	                       		if ($color == 'red')	{
						$result['alarm'] = 'red';
					}
					elseif ($result['alarm'] == 'green' && $color == "yellow") {
						$result['alarm'] = 'yellow';
					}

                        		$total_errors += $sql_count;
            			}
        		}

        		// below - only information without red/yellow/green
//        		$result['data'] .= '<br/><b>' . __('Information only (no warn/error)') . ':</b><br/>';

        		// device in more trees
        		if ($_SESSION['allowed_hosts_count'][$user['id']] > 0) {
                		$sql_result = db_fetch_assoc('SELECT host.id, host.description, COUNT(*) AS `count`
                        		FROM host
                        		INNER JOIN graph_tree_items
                        		ON (host.id = graph_tree_items.host_id)
                        		WHERE host.id IN (' . $_SESSION['allowed_hosts'][$user['id']] . ')
                        		GROUP BY description
                        		HAVING `count` > 1');

                		$sql_count  = ($sql_result === false) ? __('N/A', 'intropage') : count($sql_result);

                		if (cacti_sizeof($sql_result)) {
					$color = read_config_option('intropage_alert_more_trees');
	                       		if ($color == 'red')	{
						$result['alarm'] = 'red';
					}
					elseif ($result['alarm'] == 'green' && $color == "yellow") {
						$result['alarm'] = 'yellow';
					}
                		
                        		$result['data'] .= __('Devices in more than one tree: %s', $sql_count, 'intropage') . '<br/>';
                		}
        		}

        		// host without graph
        		if ($_SESSION['allowed_hosts_count'][$user['id']] > 0) {
                		$sql_result = db_fetch_assoc("SELECT id, description
                        		FROM host
                        		WHERE id IN (" . $_SESSION['allowed_hosts'][$user['id']] . ")
                        		AND disabled != 'on'
                        		AND id NOT IN (
					SELECT DISTINCT host_id
					FROM graph_local
                        		)
                        		AND snmp_version != 0");

                		$sql_count  = ($sql_result === false) ? __('N/A', 'intropage') : count($sql_result);

                		if (cacti_sizeof($sql_result)) {
					$color = read_config_option('intropage_alert_without_graph');
	                       		if ($color == 'red')	{
						$result['alarm'] = 'red';
					}
					elseif ($result['alarm'] == 'green' && $color == "yellow") {
						$result['alarm'] = 'yellow';
					}
                		
                        		$result['data'] .= __('Hosts without graphs: %s', $sql_count, 'intropage') . '<br/>';
                		}
        		}
        		// host without tree
        		if ($_SESSION['allowed_hosts_count'][$user['id']] > 0) {
                		$sql_result = db_fetch_assoc("SELECT id, description
                        		FROM host
                        		WHERE id IN (" . $_SESSION['allowed_hosts'][$user['id']] . ")
                        		AND disabled != 'on'
                        		AND id NOT IN (
                                		SELECT DISTINCT host_id
                                		FROM graph_tree_items
                        		)");

                		$sql_count  = ($sql_result === false) ? __('N/A', 'intropage') : count($sql_result);

                		if (cacti_sizeof($sql_result)) {
					$color = read_config_option('intropage_alert_without_tree');
	                       		if ($color == 'red')	{
						$result['alarm'] = 'red';
					}
					elseif ($result['alarm'] == 'green' && $color == "yellow") {
						$result['alarm'] = 'yellow';
					}

                       	 		$result['data'] .= __('Hosts without tree: %s', $sql_count, 'intropage') . '<br/>';
                		}
        		}

        		// public/private community
        		if ($_SESSION['allowed_hosts_count'][$user['id']] > 0) {
                		$sql_result = db_fetch_assoc("SELECT id, description
                        		FROM host
                        		WHERE id IN (" . $_SESSION['allowed_hosts'][$user['id']] . ")
                        		AND disabled != 'on'
                        		AND (snmp_community ='public' OR snmp_community='private')
					AND snmp_version IN (1,2) 
                        		ORDER BY description");

                		$sql_count  = ($sql_result === false) ? __('N/A', 'intropage') : count($sql_result);

                		if (cacti_sizeof($sql_result)) {
					$color = read_config_option('intropage_alert_default_community');
	                       		if ($color == 'red')	{
						$result['alarm'] = 'red';
					}
					elseif ($result['alarm'] == 'green' && $color == "yellow") {
						$result['alarm'] = 'yellow';
					}

                        		$result['data'] .= __('Hosts with default public/private community: %s', $sql_count, 'intropage') . '<br/>';
                		}
        		}

        		// plugin monitor - host without monitoring
        		if (db_fetch_cell("SELECT directory FROM plugin_config WHERE directory='monitor'")) { // installed plugin monitor?
        			if ($_SESSION['allowed_hosts_count'][$user['id']] > 0) {
                        		$sql_result = db_fetch_assoc("SELECT id, description, hostname
                                		FROM host
                                		WHERE id IN (" . $_SESSION['allowed_hosts'][$user['id']] . ")
                                		AND monitor != 'on'");

                        		$sql_count  = ($sql_result === false) ? __('N/A', 'intropage') : count($sql_result);

                        		if (cacti_sizeof($sql_result)) {
						$color = read_config_option('intropage_alert_without_monitoring');
	        	               		if ($color == 'red')	{
							$result['alarm'] = 'red';
						}
						elseif ($result['alarm'] == 'green' && $color == "yellow") {
							$result['alarm'] = 'yellow';
						}

                                		$result['data'] .= __('Plugin Monitor - Unmonitored hosts: %s', $sql_count, 'intropage') . '</b><br/>';
                        		}
                		}
        		}

        		if ($total_errors > 0) {
                		$result['data'] = '<span class="txt_big">' . __('Found %s problems', $total_errors, 'intropage') . '</span><br/>' . $result['data'];
        		} else {
                		$result['data'] = '<span class="txt_big">' . __('Everything OK', 'intropage') . '</span><br/>' . $result['data'];
        		}


	    		db_execute_prepared('REPLACE INTO plugin_intropage_panel_data (id,panel_id,user_id,data,alarm)
				VALUES ( ?, ?, ?, ?, ?)',
			    	array($id,$panel_id,$user['id'],$result['data'],$result['alarm']));

		}
	}

	if ($display)    {
                $result = db_fetch_row_prepared('SELECT id, data, alarm, last_update FROM plugin_intropage_panel_data 
                                            WHERE panel_id= ? AND user_id= ?',
                                            array($panel_id, $_SESSION['sess_user_id'])); 

                $result['recheck'] = db_fetch_cell_prepared("SELECT concat(
                        floor(TIME_FORMAT(SEC_TO_TIME(refresh_interval), '%H') / 24), 'd ',
                        MOD(TIME_FORMAT(SEC_TO_TIME(refresh_interval), '%H'), 24), 'h:',
                        TIME_FORMAT(SEC_TO_TIME(refresh_interval), '%im'))
                        FROM plugin_intropage_panel_definition
                        WHERE panel_id= ?",
                        array($panel_id));

                $result['name'] = $panel_name;
                return $result;
	}
}


//------------------------------------ top5_availability -----------------------------------------------------
function top5_availability($display=false, $update=false, $force_update=false) {
	global $config;

	$panel_id = 'top5_availability';
	$panel_name = __('Top5 worst availability', 'intropage');

	$update_interval = db_fetch_cell_prepared('SELECT refresh_interval FROM plugin_intropage_panel_definition
					WHERE panel_id= ?',
					array($panel_id));

	if ($_SESSION['sess_user_id'] > 0)	{ // specific user wants his panel only
		$users = array(array('id'=>$_SESSION['sess_user_id']));
	}
	else	{ // poller wants all
//	    	$users = db_fetch_assoc("SELECT id FROM user_auth WHERE enabled='on'");
		$users = db_fetch_assoc("SELECT t1.id AS id FROM user_auth AS t1 JOIN plugin_intropage_user_auth AS t2
				 ON t1.id=t2.user_id WHERE t1.enabled='on'");

	}

	foreach ($users as $user)	{

		$id = db_fetch_cell_prepared('SELECT id FROM plugin_intropage_panel_data WHERE
				panel_id= ? AND user_id= ? AND last_update IS NOT NULL',
				array($panel_id,$user['id']));

		if (!$id) {
	    		db_execute_prepared('REPLACE INTO plugin_intropage_panel_data (panel_id,user_id,data,alarm,last_update)
				VALUES ( ?, ?, ?, "gray", 1000)',
			    	array($panel_id, $user['id'],__('Waiting for data', 'intropage')));

	    		$id = db_fetch_insert_id();
		}

		$last_update = db_fetch_cell_prepared('SELECT unix_timestamp(last_update) FROM plugin_intropage_panel_data
					WHERE user_id= ? AND panel_id= ?',
					array($user['id'],$panel_id));

        	if ( $force_update || time() > ($last_update + $update_interval))       {

			$result = array(
				'name' => $panel_name,
				'alarm' => 'green',
				'data' => '',
				'last_update' =>  NULL,
			);

	    		if ($_SESSION['allowed_hosts_count'][$user['id']] > 0)	{
				$console_access = (db_fetch_assoc_prepared('SELECT realm_id FROM user_auth_realm
					WHERE user_id = ?
				    	AND user_auth_realm.realm_id=8',
				    	array($user['id']))) ? true : false;

                		$sql_worst_host = db_fetch_assoc("SELECT description, id, availability
                        		FROM host
                        		WHERE host.id IN (" . $_SESSION['allowed_hosts'][$user['id']] . ")
                        		AND disabled != 'on'
                        		ORDER BY availability
                        		LIMIT 5");

                		if (cacti_sizeof($sql_worst_host)) {

					$color = read_config_option('intropage_alert_worst_availability');
					list($red,$yellow) = explode ('/',$color);

                        		foreach ($sql_worst_host as $host) {
                                		if ($console_access) {
                                        		$row = '<tr><td class="rpad"><a href="' . html_escape($config['url_path'] . 'host.php?action=edit&id=' . $host['id']) . '">' . html_escape($host['description']) . '</a>';
                                		} else {
                                        		$row = '<tr><td class="rpad">' . html_escape($host['description']) . '</td>';
                                		}

                                		if ($host['availability'] < $red) {
                                			$result['alarm'] = 'red';
						}
						elseif ($result['alarm'] == 'green' && $host['availability'] < $yellow)	{
							$result['alarm'] = 'yellow';
						}

                                		if ($host['availability'] < $red) {
                                        		$row .= '<td class="rpad texalirig"><b>' . round($host['availability'], 2) . '%</b></td></tr>';
                                		} else {
                                        		$row .= '<td class="rpad texalirig">' . round($host['availability'], 2) . '%</td></tr>';
                                		}

                                		$result['data'] .= $row;
                        		}
                        		$result['data'] = '<table>' . $result['data'] . '</table>';

                		} else {        // no data
                        		$result['data'] = __('Waiting for data', 'intropage');
                		}
        		} else {
            			$result['data'] = __('You don\'t have permissions to any hosts', 'intropage');
        		}

	    		db_execute_prepared('REPLACE INTO plugin_intropage_panel_data (id,panel_id,user_id,data,alarm)
			    VALUES (?,?,?,?,?)',
			    array($id,$panel_id,$user['id'],$result['data'],$result['alarm']));

		}
	}

	if ($display)    {
	        $result = db_fetch_row_prepared('SELECT id, data, alarm, last_update FROM plugin_intropage_panel_data 
	    				    WHERE panel_id= ? AND user_id= ?',
	    				    array($panel_id, $_SESSION['sess_user_id'])); 

		$result['recheck'] = db_fetch_cell_prepared("SELECT concat(
			floor(TIME_FORMAT(SEC_TO_TIME(refresh_interval), '%H') / 24), 'd ',
			MOD(TIME_FORMAT(SEC_TO_TIME(refresh_interval), '%H'), 24), 'h:',
			TIME_FORMAT(SEC_TO_TIME(refresh_interval), '%im'))
			FROM plugin_intropage_panel_definition
			WHERE panel_id= ?",
			array($panel_id));

		$result['name'] = $panel_name;
	        return $result;
	}
}


//------------------------------------ top5_worst_polltime -----------------------------------------------------
function top5_polltime($display=false, $update=false, $force_update=false) {
	global $config;

	$panel_id = 'top5_polltime';
	$panel_name = __('Top5 worst polling time', 'intropage');

	$update_interval = db_fetch_cell_prepared('SELECT refresh_interval FROM plugin_intropage_panel_definition
					WHERE panel_id= ?',
					array($panel_id));


	if ($_SESSION['sess_user_id'] > 0)	{ // specific user wants his panel only
		$users = array(array('id'=>$_SESSION['sess_user_id']));
	}
	else	{ // poller wants all
		$users = db_fetch_assoc("SELECT t1.id AS id FROM user_auth AS t1 JOIN plugin_intropage_user_auth AS t2
				 ON t1.id=t2.user_id WHERE t1.enabled='on'");
	}

	foreach ($users as $user)	{

		$id = db_fetch_cell_prepared('SELECT id FROM plugin_intropage_panel_data WHERE
				panel_id= ? AND user_id= ? AND last_update IS NOT NULL',
				array($panel_id,$user['id']));

		if (!$id) {
	    		db_execute_prepared('REPLACE INTO plugin_intropage_panel_data (panel_id,user_id,data,alarm,last_update)
				VALUES ( ?, ?, ?, "gray", 1000)',
			    	array($panel_id, $user['id'],__('Waiting for data', 'intropage')));

	    		$id = db_fetch_insert_id();
		}

		$last_update = db_fetch_cell_prepared('SELECT unix_timestamp(last_update) FROM plugin_intropage_panel_data
					WHERE user_id= ? AND panel_id= ?',
					array($user['id'],$panel_id));

        	if ( $force_update || time() > ($last_update + $update_interval))       {

			$result = array(
				'name' => $panel_name,
				'alarm' => 'green',
				'data' => '',
				'last_update' =>  NULL,
			);

	    		if ($_SESSION['allowed_hosts_count'][$user['id']] > 0)	{
				$console_access = (db_fetch_assoc_prepared('SELECT realm_id FROM user_auth_realm
					WHERE user_id = ?
				    	AND user_auth_realm.realm_id=8',
				    	array($user['id']))) ? true : false;

		                $sql_worst_host = db_fetch_assoc("SELECT id, description, polling_time
                		        FROM host
                        		WHERE host.id in (" . $_SESSION['allowed_hosts'][$user['id']] . ")
                        		AND disabled != 'on'
                        		ORDER BY polling_time desc
                        		LIMIT 5");

                		if (cacti_sizeof($sql_worst_host)) {
					$color = read_config_option('intropage_alert_worst_polling_time');
					list($red,$yellow) = explode ('/',$color);

                        		foreach ($sql_worst_host as $host) {
                                		if ($console_access) {
                                        		$row = '<tr><td class="rpad"><a href="' . html_escape($config['url_path'] . 'host.php?action=edit&id=' . $host['id']) . '">' . html_escape($host['description']) . '</a>';
                                		} else {
                                       	 		$row = '<tr><td class="rpad">' . html_escape($host['description']) . '</td>';
	                               		}

                                		if ($host['polling_time'] > $red) {
                                			$result['alarm'] = 'red';
						}
						elseif ($result['alarm'] == 'green' && $host['polling_time'] > $yellow)	{
							$result['alarm'] = 'yellow';
						}
                                		if ($host['polling_time'] > $red) {
                                        		$row .= '<td class="rpad texalirig"><b>' . round($host['polling_time'], 2) . 's</b></td></tr>';
                                		} else {
                                        		$row .= '<td class="rpad texalirig">' . round($host['polling_time'], 2) . 's</td></tr>';
                                		}

                                		$result['data'] .= $row;
                        		}

                        		$result['data'] = '<table>' . $result['data'] . '</table>';
                		} else {        // no data
                        		$result['data'] = __('Waiting for data', 'intropage');
                		}
        		} else {
            			$result['data'] = __('You don\'t have permissions to any hosts', 'intropage');
        		}

	    		db_execute_prepared('REPLACE INTO plugin_intropage_panel_data (id,panel_id,user_id,data,alarm)
			    VALUES (?,?,?,?,?)',
			    array($id,$panel_id,$user['id'],$result['data'],$result['alarm']));
		}
	}

	if ($display)    {
	        $result = db_fetch_row_prepared('SELECT id, data, alarm, last_update FROM plugin_intropage_panel_data 
	    				    WHERE panel_id= ? AND user_id= ?',
	    				    array($panel_id, $_SESSION['sess_user_id'])); 

		$result['recheck'] = db_fetch_cell_prepared("SELECT concat(
			floor(TIME_FORMAT(SEC_TO_TIME(refresh_interval), '%H') / 24), 'd ',
			MOD(TIME_FORMAT(SEC_TO_TIME(refresh_interval), '%H'), 24), 'h:',
			TIME_FORMAT(SEC_TO_TIME(refresh_interval), '%im'))
			FROM plugin_intropage_panel_definition
			WHERE panel_id= ?",
			array($panel_id));

		$result['name'] = $panel_name;
	        return $result;
	}
}


//------------------------------------ top5_worst_pollratio -----------------------------------------------------
function top5_pollratio($display=false, $update=false, $force_update=false) {
	global $config;

	$panel_id = 'top5_pollratio';
	$panel_name = __('Top5 worst polling ratio (failed, total, ratio)', 'intropage');

	$update_interval = db_fetch_cell_prepared('SELECT refresh_interval FROM plugin_intropage_panel_definition
					WHERE panel_id= ?',
					array($panel_id));

	if ($_SESSION['sess_user_id'] > 0)	{ // specific user wants his panel only
	    	$users = array(array('id'=>$_SESSION['sess_user_id']));
	}
	else	{ // poller wants all
		$users = db_fetch_assoc("SELECT t1.id AS id FROM user_auth AS t1 JOIN plugin_intropage_user_auth AS t2
				 ON t1.id=t2.user_id WHERE t1.enabled='on'");
	}

	foreach ($users as $user)	{

		$id = db_fetch_cell_prepared('SELECT id FROM plugin_intropage_panel_data WHERE
				panel_id= ? AND user_id= ? AND last_update IS NOT NULL',
				array($panel_id,$user['id']));

		if (!$id) {
	    		db_execute_prepared('REPLACE INTO plugin_intropage_panel_data (panel_id,user_id,data,alarm,last_update)
				VALUES ( ?, ?, ?, "gray", 1000)',
			    	array($panel_id, $user['id'],__('Waiting for data', 'intropage')));

	    		$id = db_fetch_insert_id();
		}

		$last_update = db_fetch_cell_prepared('SELECT unix_timestamp(last_update) FROM plugin_intropage_panel_data
					WHERE user_id= ? AND panel_id= ?',
					array($user['id'],$panel_id));

        	if ( $force_update || time() > ($last_update + $update_interval))       {

			$result = array(
				'name' => $panel_name,
				'alarm' => 'green',
				'data' => '',
				'last_update' =>  NULL,
			);

	    		if ($_SESSION['allowed_hosts_count'][$user['id']] > 0)	{
				$console_access = (db_fetch_assoc_prepared('SELECT realm_id FROM user_auth_realm
					WHERE user_id = ?
				    	AND user_auth_realm.realm_id=8',
				    	array($user['id']))) ? true : false;

                		$sql_worst_host = db_fetch_assoc("SELECT id, description, failed_polls, total_polls, failed_polls/total_polls as ratio
                        		FROM host
                        		WHERE host.id in (" . $_SESSION['allowed_hosts'][$user['id']] . ")
                        		AND disabled != 'on'
                        		ORDER BY ratio desc
                       	 		LIMIT 5");

                		if (cacti_sizeof($sql_worst_host)) {
					$color = read_config_option('intropage_alert_worst_polling_ratio');
					list($red,$yellow) = explode ('/',$color);

                        		foreach ($sql_worst_host as $host) {

                                       		$result['alarm'] = 'red';
                                		if ($host['ratio'] > $red) {
                                			$result['alarm'] = 'red';
						}
						elseif ($result['alarm'] == 'green' && $host['ratio'] > $yellow)	{
							$result['alarm'] = 'yellow';
						}

                                		if ($console_access) {
                                        		$row = '<tr><td class="rpad"><a href="' . html_escape($config['url_path'] . 'host.php?action=edit&id=' . $host['id']) . '">' . html_escape($host['description']) . '</a>';
                                		} else {
                                        		$row = '<tr><td class="rpad">' . html_escape($host['description']) . '</td>';
                                		}

                                		$row .= '<td class="rpad texalirig">' . $host['failed_polls'] . '</td>';
                                		$row .= '<td class="rpad texalirig">' . $host['total_polls'] . '</td>';
                                		$row .= '<td class="rpad texalirig">' . round($host['ratio'], 2) . '</td></tr>';
                                		$result['data'] .= $row;
                        		}

					$result['data'] = '<table>' . 
								'<tr><td>' . __('Host', 'intropage') . '</td>' . 
								'<td>' . __('Failed', 'intropage') . '</td>' .
								'<td>' . __('Total', 'intropage') . '</td>' . 
								'<td>' . __('Ratio', 'intropage') . '</td></tr>' . 
								$result['data'] . '</table>';
                       		
                		} else {        // no data
                        		$result['data'] = __('Waiting for data', 'intropage');
                		}
        		} else {
            			$result['data'] = __('You don\'t have permissions to any hosts', 'intropage');
        		}

	    		db_execute_prepared('REPLACE INTO plugin_intropage_panel_data (id,panel_id,user_id,data,alarm)
			    VALUES (?,?,?,?,?)',
			    array($id,$panel_id,$user['id'],$result['data'],$result['alarm']));
		}
	}

	if ($display)    {
	        $result = db_fetch_row_prepared('SELECT id, data, alarm, last_update FROM plugin_intropage_panel_data 
	    				    WHERE panel_id= ? AND user_id= ?',
	    				    array($panel_id, $_SESSION['sess_user_id'])); 

		$result['recheck'] = db_fetch_cell_prepared("SELECT concat(
			floor(TIME_FORMAT(SEC_TO_TIME(refresh_interval), '%H') / 24), 'd ',
			MOD(TIME_FORMAT(SEC_TO_TIME(refresh_interval), '%H'), 24), 'h:',
			TIME_FORMAT(SEC_TO_TIME(refresh_interval), '%im'))
			FROM plugin_intropage_panel_definition
			WHERE panel_id= ?",
			array($panel_id));

		$result['name'] = $panel_name;
	        return $result;
	}
}


//------------------------------------ thold event -----------------------------------------------------
function thold_event($display=false, $update=false, $force_update=false) {
	global $config;

	$panel_id = 'thold_event';
	$panel_name = __('Last thold events', 'intropage');

	
	$update_interval = db_fetch_cell_prepared('SELECT refresh_interval FROM plugin_intropage_panel_definition
					WHERE panel_id= ?',
					array($panel_id));

	if ($_SESSION['sess_user_id'] > 0)	{ // specific user wants his panel only	
	    	$users = array(array('id'=>$_SESSION['sess_user_id']));
	}
	else	{ // poller wants all
		$users = db_fetch_assoc("SELECT t1.id AS id FROM user_auth AS t1 JOIN plugin_intropage_user_auth AS t2
				 ON t1.id=t2.user_id WHERE t1.enabled='on'");
	}

	foreach ($users as $user)	{

		$id = db_fetch_cell_prepared('SELECT id FROM plugin_intropage_panel_data WHERE 
				panel_id= ? AND user_id= ? AND last_update IS NOT NULL',
				array($panel_id,$user['id']));

		if (!$id) {				
	    		db_execute_prepared('REPLACE INTO plugin_intropage_panel_data (panel_id,user_id,data,alarm,last_update) 
				VALUES ( ?, ?, ?, "gray", 1000)',
			    	array($panel_id, $user['id'],__('Waiting for data', 'intropage')));

	    		$id = db_fetch_insert_id();
		}

		$last_update = db_fetch_cell_prepared('SELECT unix_timestamp(last_update) FROM plugin_intropage_panel_data
					WHERE user_id= ? AND panel_id= ?',
					array($user['id'],$panel_id));

        	if ( $force_update || time() > ($last_update + $update_interval))       {

			$result = array(
				'name' => $panel_name,
				'alarm' => 'green',
				'data' => '',
				'last_update' =>  NULL,
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
                	        WHERE td.host_id IN (' . $_SESSION['allowed_hosts'][$user['id']] . ')
	                        HAVING (user0 IS NULL OR (user1 IS NULL OR user2 IS NULL))
        	                ORDER BY `time` DESC
                	        LIMIT 10');

	                	if (cacti_sizeof($sql_result)) {
        	                	foreach ($sql_result as $row) {
                	                	$result['data'] .= date('Y-m-d H:i:s', $row['time']) . ' - ' . html_escape($row['description']) . '<br/>';
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

	    		db_execute_prepared('REPLACE INTO plugin_intropage_panel_data (id,panel_id,user_id,data,alarm) 
			    VALUES (?,?,?,?,?)',
			    array($id,$panel_id,$user['id'],$result['data'],$result['alarm']));
		}

	}

	if ($display)    {
	        $result = db_fetch_row_prepared('SELECT id, data, alarm, last_update FROM plugin_intropage_panel_data 
	    				    WHERE panel_id= ? AND user_id= ?',
	    				    array($panel_id, $_SESSION['sess_user_id'])); 

		$result['recheck'] = db_fetch_cell_prepared("SELECT concat(
			floor(TIME_FORMAT(SEC_TO_TIME(refresh_interval), '%H') / 24), 'd ',
			MOD(TIME_FORMAT(SEC_TO_TIME(refresh_interval), '%H'), 24), 'h:',
			TIME_FORMAT(SEC_TO_TIME(refresh_interval), '%im'))
			FROM plugin_intropage_panel_definition
			WHERE panel_id= ?",
			array($panel_id));

		$result['name'] = $panel_name;
	        return $result;
	}
}


//--------------------------------boost--------------------------------
function boost($display=false, $update=false, $force_update=false) {
	global $config, $boost_refresh_interval, $boost_max_runtime;

	$panel_id = 'boost';
	$panel_name = __('Boost statistics', 'intropage');

	$result = array(
		'name' => $panel_name,
		'alarm' => 'green',
		'data' => '',
		'last_update' =>  NULL,
	);

	$id = db_fetch_cell_prepared('SELECT id FROM plugin_intropage_panel_data WHERE
				panel_id=? AND last_update IS NOT NULL',
				array($panel_id));

	if (!$id) {
		db_execute_prepared('REPLACE INTO plugin_intropage_panel_data (panel_id,user_id,data,alarm,last_update)
			    VALUES (?, ?, ?,"gray",1000)',
			    array($panel_id, 0, __('Waiting for data', 'intropage')));

		$id = db_fetch_insert_id();
	}

	$last_update = db_fetch_cell_prepared('SELECT unix_timestamp(last_update) FROM plugin_intropage_panel_data
					WHERE user_id=0 and panel_id= ?',
					array($panel_id));

	$update_interval = db_fetch_cell_prepared('SELECT refresh_interval FROM plugin_intropage_panel_definition
					WHERE panel_id= ?',
					array($panel_id));

	if ( $force_update || time() > ($last_update + $update_interval))	{

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

	    	db_execute_prepared('REPLACE INTO plugin_intropage_panel_data (id,panel_id,user_id,data,alarm)
			    VALUES ( ?, ?, ?, ?, ?)',
			    array($id,$panel_id,0,$result['data'],$result['alarm']));
        }

	if ($display)    {
	        $result = db_fetch_row_prepared('SELECT id, data, alarm, last_update FROM plugin_intropage_panel_data
	    				    WHERE panel_id= ?',
	    				    array($panel_id));

		$result['recheck'] = db_fetch_cell_prepared("SELECT concat(
			floor(TIME_FORMAT(SEC_TO_TIME(refresh_interval), '%H') / 24), 'd ',
			MOD(TIME_FORMAT(SEC_TO_TIME(refresh_interval), '%H'), 24), 'h:',
			TIME_FORMAT(SEC_TO_TIME(refresh_interval), '%im'))
			FROM plugin_intropage_panel_definition
			WHERE panel_id= ?",
			array($panel_id));

		$result['name'] = $panel_name;
	        return $result;
	}
}


//------------------------------------ extrem -----------------------------------------------------
function extrem($display=false, $update=false, $force_update=false) {
	global $config, $run_from_poller;

	$panel_id = 'extrem';
	$panel_name = __('24 hours extrem', 'intropage');

	if (isset($run_from_poller))	{ // update in poller

		$users = db_fetch_assoc("SELECT t1.id AS id FROM user_auth AS t1 JOIN plugin_intropage_user_auth AS t2
				 ON t1.id=t2.user_id WHERE t1.enabled='on' AND t2.trend='on'");
		foreach ($users as $user)	{

			$count = db_fetch_cell('SELECT SUM(failed_polls) FROM host WHERE id IN (' . $_SESSION['allowed_hosts'][$user['id']] . ')');
        		db_execute_prepared('REPLACE INTO plugin_intropage_trends
                		(name, value, user_id) VALUES (?, ?, 0)',
                		array('failed_polls', $count));

		        $count = db_fetch_cell("SELECT COUNT(local_data_id) FROM poller_output");
		}

        	db_execute_prepared('REPLACE INTO plugin_intropage_trends
                	(name, value, user_id) VALUES (?, ?, 0)',
                	array('poller_output', $count));
	}

	$update_interval = db_fetch_cell_prepared('SELECT refresh_interval FROM plugin_intropage_panel_definition
					WHERE panel_id= ?',
					array($panel_id));

	if ($_SESSION['sess_user_id'] > 0)	{ // specific user wants his panel only	
		$users = array(array('id'=>$_SESSION['sess_user_id']));
	}
	else	{ // poller wants all
		$users = db_fetch_assoc("SELECT t1.id AS id FROM user_auth AS t1 JOIN plugin_intropage_user_auth AS t2
				 ON t1.id=t2.user_id WHERE t1.enabled='on'");
	}

	foreach ($users as $user)	{

		$id = db_fetch_cell_prepared('SELECT id FROM plugin_intropage_panel_data WHERE 
				panel_id= ? AND user_id= ? AND last_update IS NOT NULL',
				array($panel_id,$user['id']));

		if (!$id) {				
	    		db_execute_prepared('REPLACE INTO plugin_intropage_panel_data (panel_id,user_id,data,alarm,last_update) 
				VALUES ( ?, ?, ?, "gray", 1000)',
			    	array($panel_id, $user['id'],__('Waiting for data', 'intropage')));

	    		$id = db_fetch_insert_id();
		}

		$last_update = db_fetch_cell_prepared('SELECT unix_timestamp(last_update) FROM plugin_intropage_panel_data
					WHERE user_id= ? AND panel_id= ?',
					array($user['id'],$panel_id));

        	if ( $force_update || time() > ($last_update + $update_interval))       {

			$result = array(
				'name' => $panel_name,
				'alarm' => 'gray',
				'data' => '',
				'last_update' =>  NULL,
			);

	    		$console_access = (db_fetch_assoc_prepared('SELECT realm_id FROM user_auth_realm
					WHERE user_id = ?
				    	AND user_auth_realm.realm_id=8',
				    	array($user['id']))) ? true : false;

			if ($console_access) {
		       		$result['data'] = '<table><tr><td class="rpad">';

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
		        	} else {
        		        	$result['data'] .= '<br/>' . __('Waiting<br/>for data', 'intropage');
        			}

		        	$result['data'] .= '</td>';
			}


	        	// max host down
        		$result['data'] .= '<td class="rpad texalirig">';
        		$result['data'] .= '<strong>Max host<br/>down: </strong>';

	        	$sql_result = db_fetch_assoc_prepared("SELECT date_format(time(cur_timestamp),'%H:%i') AS `date`, value
        	        	FROM plugin_intropage_trends
                		WHERE name='host'
                		AND user_id = ? 
                		AND cur_timestamp > date_sub(now(),interval 1 day)
	                	ORDER BY value desc,cur_timestamp
        	        	LIMIT 8",
        	        	array($user['id']));

	        	if (cacti_sizeof($sql_result)) {
        	        	foreach ($sql_result as $row) {
                	        	$result['data'] .= '<br/>' . $row['date'] . ' ' . $row['value'];
                		}
        		} else {
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
	                		AND user_id = " . $user['id'] . "
                	        	AND cur_timestamp > date_sub(now(),interval 1 day)
                        		ORDER BY value desc,cur_timestamp
                        		LIMIT 8");

	                	if (cacti_sizeof($sql_result)) {
        	                	foreach ($sql_result as $row) {
                	                	$result['data'] .= '<br/>' . $row['date'] . ' ' . $row['value'];
                        		}
	                	} else {
        	                	$result['data'] .= '<br/>' . __('Waiting<br/>for data', 'intropage');
                		}
	        	} else {
        	        	$result['data'] .= '<br/>no<br/>plugin<br/>installed<br/>or<br/>running';
        		}

	        	$result['data'] .= '</td>';

        		// poller output items
        		if ($console_access) {
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
		        	} else {
                			$result['data'] .= '<br/>' . __('Waiting<br/>for data', 'intropage');
        			}	

	        		$result['data'] .= '</td>';
	        	}

	        	// failed polls
			if ($console_access) {
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
        			} else {
		                	$result['data'] .= '<br/>' . __('Waiting<br/>for data', 'intropage');
        			}

		        	$result['data'] .= '</td>';
			}

        		$result['data'] .= '</tr></table>';

	    		db_execute_prepared('REPLACE INTO plugin_intropage_panel_data (id,panel_id,user_id,data,alarm) 
			    VALUES (?,?,?,?,?)',
			    array($id,$panel_id,$user['id'],$result['data'],$result['alarm']));
		}
	}



	if ($display)    {
	        $result = db_fetch_row_prepared('SELECT id, data, alarm, last_update FROM plugin_intropage_panel_data 
	    				    WHERE panel_id= ? AND user_id= ?',
	    				    array($panel_id, $_SESSION['sess_user_id'])); 

		$result['recheck'] = db_fetch_cell_prepared("SELECT concat(
			floor(TIME_FORMAT(SEC_TO_TIME(refresh_interval), '%H') / 24), 'd ',
			MOD(TIME_FORMAT(SEC_TO_TIME(refresh_interval), '%H'), 24), 'h:',
			TIME_FORMAT(SEC_TO_TIME(refresh_interval), '%im'))
			FROM plugin_intropage_panel_definition
			WHERE panel_id= ?",
			array($panel_id));

		$result['name'] = $panel_name;
	        return $result;
	}
}


//------------------------------------ graph_thold -----------------------------------------------------
function graph_thold($display=false, $update=false, $force_update=false) {
	global $config;

	$panel_id = 'graph_thold';
	$panel_name = __('Thresholds', 'intropage');

	$update_interval = db_fetch_cell_prepared('SELECT refresh_interval FROM plugin_intropage_panel_definition
					WHERE panel_id= ?',
					array($panel_id));

	if ($_SESSION['sess_user_id'] > 0)	{ // specific user wants his panel only
	    $users = array(array('id'=>$_SESSION['sess_user_id']));
	}
	else	{ // poller wants all
	    $users = db_fetch_assoc("SELECT id FROM user_auth WHERE enabled='on'");
	}

	foreach ($users as $user)	{
		$result = array(
			'name' => $panel_name,
			'alarm' => 'green',
			'data' => '',
			'last_update' =>  NULL,
		);

		$id = db_fetch_cell_prepared('SELECT id FROM plugin_intropage_panel_data WHERE
				panel_id= ? AND user_id= ? AND last_update IS NOT NULL',
				array($panel_id,$user['id']));

		if (!$id) {
	    		db_execute_prepared('REPLACE INTO plugin_intropage_panel_data (panel_id,user_id,data,alarm,last_update)
				VALUES ( ?, ?, ?, "gray", 1000)',
			    	array($panel_id, $user['id'],__('Waiting for data', 'intropage')));

	    		$id = db_fetch_insert_id();
		}

		$last_update = db_fetch_cell_prepared('SELECT unix_timestamp(last_update) FROM plugin_intropage_panel_data
					WHERE user_id= ? AND panel_id= ?',
					array($user['id'],$panel_id));

        	if ( $force_update || time() > ($last_update + $update_interval))       {

			if (!db_fetch_cell("SELECT directory FROM plugin_config WHERE directory='thold' and status=1")) {
				$result['alarm'] = 'gray';
				$result['data']  = __('Thold plugin not installed/running', 'intropage');
			} elseif (!db_fetch_cell('SELECT DISTINCT user_id FROM user_auth_realm WHERE user_id = ' . $user['id'] . " AND realm_id IN (SELECT id + 100 FROM plugin_realms WHERE file LIKE '%thold%')")) {
				$result['data'] = __('You don\'t have plugin permission', 'intropage');
			} else {
/*
				// old code, faster but wrong count
https://github.com/Cacti/plugin_thold/issues/440

                               // need for thold - isn't any better solution?
                               $current_user  = db_fetch_row('SELECT * FROM user_auth WHERE id=' . $user['id']);
                               $sql_where = get_graph_permissions_sql($current_user['policy_graphs'], $current_user['policy_hosts'], $current_user['policy_graph_templates']);

                               $sql_join = ' LEFT JOIN host ON thold_data.host_id=host.id     LEFT JOIN user_auth_perms ON ((thold_data.graph_template_id=user_auth_perms.item_id AND user_auth_perms.type=1 AND user_auth_perms.user_id= ' . $user['id'] . ') OR
                                       (thold_data.host_id=user_auth_perms.item_id AND user_auth_perms.type=3 AND user_auth_perms.user_id= ' . $user['id'] . ') OR
                                       (thold_data.graph_template_id=user_auth_perms.item_id AND user_auth_perms.type=4 AND user_auth_perms.user_id= ' . $user['id'] . '))';

                               $t_all  = db_fetch_cell("SELECT COUNT(*) FROM thold_data $sql_join WHERE thold_data.thold_enabled = 'on' AND $sql_where");
                               $t_brea = db_fetch_cell("SELECT COUNT(*) FROM thold_data $sql_join WHERE thold_data.thold_enabled = 'on' AND (thold_data.thold_alert!=0 OR thold_data.bl_alert>0) AND $sql_where");
                               $t_trig = db_fetch_cell("SELECT COUNT(*) FROM thold_data $sql_join WHERE thold_data.thold_enabled = 'on' AND ((thold_data.thold_alert!=0 AND thold_data.thold_fail_count >= thold_data.thold_fail_trigger) OR (thold_data.bl_alert > 0 AND thold_data.bl_fail_count >= thold_data.bl_fail_trigger)) AND $sql_where");
                               $t_disa = db_fetch_cell("SELECT COUNT(*) FROM thold_data $sql_join WHERE thold_data.thold_enabled='off' AND $sql_where");

                               $count = $t_all + $t_brea + $t_trig + $t_disa;
*/

				// right way but it is slow

				include_once($config['base_path'] . '/plugins/thold/thold_functions.php');

				$t_all = 0; $t_brea = 0; $t_trig = 0; $t_disa = 0;
				$sql_where = '';
				$x = '';
				$x = get_allowed_thresholds($sql_where, 'null', 1, $t_all, $user['id']);
				$sql_where = "td.thold_enabled = 'on' AND ((td.thold_alert != 0 OR td.bl_alert > 0))";
				$x = get_allowed_thresholds($sql_where, 'null', 1, $t_brea, $user['id']);
				$sql_where = "td.thold_enabled = 'on' AND (((td.thold_alert != 0 AND td.thold_fail_count >= td.thold_fail_trigger) 
						OR (td.bl_alert > 0 AND td.bl_fail_count >= td.bl_fail_trigger)))";
				$x = get_allowed_thresholds($sql_where, 'null', 1, $t_trig, $user['id']);
				$sql_where = "td.thold_enabled = 'off'";
				$x = get_allowed_thresholds($sql_where, 'null', 1, $t_disa, $user['id']);

				$has_access = db_fetch_cell('SELECT COUNT(*) FROM user_auth_realm WHERE user_id = '.$user['id']." AND realm_id IN (SELECT id + 100 FROM plugin_realms WHERE file LIKE '%thold_graph.php%')");
				$url_prefix = $has_access ? '<a href="' . html_escape($config['url_path'] . 'plugins/thold/thold_graph.php?tab=thold&triggered=%s') . '">' : '';
				$url_suffix = $has_access ? '</a>' : '';

				$result['data']  = sprintf($url_prefix, '-1') . __('All', 'intropage') . ": $t_all$url_suffix<br/>";
				$result['data'] .= sprintf($url_prefix, '1') . __('Breached', 'intropage') . ": $t_brea$url_suffix<br/>";
				$result['data'] .= sprintf($url_prefix, '3') . __('Trigged', 'intropage') . ": $t_trig$url_suffix<br/>";
				$result['data'] .= sprintf($url_prefix, '0') . __('Disabled', 'intropage') . ": $t_disa$url_suffix<br/>";

				if ($t_all > 0) {
		                	$graph = array ('pie' => array(
						'title' => $panel_name,
						'label' => array(
							__('OK', 'intropage'),
							__('Triggered', 'intropage'),
							__('Breached', 'intropage'),
							__('Disabled', 'intropage'),
						),
						'data' => array($t_all - $t_brea - $t_trig - $t_disa, $t_trig, $t_brea, $t_disa))
					);

					$result['data'] = intropage_prepare_graph($graph);

					// alarms and details
					if ($t_brea > 0) {
						$result['alarm'] = 'yellow';
					}

					if ($t_trig > 0) {
						$result['alarm'] = 'red';
					}
				}
			}

	    		db_execute_prepared('REPLACE INTO plugin_intropage_panel_data (id,panel_id,user_id,data,alarm)
			    VALUES (?,?,?,?,?)',
			    array($id,$panel_id,$user['id'],$result['data'],$result['alarm']));
		}
	}

	if ($display)    {
	        $result = db_fetch_row_prepared('SELECT id, data, alarm, last_update FROM plugin_intropage_panel_data 
	    				    WHERE panel_id= ? AND user_id= ?',
	    				    array($panel_id, $_SESSION['sess_user_id'])); 

		$result['recheck'] = db_fetch_cell_prepared("SELECT concat(
			floor(TIME_FORMAT(SEC_TO_TIME(refresh_interval), '%H') / 24), 'd ',
			MOD(TIME_FORMAT(SEC_TO_TIME(refresh_interval), '%H'), 24), 'h:',
			TIME_FORMAT(SEC_TO_TIME(refresh_interval), '%im'))
			FROM plugin_intropage_panel_definition
			WHERE panel_id= ?",
			array($panel_id));

		$result['name'] = $panel_name;
	        return $result;
	}
}


//------------------------------------ mactrack -----------------------------------------------------
function mactrack($display=false, $update=false, $force_update=false) {
	global $config;

	$panel_id = 'mactrack';
	$panel_name = __('Mactrack plugin', 'intropage');

	$update_interval = db_fetch_cell_prepared('SELECT refresh_interval FROM plugin_intropage_panel_definition
					WHERE panel_id= ?',
					array($panel_id));

	if ($_SESSION['sess_user_id'] > 0)	{ // specific user wants his panel only
	    	$users = array(array('id'=>$_SESSION['sess_user_id']));
	}
	else	{ // poller wants all
	    	$users = db_fetch_assoc("SELECT id FROM user_auth WHERE enabled='on'");
	}

	foreach ($users as $user)	{

		$id = db_fetch_cell_prepared('SELECT id FROM plugin_intropage_panel_data WHERE
				panel_id= ? AND user_id= ? AND last_update IS NOT NULL',
				array($panel_id,$user['id']));

		if (!$id) {
	    		db_execute_prepared('REPLACE INTO plugin_intropage_panel_data (panel_id,user_id,data,alarm,last_update)
				VALUES ( ?, ?, ?, "gray", 1000)',
			    	array($panel_id, $user['id'],__('Waiting for data', 'intropage')));

	    		$id = db_fetch_insert_id();
		}

		$last_update = db_fetch_cell_prepared('SELECT unix_timestamp(last_update) FROM plugin_intropage_panel_data
					WHERE user_id= ? AND panel_id= ?',
					array($user['id'],$panel_id));

        	if ( $force_update || time() > ($last_update + $update_interval))       {

			$result = array(
				'name' => $panel_name,
				'alarm' => 'green',
				'data' => '',
				'last_update' =>  NULL,
			);

			// SELECT id from plugin_realms WHERE plugin='mactrack' and display like '%view%';
			// = 329 +100

			if (!db_fetch_cell("SELECT directory FROM plugin_config WHERE directory='mactrack' AND status=1")) {
				$result['alarm'] = 'gray';
				$result['data']  = __('Mactrack plugin not installed/running', 'intropage');
			} else {
				$mactrack_id = db_fetch_cell("SELECT id FROM plugin_realms
					WHERE plugin='mactrack'	AND display LIKE '%view%'");

				if (!db_fetch_cell('SELECT DISTINCT user_id FROM user_auth_realm WHERE user_id = ' . $user['id'] . ' AND realm_id =' . ($mactrack_id + 100))) {
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

		                	$graph = array ('pie' => array(
						'title' => __('Mactrack', 'intropage'),
						'label' => array(
							__('Up', 'intropage'),
							__('Down', 'intropage'),
							__('Error', 'intropage'),
							__('Unknown', 'intropage'),
							__('Disabled', 'intropage'),
						),
						'data' => array($m_up, $m_down, $m_err, $m_unkn, $m_disa))
					);
					$result['data'] = intropage_prepare_graph($graph);

				}
			}

	    		db_execute_prepared('REPLACE INTO plugin_intropage_panel_data (id,panel_id,user_id,data,alarm)
			    VALUES (?,?,?,?,?)',
			    array($id,$panel_id,$user['id'],$result['data'],$result['alarm']));

		} // konec smycky pres vsechny uzivatele
	}

	if ($display)    {
	        $result = db_fetch_row_prepared('SELECT id, data, alarm, last_update FROM plugin_intropage_panel_data
	    				    WHERE panel_id= ?',
	    				    array($panel_id));

		$result['recheck'] = db_fetch_cell_prepared("SELECT concat(
			floor(TIME_FORMAT(SEC_TO_TIME(refresh_interval), '%H') / 24), 'd ',
			MOD(TIME_FORMAT(SEC_TO_TIME(refresh_interval), '%H'), 24), 'h:',
			TIME_FORMAT(SEC_TO_TIME(refresh_interval), '%im'))
			FROM plugin_intropage_panel_definition
			WHERE panel_id= ?",
			array($panel_id));

		$result['name'] = $panel_name;
	        return $result;
	}
}


//------------------------------------ mactrack sites -----------------------------------------------------
function mactrack_sites($display=false, $update=false, $force_update=false) {
	global $config;

	$panel_id = 'mactrack_sites';
	$panel_name = __('Mactrack sites', 'intropage');

	$update_interval = db_fetch_cell_prepared('SELECT refresh_interval FROM plugin_intropage_panel_definition
					WHERE panel_id= ?',
					array($panel_id));

	if ($_SESSION['sess_user_id'] > 0)	{ // specific user wants his panel only
	    $users = array(array('id'=>$_SESSION['sess_user_id']));
	}
	else	{ // poller wants all
	    $users = db_fetch_assoc("SELECT id FROM user_auth WHERE enabled='on'");
	}

	foreach ($users as $user)	{

		$id = db_fetch_cell_prepared('SELECT id FROM plugin_intropage_panel_data WHERE
				panel_id= ? AND user_id= ? AND last_update IS NOT NULL',
				array($panel_id,$user['id']));

		if (!$id) {
	    		db_execute_prepared('REPLACE INTO plugin_intropage_panel_data (panel_id,user_id,data,alarm,last_update)
				VALUES ( ?, ?, ?, "gray", 1000)',
			    	array($panel_id, $user['id'],__('Waiting for data', 'intropage')));

	    		$id = db_fetch_insert_id();
		}

		$last_update = db_fetch_cell_prepared('SELECT unix_timestamp(last_update) FROM plugin_intropage_panel_data
					WHERE user_id= ? AND panel_id= ?',
					array($user['id'],$panel_id));

        	if ( $force_update || time() > ($last_update + $update_interval))       {

			$result = array(
				'name' => $panel_name,
				'alarm' => 'gray',
				'data' => '',
				'last_update' =>  NULL,
			);

			if (!db_fetch_cell("SELECT directory FROM plugin_config WHERE directory='mactrack' AND status=1")) {
				$result['alarm'] = 'gray';
				$result['data']  = __('Mactrack plugin not installed/running', 'intropage');
			} else {
				$mactrack_id = db_fetch_cell("SELECT id
					FROM plugin_realms
					WHERE plugin='mactrack'
					AND display LIKE '%view%'");

				if (!db_fetch_cell('SELECT DISTINCT user_id FROM user_auth_realm WHERE user_id = '.$user['id'].' AND realm_id =' . ($mactrack_id + 100))) {
		    			$result['data'] =  __('You don\'t have plugin permission', 'intropage');
				} else {
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

	    		db_execute_prepared('REPLACE INTO plugin_intropage_panel_data (id,panel_id,user_id,data,alarm)
			    VALUES (?,?,?,?,?)',
			    array($id,$panel_id,$user['id'],$result['data'],$result['alarm']));
		}
	}

	if ($display)    {
	        $result = db_fetch_row_prepared('SELECT id, data, alarm, last_update FROM plugin_intropage_panel_data
	    				    WHERE panel_id= ?',
	    				    array($panel_id));

		$result['recheck'] = db_fetch_cell_prepared("SELECT concat(
			floor(TIME_FORMAT(SEC_TO_TIME(refresh_interval), '%H') / 24), 'd ',
			MOD(TIME_FORMAT(SEC_TO_TIME(refresh_interval), '%H'), 24), 'h:',
			TIME_FORMAT(SEC_TO_TIME(refresh_interval), '%im'))
			FROM plugin_intropage_panel_definition
			WHERE panel_id= ?",
			array($panel_id));

		$result['name'] = $panel_name;
	        return $result;
	}
}


// ----------------syslog----------------------
function plugin_syslog($display=false, $update=false, $force_update=false) {
        global $config, $run_from_poller;

        $panel_id = 'plugin_syslog';
        $panel_name = __('Plugin syslog', 'intropage');

        include_once($config['base_path'] . '/plugins/intropage/include/functions.php');

        $result = array(
                'name' => $panel_name,
                'alarm' => 'gray',
                'data' => '',
                'last_update' =>  NULL,
        );

       $graph = array ('line' => array(
                        'title' => $panel_name,
                        'title1' => '',
                        'label1' => array(),
                        'data1' => array(),
                        'title2' => '',
                        'label2' => array(),
                        'data2' => array(),
                        'title3' => '',
                        'label3' => array(),
                        'data3' => array(),
                ),
        );

        if (isset($run_from_poller))    { // update in poller

                if (db_fetch_cell("SELECT directory FROM plugin_config WHERE directory='syslog' and status=1")) {

                        // Grab row counts from the information schema, it's faster
                        $i_rows     = syslog_db_fetch_cell("SELECT TABLE_ROWS FROM information_schema.TABLES WHERE TABLE_NAME = 'syslog_incoming'");
                        $total_rows = syslog_db_fetch_cell("SELECT TABLE_ROWS FROM information_schema.TABLES WHERE TABLE_NAME = 'syslog'");

                        $alert_rows = syslog_db_fetch_cell_prepared('SELECT ifnull(sum(count),0) FROM syslog_logs WHERE
                                logtime > date_sub(now(), INTERVAL ? SECOND)',
                                array(read_config_option('poller_interval')));

                        db_execute_prepared('INSERT INTO plugin_intropage_trends (name,value,user_id) VALUES ("syslog_incoming", ?, 0)',
                        array($i_rows));
                        db_execute_prepared('INSERT INTO plugin_intropage_trends (name,value,user_id) VALUES ("syslog_total", ?, 0)',
                        array ($total_rows));
                        db_execute_prepared('INSERT INTO plugin_intropage_trends (name,value,user_id) VALUES ("syslog_alert", ?, 0)',
                        array ($alert_rows));
                }
        }

        $id = db_fetch_cell_prepared('SELECT id FROM plugin_intropage_panel_data WHERE
                                panel_id= ? AND last_update IS NOT NULL', array($panel_id));

        if (!$id) {
                db_execute_prepared('REPLACE INTO plugin_intropage_panel_data (panel_id,user_id,data,alarm,last_update)
                            VALUES ( ?, ?, ? ,"gray",1000)',
                            array($panel_id, 0, __('Waiting for data', 'intropage')));

                $id = db_fetch_insert_id();
        }

        $last_update = db_fetch_cell_prepared('SELECT unix_timestamp(last_update) FROM plugin_intropage_panel_data
                                        WHERE user_id= ? and panel_id= ?',
                                        array( $_SESSION['sess_user_id'], $panel_id));

        $update_interval = db_fetch_cell_prepared('SELECT refresh_interval FROM plugin_intropage_panel_definition
                                        WHERE panel_id= ?', array($panel_id));

        if ( $force_update || time() > ($last_update + $update_interval))       {

                if (db_fetch_cell("SELECT directory FROM plugin_config WHERE directory='syslog' and status=1")) {
                        $sql = db_fetch_assoc("SELECT date_format(time(cur_timestamp),'%H:%i') AS `date`, name, value
                                FROM plugin_intropage_trends
                                WHERE name='syslog_total'
                                ORDER BY cur_timestamp desc
                                LIMIT 11");

                        if (cacti_sizeof($sql)) {
                                $val = 0;
                                $graph['line']['title1'] = __('Total', 'intropage');
                                foreach ($sql as $row) {
                                        array_push($graph['line']['label1'], $row['date']);
                                        array_push($graph['line']['data1'], $val - $row['value']);
                                        $val = $row['value'];
                                }
                                array_shift($graph['line']['label1']);
                                array_shift($graph['line']['data1']);
                        }

                        $sql = db_fetch_assoc("SELECT date_format(time(cur_timestamp),'%H:%i') AS `date`, name, value
                                FROM plugin_intropage_trends
                                WHERE name='syslog_incoming'
                                ORDER BY cur_timestamp desc
                                LIMIT 11");

                        if (cacti_sizeof($sql)) {
                                $val = 0;
                                $graph['line']['title2'] = __('Incoming', 'intropage');

                                foreach ($sql as $row) {
                                        array_push($graph['line']['label2'], $row['date']);
                                        array_push($graph['line']['data2'], $val - $row['value']);
                                        $val = $row['value'];
                                }
                                array_shift($graph['line']['label2']);
                                array_shift($graph['line']['data2']);
                        }

                        $sql = db_fetch_assoc("SELECT date_format(time(cur_timestamp),'%H:%i') AS `date`, name, value
                                FROM plugin_intropage_trends
                                WHERE name='syslog_alert'
                                ORDER BY cur_timestamp desc
                                LIMIT 11");

                        if (cacti_sizeof($sql)) {
                                $val = 0;
                                $graph['line']['title3'] = __('Alerts', 'intropage');
                                foreach ($sql as $row) {
                                        array_push($graph['line']['label3'], $row['date']);
                                        array_push($graph['line']['data3'], $val - $row['value']);
                                        if ($row['value']-$val > 0)     {
                                                $result['alert'] = 'yellow';
                                        }
                                        $val = $row['value'];
                                }
                                array_shift($graph['line']['label3']);
                                array_shift($graph['line']['data3']);

                                $result['data'] = intropage_prepare_graph($graph);

                                if (cacti_sizeof($sql) < 3) {
                                        unset($result['line']);
                                        $result['data'] = 'Waiting for data';
                                } else {
                                        $graph['line']['data1'] = array_reverse($graph['line']['data1']);
                                        $graph['line']['data2'] = array_reverse($graph['line']['data2']);
                                        $graph['line']['data3'] = array_reverse($graph['line']['data3']);
                                        $graph['line']['label1'] = array_reverse($graph['line']['label1']);
                                        $graph['line']['label2'] = array_reverse($graph['line']['label2']);
                                        $graph['line']['label3'] = array_reverse($graph['line']['label3']);
                                }
                        }
                } else {
                        $result['data']  = __('Syslog plugin not installed/running', 'intropage');
                        unset($graph['line']);
                }

                db_execute_prepared('REPLACE INTO plugin_intropage_panel_data (id,panel_id,user_id,data,alarm)
                        VALUES ( ?, ?, 0, ?, ?)',
                        array($id,$panel_id,$result['data'],$result['alarm']));

        }

       if ($display)    {
                $result = db_fetch_row_prepared('SELECT id, data, alarm, last_update FROM plugin_intropage_panel_data
                                            WHERE panel_id= ?',
                                            array($panel_id));

                $result['recheck'] = db_fetch_cell_prepared("SELECT concat(
                        floor(TIME_FORMAT(SEC_TO_TIME(refresh_interval), '%H') / 24), 'd ',
                        MOD(TIME_FORMAT(SEC_TO_TIME(refresh_interval), '%H'), 24), 'h:',
                        TIME_FORMAT(SEC_TO_TIME(refresh_interval), '%im'))
                        FROM plugin_intropage_panel_definition
                        WHERE panel_id= ?",
                        array($panel_id));

                $result['name'] = $panel_name;
                return $result;
        }
}


// -------------------------------------plugin webseer-------------------------------------------
function webseer($display=false, $update=false, $force_update=false) {
	global $config;

	$panel_id = 'webseer';
	$panel_name = __('Webseer plugin', 'intropage');

	$result = array(
		'name' => $panel_name,
		'alarm' => 'green',
		'data' => '',
		'last_update' =>  NULL,
	);

	$id = db_fetch_cell_prepared('SELECT id FROM plugin_intropage_panel_data WHERE
				panel_id= ? AND last_update IS NOT NULL',
				array($panel_id));

	if (!$id) {
		db_execute_prepared('REPLACE INTO plugin_intropage_panel_data (panel_id,user_id,data,alarm,last_update)
			VALUES ( ?, ?, ?, "gray", 1000)',
			array($panel_id, 0, __('Waiting for data', 'intropage')));

	    	$id = db_fetch_insert_id();
	}

	$last_update = db_fetch_cell_prepared('SELECT unix_timestamp(last_update) FROM plugin_intropage_panel_data
					WHERE user_id=0 and panel_id= ?',
					array($panel_id));

	$update_interval = db_fetch_cell_prepared('SELECT refresh_interval FROM plugin_intropage_panel_definition
					WHERE panel_id= ?',
					array($panel_id));

        if ( $force_update || time() > ($last_update + $update_interval))       {
        	if (db_fetch_cell("SELECT count(*) FROM plugin_config WHERE directory='webseer' AND status = 1") == 0) {
       	        	$result['alarm'] = 'yellow';
               		$result['data']  = __('Plugin Webseer isn\'t installed or started', 'intropage');
               		$result['detail'] = FALSE;
        	} else {
        		$all = db_fetch_cell('SELECT count(*) FROM plugin_webseer_urls');
        		$disa = db_fetch_cell("SELECT count(*) FROM plugin_webseer_urls WHERE enabled != 'on'");
        		$ok = db_fetch_cell("SELECT count(*) FROM plugin_webseer_urls WHERE enabled = 'on' AND result = 1");
        		$ko = db_fetch_assoc("SELECT * FROM plugin_webseer_urls WHERE enabled = 'on' AND result != 1");
        		
			$result['data']  = __('Number of checks: ', 'intropage') . $all . '<br/>';
			$result['data'] .= __('Disabled: ', 'intropage') . $disa . '<br/>';
			$result['data'] .= __('Status up: ', 'intropage') . $ok . '<br/>';
			$result['data'] .= __('Status down: ', 'intropage') . cacti_sizeof($ko) . '<br/><br/>';

			if (cacti_sizeof($ko) > 0)	{
				$count = 0;
				$result['alarm'] = 'red';
				$result['data'] .= '<table>';
				$result['data'] .= '<tr><td colspan="3"><strong>' . __('First 3 failed sites', 'intropage') . '</td></tr>';
				$result['data'] .= '<tr><td class="rpad">' . __('url', 'intropage') . '</td>' . 
							'<td class="rpad">' . __('Status', 'intropage') . '</td>' .
							'<td class="rpad">' . __('HTTP code', 'intropage') . '</td></tr>';

				foreach ($ko as $row)	{
					if ($count < 3)	{
						$result['data'] .= '<td class="rpad">' . $row['url'] . '</td>' . 
							'<td class="rpad">' . $row['result'] . '</td>' . 
							'<td class="rpad">' . $row['http_code'] . '</td></tr>'; 
					}
					$count++;
				}
				$result['data'] .= '</table>';
			}
		}
		
	    	db_execute_prepared('REPLACE INTO plugin_intropage_panel_data (id,panel_id,user_id,data,alarm)
			VALUES (?,?,?,?,?)',
			array($id,$panel_id,0,$result['data'],$result['alarm']));
        }

        if ($display)    {
	        $result = db_fetch_row_prepared('SELECT id, data, alarm, last_update FROM plugin_intropage_panel_data
			    WHERE panel_id= ?',
			    array($panel_id));

		$result['recheck'] = db_fetch_cell_prepared("SELECT concat(
			floor(TIME_FORMAT(SEC_TO_TIME(refresh_interval), '%H') / 24), 'd ',
			MOD(TIME_FORMAT(SEC_TO_TIME(refresh_interval), '%H'), 24), 'h:',
			TIME_FORMAT(SEC_TO_TIME(refresh_interval), '%im'))
			FROM plugin_intropage_panel_definition
			WHERE panel_id= ?",
			array($panel_id));

                $result['name'] = $panel_name;
                return $result;
        }
}
