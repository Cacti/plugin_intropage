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

if (!isset($_SESSION['sess_user_id']))
	$_SESSION['sess_user_id'] = 0;

include_once($config['base_path'] . '/plugins/intropage/include/functions.php');

//------------------------------------ analyse_login -----------------------------------------------------
function analyse_login($display=false, $update=false, $force_update=false) {
	global $config;

	$result = array(
		'name' => __('Analyze logins', 'intropage'),
		'alarm' => 'green',
		'data' => '',
		'last_update' =>  NULL,
	);
	
	$id = db_fetch_cell("SELECT id FROM plugin_intropage_panel_data WHERE 
				panel_id='analyse_login' AND last_update IS NOT NULL");
				
	if (!$id) {				
		db_execute("REPLACE INTO plugin_intropage_panel_data (panel_id,user_id,data,alarm,last_update) 
			    VALUES ('analyse_login'," . $_SESSION['sess_user_id'] . ",
			    '" . __('Waiting for data', 'intropage') . "','gray',1000)");

		$id = db_fetch_insert_id();
	}

	$last_update = db_fetch_cell("SELECT unix_timestamp(last_update) FROM plugin_intropage_panel_data
					WHERE user_id=" . $_SESSION['sess_user_id'] . 
					" and panel_id='analyse_login'");

	$update_interval = db_fetch_cell("SELECT refresh_interval FROM plugin_intropage_panel_definition
					WHERE panel_id='analyse_login'");

	if ( $force_update || time() > ($last_update + $update_interval))	{

		// active users in last hour:
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

	    	// active users in last hour:
	    	$result['data'] .= '<b>Active users in last hour:</b><br/>';

	    	$sql_result = db_fetch_assoc('SELECT DISTINCT username
			FROM user_log WHERE time > adddate(now(), INTERVAL -1 HOUR) LIMIT 10');

	    	if (cacti_sizeof($sql_result)) {
			foreach ($sql_result as $row) {
				$result['data'] .= $row['username'] . '<br/>';
			}
	    	}

	    	db_execute("REPLACE INTO plugin_intropage_panel_data (id,panel_id,user_id,data,alarm) 
			    VALUES (" . $id . ", 'analyse_login'," . $_SESSION['sess_user_id'] . ",
			    '" . $result['data'] . "','" . $result['alarm'] . "')");
	}

	if ($display)    {
	        $result = db_fetch_row ("SELECT id,data, alarm, last_update FROM plugin_intropage_panel_data 
	    				    WHERE panel_id='analyse_login'"); 

		$result['recheck'] = db_fetch_cell("SELECT concat(
			floor(TIME_FORMAT(SEC_TO_TIME(refresh_interval), '%H') / 24), 'd ',
			MOD(TIME_FORMAT(SEC_TO_TIME(refresh_interval), '%H'), 24), 'h:',
			TIME_FORMAT(SEC_TO_TIME(refresh_interval), '%im'))
			FROM plugin_intropage_panel_definition
			WHERE panel_id='analyse_login'");
		
		
		$result['name'] = 'Analyse login';
	        return $result;
	}
}



//------------------------------------ analyse_log -----------------------------------------------------
function analyse_log($display=false, $update=false, $force_update=false) {
	global $config;

	$result = array(
		'name' => __('Analyze log', 'intropage'),
		'alarm' => 'green',
		'data' => '',
		'last_update' =>  NULL,
	);
	
	
	$id = db_fetch_cell("SELECT id FROM plugin_intropage_panel_data WHERE 
				panel_id='analyse_log' AND last_update IS NOT NULL");
				
	if (!$id) {				
	    db_execute("REPLACE INTO plugin_intropage_panel_data (panel_id,user_id,data,alarm,last_update) 
			    VALUES ('analyse_log'," . $_SESSION['sess_user_id'] . ",
			    '" . __('Waiting for data', 'intropage') . "','gray',1000)");

	    $id = db_fetch_insert_id();
	}

	

	$last_update = db_fetch_cell("SELECT unix_timestamp(last_update) FROM plugin_intropage_panel_data
					WHERE user_id=" . $_SESSION['sess_user_id'] . 
					" and panel_id='analyse_log'");

	$update_interval = db_fetch_cell("SELECT refresh_interval FROM plugin_intropage_panel_definition
					WHERE panel_id='analyse_log'");

        if ( $force_update || time() > ($last_update + $update_interval))       {

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



	    db_execute("REPLACE INTO plugin_intropage_panel_data (id,panel_id,user_id,data,alarm) 
			    VALUES (" . $id . ",'analyse_log'," . $_SESSION['sess_user_id'] . ",
			    '" . $result['data'] . "','" . $result['alarm'] . "')");
	}

	if ($display)    {
	        $result = db_fetch_row ("SELECT id, data, alarm, last_update FROM plugin_intropage_panel_data 
	    				    WHERE panel_id='analyse_log'"); 

		$result['recheck'] = db_fetch_cell("SELECT concat(
			floor(TIME_FORMAT(SEC_TO_TIME(refresh_interval), '%H') / 24), 'd ',
			MOD(TIME_FORMAT(SEC_TO_TIME(refresh_interval), '%H'), 24), 'h:',
			TIME_FORMAT(SEC_TO_TIME(refresh_interval), '%im'))
			FROM plugin_intropage_panel_definition
			WHERE panel_id='analyse_log'");

		$result['name'] = 'Analyse log';

	        return $result;
	}
}






//------------------------------------ top5_ping -----------------------------------------------------
function top5_ping($display=false, $update=false, $force_update=false) {
	global $config;

	$result = array(
		'name' => __('Top5 ping', 'intropage'),
		'alarm' => 'green',
		'data' => '',
		'last_update' =>  NULL,
	);
	
	
	$update_interval = db_fetch_cell("SELECT refresh_interval FROM plugin_intropage_panel_definition
					WHERE panel_id='top5_ping'");
	

	if ($_SESSION['sess_user_id'] > 0)	{ // specific user wants his panel only	
	    $users = array(array('id'=>$_SESSION['sess_user_id']));
	}
	else	{ // poller wants all
	    $users = db_fetch_assoc("SELECT id FROM user_auth WHERE enabled='on'");
	}


	foreach ($users as $user)	{

		$id = db_fetch_cell("SELECT id FROM plugin_intropage_panel_data WHERE 
				panel_id='top5_ping' AND user_id=" . $user['id'] . " AND last_update IS NOT NULL");
				
		if (!$id) {				
	    		db_execute("REPLACE INTO plugin_intropage_panel_data (panel_id,user_id,data,alarm,last_update) 
			    VALUES ('top5_ping'," . $user['id'] . ",
			    '" . __('Waiting for data', 'intropage') . "','gray',1000)");

	    	$id = db_fetch_insert_id();
		}


		$last_update = db_fetch_cell("SELECT unix_timestamp(last_update) FROM plugin_intropage_panel_data
					WHERE user_id=" . $user['id'] . 
					" and panel_id='top5_ping'");


        	if ( $force_update || time() > ($last_update + $update_interval))       {

	    		$x = 0;	// reference
			//get_allowed_devices($sql_where = '', $order_by = 'description', $limit = '', &$total_rows = 0, $user = 0, $host_id = 0)
			$allowed =  get_allowed_devices('','description',-1,$x,$user['id']); 

	    		if (count($allowed) > 0) {
                		$allowed_hosts = implode(',', array_column($allowed, 'id'));
    	    		} else {
                		$allowed_hosts = false;
    	    		}

	    		if ($allowed_hosts)	{
				$console_access = (db_fetch_assoc_prepared('SELECT realm_id FROM user_auth_realm
					WHERE user_id = ?
				    	AND user_auth_realm.realm_id=8',
				    	array($user['id']))) ? true : false;
	    
				$sql_worst_host = db_fetch_assoc("SELECT description, id, avg_time, cur_time
					FROM host
					WHERE host.id in (" . $allowed_hosts . ")
					AND disabled != 'on'
					ORDER BY avg_time desc
					LIMIT 5");
			
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

					$result['data'] .= $row;
				}

				$result['data'] = '<table>' . $result['data'] . '</table>';
			} else {	// no data
				$result['data'] = __('Waiting for data', 'intropage');
			}
	    	}
	    
	    	db_execute("REPLACE INTO plugin_intropage_panel_data (id,panel_id,user_id,data,alarm) 
			    VALUES (" . $id . ",'top5_ping'," . $user['id'] . ",
			    '" . $result['data'] . "','" . $result['alarm'] . "')");
		} // konec smycky pres vsechny uzivatele
	}

	if ($display)    {
	        $result = db_fetch_row ("SELECT id, data, alarm, last_update FROM plugin_intropage_panel_data 
	    				    WHERE panel_id='top5_ping'"); 

		$result['recheck'] = db_fetch_cell("SELECT concat(
			floor(TIME_FORMAT(SEC_TO_TIME(refresh_interval), '%H') / 24), 'd ',
			MOD(TIME_FORMAT(SEC_TO_TIME(refresh_interval), '%H'), 24), 'h:',
			TIME_FORMAT(SEC_TO_TIME(refresh_interval), '%im'))
			FROM plugin_intropage_panel_definition
			WHERE panel_id='top5_ping'");

		$result['name'] = 'Top5 ping';

	        return $result;
	}
}


//------------------------------------ cpuload -----------------------------------------------------
function cpuload($display=false, $update=false, $force_update=false) {
        global $config;

        $result = array(
                'name' => __('CPU utilization', 'intropage'),
                'alarm' => 'gray',
                'data' => '',
                'last_update' =>  NULL,

        );
        
        $graph = array ('line' => array(
                        'title' => __('CPU load: ', 'intropage'),
                        'label1' => array(),
                        'data1' => array(),
                ),
        );
        
       $id = db_fetch_cell("SELECT id FROM plugin_intropage_panel_data WHERE 
                                panel_id='cpuload' AND last_update IS NOT NULL");
                                
        if (!$id) {                             
                db_execute("REPLACE INTO plugin_intropage_panel_data (panel_id,user_id,data,alarm,last_update) 
                            VALUES ('cpuload'," . $_SESSION['sess_user_id'] . ",
                            '" . __('Waiting for data', 'intropage') . "','gray',1000)");

                $id = db_fetch_insert_id();
        }

        $last_update = db_fetch_cell("SELECT unix_timestamp(last_update) FROM plugin_intropage_panel_data
                                        WHERE user_id=" . $_SESSION['sess_user_id'] . 
                                        " and panel_id='cpuload'");

        $update_interval = db_fetch_cell("SELECT refresh_interval FROM plugin_intropage_panel_definition
                                        WHERE panel_id='cpuload'");

        if ( $force_update || time() > ($last_update + $update_interval))       {

		if ($_SESSION['sess_user_id'] == 0)	{ // update in poller

        		if (!stristr(PHP_OS, 'win')) {
                		$load    = sys_getloadavg();
                		$load[0] = round($load[0], 2);

                		db_execute_prepared("REPLACE INTO plugin_intropage_trends
                        		(name, value, user_id) VALUES
                        		('cpuload', ?, ?)", array($load[0],0));

			echo db_error();
        		
			}
		}

// puvodni kod
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
                        	$result['data'] = __('Waiting for data3', 'intropage');
                	}
        	}

              	db_execute("REPLACE INTO plugin_intropage_panel_data (id,panel_id,user_id,data,alarm) 
                            VALUES (" . $id . ", 'cpuload'," . $_SESSION['sess_user_id'] . ",
                            '" . $result['data'] . "',
                            '" . $result['alarm'] . "')");
        }

        if ($display)    {
                $result = db_fetch_row ("SELECT id, data, alarm, last_update FROM plugin_intropage_panel_data 
                                            WHERE panel_id='cpuload'"); 

		$result['recheck'] = db_fetch_cell("SELECT concat(
			floor(TIME_FORMAT(SEC_TO_TIME(refresh_interval), '%H') / 24), 'd ',
			MOD(TIME_FORMAT(SEC_TO_TIME(refresh_interval), '%H'), 24), 'h:',
			TIME_FORMAT(SEC_TO_TIME(refresh_interval), '%im'))
			FROM plugin_intropage_panel_definition
			WHERE panel_id='cpuload'");


                $result['name'] = 'CPU utilization';
                return $result;
        }
}


// -------------------------------------ntp-------------------------------------------
function ntp($display=false, $update=false, $force_update=false) {
	global $config;

	$result = array(
		'name' => __('NTP', 'intropage'),
		'alarm' => 'green',
		'data' => '',
		'last_update' =>  NULL,
	);
	
	
	$id = db_fetch_cell("SELECT id FROM plugin_intropage_panel_data WHERE 
				panel_id='ntp' AND last_update IS NOT NULL");
				
	if (!$id) {				
	    db_execute("REPLACE INTO plugin_intropage_panel_data (panel_id,user_id,data,alarm,last_update) 
			    VALUES ('ntp'," . $_SESSION['sess_user_id'] . ",
			    '" . __('Waiting for data', 'intropage') . "','gray',1000)");

	    $id = db_fetch_insert_id();
	}

	

	$last_update = db_fetch_cell("SELECT unix_timestamp(last_update) FROM plugin_intropage_panel_data
					WHERE user_id=" . $_SESSION['sess_user_id'] . 
					" and panel_id='ntp'");

	$update_interval = db_fetch_cell("SELECT refresh_interval FROM plugin_intropage_panel_definition
					WHERE panel_id='ntp'");

        if ( $force_update || time() > ($last_update + $update_interval))       {

  		$ntp_server = read_config_option('intropage_ntp_server');

		if (!preg_match('/^(([a-z0-9]|[a-z0-9][a-z0-9\-]*[a-z0-9])\.)*([a-z0-9]|[a-z0-9][a-z0-9\-]*[a-z])$/i', $ntp_server))    {
                	$result['alarm'] = 'red';
                	$result['data']  = __('Wrong NTP server configured - ' . $ntp_server . '<br/>Please fix it in settings', 'intropage');
        	}
        	elseif (empty($ntp_server)) {
                	$result['alarm'] = 'gray';
                	$result['data']  = __('No NTP server configured', 'intropage');
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

              	db_execute("REPLACE INTO plugin_intropage_panel_data (id,panel_id,user_id,data,alarm) 
                            VALUES (" . $id . ", 'ntp'," . $_SESSION['sess_user_id'] . ",
                            '" . $result['data'] . "',
                            '" . $result['alarm'] . "')");

                            echo db_error();
        }

        if ($display)    {
                $result = db_fetch_row ("SELECT id, data, alarm, last_update FROM plugin_intropage_panel_data 
                                            WHERE panel_id='ntp'"); 

		$result['recheck'] = db_fetch_cell("SELECT concat(
			floor(TIME_FORMAT(SEC_TO_TIME(refresh_interval), '%H') / 24), 'd ',
			MOD(TIME_FORMAT(SEC_TO_TIME(refresh_interval), '%H'), 24), 'h:',
			TIME_FORMAT(SEC_TO_TIME(refresh_interval), '%im'))
			FROM plugin_intropage_panel_definition
			WHERE panel_id='ntp'");


                $result['name'] = 'NTP';
                return $result;
        }
}






//------------------------------------ top5_worst polling -----------------------------------------------------

function top5_worst_polling($display=false, $update=false, $force_update=false) {
	global $config;

	$result = array(
                'name' => __('Top5 worst polling ratio (failed, total, ratio)', 'intropage'),
		'alarm' => 'gray',
		'data' => '',
		'last_update' =>  NULL,
	);


/// !!!!!!!!!!! tady jsem skoncil
/*
        if ($_SESSION['allowed_hosts']) {
                $sql_worst_host = db_fetch_assoc("SELECT id, description, failed_polls, total_polls, failed_polls/total_polls as ratio
                        FROM host
                        WHERE host.id in (" . $_SESSION['allowed_hosts'] . ")
                        AND disabled != 'on'
                        ORDER BY ratio desc
                        LIMIT 5");

                if (cacti_sizeof($sql_worst_host)) {
                        foreach ($sql_worst_host as $host) {
                                if ($console_access) {
                                        $row = '<tr><td class="rpad"><a href="' . html_escape($config['url_path'] . 'host.php?action=edit&id=' . $host['id'])
                                } else {
                                        $row = '<tr><td class="rpad">' . html_escape($host['description']) . '</td>';
                                }

                                $row .= '<td class="rpad texalirig">' . $host['failed_polls'] . '</td>';
                                $row .= '<td class="rpad texalirig">' . $host['total_polls'] . '</td>';
                                $row .= '<td class="rpad texalirig">' . round($host['ratio'], 2) . '</td></tr>';

                                $result['data'] .= $row;
                        }

                        $result['data'] = '<table>' . $result['data'] . '</table>';
                } else {        // no data
                        $result['data'] = __('Waiting for data', 'intropage');
                }
        } else {
            $result['detail'] = FALSE;
            $result['data'] = __('You don\'t have permissions to any hosts', 'intropage');
        }


        return $result;
*/
}
