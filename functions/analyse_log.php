<?php




function tail_log($log_file, $nbr_lines = 1000, $adaptive = true) {
	
	if (!(file_exists($log_file) && is_readable($log_file))) { return false; }
	
	$f_handle = @fopen($log_file,"rb");
	if ($f_handle === false) { return false; }
	
	if (!$adaptive) { $buffer = 4096; }
	else { $buffer = ($nbr_lines < 2 ? 64 : ($nbr_lines < 10 ? 512 : 4096)); }
	
	fseek($f_handle, -1, SEEK_END);
	
	if (fread($f_handle, 1) != "\n") $nbr_lines -= 1;
	
	// Start reading
	$output = '';
	$chunk = '';
	// While we would like more
	while (ftell($f_handle) > 0 && $nbr_lines >= 0) {
		// Figure out how far back we should jump
		$seek = min(ftell($f_handle), $buffer);
		// Do the jump (backwards, relative to where we are)
		fseek($f_handle, -$seek, SEEK_CUR);
		// Read a chunk and prepend it to our output
		$output = ($chunk = fread($f_handle, $seek)) . $output;
		// Jump back to where we started reading
		fseek($f_handle, -mb_strlen($chunk, '8bit'), SEEK_CUR);
		// Decrease our line counter
		$nbr_lines -= substr_count($chunk, "\n");
	}
	
	// While we have too many lines (Because of buffer size we might have read too many)
	while ($nbr_lines++ < 0) {
		// Find first newline and remove all text before that
		$output = substr($output, strpos($output, "\n") + 1);
	}
	
	// Close file
	fclose($f_handle);
	
	return explode("\n",$output);
}

function human_filesize($bytes, $decimals = 2) {
	$size = array('B', 'kB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB');
	$factor = floor((strlen($bytes) - 1) / 3);
	return sprintf("%.{$decimals}f", $bytes / pow(1024, $factor)) . @$size[$factor];
}

/*
function analyse_log_size() {
	global $config, $log;
	
	$result = array(
		'name' => 'Log size',
		'alarm' => 'green',
		'data' => '',
	);


//	$result['data'] = "<a href=\"" . htmlspecialchars($config['url_path']) . "utilities.php?action=view_logfile\">";
	
	if (!$log['size']) {
		$result['alarm'] = "red";
		$result['data'] .= "Log file not accessible</a>";
	} elseif ($log['size'] < 0) {
		$result['alarm'] = "red";
		$result['data'] .= "Log file is larger than 2GB</a>";
	} elseif ($log['size'] < 255999999) {
		$result['data'] .= "<span class=\"txt_big\">" . human_filesize($log['size']) . "</span><br/><br/>Log size OK";
	} else {
		$result['alarm'] = "yellow";
		$result['data'] .= "<span class=\"txt_big\">" . human_filesize($log['size']) . "</span><br/><br/>Logfile is quite large</a>";
	}
	
	return $result;
}

*/

function analyse_log() {
	global $config, $log;
	
	$result = array(
		'name' => "Analyse cacti log",
		'alarm' => 'green',
		'data' => '',
	);

	if (read_config_option('intropage_analyse_log')) {
		$log = array(
			'file' => read_config_option("path_cactilog"),
			'nbr_lines' => read_config_option("intropage_analyse_log_rows"),
		);
		$log['size'] = filesize($log['file']);
		$log['lines'] = tail_log($log['file'],$log['nbr_lines']);
	} else {
		$log = array(
			'size' => false,
			'file' => read_config_option("path_cactilog"),
			'nbr_lines' => 0,
		);
	}
	
	
	
	if (!$log['size'] || !isset($log['lines'])) {
		$result['alarm'] = "red";
		$result['data'] .= "Log file not accessible";
	} else {
		$result['detail'] = '';
		$error = 0;
		$ecount = 0;
		$warn = 0;
		foreach($log['lines'] as $line) {
//		    if ($ecount < 11)	{
		    
			if (preg_match('/(WARN|ERROR|FATAL)/',$line,$matches)) {

				if (strcmp($matches[1],"WARN"))	{
				    $warn++;

    				    $ecount++;
    				    $result['detail'] .= "<b>$line</b><br/>";					
					
				} elseif ((strcmp($matches[1],"ERROR") || strcmp($matches[1],"FATAL")))	{
				    $error++;
				    $ecount++;
				    $result['detail'] .= "<b>$line</b><br/>";					

				}
			}
//		    }
		    
		}
		        
		$result['data'] .= "<span class=\"txt_big\">\n";
		$result['data'] .= "Errors: $error<br/>";
		$result['data'] .= "Warnings: $warn<br/>";
		$result['data'] .= "</span>\n";


// analyse log size
//	if (!$log['size']) {
//		$result['alarm'] = "red";
//		$result['data'] .= "<span class=\"txt_big\">Log file not accessible</span><br/>";
//	} elseif ($log['size'] < 0) {
	
	if ($log['size'] < 0) {
		$result['alarm'] = "red";
		$result['data'] .= "<span class=\"txt_big\">Log size: file is larger than 2GB</span><br/>";
	} elseif ($log['size'] < 255999999) {
		$result['data'] .= "<span class=\"txt_big\">Log size: " . human_filesize($log['size']) . "</span><br/>(Log size OK)<br/>";
	} else {
		$result['alarm'] = "yellow";
		$result['data'] .= "<span class=\"txt_big\">Log size: " . human_filesize($log['size']) . "</span><br/>(Logfile is quite large)<br/>";
	}




		$result['data'] .= "<br/>( errors and warning in last ".read_config_option("intropage_analyse_log_rows")." lines)";

		if ($error > 0)
		    $result['alarm'] = "red";
		    
		if ($warn > 0 && $result['alarm'] == "green")
		    $result['alarm'] = "yellow";

		
	}



	return $result;
}

?>
