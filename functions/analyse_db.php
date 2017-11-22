<?php

function analyse_db() {
	global $config;
	
	$result = array(
		'name' => 'Database check',
		'alarm' => 'green',
		'data' => '',
		'detail' => '',
	);
	
	$damaged = 0;
	$memtables = 0;
	$db_check_level = read_config_option('intropage_analyse_db_level');
	
	$tables = db_fetch_assoc ("SHOW TABLES");
	foreach($tables as $key=>$val) {

		$row = db_fetch_row("check table ".current($val)." $db_check_level");
		if (preg_match('/^note$/i',$row["Msg_type"]) && preg_match('/doesn\'t support/i',$row["Msg_text"])) { $memtables++; }
		elseif (!preg_match('/OK/i',$row["Msg_text"])) { $damaged++; $result['detail'] .= "Table " . $row["Table"] . " status " . $row["Msg_text"] . "<br/>\n"; }
	}
	
	if ($damaged > 0) { 
	    $result['alarm'] = "red";
	    $result['data'] = "<span class='txt_big'>DB problem</span><br/><br/>"; 
	}
	else	{
	    $result['data'] = "<span class='txt_big'>DB OK</span><br/><br/>"; 
	}	

	// connection errors
	$cerrors = 0;
	$con_err = db_fetch_assoc ("SHOW GLOBAL STATUS LIKE '%Connection_errors%'");

	foreach($con_err as $key=>$val) {
	    $cerrors = $cerrors + $val['Value'];
	}

	if ($cerrors > 0 )	{	// only yellow
	    $result['detail'] .= "Connection errors - try to restart database. <br/>";
	    
	    if ($result['alarm'] == "green")
		$result['alarm'] = "yellow";
	}

        $result['data'] .= "Connection errors: $cerrors<br/>";
	
	$result['data'] .= "Damaged tables: $damaged<br/>Memory tables: $memtables<br/>All tables: " . count($tables);
	
	return $result;
}

?>