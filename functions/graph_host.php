<?php

function graph_host() {
	global $config, $allowed_hosts, $console_access, $sql_where;
	
	$result = array(
		'name' => 'Hosts',
		'data' => '',
		'alarm' => "grey",
	);
	
	$h_all  = db_fetch_cell ("SELECT count(id) FROM host WHERE id IN ($allowed_hosts)");
	$h_up   = db_fetch_cell ("SELECT count(id) FROM host WHERE id IN ($allowed_hosts) AND status=3 AND disabled=''");
	$h_down = db_fetch_cell ("SELECT count(id) FROM host WHERE id IN ($allowed_hosts) AND status=1 AND disabled=''");
	$h_reco = db_fetch_cell ("SELECT count(id) FROM host WHERE id IN ($allowed_hosts) AND status=2 AND disabled=''");
	$h_disa = db_fetch_cell ("SELECT count(id) FROM host WHERE id IN ($allowed_hosts) AND disabled='on'");

	
	if ($h_down > 0) { $result['alarm'] = "red"; }
	elseif ($h_disa > 0) { $result['alarm'] = "yellow"; }
	
	if ($console_access) {
	    $result['data'] = "<a href=\"" . htmlspecialchars($config['url_path']) . "host.php?host_status=-1\">All: $h_all</a><br/>\n";
	    $result['data'] .= "<a href=\"" . htmlspecialchars($config['url_path']) . "host.php?host_status=3\">Up: $h_up</a><br/>\n";
	    $result['data'] .= "<a href=\"" . htmlspecialchars($config['url_path']) . "host.php?host_status=1\">Down: $h_down</a><br/>\n";
	    $result['data'] .= "<a href=\"" . htmlspecialchars($config['url_path']) . "host.php?host_status=-2\">Disabled: $h_disa</a><br/>\n";
	    $result['data'] .= "<a href=\"" . htmlspecialchars($config['url_path']) . "host.php?host_status=2\">Recovering: $h_reco</a>\n";
	} else {
    	    $result['data'] = "All: $h_all<br/>\n";
	    $result['data'] .= "Up: $h_up<br/>\n";
	    $result['data'] .= "Down: $h_down<br/>\n";
	    $result['data'] .= "Disabled: $h_disa<br/>\n";
	    $result['data'] .= "Recovering: $h_reco\n";
	}
	if (read_config_option('intropage_graph_host') == "on") {
		$result['pie'] = array('title' => 'Hosts: ', 'label' => array("Up","Down","Recovering","Disabled"), 'data' => array($h_up,$h_down,$h_reco,$h_disa));
	}
	
	return $result;
}



?>
