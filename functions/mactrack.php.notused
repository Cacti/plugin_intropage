<?php

function get_mactrack() {
	global $config;
	
	$result = array(
		'name' => 'Mactrack',
		'alarm' => 'green',
	);
	
	$console_access = db_fetch_assoc("select realm_id from user_auth_realm where user_id='" . $_SESSION["sess_user_id"] . "' and user_auth_realm.realm_id=8") ? true : false;
	
	if (!db_fetch_cell("SELECT directory FROM plugin_config where directory='mactrack' and status=1")) {
		$result['alarm'] = "grey";
		$result['data'] = "Mactrack plugin not installed/running\n";
	} elseif (!db_fetch_cell("SELECT DISTINCT user_id FROM user_auth_realm WHERE user_id = ".$_SESSION["sess_user_id"]." AND realm_id =2120")) {
		$result['data'] =  "You don't have permission\n";
	} else {
		$sql_no_mt = db_fetch_assoc("SELECT id, description, hostname FROM host WHERE id NOT IN (SELECT DISTINCT host_id FROM mac_track_devices) AND snmp_version != 0");
		if ($sql_no_mt) {
			$result['detail'] .= "Host without mac-track: <br/>";
			foreach ($sql_no_mt as $item) {
				$result['detail'] .= ($console_access)?
					sprintf("<a href=\"%shost.php?action=edit&amp;id=%s\">%s-%s</a><br/>\n",$config['url_path'],$item['id'],$item['description'],$item['hostname']):
					sprintf("%s-%s<br/>\n",$item['description'],$item['hostname']);
			}
		}
		$m_all  = db_fetch_cell ("select count(host_id) from mac_track_devices");
		$m_up   = db_fetch_cell ("select count(host_id) from mac_track_devices where snmp_status='3'");
		$m_down = db_fetch_cell ("select count(host_id) from mac_track_devices where snmp_status='1'");
		$m_disa = db_fetch_cell ("select count(host_id) from mac_track_devices where snmp_status='-2'");
		$m_err  = db_fetch_cell ("select count(host_id) from mac_track_devices where snmp_status='4'");
		$m_unkn = db_fetch_cell ("select count(host_id) from mac_track_devices where snmp_status='0'");
		
		if ($m_down > 0 || $m_err > 0 || $m_unkn > 0) { $result['alarm'] = "red"; }
		elseif ($m_disa > 0) { $result['alarm'] = "yellow"; }
		
		if (db_fetch_cell("SELECT COUNT(*) FROM user_auth_realm WHERE user_id = ".$_SESSION["sess_user_id"]." AND realm_id IN (SELECT id + 100 FROM plugin_realms WHERE file LIKE '%thold_graph.php%')")) {
			$result['data']  = "<a href=\"" . htmlspecialchars($config['url_path']) . "plugins/mactrack/mactrack_devices.php?site_id=-1&amp;status=-1&amp;type_id=-1&amp;device_type_id=-1&amp;filter=&amp;rows=-1\">All: $m_all</a> | \n";
			$result['data'] .= "<a href=\"" . htmlspecialchars($config['url_path']) . 	"plugins/mactrack/mactrack_devices.php?site_id=-1&amp;status=3&amp;type_id=-1&amp;device_type_id=-1&amp;filter=&amp;rows=-1\">Up: $m_up</a> | \n";
			$result['data'] .= "<a href=\"" . htmlspecialchars($config['url_path']) . "plugins/mactrack/mactrack_devices.php?site_id=-1&amp;status=1&amp;type_id=-1&amp;device_type_id=-1&amp;filter=&amp;rows=-1\">Down: $m_down</a> | \n";
			$result['data'] .= "<a href=\"" . htmlspecialchars($config['url_path']) . "plugins/mactrack/mactrack_devices.php?site_id=-1&amp;status=4&amp;type_id=-1&amp;device_type_id=-1&amp;filter=&amp;rows=-1\">Error: $m_err</a> | \n";
			$result['data'] .= "<a href=\"" . htmlspecialchars($config['url_path']) . "plugins/mactrack/mactrack_devices.php?site_id=-1&amp;status=0&amp;type_id=-1&amp;device_type_id=-1&amp;filter=&amp;rows=-1\">Unknown: $m_unkn</a> | \n";
			$result['data'] .= "<a href=\"" . htmlspecialchars($config['url_path']) . "plugins/mactrack_devices.php?site_id=-1&amp;status=-2&amp;type_id=-1&amp;device_type_id=-1&amp;filter=&amp;rows=-1\">Disabled: $m_disa</a>\n";
		} else {
			$result['data'] = "All: $m_all</a> | \n";
			$result['data'] .= "Up: $m_up | \n";
			$result['data'] .= "Down: $m_down | \n";
			$result['data'] .= "Error: $m_err | \n";
			$result['data'] .= "Unknown: $m_unkn | \n";
			$result['data'] .= "Disabled: $m_disa | \n";
		}
		
		if (read_config_option('intropage_display_pie_mactrack') == "on")	{
			$result['pie'] = array('title' => 'MAC Tracks:', 'label' => array("Down","Up","Error","Unknown","Disabled"), 'data' => array($m_down,$m_up,$m_err,$m_unkn,$m_disa));
		}
	}
	
	return $result;
}

?>
