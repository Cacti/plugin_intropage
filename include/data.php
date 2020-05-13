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

//if (!isset($_SESSION['sess_user_id']))	{
if (isset($run_from_poller))	{
	$_SESSION['sess_user_id'] = 0;

}

include_once($config['base_path'] . '/plugins/intropage/include/functions.php');

//------------------------------------ analyse_login -----------------------------------------------------
function analyse_login($display=false, $update=false, $force_update=false) {
	global $config;

	$panel_id = 'analyse_login';

	$result = array(
		'name' => __('Analyze logins', 'intropage'),
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
			    array($panel_id, $_SESSION['sess_user_id'],__('Waiting for data', 'intropage')));

		$id = db_fetch_insert_id();
	}

	$last_update = db_fetch_cell_prepared('SELECT unix_timestamp(last_update) FROM plugin_intropage_panel_data
					WHERE user_id=0 and panel_id= ?',
					array($panel_id));

	$update_interval = db_fetch_cell_prepared('SELECT refresh_interval FROM plugin_intropage_panel_definition
					WHERE panel_id= ?',
					array($panel_id));

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

	    	db_execute_prepared('REPLACE INTO plugin_intropage_panel_data (id,panel_id,user_id,data,alarm) 
			    VALUES ( ?, ?, ?, ?, ?)',
			    array($id,$panel_id,$_SESSION['sess_user_id'],$result['data'],$result['alarm']));
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
		
		
		$result['name'] = 'Analyse login';
	        return $result;
	}
}



//------------------------------------ analyse_log -----------------------------------------------------
function analyse_log($display=false, $update=false, $force_update=false) {
	global $config;
	
	$panel_id = 'analyse_log';

	$result = array(
		'name' => __('Analyze log', 'intropage'),
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
			    array($panel_id, $_SESSION['sess_user_id'],__('Waiting for data', 'intropage')));

	    $id = db_fetch_insert_id();
	}

	$last_update = db_fetch_cell_prepared('SELECT unix_timestamp(last_update) FROM plugin_intropage_panel_data
					WHERE user_id=0 and panel_id= ?',
					array($panel_id));
//echo 'SELECT unix_timestamp(last_update) FROM plugin_intropage_panel_data
//                                        WHERE user_id=' . $_SESSION['sess_user_id'] . " and panel_id='" . $panel_id . "'\n";
//echo db_error();                                        
                                        
	$update_interval = db_fetch_cell_prepared('SELECT refresh_interval FROM plugin_intropage_panel_definition
					WHERE panel_id= ?',
					array($panel_id));


//echo "        if ( $force_update || ".  time() . " > (" , $last_update . " + " . $update_interval . ")) \n ";


        if ( $force_update || time() > ($last_update + $update_interval))       {
//echo "Jsem in\n";
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

		db_execute_prepared('REPLACE INTO plugin_intropage_panel_data (id,panel_id,user_id,data,alarm) 
		    	VALUES ( ?, ?, ?, ?, ?)',
			    array($id,$panel_id,$_SESSION['sess_user_id'],$result['data'],$result['alarm']));

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

		$result['name'] = 'Analyse log';

	        return $result;
	}
}


//------------------------------------ top5_ping -----------------------------------------------------
function top5_ping($display=false, $update=false, $force_update=false) {
	global $config;

	$panel_id = 'top5_ping';

	
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
				'name' => __('Top5 ping', 'intropage'),
				'alarm' => 'green',
				'data' => '',
				'last_update' =>  NULL,
			);


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
/*	    it returns only one row
				$sql_worst_host = db_fetch_assoc_prepared("SELECT description, id, avg_time, cur_time
					FROM host
					WHERE host.id in ( ? )
					AND disabled != 'on'
					ORDER BY avg_time desc
					LIMIT 5",
					array($allowed_hosts));
*/
				$sql_worst_host = db_fetch_assoc("SELECT description, id, avg_time, cur_time
					FROM host
					WHERE host.id in ( $allowed_hosts )
					AND disabled != 'on'
					ORDER BY avg_time desc
					LIMIT 5",
					);

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
	    		} else {
            			$result['data'] = __('You don\'t have permissions to any hosts', 'intropage');
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

		$result['name'] = 'Top5 ping';

	        return $result;
	}
}


