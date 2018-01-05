<?php

function graph_thold() {
	global $config, $sql_where;
	
	$result = array(
		'name' => 'Thresholds',
		'data' => '',
		'alarm' => 'green',
	);
	
	if (!db_fetch_cell("SELECT directory FROM plugin_config where directory='thold' and status=1")) {
		$result['alarm'] = "grey";
		$result['data'] = "Thold plugin not installed/running\n";
	} elseif (!db_fetch_cell("SELECT DISTINCT user_id FROM user_auth_realm WHERE user_id = ".$_SESSION["sess_user_id"]." AND realm_id IN (SELECT id + 100 FROM plugin_realms WHERE file LIKE '%thold%')")) {
		$result['data'] = "You don't have permission\n";
	} else {
		$sql_join = "LEFT JOIN user_auth_perms ON ((thold_data.graph_template_id=user_auth_perms.item_id AND user_auth_perms.type=1 AND user_auth_perms.user_id= " . $_SESSION["sess_user_id"] . ") OR
			(thold_data.host_id=user_auth_perms.item_id AND user_auth_perms.type=3 AND user_auth_perms.user_id= " . $_SESSION["sess_user_id"] . ") OR
    		(thold_data.graph_template_id=user_auth_perms.item_id AND user_auth_perms.type=4 AND user_auth_perms.user_id= " . $_SESSION["sess_user_id"] . "))";
		
		$t_all = db_fetch_cell("SELECT COUNT(*) FROM thold_data $sql_join WHERE $sql_where");
		$t_brea = db_fetch_cell("SELECT COUNT(*) FROM thold_data $sql_join WHERE (thold_data.thold_alert!=0 OR thold_data.bl_alert>0) AND $sql_where");
#		$t_trig = db_fetch_cell("SELECT COUNT(*) FROM thold_data $sql_join WHERE (thold_data.thold_alert!=0 OR thold_data.bl_fail_count >= thold_data.bl_fail_trigger) AND $sql_where");
		$t_trig = db_fetch_cell("SELECT COUNT(*) FROM thold_data $sql_join WHERE ((thold_data.thold_alert!=0 AND thold_data.thold_fail_count >= thold_data.thold_fail_trigger) OR (thold_data.bl_alert>0 AND thold_data.bl_fail_count >= thold_data.bl_fail_trigger)) AND $sql_where");
									 
		$t_disa = db_fetch_cell("SELECT COUNT(*) FROM thold_data $sql_join WHERE thold_data.thold_enabled='off' AND $sql_where");
		
		if ($t_brea > 0 || $t_trig > 0) { $result['alarm'] = "red"; }
		elseif ($t_disa > 0) { $result['alarm'] = "yellow"; }
		
		if (db_fetch_cell("SELECT COUNT(*) FROM user_auth_realm WHERE user_id = ".$_SESSION["sess_user_id"]." AND realm_id IN (SELECT id + 100 FROM plugin_realms WHERE file LIKE '%thold_graph.php%')")) {
			$result['data'] = "<a href=\"" . htmlspecialchars($config['url_path']) . "plugins/thold/thold_graph.php?tab=thold&amp;triggered=-1\">All: $t_all</a><br/>\n";
			$result['data'] .= "<a href=\"" . htmlspecialchars($config['url_path']) . "plugins/thold/thold_graph.php?tab=thold&amp;triggered=1\">Breached: $t_brea</a><br/>\n";
			$result['data'] .= "<a href=\"" . htmlspecialchars($config['url_path']) . "plugins/thold/thold_graph.php?tab=thold&amp;triggered=3\">Trigged: $t_trig</a><br/>\n";
			$result['data'] .= "<a href=\"" . htmlspecialchars($config['url_path']) . "plugins/thold/thold_graph.php?tab=thold&amp;triggered=0\">Disabled: $t_disa</a><br/>\n";
		} else {
			$result['data'] = "All: $t_all<br/>\n";
			$result['data'] .= "Breached: $t_brea<br/>\n";
			$result['data'] .= "Trigged: $t_trig<br/>\n";
			$result['data'] .= "Disabled: $t_disa<br/>\n";
		}
		if (read_config_option('intropage_graph_threshold') == "on")	{
			$result['pie'] = array('title' => 'Thresholds: ', 'label' => array("OK","Breached","Trigerred","Disabled"), 'data' => array($t_all-$t_brea-$t_trig-$t_disa,$t_brea,$t_trig,$t_disa));
		}
	}
	
	return $result;
}

?>
