<?php

function top5_ping() {
	global $config, $allowed_hosts, $console_access;
	
	$result = array(
		'name' => 'Top5 ping (avg, current)',
		'alarm' => 'green',
	);
	
	
	$result['data'] = "<table>";
        $sql_worst_host = db_fetch_assoc("SELECT description, id , avg_time, cur_time FROM host where host.id in ($allowed_hosts) order by avg_time desc limit 5");
	foreach($sql_worst_host as $host) {
            if ($console_access)  
        	$result['data'] .= "<td style=\"padding-right: 2em;\"><a href=\"".htmlspecialchars($config['url_path'])."host.php?action=edit&amp;id=".$host['id']."\">".$host['description']."</a>";
            else  
        	$result['data'] .=  "<td style=\"padding-right: 2em;\">".$host['description']."</td>\n"; 
    
	    $result['data'] .= "<td style=\"padding-right: 2em; text-align: right;\">" . round($host['avg_time'],2) . "ms</td>\n";
	
	    if ($host['cur_time'] > 1000)	{
		$result['alarm'] = "yellow";
        	$result['data'] .= "<td style=\"padding-right: 2em; text-align: right;\"><b>" . round($host['cur_time'],2) . "ms</b></td></tr>\n";
	    }
	    else
		$result['data'] .= "<td style=\"padding-right: 2em; text-align: right;\">" . round($host['cur_time'],2) . "ms</td></tr>\n";

		
	}	
	
	$result['data'] .= "</table>\n";
	return $result;
}



function top5_availability() {
	global $config, $allowed_hosts, $console_access;
	
	$result = array(
		'name' => 'Top5 worst availability',
		'alarm' => 'green',
	);
	

	$result['data'] = "<table>";
	 $sql_worst_host = db_fetch_assoc("SELECT description, id, availability FROM host where  host.id in ($allowed_hosts) order by availability  limit 5");

	foreach($sql_worst_host as $host) {
            if ($console_access)  
        	$result['data'] .= "<td style=\"padding-right: 2em;\"><a href=\"".htmlspecialchars($config['url_path'])."host.php?action=edit&amp;id=".$host['id']."\">".$host['description']."</a>";
            else  
        	$result['data'] .=  "<td style=\"padding-right: 2em;\">".$host['description']."</td>\n"; 
    
	    if ($host['availability'] < 90)	{
		$result['alarm'] = "yellow";
        	$result['data'] .= "<td style=\"padding-right: 2em; text-align: right;\"><b>" . round($host['availability'],2) . "%</b></td></tr>\n";
	    }
	    else
        	$result['data'] .= "<td style=\"padding-right: 2em; text-align: right;\">" . round($host['availability'],2) . "%</td></tr>\n";


	}	
	
	$result['data'] .= "</table>\n";
	return $result;

}


?>