//------------------------------------ cpuload -----------------------------------------------------
function cpuload($display=false, $update=false, $force_update=false) {
        global $config, $run_from_poller;

	$panel_id = 'cpuload';

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
			    array($panel_id, $_SESSION['sess_user_id'],__('Waiting for data', 'intropage')));

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
			    array($id,$panel_id,$_SESSION['sess_user_id'],$result['data'],$result['alarm']));


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

                $result['name'] = 'CPU utilization';
                return $result;
        }
}


// -------------------------------------ntp-------------------------------------------
function ntp($display=false, $update=false, $force_update=false) {
	global $config;

	$panel_id = 'ntp';

	$result = array(
		'name' => __('NTP', 'intropage'),
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
			    array($panel_id, $_SESSION['sess_user_id'],__('Waiting for data', 'intropage')));

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

	    	db_execute_prepared('REPLACE INTO plugin_intropage_panel_data (id,panel_id,user_id,data,alarm) 
			    VALUES (?,?,?,?,?)',
			    array($id,$panel_id,$_SESSION['sess_user_id'],$result['data'],$result['alarm']));
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

                $result['name'] = 'NTP';
                return $result;
        }
}


// ------------------------- graph data source---------------------
function graph_data_source($display=false, $update=false, $force_update=false) {
        global $config, $input_types, $run_from_poller;

	$panel_id = 'graph_data_source';
                            
        $result = array(
                'name' => 'Data sources',
                'alarm' => 'gray',
                'data' => '',
		'last_update' => NULL,
        );

        $graph = array ('pie' => array(
                        'title' => __('Datasources: ', 'intropage'),
                        'label' => array(),
                        'data' => array(),
                ),
	);

        $id = db_fetch_cell_prepared('SELECT id FROM plugin_intropage_panel_data WHERE 
                                panel_id= ? AND last_update IS NOT NULL',
                                array($panel_id));
                                
        if (!$id) {                             
            db_execute_prepared('REPLACE INTO plugin_intropage_panel_data (panel_id,user_id,data,alarm,last_update) 
                            VALUES ( ?, ?, ?, "gray", 1000)',
                            array($panel_id, $_SESSION['sess_user_id'],__('Waiting for data', 'intropage')));

            $id = db_fetch_insert_id();
        }

        $last_update = db_fetch_cell_prepared('SELECT unix_timestamp(last_update) FROM plugin_intropage_panel_data
                                        WHERE user_id=0 and panel_id= ?',
                                        array($panel_id));
                                        
        $update_interval = db_fetch_cell_prepared('SELECT refresh_interval FROM plugin_intropage_panel_definition
                                        WHERE panel_id= ?',
                                        array($panel_id));

        if ( $force_update || time() > ($last_update + $update_interval))       {

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
                                	array_push($graph['pie']['label'], preg_replace('/script server/', 'SS', $input_types[$item['type_id']]));
                                	array_push($graph['pie']['data'], $item['total']);

                                	$result['data'] .= preg_replace('/script server/', 'SS', $input_types[$item['type_id']]) . ': ';
                                	$result['data'] .= $item['total'] . '<br/>';
                        	}
                	}
                       	$result['data'] = intropage_prepare_graph($graph);
	
        	} else {
                	$result['data'] = __('No untemplated datasources found');
                	unset($graph);
        	}

		db_execute_prepared('REPLACE INTO plugin_intropage_panel_data (id,panel_id,user_id,data,alarm) 
			VALUES ( ?, ?, ?, ?, ?)',
			array($id,$panel_id,$_SESSION['sess_user_id'],$result['data'],$result['alarm']));

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

                $result['name'] = 'Graph data sources';
                return $result;
        }
}



