<?php

function get_trend() {
	global $config, $allowed_hosts;
	
	$result = array(
		'name' => 'Trends',
		'alarm' => 'green',
		'data' => '',
		
                'line' => array(
                        'title' => 'Trends: ',
                        'label1' => array(),
                        'data1' => array(),
                        'title1' => "",
                        'data2' => array(),
                        'title2' => "",
                    ),
	);
	
//echo "&#8600;";
//echo "&#8599;";
//echo "&#8594;";

	$sql = db_fetch_assoc("SELECT date_format(time(date),'%H:%i') as xdate,name,value FROM plugin_intropage_trends where name='thold' order by date desc limit 10");
        if (count($sql)) {
    	    $result['line']['title1'] = "Tholds triggered";
            foreach($sql as $row) {
		// no gd data
                $result['data'] .= $row['xdate'] . " " . $row['name'] ." " . $row['value']. "<br/>"; 
                array_push($result['line']['label1'],$row['xdate']);
                array_push($result['line']['data1'],$row['value']);

	    }
	}
	else	{
	    unset ($result['line']);
	}
	
	$sql = db_fetch_assoc("SELECT date_format(time(date),'%h:%i') as xdate,name,value FROM plugin_intropage_trends where name='host' order by date desc limit 10");
        if (count($sql)) {
    	    $result['line']['title2'] = "Hosts down";
        
            foreach($sql as $row) {
		// no gd data
                $result['data'] .= $row['xdate'] . " " . $row['name'] ." " . $row['value']. "<br/>"; 
                    array_push($result['line']['data2'],$row['value']);
	    }
	}
	else	{
	    unset ($result['line']);
	}
	
	if (count($sql) < 3)	{
	    unset($result['line']);
	    $result['data'] = "Waiting for data";
	
	}
	else	{
	    $result['line']['data1'] = array_reverse ($result['line']['data1']);
	    $result['line']['data2'] = array_reverse ($result['line']['data2']);

	    $result['line']['label1'] = array_reverse ($result['line']['label1']);
	
	}
	
	return $result;
}

?>