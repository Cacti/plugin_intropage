<?php

function info() {
	global $config, $allowed_hosts, $poller_options;
	
	$result = array(
		'name' => 'Info',
		'alarm' => 'green',
		'data' => '',
//		'detail' => '',
	);
	
	if ($poller_options[read_config_option("poller_type")] == 'spine' && file_exists(read_config_option("path_spine")) && (function_exists('is_executable')) && (is_executable(read_config_option("path_spine")))) {
	    $spine_version = "SPINE";
	    exec(read_config_option("path_spine") . " --version", $out_array);
    	    if (sizeof($out_array)) {
		$spine_version = $out_array[0];
	    }
	    
	    $result['data'] .= "Poller type: <a href=\"" . htmlspecialchars($config['url_path']) .  "settings.php?tab=poller\">$spine_version</a><br/>";
	} else {
	    $result['data'] .= "Poller type: <a href=\"" . htmlspecialchars($config['url_path']) .  "settings.php?tab=poller\">".$poller_options[read_config_option("poller_type")]."</a><br/>";
	}
		
	$result['data'] .= "Running on: ";
	if (function_exists("php_uname")) { 
	    $result['data'] .= php_uname(); 
	}
	else { 
	    $result['data'] .= PHP_OS; 
	}
	    	
	return $result;
}


?>