// -----------------------graph_host template--------------------
function graph_host_template($display=false, $update=false, $force_update=false) {
	global $config;

	$panel_id = 'graph_host_template';


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
				'name' => __('Host templates', 'intropage'),
				'alarm' => 'gray',
				'data' => '',
				'last_update' =>  NULL,
			);



	    		$x = 0;	// reference
			//get_allowed_devices($sql_where = '', $order_by = 'description', $limit = '', &$total_rows = 0, $user = 0, $host_id = 0)
			$allowed =  get_allowed_devices('','description',-1,$x,$user['id']); 

	    		if (count($allowed) > 0) {
                		$allowed_hosts = implode(',', array_column($allowed, 'id'));
    	    		} else {
                		$allowed_hosts = false;
    	    		}

	    		if ($allowed_hosts)	{
        			$graph = array ('pie' => array(
                        		'title' => __('Host templates: ', 'intropage'),
                        		'label' => array(),
                        		'data' => array(),
                			),
				);

                		$sql_ht = db_fetch_assoc("SELECT host_template.id as id, name, 
                			count(host.host_template_id) AS total
                        		FROM host_template LEFT JOIN host
                        		ON (host_template.id = host.host_template_id) AND host.id IN ( $allowed_hosts )
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
	    
	} // konec smycky pres vsechny uzivatele
	

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

		$result['name'] = 'Host templates';

	        return $result;
	}
}



//---------------------------------------graph host-----------------------------

function graph_host($display=false, $update=false, $force_update=false) {
        global $config;

	$panel_id = 'graph_host';



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
				'name' => __('Hosts', 'intropage'),
				'alarm' => 'green',
				'data' => '',
				'last_update' =>  NULL,
			);


	    		$x = 0;	// reference
			//get_allowed_devices($sql_where = '', $order_by = 'description', $limit = '', &$total_rows = 0, $user = 0, $host_id = 0)
			$allowed =  get_allowed_devices('','description',-1,$x,$user['id']); 

	    		if (count($allowed) > 0) {
                		$allowed_hosts = implode(',', array_column($allowed, 'id'));
    	    		} else {
                		$allowed_hosts = false;
    	    		}


        		if ($allowed_hosts) {
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


                		$h_all  = db_fetch_cell('SELECT count(id) FROM host WHERE id IN (' . $allowed_hosts . ')');
                		$h_up   = db_fetch_cell('SELECT count(id) FROM host WHERE id IN (' . $allowed_hosts . ') AND status=3 AND disabled=""');
                		$h_down = db_fetch_cell('SELECT count(id) FROM host WHERE id IN (' . $allowed_hosts . ') AND status=1 AND disabled=""');
                		$h_reco = db_fetch_cell('SELECT count(id) FROM host WHERE id IN (' . $allowed_hosts . ') AND status=2 AND disabled=""');
                		$h_disa = db_fetch_cell('SELECT count(id) FROM host WHERE id IN (' . $allowed_hosts . ') AND disabled="on"');

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
	} // konec smycky pres vsechny uzivatele

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

		$result['name'] = 'Hosts';

	        return $result;
	}

}



//------------------------- info-------------------------
function info($display=false, $update=false, $force_update=false) {
        global $config, $poller_options;

//!!!! mam poller_options?

	$panel_id = 'info';

	$result = array(
		'name' => __('Info', 'intropage'),
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
                            array($panel_id, $_SESSION['sess_user_id'],__('Waiting for data', 'intropage')));

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
			array($id,$panel_id,$_SESSION['sess_user_id'],$result['data'],$result['alarm']));

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

                $result['name'] = 'Info';
                return $result;
        }
}


