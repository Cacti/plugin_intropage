<?php

function cpu() {
    global $config;
	
    $result = array(
	'name' => 'CPU utilization',
	'alarm' => 'green',
	'data' => '',
        'line' => array(
            'title' => 'CPU load: ',
            'label1' => array(),
            'data1' => array(),
        ),
    );
	
    if (stristr(PHP_OS, 'win')) {
	$result['data'] = "This function is not implemented on Windows platforms";
	unset ($result['line']);
    }
    else	{

        $sql = db_fetch_assoc("SELECT date_format(time(date),'%H:%i') as xdate,name,value FROM plugin_intropage_trends where name='cpuload' order by date desc limit 10");
        if (count($sql)) {
            $result['line']['title1'] = "Load";
            foreach($sql as $row) {
                // no gd data
                // $result['data'] .= $row['xdate'] . " " . $row['name'] ." " . $row['value']. "<br/>";
                array_push($result['line']['label1'],$row['xdate']);
                array_push($result['line']['data1'],$row['value']);
            }
            $result['line']['data1'] = array_reverse ($result['line']['data1']);
            $result['line']['label1'] = array_reverse ($result['line']['label1']);

        }
        else    {
            unset ($result['line']);
            $result['data'] = "Waiting for data";
        }

    }


	
	return $result;
}

?>
