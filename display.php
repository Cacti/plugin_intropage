<?php



function display_informations() {

	global $config, $colors, $poller_options,$console_access,$allowed_hosts,$sql_where;

	if (!api_user_realm_auth('intropage.php'))	{
		print "Intropage - permission denied";
		print "<br/><br/>";
		return false;
	}

	$debug = "";
	$debug_start = microtime(true);


	// ugly but works. With jquery reload it works strange and I don't know why
	if (read_config_option("intropage_autorefresh") > 0)
	 	header("refresh: " . read_config_option("intropage_autorefresh") .";");

	 $selectedTheme = get_selected_theme();


	// common
	include_once($config['base_path'] . '/plugins/intropage/functions/common.php');
	// style for panels
	 print "<link type='text/css' href='" . $config["url_path"] . "plugins/intropage/themes/common.css' rel='stylesheet'>\n";
	 print "<link type='text/css' href='" . $config["url_path"] . "plugins/intropage/themes/" . $selectedTheme . ".css' rel='stylesheet'>\n";


	// Retrieve global configuration options
	$display_important_first = read_config_option("intropage_display_important_first");
	$display_level = read_config_option("intropage_display_level");
	$intropage_debug = read_config_option("intropage_debug");
	
	$current_user = db_fetch_row('SELECT * FROM user_auth WHERE id=' . $_SESSION['sess_user_id']);
	$sql_where = get_graph_permissions_sql($current_user['policy_graphs'], $current_user['policy_hosts'], $current_user['policy_graph_templates']);
	$allowed_hosts = '';

/*  old and bad
	$sql = "SELECT distinct host.id as id FROM host
        LEFT JOIN graph_local ON (host.id = graph_local.host_id)
        LEFT JOIN graph_templates_graph ON (graph_templates_graph.local_graph_id = graph_local.id)
        LEFT JOIN user_auth_perms ON ((graph_templates_graph.local_graph_id=user_auth_perms.item_id AND user_auth_perms.type=1 AND user_auth_perms.user_id= " . $_SESSION["sess_user_id"] . ") OR
            (host.id=user_auth_perms.item_id AND user_auth_perms.type=3 AND user_auth_perms.user_id=" . $_SESSION["sess_user_id"] . ") OR
            (graph_templates_graph.id=user_auth_perms.item_id AND user_auth_perms.type=4 AND user_auth_perms.user_id=" . $_SESSION["sess_user_id"] . "))
        WHERE graph_templates_graph.local_graph_id=graph_local.id and  $sql_where";

	//echo $sql;

	$sql_result = db_fetch_assoc ($sql);
	if ($sql_result) {
    	    $sql_array_result = array();
    	    foreach ($sql_result as $item) { array_push($sql_array_result,$item['id']); }
    	    $allowed_hosts = sprintf("%s",implode(",",$sql_array_result));
	}
    
*/    

// allowed host new


	/* get policies for all groups and user - from user_admin.php */

	$policies   = db_fetch_assoc("SELECT uag.id, 'group' AS type, uag.name, policy_graphs, policy_hosts, policy_graph_templates
    	    FROM user_auth_group AS uag 
    	    INNER JOIN user_auth_group_members AS uagm  ON uag.id = uagm.group_id
    	    WHERE uag.enabled = 'on' AND uagm.user_id = " . $_SESSION["sess_user_id"]);

	$policies[] = db_fetch_row("SELECT id, 'user' AS type, 'user' AS name, policy_graphs, policy_hosts, policy_graph_templates
    	    FROM user_auth WHERE id = " .$_SESSION["sess_user_id"]);

	// user ma prednost, proto se dela reverse
	array_reverse($policies);
                
	$policy=$policies[0]['policy_hosts'];

	$sql_query = "SELECT host.*, user_auth_perms.user_id FROM host LEFT JOIN user_auth_perms ON 
	    host.id = user_auth_perms.item_id AND user_auth_perms.type = 3 AND user_auth_perms.user_id = " . $_SESSION["sess_user_id"];

	$hosts = db_fetch_assoc($sql_query);
	if (sizeof($hosts)) {
	    foreach ($hosts as $host) {
    		if (empty($host['user_id']) || $host['user_id'] == NULL) {
        	    if ($policy == 1) {
            		// ulozit
            		$allowed_hosts .= $host['id'] . ",";
        	    } 
        
    		}
    		else    {
        	    if ($policy != 1) {
            		$allowed_hosts .= $host['id'] . ",";                
        	    }
    		}
	    }   
	}

	$allowed_hosts = substr($allowed_hosts,0,-1);

// allowed host new - end


    
	
	// Retrieve access
	$console_access = (db_fetch_assoc("select realm_id from user_auth_realm where user_id='" . $_SESSION["sess_user_id"] . "' and user_auth_realm.realm_id=8"))?true:false;


	
	// Start
	$values = array();

	// analyze_log  - 2 pannels
	if ($console_access && read_config_option('intropage_analyse_log') == "on") {
		$start = microtime(true);
		include_once($config['base_path'] . '/plugins/intropage/functions/analyse_log.php');
		$values['analyse_log'] = analyse_log();
		$values['analyse_log_size'] = analyse_log_size();
		$debug .= "Analyse log: " . round(microtime(true) -$start,2) . "<br/>\n";

	}

	// analyse login
	if ($console_access && read_config_option('intropage_analyse_login') == "on") {
		$start = microtime(true);
		include_once($config['base_path'] . '/plugins/intropage/functions/analyse_login.php');
		$values['analyse_login'] = analyse_login();
		
		$debug .= "Analyse login: " . round(microtime(true)-$start,2) . "<br/>\n";

	}

	// analyse_db
	if ($console_access && read_config_option('intropage_analyse_db') == "on") {
		$start = microtime(true);

		include_once($config['base_path'] . '/plugins/intropage/functions/analyse_db.php');
		$values['analyse_db'] = analyse_db();
		$debug .= "Analyse db: " . round(microtime(true)-$start,2) . "<br/>\n";

	}

	// analyse tree/host/graph 
	if ($console_access && read_config_option('intropage_analyse_tree_host_graph') == "on") {
		$start = microtime(true);
		include_once($config['base_path'] . '/plugins/intropage/functions/analyse_tree_host_graph.php');
		$values['analyse_tree_host_graph'] = analyse_tree_host_graph();
		$debug .= "Analyse tree host graph: " . round(microtime(true)-$start,2) . "<br/>\n";

	}


	// trend
	if (read_config_option("intropage_trend") == "on") {
		$start = microtime(true);
		include_once($config['base_path'] . '/plugins/intropage/functions/trend.php');
		$values['trend'] = get_trend();
		$debug .= "Trend: " . round(microtime(true)-$start,2) . "<br/>\n";

	}
	

	// Check NTP
	if ($console_access && read_config_option('intropage_ntp') == "on") {
		$start = microtime(true);
	
		include_once($config['base_path'] . '/plugins/intropage/functions/ntp.php');
		$values['ntp'] = ntp();
		$debug .= "NTP: " . round(microtime(true)-$start,2) . "<br/>\n";
		
	}
	
	// poller_info - 2 pannels
	if ($console_access && read_config_option('intropage_poller_info') == "on") {
		$start = microtime(true);

		include_once($config['base_path'] . '/plugins/intropage/functions/poller.php');
		$values['poller_info'] = poller_info();
		$values['poller_stat'] = poller_stat();
		$debug .= "Poller info: " . round(microtime(true)-$start,2) . "<br/>\n";

	}

	// graph_poller
	if ($console_access && read_config_option('intropage_graph_poller') == "on") {
		$start = microtime(true);
		include_once($config['base_path'] . '/plugins/intropage/functions/poller.php');
		$values['graph_poller'] = graph_poller();
		$debug .= "graph poller: " . round(microtime(true)-$start,2) . "<br/>\n";

	}
	

	// graph_host
	if (read_config_option("intropage_graph_host") == "on") {
		$start = microtime(true);
	    include_once($config['base_path'] . '/plugins/intropage/functions/graph_host.php');
	    $values['graph_host'] = graph_host();
		$debug .= "graph host: " . round(microtime(true)-$start,2) . "<br/>\n";

	}
	
	// Check Thresholds
	if (read_config_option("intropage_graph_threshold") == "on") {
		$start = microtime(true);
	    include_once($config['base_path'] . '/plugins/intropage/functions/graph_thold.php');
	    $values['graph_thold'] = graph_thold();
		$debug .= "graph thold: " . round(microtime(true)-$start,2) . "<br/>\n";
	    
	}
	
	
	// Get Datasources
	if (read_config_option("intropage_graph_data_source") == "on") {
		$start = microtime(true);
		include_once($config['base_path'] . '/plugins/intropage/functions/graph_data_source.php');
		$values['graph_data_source'] = graph_data_source();
		$debug .= "graph data source: " . round(microtime(true)-$start,2) . "<br/>\n";

	}

	
	// Get Hosts templates
	if (read_config_option("intropage_graph_host_template") == "on") {
		$start = microtime(true);
		include_once($config['base_path'] . '/plugins/intropage/functions/graph_host_template.php');
		$values['graph_host_template'] = graph_host_template();
		$debug .= "graph host template: " . round(microtime(true)-$start,2) . "<br/>\n";

	}

	// top5
	if (read_config_option("intropage_top5") == "on") {
		$start = microtime(true);
		include_once($config['base_path'] . '/plugins/intropage/functions/top5.php');
		$values['top5_ping'] = top5_ping();
		$values['top5_availability'] = top5_availability();
		$debug .= "top5: " . round(microtime(true)-$start,2) . "<br/>\n";

	}

	// info
	if ($console_access && read_config_option("intropage_info") == "on") {
		$start = microtime(true);
		include_once($config['base_path'] . '/plugins/intropage/functions/info.php');
		$values['info'] = info();
		$debug .= "info: " . round(microtime(true)-$start,2) . "<br/>\n";
		
	}

	// cpu
	if ($console_access && read_config_option("intropage_cpu") == "on") {
		$start = microtime(true);
		include_once($config['base_path'] . '/plugins/intropage/functions/cpu.php');
		$values['cpu'] = cpu();
		$debug .= "cpu: " . round(microtime(true)-$start,2) . "<br/>\n";

	}



	// Display ----------------------------------

//	$display_important_first = on/off
//	$display_level   =  0 "Only errors", 1 "Errors and warnings", 2 => "All"
// 	0 chyby, 1 - chyby/warn, 2- all

    if (!isset($_SESSION['intropage_cur_panel']))
	$_SESSION['intropage_cur_panel'] = 1;

    
//    print '<div id="obal" class="container"  style="width: 100%; margin: 20px auto;">';
    print '<div id="obal" style="width: 100%; margin: 20px auto;">';

    $query = "select * from plugin_intropage_panel order by priority desc";
    
    $panels = db_fetch_assoc($query);
    $_SESSION['intropage_max_panel'] = count ($panels);


    if ($display_important_first == "on")	{  // important first
    	    foreach($values as $key=>$value) {	
		if ($value['alarm'] == "red")	{

//		    $size = db_fetch_cell ("select size from plugin_intropage_panel where panel='$key'");


		    intropage_display_panel($size,$value['alarm'],$value['name'],$value);
		    $value['displayed'] = true;
		}
	    }

	    // yellow (errors and warnings)
	    if ($display_level == 1 || ($display_level == 2 && !isset($value['displayed'])))	{
    		foreach($values as $key=>$value) {	
		    if ($value['alarm'] == "yellow")	{
	
//			$size = db_fetch_cell ("select size from plugin_intropage_panel where panel='$key'");

      		        intropage_display_panel($size,$value['alarm'],$value['name'],$value);
			$value['displayed'] = true;
		    }
		}
	    }
	    // green (all)
	    if ($display_level == 2)	{
    		foreach($values as $key=>$value) {	
		    if ($value['alarm'] == "green" && !isset($value['displayed']))	{
//			$size = db_fetch_cell ("select size from plugin_intropage_panel where panel='$key'");

			intropage_display_panel($size,$value['alarm'],$value['name'],$value);
		    }
		}
	    }
    }
    else	{	// order by priority

	foreach ($panels as $key=>$value)	{

	    $pom = $value['panel'];

	
		if (
		    ($display_level == 2 ) ||
	            ($display_level == 1 && ($values[$pom]['alarm'] == "red" || $values[$pom]['alarm'] =="yellow") ) ||
	            ($display_level == 0 &&  $values[$pom]['alarm'] == "red") )	{
			if (isset ($values[$pom]))	// only active panels, not disable
			    intropage_display_panel($value['size'],$values[$pom]['alarm'],$values[$pom]['name'],$values[$pom]);
		}
	}

    }
    
// js for displaying detail
	print <<<EOF
<script type="text/javascript">
function hide_display (id)      {
    var temp = document.getElementById(id);

    if (temp.style.display=='block')
        temp.style.display='none';
    else
        temp.style.display='block';
    return false;
}
</script>

EOF;

    unset ($_SESSION['intropage_cur_panel'],$_SESSION['intropage_max_panel']);

    print "<div style='clear: both;'></div>";
    print "<div style=\"width: 100%\"> Generated: " . date("H:i:s") . " (" . round(microtime(true) - $debug_start)  . "s)</div>\n";

	    if ($intropage_debug) {
		echo $debug;
	    
	    }


 
    print "</div>\n"; // div id=obal


// reload

/*
$timeout = read_config_option("intropage_autorefresh");
if ($timeout >0)	{


<script type="text/javascript">
var timeout = setInterval(reloadChat, <?php echo ($timeout*1000);?>);    

function reloadChat () {

     $('#obal').load('<?php echo $config["url_path"];?>plugins/intropage/intropage_ajax.php');

}

</script>


}
*/
// end of reload



	
	return true;
}

?>