// -------------------------------------analyse db-------------------------------------------
function analyse_db($display=false, $update=false, $force_update=false) {
	global $config;

	$panel_id = 'analyse_db';

	$result = array(
		'name' => __('Analyse Database', 'intropage'),
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
			    array($panel_id, $_SESSION['sess_user_id'],__('Waiting for data', 'intropage')));

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
                		$result['data'] .= '<span class="txt_big">' . __('DB problem', 'intropage') . '</span><br/><br/>';
        		} else {
                		$result['data'] .= '<span class="txt_big">' . __('DB OK', 'intropage') . '</span><br/><br/>';
        		}
                
        		// connection errors
        		$cerrors = 0;
        		$con_err = db_fetch_assoc("SHOW GLOBAL STATUS LIKE '%Connection_errors%'");

        		foreach ($con_err as $key => $val) {
                		$cerrors = $cerrors + $val['Value'];
        		}

        		if ($cerrors > 0) {     // only yellow
                		$result['data'] .= __('Connection errors: %s - try to restart SQL service, check SQL log, ...', $cerrors, 'intropage') . '<br/>';

                		if ($result['alarm'] == 'green') {
                        		$result['alarm'] = 'yellow';
                		}
        		}

        		// aborted problems
        		$aerrors = 0;
        		$con_err = db_fetch_assoc("SHOW GLOBAL STATUS LIKE '%Aborted_c%'");

        		foreach ($con_err as $key => $val) {
                		$aerrors = $aerrors + $val['Value'];
        		}

        		if ($aerrors > 0) {     // only yellow
                		$result['data'] .= __('Aborted clients/connects: %s - check logs.', $aerrors, 'intropage') . '<br/>';

                		if ($result['alarm'] == 'green') {
                        		$result['alarm'] = 'yellow';
                		}
        		}

        		$result['data'] .= __('Connection errors: %s', $cerrors, 'intropage') . '<br/>';
        		$result['data'] .= __('Aborted clients/connects: %s', $aerrors, 'intropage') . '<br/>';
        		$result['data'] .= __('Damaged tables: %s', $damaged, 'intropage') . '<br/>' .
                		__('Memory tables: %s', $memtables, 'intropage') . '<br/>' .
               			__('All tables: %s', count($tables), 'intropage');

	    		db_execute_prepared('REPLACE INTO plugin_intropage_panel_data (id,panel_id,user_id,data,alarm) 
			    	VALUES (?,?,?,?,?)',
			    	array($id,$panel_id,$_SESSION['sess_user_id'],$result['data'],$result['alarm']));
	}


        if ($display)    {
	        $result = db_fetch_row_prepared('SELECT id, data, alarm, last_update FROM plugin_intropage_panel_data 
	    				    WHERE panel_id= ?',
	    				    array($panel_id)); 

		if ($update_interval == 0)	{
			$result['recheck'] = __('Scheduled db check disabled','intropage');
		}
		else {
			$result['recheck'] = "Every " . $update_interval/3600 . "h";
		}
		
                $result['name'] = 'Database check';
                return $result;
        }
}


