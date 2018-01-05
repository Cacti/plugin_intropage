<?php

function thold_events() {
	global $config;
	
	$result = array(
		'name' => "Last thold events",
		'alarm' => 'green',
		'data' => '',
		'detail' => '',
	);

	if (db_fetch_cell("select count(*) from plugin_config where directory='thold' and status = 1") == 0)	{
	    $result['alarm'] = "yellow";
	    $result['data'] = "Plugin Thold isn't installed or started";
	
	}
	else	{

	    $sql_result = db_fetch_assoc("SELECT tl.description as description,tl.time as time, tl.status as status, uap0.user_id AS user0, uap1.user_id AS user1, uap2.user_id AS user2 FROM plugin_thold_log AS tl INNER JOIN thold_data AS td ON tl.threshold_id=td.id INNER JOIN graph_local AS gl ON gl.id=td.local_graph_id LEFT JOIN graph_templates AS gt ON gt.id=gl.graph_template_id LEFT JOIN graph_templates_graph AS gtg ON gtg.local_graph_id=gl.id LEFT JOIN host AS h ON h.id=gl.host_id LEFT JOIN user_auth_perms AS uap0 ON (gl.id=uap0.item_id AND uap0.type=1) LEFT JOIN user_auth_perms AS uap1 ON (gl.host_id=uap1.item_id AND uap1.type=3) LEFT JOIN user_auth_perms AS uap2 ON (gl.graph_template_id=uap2.item_id AND uap2.type=4) HAVING (user0 IS NULL OR (user1 IS NULL OR user2 IS NULL)) ORDER BY `time` DESC LIMIT 10");

	    foreach($sql_result as $row) {
		$result['data'] .=  date('Y-m-d H:i:s', $row['time']) . " - " . $row['description'] . "<br/>\n";
		if ($row['status'] == 1 || $row['status'] == 4 || $row['status'] == 7 )
		    $result['alarm'] = "red";
		elseif ($result['alarm'] == "green" && ($row['status'] == 2 || $row['status'] == 3))
		    $result['alarm'] == "yellow";

	    }
	}
	
	return $result;
}
?>
