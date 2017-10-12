<?php

function get_hosts_monitor() {
	global $config, $allowed_hosts;
	
	$result = array(
		'name' => 'Not monitored hosts',
		'alarm' => 'green',
		'detail' => '',
	);
	
	if (!db_fetch_cell("SELECT directory FROM plugin_config where directory='monitor' and status=1")) {
		$result['alarm'] = "grey";
		$result['data'] = "Monitor plugin not installed/running";
	} else {
		$sql_monitor = db_fetch_assoc("SELECT description, id FROM host WHERE id in ($allowed_hosts) and monitor != 'on'");
		$result['data'] = count($sql_monitor);
		if ($sql_monitor) {
			$result['alarm'] = "red";
			foreach($sql_monitor as $row) {
				$result['detail'] .= sprintf("<a href=\"%shost.php?action=edit&amp;id=%d\">%s (ID: %d)</a><br/>\n",htmlspecialchars($config['url_path']),$row['id'],$row['description'],$row['id']);
			}
		}
	}
	
	return $result;
}

?>