//---------------------------maint plugin--------------------
function maint($display=false, $update=false, $force_update=false) {
	global $config;

	$panel_id = 'maint';

	$result = array(
		'name' => __('Maintenance plugin', 'intropage'),
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
			    array($panel_id, $_SESSION['sess_user_id'],__('Waiting for data', 'intropage')));

	    $id = db_fetch_insert_id();
	}

	$last_update = db_fetch_cell_prepared('SELECT unix_timestamp(last_update) FROM plugin_intropage_panel_data
					WHERE user_id=0 and panel_id= ?',
					array($panel_id));
                                        
	$update_interval = read_config_option('intropage_analyse_db_interval');

        if ($force_update || time() > ($last_update + $update_interval))       {

        	$maint_days_before = read_config_option('intropage_maint_plugin_days_before');

        	$schedules = db_fetch_assoc("SELECT * FROM plugin_maint_schedules WHERE enabled='on'");
        	if (cacti_sizeof($schedules)) {
                	foreach ($schedules as $sc) {
                        	$t = time();

                        	switch ($sc['mtype']) {
                                	case 1:
                                        	if ($t > ($sc['stime'] - $maint_days_before) && $t < $sc['etime']) {
                                                	$result['data'] .= '<b>' . date('d. m . Y  H:i', $sc['stime']) . ' - ' . date('d. m . Y  H:i', $sc['etime']) .
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
                                                	$result['data'] = substr($result['data'], 0, -2) .'<br/><br/>';
                                        	}
                                        	break;

                                	case 2:
                                        	while ($sc['etime'] < $t) {
                                                	$sc['etime'] += $sc['minterval'];
                                                	$sc['stime'] += $sc['minterval'];
                                        	}

                                        	if ($t > ($sc['stime'] - $maint_days_before) && $t < $sc['etime']) {
                                                	$result['data'] .= '<b>' . date('d. m . Y  H:i', $sc['stime']) . ' - ' . date('d. m . Y  H:i', $sc['etime']) .
                                                        	' - ' . $sc['name'] . ' (Reoccurring)<br/>Affected hosts:</b> ';

                                                	$hosts = db_fetch_assoc_prepared('SELECT description FROM host
                                                        	INNER JOIN plugin_maint_hosts
                                                        	ON host.id=plugin_maint_hosts.host
                                                        	WHERE schedule = ?',
                                                        	array($sc['id']));

                                                	if (cacti_sizeof($hosts)) {
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

    		db_execute_prepared('REPLACE INTO plugin_intropage_panel_data (id,panel_id,user_id,data,alarm) 
			    	VALUES (?,?,?,?,?)',
			    	array($id,$panel_id,$_SESSION['sess_user_id'],$result['data'],$result['alarm']));
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

                $result['name'] = 'Maint plugin';
                return $result;
        }

}




//---------------------------admin alert--------------------
function admin_alert($display=false, $update=false, $force_update=false) {
	global $config;

	$panel_id = 'admin_alert';

	$result = array(
		'name' => __('Admin alert', 'intropage'),
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
			    array($panel_id, $_SESSION['sess_user_id'],__('Waiting for data', 'intropage')));

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
			    	array($id,$panel_id,$_SESSION['sess_user_id'],$result['data'],$result['alarm']));
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

                $result['name'] = 'Admin alert';
                return $result;
        }

}




//------------------------------------ trends -----------------------------------------------------
function trend($display=false, $update=false, $force_update=false) {
	global $config;

	$panel_id = 'trend';

	$result = array(
		'name' => __('Trends', 'intropage'),
		'alarm' => 'green',
		'data' => '',
		'last_update' =>  NULL,
	);
	
	if (isset($run_from_poller))	{ // update in poller
                db_execute("REPLACE INTO plugin_intropage_trends
                        (name,value)
                        SELECT 'thold', COUNT(*)
                        FROM thold_data
                        WHERE thold_data.thold_alert!=0
                        OR thold_data.bl_fail_count >= thold_data.bl_fail_trigger");
                        
        	db_execute("REPLACE INTO plugin_intropage_trends
                	(name, value)
                	SELECT 'host', COUNT(id)
                	FROM host
                	WHERE status='1' AND disabled=''");
	}


	$id = db_fetch_cell_prepared('SELECT id FROM plugin_intropage_panel_data WHERE 
				panel_id=? AND last_update IS NOT NULL',
				array($panel_id));

	if (!$id) {			
		db_execute_prepared('REPLACE INTO plugin_intropage_panel_data (panel_id,user_id,data,alarm,last_update) 
			    VALUES (?, ?, ?,"gray",1000)',
			    array($panel_id, $_SESSION['sess_user_id'],__('Waiting for data', 'intropage')));

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
                        	'title' => __('Trends: ', 'intropage'),
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
                        	$graph['line']['title1'] = __('Tholds triggered', 'intropage');
                        	foreach ($sql as $row) {
                                	array_push($graph['line']['label1'], $row['date']);
                                	array_push($graph['line']['data1'], $row['value']);
                        	}
                	}
        	}

        	$sql = db_fetch_assoc("SELECT date_format(time(cur_timestamp),'%h:%i') as `date`, name, value
                	FROM plugin_intropage_trends
                	WHERE name='host'
                	ORDER BY cur_timestamp desc
                	LIMIT 10");

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
			    	array($id,$panel_id,$_SESSION['sess_user_id'],$result['data'],$result['alarm']));
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

                $result['name'] = 'Trends';
                return $result;
	}
}

//------------------------------------ poller info -----------------------------------------------------
function poller_info($display=false, $update=false, $force_update=false) {
	global $config;

	$panel_id = 'poller_info';

	$result = array(
		'name' => __('Poller info', 'intropage'),
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
			    array($panel_id, $_SESSION['sess_user_id'],__('Waiting for data', 'intropage')));

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
			    	array($id,$panel_id,$_SESSION['sess_user_id'],$result['data'],$result['alarm']));
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

                $result['name'] = 'Poller info';
                return $result;
	}
                        
}



