<?php

function graph_data_source() {
	global $config, $input_types;


	$result = array(
		'name' => 'Data sources',
		'alarm' => 'green',
		'data' => '',
		'pie' => array(
			'title' => 'Datasources: ',
			'label' => array(),
			'data' => array(),
		),
	);
	
	$sql_ds = db_fetch_assoc("SELECT data_input.type_id, COUNT(data_input.type_id) AS total FROM data_local INNER JOIN data_template_data ON (data_local.id = data_template_data.local_data_id) LEFT JOIN data_input ON (data_input.id=data_template_data.data_input_id) LEFT JOIN data_template ON (data_local.data_template_id=data_template.id) WHERE local_data_id<>0 group by type_id LIMIT 6");
	if ($sql_ds) {

		foreach ($sql_ds as $item) {
			if (!is_null ($item['type_id']))	{
				array_push($result['pie']['label'],preg_replace('/script server/','SS',$input_types[$item['type_id']]));
				array_push($result['pie']['data'],$item['total']);


				$result['data'] .= preg_replace('/script server/','SS',$input_types[$item['type_id']]) . ": ";
				$result['data'] .= $item['total'] . "<br/>";
			}


		}
	}
	
	
	return $result;
}

?>
