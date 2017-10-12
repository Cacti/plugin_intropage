<?php

function cpu() {
	global $config;
	
	$result = array(
		'name' => 'CPU utilization',
		'alarm' => 'green',
		'data' => '',
		
	);
	
    if (stristr(PHP_OS, 'win')) {
	$result['data'] = "This function is not implemented on Windows platforms";
    }
    else	{
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
    }



	
	return $result;
}

?>