<?php

function poller_info() {
	global $config, $sql_where;
	
	$result = array(
		'name' => 'Poller info',
		'alarm' => 'green',
	);
/*
        0 => '<div class="deviceUnknown">'    . __('New/Idle')     . '</div>',
        1 => '<div class="deviceUp">'         . __('Running')      . '</div>',
        2 => '<div class="deviceRecovering">' . __('Idle')         . '</div>',
        3 => '<div class="deviceDown">'       . __('Unknown/Down') . '</div>',
        4 => '<div class="deviceDisabled">'   . __('Disabled')     . '</div>',
        5 => '<div class="deviceDown">'       . __('Recovering')   . '</div>'
*/
        $sql_pollers = db_fetch_assoc("SELECT id,status,last_update FROM poller ORDER BY id");

	$count = count($sql_pollers);
	$ok = 0;
	$running = false;
        if (sizeof($sql_pollers)) {
                foreach ($sql_pollers as $poller) {
                        if ($poller['status'] == 1 || $poller['status'] == 2 || $poller_status['5']) 
                                $ok++;
                        if ($poller['status'] == 1)
                    	    $running = true;
                                
		}	
	}


    	$result['data'] = "<span class=\"txt_big\">$ok</span>(ok)<span class=\"txt_big\">/$count</span>(all)</span><br/><br/>";
    	if ($running)
    	    $result['data'] .= "Poller is running now.<br/>";
    	else	{
    	    $result['data'] .= "Poller isn't running now.<br/>";
    	    $rozdil = db_fetch_cell("SELECT time_to_sec(max(timediff(end_time,start_time))) from poller_time");
    	    $result['data'] .= "Last poller time $rozdil seconds."; 

	}

        if ($count > $ok) {
                $result['alarm'] = "red";
                $result['data'] .= "Not all pollers are ok";
        }
        else	{
    	    $result['alarm'] = "green";
        }


	
	

	return $result;
}



function poller_stat() {
	global $config, $log;
	
	$poller_interval = read_config_option("poller_interval");
	$result = array(
		'name' => "Poller stats (interval ".$poller_interval."s)",
		'alarm' => 'green',
		'data' => '',
                'bar' => array(
                        'title1' => 'Poller time',
                        'label1' => array(),
                        'data1' => array(),
                        'title2' => 'Average',
                        'label2' => array(),
                        'data2' => array(),

		),	
	);
	

	$avg = db_fetch_cell("SELECT floor(avg(value)) FROM (select value from plugin_intropage_trends where name='poller'  order by date desc limit 10) prumery");

	$pollers_time = db_fetch_assoc("SELECT date_format(time(date),'%H:%i') as xdate,value FROM plugin_intropage_trends where name='poller'  order by date desc limit 10");
	foreach($pollers_time as $time) {
	    if ($time['value'] > $poller_interval)	{
		$result['data'] .= '<b>' . $time['xdate'] . " " . $time['value'] . 's</b><br/>';
		$result['alarm'] = "red";
	    }
	    else
		$result['data'] .= $time['xdate'] . ' ' . $time['value'] . 's<br/>';
		
	    // graph data
            array_push($result['bar']['label1'],$time['xdate']);
            array_push($result['bar']['data1'],$time['value']);
            array_push($result['bar']['data2'],$avg);
            
	}

	$result['bar']['label1'] = array_reverse ($result['bar']['label1']);
	$result['bar']['data1'] = array_reverse ($result['bar']['data1']);
	$result['bar']['data2'] = array_reverse ($result['bar']['data2']);


	if (count($pollers_time) < 3)	{
	    $result['data'] = "Waiting for data";
	    unset ($result['bar']);
	}
	else
	    $result['data'] .= "<br/>Average $avg s";

	/*
	$pollers_access = (db_fetch_assoc("select realm_id from user_auth_realm where user_id='" . $_SESSION["sess_user_id"] . "' and user_auth_realm.realm_id=3"))?true:false;
	
	// Check the poller duration through the pollers table
	if (db_table_exists("poller",false)) {
		$pollers_time = db_fetch_assoc("SELECT id, hostname, total_time FROM poller WHERE disabled != 'on'");
		if ($pollers_time) {
			$max_time = 0;
			$mean_time = 0;
			$result['detail'] = '';
			foreach($pollers_time as $time) {
				$result['detail'] .= ($pollers_access) ? 
					sprintf('<a href="%spollers.php?action=edit&amp;id=%s">%s (%s)</a><br/>',htmlspecialchars($config['url_path']),$time['id'],$time['hostname'],$time['total_time']):
					sprintf('%s (%s)<br/>',$time['hostname'],$time['total_time']);
				if ($max_time < $time['total_time']) $max_time = $time['total_time'];
				$mean_time += $time['total_time'];
			}
			$mean_time = $mean_time / count($pollers_time);
			if ($poller_interval/$mean_time < 1.2 || $poller_interval/$max_time < 1.2) {
				$result['alarm'] = "red";
				$result['data'] = "average: ".$mean_time."s | max: ".$max_time."s (Polling is almost reaching the limit)";
			} elseif ($poller_interval/$mean_time < 1.5 || $poller_interval/$max_time < 1.5) {
				$result['alarm'] = "yellow";
				$result['data'] = "average: ".$mean_time."s | max: ".$max_time."s (Polling is close to the limit)";
			} else {
				$result['data'] = "average: ".$mean_time."s | max: ".$max_time."s";
			}
		} else {
			$result['alarm'] = "red";
			$result['data'] = ($pollers_access)?"<a href=\"".htmlspecialchars($config['url_path'])."pollers.php\">No poller servers is active</a>":"No poller servers is active";
		}
	} elseif ($log['size'] && isset($log['lines'])) {
		$stats_lines = preg_grep('/STATS/',$log['lines']);
		if ($stats_lines) {
			$result['detail'] = '';
			$max_time = 0;
			$mean_time = 0;
			$count = 0;
			foreach ($stats_lines as $line) {
				$result['detail'] .= "$line<br/>";
				if (preg_match('/SYSTEM STATS: Time:([0-9.]+)/',$line,$matches)) {
					$count++;
					$mean_time += $matches[1];
					if ($max_time < $matches[1]) $max_time = $matches[1];
				}
			}
			$mean_time = $mean_time / $count;
			if ($poller_interval/$mean_time < 1.2 || $poller_interval/$max_time < 1.2) {
				$result['alarm'] = "red";
				$result['data'] = "average: ".$mean_time."s | max: ".$max_time."s (Polling is almost reaching the limit)";
			} elseif ($poller_interval/$mean_time < 1.5 || $poller_interval/$max_time < 1.5) {
				$result['alarm'] = "yellow";
				$result['data'] = "average: ".$mean_time."s | max: ".$max_time."s (Polling is close to the limit)";
			} else {
				$result['data'] = "average: ".$mean_time."s | max: ".$max_time."s";
			}
		} else {
			$result['alarm'] = "red";
			$result['data'] = "No stats found in the last $nbr_lines of the log file";
		}
	} else {
		$result['alarm'] = "red";
		$result['data'] = "No solution found to retrieve the pollers stats";
	}
	
	
	*/
	return $result;
}



function graph_poller() {
	global $config, $log;

}

?>