//------------------------------------ poller stat -----------------------------------------------------
function poller_stat($display=false, $update=false, $force_update=false) {
	global $config;

	$panel_id = 'poller_stat';

	$result = array(
		'name' => __('Poller stats', 'intropage'),
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
                        	(name, cur_timestamp, value) VALUES
                        	('poller', ?, ?)",
                        	array($stat['start'], $stat['id'] . ':' . round($stat['total_time'])));
        	}
	}


	$id = db_fetch_cell_prepared('SELECT id FROM plugin_intropage_panel_data WHERE 
				panel_id=? AND last_update IS NOT NULL',
				array($panel_id));

	if (!$id) {			
		db_execute_prepared('REPLACE INTO plugin_intropage_panel_data (panel_id,user_id,data,alarm,last_update) 
			    VALUES (?, ?, ?,"gray",1000)',
			    array($panel_id, $_SESSION['sess_user_id'],__('Waiting for data', 'intropage')));

		$id = db_fetch_insert_id();
	}

	$last_update = db_fetch_cell_prepared('SELECT unix_timestamp(last_update) FROM plugin_intropage_panel_data
					WHERE user_id=0 and panel_id= ?',
					array($panel_id));

	$update_interval = db_fetch_cell_prepared('SELECT refresh_interval FROM plugin_intropage_panel_definition
					WHERE panel_id= ?',
					array($panel_id));

	if ( $force_update || time() > ($last_update + $update_interval))	{
//////////////

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



/////////////
    		db_execute_prepared('REPLACE INTO plugin_intropage_panel_data (id,panel_id,user_id,data,alarm) 
			    	VALUES (?,?,?,?,?)',
			    	array($id,$panel_id,$_SESSION['sess_user_id'],$result['data'],$result['alarm']));
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

                $result['name'] = 'Poller stats';
                return $result;
	}
                        
}





// --------------------------------analyse_tree_host_graph
function analyse_tree_host_graph($display=false, $update=false, $force_update=false) {
	global $config;

	$panel_id = 'analyse_tree_host_graph';

	
	if (isset($run_from_poller))	{ // update in poller
	}

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
				'name' => __('Analyze tree/host/graph', 'intropage'),
				'alarm' => 'green',
				'data' => '',
				'last_update' =>  NULL,
			);

	    		$x = 0;	// reference
			//get_allowed_devices($sql_where = '', $order_by = 'description', $limit = '', &$total_rows = 0, $user = 0, $host_id = 0)
			$allowed =  get_allowed_devices('','description',-1,$x,$user['id']); 

	    		if (count($allowed) > 0) {
                		$allowed_hosts = implode(',', array_column($allowed, 'id'));
    	    		} else {
                		$allowed_hosts = false;
    	    		}

