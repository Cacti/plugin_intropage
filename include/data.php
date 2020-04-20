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


//------------------------------------ analyse_login -----------------------------------------------------
function intropage_analyse_login($display=false, $update=false, $force_update=false) {
	global $config;

	$result = array(
		'name' => __('Analyze logins', 'intropage'),
		'alarm' => 'green',
		'data' => '',
		'last_update' =>  NULL,
	);
	
	// for all users
	if (db_fetch_cell("SELECT count(*) FROM plugin_intropage_panel_data WHERE 
				panel_id='analyse_login' AND
				last_update IS NOT NULL") == 0) {
	    db_execute("REPLACE INTO plugin_intropage_panel_data (panel_id,user_id,data,detail,alarm) 
			    VALUES ('analyse_login'," . $_SESSION['sess_user_id'] . ",
			    '" . __('Waiting for data', 'intropage') . "',
			    '" . __('Waiting for data', 'intropage') . "','gray')");
	}

	$last_update = db_fetch_cell("SELECT last_update FROM plugin_intropage_panel_data
					WHERE user_id=" . $_SESSION['sess_user_id'] . 
					" and panel_id='analyse_login'");

	$update_interval = db_fetch_cell("SELECT refresh_interval FROM plugin_intropage_panel_definition
					WHERE panel_id='analyse_login'");

	if ($force_update || time() > $last_update + $update_interval)	{
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
		FROM user_log
		WHERE time > adddate(now(), INTERVAL -1 HOUR) LIMIT 10');

	    if (cacti_sizeof($sql_result)) {
		foreach ($sql_result as $row) {
			$result['data'] .= $row['username'] . '<br/>';
		}
	    }

	    db_execute("REPLACE INTO plugin_intropage_panel_data (panel_id,user_id,data,detail,alarm) 
			    VALUES ('analyse_login'," . $_SESSION['sess_user_id'] . ",
			    '" . $result['data'] . "',
			    '" . __('missing!!', 'intropage') . "','" . $result['alarm'] . "')");
	}

	if ($display)    {
	        $result = db_fetch_row ("SELECT data, detail, alarm, last_update FROM plugin_intropage_panel_data 
	    				    WHERE panel_id='analyse_login'"); 

		$result['name'] = 'Analyse login';

	        return $result;
	}
}



//------------------------------------ analyse_log -----------------------------------------------------
function intropage_analyse_login($display=false, $update=false, $force_update=false) {
	global $config;

	$result = array(
		'name' => __('Analyze logins', 'intropage'),
		'alarm' => 'green',
		'data' => '',
		'last_update' =>  NULL,
	);
	
	// for all users
	if (db_fetch_cell("SELECT count(*) FROM plugin_intropage_panel_data WHERE 
				panel_id='analyse_login' AND
				last_update IS NOT NULL") == 0) {
	    db_execute("REPLACE INTO plugin_intropage_panel_data (panel_id,user_id,data,detail,alarm) 
			    VALUES ('analyse_login'," . $_SESSION['sess_user_id'] . ",
			    '" . __('Waiting for data', 'intropage') . "',
			    '" . __('Waiting for data', 'intropage') . "','gray')");
	}

	$last_update = db_fetch_cell("SELECT last_update FROM plugin_intropage_panel_data
					WHERE user_id=" . $_SESSION['sess_user_id'] . 
					" and panel_id='analyse_login'");

	$update_interval = db_fetch_cell("SELECT refresh_interval FROM plugin_intropage_panel_definition
					WHERE panel_id='analyse_login'");

	if ($force_update || time() > $last_update + $update_interval)	{

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



	    db_execute("REPLACE INTO plugin_intropage_panel_data (panel_id,user_id,data,detail,alarm) 
			    VALUES ('analyse_log'," . $_SESSION['sess_user_id'] . ",
			    '" . $result['data'] . "',
			    '" . __('missing!!', 'intropage') . "','" . $result['alarm'] . "')");
	}

	if ($display)    {
	        $result = db_fetch_row ("SELECT data, detail, alarm, last_update FROM plugin_intropage_panel_data 
	    				    WHERE panel_id='analyse_log'"); 

		$result['name'] = 'Analyse log';

	        return $result;
	}
}





//------------------------------------ top5_ping -----------------------------------------------------
function intropage_analyse_login($display=false, $update=false, $force_update=false) {
	global $config;

	$result = array(
		'name' => __('Analyze logins', 'intropage'),
		'alarm' => 'green',
		'data' => '',
		'last_update' =>  NULL,
	);
	
	// for all users
	if (db_fetch_cell("SELECT count(*) FROM plugin_intropage_panel_data WHERE 
				panel_id='analyse_login' AND
				last_update IS NOT NULL") == 0) {
	    db_execute("REPLACE INTO plugin_intropage_panel_data (panel_id,user_id,data,detail,alarm) 
			    VALUES ('analyse_login'," . $_SESSION['sess_user_id'] . ",
			    '" . __('Waiting for data', 'intropage') . "',
			    '" . __('Waiting for data', 'intropage') . "','gray')");
	}

	$last_update = db_fetch_cell("SELECT last_update FROM plugin_intropage_panel_data
					WHERE user_id=" . $_SESSION['sess_user_id'] . 
					" and panel_id='analyse_login'");

	$update_interval = db_fetch_cell("SELECT refresh_interval FROM plugin_intropage_panel_definition
					WHERE panel_id='analyse_login'");

	if ($force_update || time() > $last_update + $update_interval)	{

// tady budes smycka pres vsechny uzivatele
/////

	$users = db_fetch_assoc("SELECT id FROM user_auth WHERE enabled='on'");
	foreach ($users as $user)	{

	    $allowed =  get_allowed_devices('','description','',$user['id']); 
	    if (count($allowed) > 0) {
                $allowed_hosts = implode(',', array_column($allowed, 'id'));
    	    } else {
                $allowed_hosts = false;
    	    }

	    if ($_SESSION['allowed_hosts'])	{
		$sql_worst_host = db_fetch_assoc("SELECT description, id, avg_time, cur_time
			FROM host
			WHERE host.id in (" . $_SESSION['allowed_hosts'] . ")
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

	} // konec smycky pres vsechny uzivatele


	    db_execute("REPLACE INTO plugin_intropage_panel_data (panel_id,user_id,data,detail,alarm) 
			    VALUES ('analyse_login'," . $_SESSION['sess_user_id'] . ",
			    '" . $result['data'] . "',
			    '" . __('missing!!', 'intropage') . "','" . $result['alarm'] . "')");
	}

	if ($display)    {
	        $result = db_fetch_row ("SELECT data, detail, alarm, last_update FROM plugin_intropage_panel_data 
	    				    WHERE panel_id='analyse_login'"); 

		$result['name'] = 'Analyse login';

	        return $result;
	}


}



