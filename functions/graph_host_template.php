<?php

function graph_host_template() {
	global $config, $allowed_hosts;
	
	$result = array(
		'name' => 'Host templates',
		'alarm' => 'grey',
		'data' => '',
		'pie' => array(
			'title' => 'Host Templates: ',
			'label' => array(),
			'data' => array(),			
		),
	);
	
	$sql_ht = db_fetch_assoc("SELECT host_template.id as id, name, count(host.host_template_id) AS total FROM host_template LEFT JOIN host ON (host_template.id = host.host_template_id) AND host.id IN ($allowed_hosts) GROUP by host_template_id ORDER BY total desc LIMIT 6");
	if ($sql_ht) {
		foreach ($sql_ht as $item) {
			array_push($result['pie']['label'],$item['name']);
			array_push($result['pie']['data'],$item['total']);

			$result['data'] .= $item['name'] . ": ";
			$result['data'] .= $item['total'] . "<br/>";

		}
	}
	
	return $result;
}

?>
