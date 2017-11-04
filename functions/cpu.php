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
//                        'title1' => "",
//                        'data2' => array(),
//                        'title2' => "",
                    ),
		
	);
	

    if (stristr(PHP_OS, 'win')) {
	$result['data'] = "This function is not implemented on Windows platforms";
    }
    else	{



        $sql = db_fetch_assoc("SELECT date_format(time(date),'%H:%i') as xdate,name,value FROM plugin_intropage_trends where name='cpuload' order by date desc limit 10");
        if (count($sql)) {
            $result['line']['title1'] = "Load";
            foreach($sql as $row) {
                // no gd data
                $result['data'] .= $row['xdate'] . " " . $row['name'] ." " . $row['value']. "<br/>";
                array_push($result['line']['label1'],$row['xdate']);
                array_push($result['line']['data1'],$row['value']);

            }
        }
        else    {
            unset ($result['line']);
            $result['data'] = "Waiting for data";
        }





    }


/* old
	$load = sys_getloadavg();

	$load[0] = round ($load[0],2);
	$load[1] = round ($load[1],2);
	$load[2] = round ($load[2],2);


	if ($load[0] > 1)	{
		$result['data'] .= "<b>Load 1 min: $load[0]</b><br/>\n";
		$result['alarm'] = "red";
	
	}
	elseif ($load[0] > 0.5)	{
		$result['data'] .= "<b>Load 1 min: $load[0]</b><br/>\n";
		$result['alarm'] = "yellow";
	}
	else
	    $result['data'] .= "Load 1 min: $load[0]<br/>\n";

	if ($load[1] > 1)
	    $result['data'] .= "<b>Load 5 min: $load[1]</b><br/>\n";
	else
	    $result['data'] .= "Load 5 min: $load[1]<br/>\n";

	if ($load[2] > 1)
	    $result['data'] .= "<b>Load 15 min: $load[2]</b><br/>\n";
	else
	    $result['data'] .= "Load 15 min: $load[2]<br/>\n";
    
*/
	
	return $result;
}

?>