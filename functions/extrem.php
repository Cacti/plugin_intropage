<?php

function extrem() {
	global $config, $allowed_hosts, $console_access;
	
	$result = array(
		'name' => '24 hour extrem',
		'alarm' => 'green',
		'data' => '',
	);
	
	
	$result['data'] .= "<table><tr><td style=\"padding-right: 2em;\">\n";
	
    // long run poller	
	$result['data'] .= "<strong>Long run poller: </strong>";
        $sql_result = db_fetch_assoc("select date_format(time(date),'%H:%i') as xdate,substring(value,instr(value,':')+1) as xvalue FROM plugin_intropage_trends WHERE name='poller' and date > date_sub(date,interval 1 day) order by xvalue desc, date  limit 5");
	foreach($sql_result as $row) {
            $result['data'] .=  "<br/>" . $row['xdate'] . " " . $row['xvalue'] . "s\n";     
	}	
	$result['data'] .="</td><td style=\"padding-right: 2em;\">\n";
	
    // max host down
	$result['data'] .= "<strong>Max host down: </strong>";
        $sql_result = db_fetch_assoc("select date_format(time(date),'%H:%i') as xdate,value FROM plugin_intropage_trends WHERE name='host' and date > date_sub(date,interval 1 day) order by value desc,date limit 5");
	foreach($sql_result as $row) {
            $result['data'] .=  "<br/>" . $row['xdate'] . " " . $row['value'] . "\n";     
	}	
	$result['data'] .="</td><td style=\"padding-right: 2em;\">\n";
	
    // max thold trig
	$result['data'] .= "<strong>Max thold triggered: </strong>";
        $sql_result = db_fetch_assoc("select date_format(time(date),'%H:%i') as xdate,value FROM plugin_intropage_trends WHERE name='thold' and date > date_sub(date,interval 1 day) order by value desc,date limit 5");
	foreach($sql_result as $row) {
            $result['data'] .=  "<br/>" . $row['xdate'] . " " . $row['value'] . "\n";     

	}	
	$result['data'] .="</td></tr>\n";

	$result['data'] .= "</table>\n";

	
	return $result;
}
?>
