<?php


function analyse_tree_host_graph() {
	global $config, $allowed_hosts;
	
	$result = array(
		'name' => 'Analyse tree/host/graph',
		'alarm' => 'green',
		'data' => '',
		'detail' => '',
	);
	
	$total_errors = 0;
	
	$result['data'] .= "Hosts with the same description: ";

	$pom = 0;
	$sql_duplicate = db_fetch_assoc("SELECT count(*) NoDups, description FROM host  WHERE id IN ($allowed_hosts) AND  disabled != 'on' GROUP BY description HAVING count(*)>1");
	$result['data'] .= count($sql_duplicate) . "<br/>";
    
	$total_errors += count($sql_duplicate);
    
	if (count($sql_duplicate) > 0) {
	    $result['alarm'] = "red";
	    foreach($sql_duplicate as $row) {
		$sql_hosts = db_fetch_assoc_prepared("SELECT id,description,hostname from host WHERE description IN(SELECT  description FROM host  WHERE id IN ($allowed_hosts) GROUP BY description HAVING count(*)>1) ORDER BY description");
		foreach ($sql_hosts as $row) {
		    if ($pom == 0)	{
			$pom++;
			$result['detail'] .= "Same description:<br/>";
		    }
		    $result['detail'] .= sprintf("<a href=\"%shost.php?action=edit&amp;id=%d\">%s (ID: %d)</a><br/>\n",htmlspecialchars($config['url_path']),$row['id'],$row['description'],$row['id']);
		}
	    }
	}

	// device in more trees

	$pom = 0;
	$result['data'] .= 'Devices in more then one tree: ';
	
	$sql_multiple = db_fetch_assoc ("SELECT host.id, host.description, count(*) AS count FROM host INNER JOIN graph_tree_items ON (host.id = graph_tree_items.host_id) GROUP BY description HAVING count(*)>1");
	$result['data'] .= count($sql_multiple) . "<br/>";
	if (count($sql_multiple) > 0) {
		$result['alarm'] = "yellow";
		foreach($sql_multiple as $row) {
			$sql_hosts = db_fetch_assoc_prepared("SELECT graph_tree.id as gtid, host.description, graph_tree_items.title, graph_tree_items.parent, graph_tree.name FROM host INNER JOIN graph_tree_items ON (host.id = graph_tree_items.host_id) INNER JOIN graph_tree ON (graph_tree_items.graph_tree_id = graph_tree.id) WHERE host.id = ?",array($row['id']));
			foreach($sql_hosts as $host) {
				$parent = $host['parent'];
				$tree = $host['name'] . " / ";
				while ($parent != 0) {
					$sql_parent = db_fetch_row("SELECT parent, title FROM graph_tree_items WHERE id = $parent");
					$parent = $sql_parent['parent'];
					$tree .= $sql_parent['title'] . " / ";
				}
				if ($pom == 0)	{
				    $pom++;
				    $result['detail'] .= "<br/><br/>Device on more then one tree:<br/>";
				}


				$result['detail'] .= sprintf("<a href=\"%stree.php?action=edit&id=%d\">Node: %s | Tree: %s</a><br/>\n",htmlspecialchars($config['url_path']),$host['gtid'],$host['description'],$tree);
			}
		}
	}

	$total_errors += count($sql_multiple);


	
	// host without graph

	$pom = 0;
	$result['data'] .= 'Hosts without graphs: ';

	$sql_no_graphs = db_fetch_assoc("SELECT id , description FROM host WHERE id IN ($allowed_hosts) AND  disabled != 'on'  AND id NOT IN (SELECT DISTINCT host_id FROM graph_local) AND snmp_version != 0");

	$result['data'] .= count($sql_no_graphs) . "<br/>";
	if (count($sql_no_graphs) > 0) {
		$result['alarm'] = "yellow";
		foreach($sql_no_graphs as $row) {
				if ($pom == 0)	{
				    $pom++;
				    $result['detail'] .= "<br/><br/>Host without graph:<br/>";
				}

			$result['detail'] .= sprintf("<a href=\"%shost.php?action=edit&amp;id=%d\">%s (ID: %d)</a><br/>\n",htmlspecialchars($config['url_path']),$row['id'],$row['description'],$row['id']);
		}
	}

	$total_errors += count($sql_no_graphs);


	// host without tree
	
	$pom = 0;
	$result['data'] .= 'Hosts without tree: ';
	
	$sql_no_graphs = db_fetch_assoc("SELECT id , description FROM host WHERE id IN ($allowed_hosts) AND  disabled != 'on' AND  id NOT IN (SELECT DISTINCT host_id FROM graph_tree_items)");
	$result['data'] .= count($sql_no_graphs) . "<br/>";
	if (count($sql_no_graphs) > 0) {
		$result['alarm'] = "yellow";
		foreach($sql_no_graphs as $row) {
				if ($pom == 0)	{
				    $pom++;
				    $result['detail'] .= "<br/><br/>Hosts without tree:<br/>";
				}

			$result['detail'] .= sprintf("<a href=\"%shost.php?action=edit&amp;id=%d\">%s (ID: %d)</a><br/>\n",htmlspecialchars($config['url_path']),$row['id'],$row['description'],$row['id']);
		}
	}

	$total_errors += count($sql_no_graphs);


	// hosts with same IP

	$pom = 0;
    	$result['data'] .= 'Devices with the same IP: ';
    
    $sql_dupip = db_fetch_assoc("SELECT count(*) NoDups, id, hostname FROM host  WHERE id IN ($allowed_hosts)  AND disabled != 'on'  GROUP BY hostname,snmp_port HAVING count(*)>1");

    $result['data'] .= count($sql_dupip) . "<br/>";

    if (count($sql_dupip) > 0) {

	$result['alarm'] = "red";
	foreach($sql_dupip as $row) {

	    $sql_hosts = db_fetch_assoc_prepared("SELECT id,description,hostname from host WHERE hostname IN(SELECT  hostname FROM host  WHERE id IN ($allowed_hosts) GROUP BY hostname,snmp_port HAVING count(*)>1) order by hostname");
	    foreach ($sql_hosts as $row) {
				if ($pom == 0)	{	
				    $pom++;
				    $result['detail'] .= "<br/><br/>Device with same ip and port:<br/>";
				}


		$result['detail'] .= sprintf("<a href=\"%shost.php?action=edit&amp;id=%d\">%s %s (ID: %d)</a><br/>\n",htmlspecialchars($config['url_path']),$row['id'],$row['description'],$row['hostname'],$row['id']);
	    }
	}
    }
    $total_errors += count($sql_dupip);



    if ($total_errors > 0)
	$result['data'] = "<span class=\"txt_big\">Found $total_errors errors</span><br/><br/>" . $result['data'];
    else
	$result['data'] = "<span class=\"txt_big\">Everithing OK</span><br/>" . $result['data'];
    
    
    
    return $result;
}

?>