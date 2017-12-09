<?php

function ntp_time($host) {
    $timestamp = -1;
    $sock = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);

    $timeout = array('sec'=>1,'usec'=>500000);
    socket_set_option($sock,SOL_SOCKET,SO_RCVTIMEO,$timeout);
    socket_clear_error();         
    
    socket_connect($sock, $host, 123);
    if (socket_last_error() == 0)	{  
		// Send request
		$msg = "\010" . str_repeat("\0", 47);
		socket_send($sock, $msg, strlen($msg), 0);
    	// Receive response and close socket
    	
    	if (@socket_recv($sock, $recv, 48, MSG_WAITALL))	{
	    	socket_close($sock);
	    	// Interpret response
	    	$data = unpack('N12', $recv);
	    	$timestamp = sprintf('%u', $data[9]);
	    	// NTP is number of seconds since 0000 UT on 1 January 1900
	    	// Unix time is seconds since 0000 UT on 1 January 1970
	    	$timestamp -= 2208988800;
		}
    }
    return $timestamp;
}

function ntp() {
	global $config;

	$result = array(
		'name' => 'Time synchronization',
		'alarm' => 'green',
	);
	
	$ntp_server = read_config_option('intropage_ntp_server');
	
	if (filter_var($ntp_server,FILTER_VALIDATE_IP) || preg_match('/^(([a-z0-9]|[a-z0-9][a-z0-9\-]*[a-z0-9])\.)*([a-z0-9]|[a-z0-9][a-z0-9\-]*[a-z])$/i',$ntp_server)) {
		$ntp_time = ntp_time($ntp_server);
		if ($ntp_time > 0) {
			$diff_time = date('U') - $ntp_time;
			if ($diff_time < -600 || $diff_time > 600) {
				$result['alarm'] = "red";
				$result['data'] = "<span class=\"txt_big\">" . date("Y-m-d") . "<br/>". date("H:i:s") . "</span><br/><br/>Please check time.<br/>It is different (more than 10 minutes) from NTP server $ntp_server";
			} elseif ($diff_time < -120 || $diff_time > 120) {
				$values['time']['alarm'] = "yellow";
				$values['time']['data'] = "<span class=\"txt_big\">" . date("Y-m-d") . "<br/>" . date("H:i:s") . "</span><br/><br/>Please check time.<br/>It is different (more than 2 minutes) from NTP server $ntp_server";
			} else {
				$result['data'] = "<span class=\"txt_big\">" . date("Y-m-d") . "<br/>" . date("H:i:s") . "</span><br/><br/>Localtime is equal to NTP server<br/>$ntp_server";
			}
		} else {
			$result['alarm'] = "red";
			$result['data'] = "Unable to contact the NTP server indicated. Please check your configuration";
		}
	} else {
		$result['alarm'] = "red";
		$result['data'] = "Incorrect ntp server address, please insert IP or DNS name";
	}
	
	return $result;
}

?>