//	    		if ($allowed_hosts)	{


        		$total_errors = 0;

        		// hosts with same IP
        		if ($allowed_hosts) {
                		$sql_result = db_fetch_assoc("SELECT COUNT(*) AS NoDups, id, hostname
                        		FROM host
                        		WHERE id IN (" . $allowed_hosts . ")
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
        		if ($allowed_hosts) {
                		$sql_result = db_fetch_assoc("SELECT COUNT(*) AS NoDups, description
                        		FROM host
					WHERE id IN (" . $allowed_hosts . ")
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
        		if ($allowed_hosts) {
                		$sql_result = db_fetch_assoc('SELECT host.id, host.description, COUNT(*) AS `count`
                        		FROM host
                        		INNER JOIN graph_tree_items
                        		ON (host.id = graph_tree_items.host_id)
                        		WHERE host.id IN (' . $allowed_hosts . ')
                        		GROUP BY description
                        		HAVING `count` > 1');

                		$sql_count  = ($sql_result === false) ? __('N/A', 'intropage') : count($sql_result);

                		if (cacti_sizeof($sql_result)) {
                        		$result['data'] .= __('Devices in more than one tree: %s', $sql_count, 'intropage') . '<br/>';
                		}
        		}

        		// host without graph
        		if ($allowed_hosts) {
                		$sql_result = db_fetch_assoc("SELECT id, description
                        		FROM host
                        		WHERE id IN (" . $allowed_hosts . ")
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
        		if ($allowed_hosts) {
                		$sql_result = db_fetch_assoc("SELECT id, description
                        		FROM host
                        		WHERE id IN (" . $allowed_hosts . ")
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
        		if ($allowed_hosts) {
                		$sql_result = db_fetch_assoc("SELECT id, description
                        		FROM host
                        		WHERE id IN (" . $allowed_hosts . ")
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
                		if ($allowed_hosts) {
                        		$sql_result = db_fetch_assoc("SELECT id, description, hostname
                                		FROM host
                                		WHERE id IN (" . $allowed_hosts . ")
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


	    		db_execute_prepared('REPLACE INTO plugin_intropage_panel_data (id,panel_id,user_id,data,alarm) 
				VALUES ( ?, ?, ?, ?, ?)',
			    	array($id,$panel_id,$user['id'],$result['data'],$result['alarm']));
			    	
//	    		db_execute("REPLACE INTO plugin_intropage_panel_data (id,panel_id,user_id,data,alarm) 
//				VALUES (" . $id . ",'" . $panel_id . "'," . $user['id'] . ",'" . $result['data'] . "','" . $result['alarm'] . "')");
//echo db_error();
//echo "\nREPLACE INTO plugin_intropage_panel_data (id,panel_id,user_id,data,alarm) 
//				VALUES (" . $id . ",'" . $panel_id . "'," . $user['id'] . ",'" . $result['data'] . "','" . $result['alarm'] . "')\n";
		}
	} // konec smycky pres vsechny uzivatele

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

                $result['name'] = 'Analyse tree/host/graph';

                return $result;
	}
                        
}



//------------------------------------ top5_availability -----------------------------------------------------
function top5_availability($display=false, $update=false, $force_update=false) {
	global $config;

	$panel_id = 'top5_availability';
	
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
				'name' => __('Top5 availability', 'intropage'),
				'alarm' => 'green',
				'data' => '',
				'last_update' =>  NULL,
			);


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
/////////
                		$sql_worst_host = db_fetch_assoc("SELECT description, id, availability
                        		FROM host
                        		WHERE host.id IN (" . $allowed_hosts . ")
                        		AND disabled != 'on'
                        		ORDER BY availability
                        		LIMIT 5");

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

                                		$result['data'] .= $row;
                        		}
                        		$result['data'] = '<table>' . $result['data'] . '</table>';

                		} else {        // no data
                        		$result['data'] = __('Waiting for data', 'intropage');
                		}
        		} else {
            			$result['data'] = __('You don\'t have permissions to any hosts', 'intropage');
        		}

///////

	    
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

		$result['name'] = 'Top5 worst availability';

	        return $result;
	}
}


//------------------------------------ top5_polltime -----------------------------------------------------
function top5_polltime($display=false, $update=false, $force_update=false) {
	global $config;

	$panel_id = 'top5_polltime';
	
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
				'name' => __('Top5 worst polling time', 'intropage'),
				'alarm' => 'green',
				'data' => '',
				'last_update' =>  NULL,
			);


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
/////////
		                $sql_worst_host = db_fetch_assoc("SELECT id, description, polling_time
                		        FROM host
                        		WHERE host.id in (" . $allowed_hosts . ")
                        		AND disabled != 'on'
                        		ORDER BY polling_time desc
                        		LIMIT 5");

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

                                		$result['data'] .= $row;
                        		}

                        		$result['data'] = '<table>' . $result['data'] . '</table>';
                		} else {        // no data
                        		$result['data'] = __('Waiting for data', 'intropage');
                		}
        		} else {
            			$result['data'] = __('You don\'t have permissions to any hosts', 'intropage');
        		}

///////
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

		$result['name'] = 'Top5 worst polling time';

	        return $result;
	}
}




//------------------------------------ top5_pollratio -----------------------------------------------------
function top5_pollratio($display=false, $update=false, $force_update=false) {
	global $config;

	$panel_id = 'top5_pollratio';
	
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
				'name' => __('Top5 worst polling ratio (failed, total, ratio)', 'intropage'),
				'alarm' => 'green',
				'data' => '',
				'last_update' =>  NULL,
			);


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
/////////
                		$sql_worst_host = db_fetch_assoc("SELECT id, description, failed_polls, total_polls, failed_polls/total_polls as ratio
                        		FROM host
                        		WHERE host.id in (" . $allowed_hosts . ")
                        		AND disabled != 'on'
                        		ORDER BY ratio desc
                       	 		LIMIT 5");

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

                                		$result['data'] .= $row;
                        		}

                        		$result['data'] = '<table>' . $result['data'] . '</table>';
                		} else {        // no data
                        		$result['data'] = __('Waiting for data', 'intropage');
                		}
        		} else {
            			$result['data'] = __('You don\'t have permissions to any hosts', 'intropage');
        		}

///////
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

		$result['name'] = 'Top5 worst polling ratio (failed, total, ratio)';

	        return $result;
	}
}


