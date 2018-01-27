<?php

function display_information() {

	global $config, $colors, $poller_options,$console_access,$allowed_hosts,$sql_where;

	if (!api_user_realm_auth('intropage.php'))	{
		print "Intropage - permission denied";
		print "<br/><br/>";
		return false;
	}

	$debug = "";
	$debug_start = microtime(true);


	$selectedTheme = get_selected_theme();

	$url_path = $config["url_path"] . "plugins/intropage";

	// functions
	include_once($config['base_path'] . '/plugins/intropage/include/helpers.php');
	include_once($config['base_path'] . '/plugins/intropage/include/data.php');	

	// style for panels
	print "<link type='text/css' href='$url_path/themes/common.css' rel='stylesheet'>\n";
	print "<link type='text/css' href='$url_path/themes/" . $selectedTheme . ".css' rel='stylesheet'>\n";

	
	// drag and drop jquery
print <<<EOF

<script type="text/javascript">

/* tohle funguje, asi pouzit, ale musim mit idcka
*/
  $(function() {

    $( "#obal" ).sortable({
	
    
      update: function( event, ui ) {
        //console.log($("#obal"));
        var xdata = new Array();
	$('#obal li').each(function() {
	    xdata.push($(this).attr("id"));
	});

	$.get('$url_path/intropage_ajax.php',{xdata:xdata});



      }
    });
    $( "#sortable" ).disableSelection();

  });


</script>

EOF;

	// Retrieve global configuration options
	$display_important_first = read_config_option("intropage_display_important_first");
	$display_level = read_config_option("intropage_display_level");
	$intropage_debug = read_config_option("intropage_debug");
	
	$current_user = db_fetch_row('SELECT * FROM user_auth WHERE id=' . $_SESSION['sess_user_id']);
	$sql_where = get_graph_permissions_sql($current_user['policy_graphs'], $current_user['policy_hosts'], $current_user['policy_graph_templates']);
	$allowed_hosts = '';

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

        if (!empty($allowed_hosts))
            $allowed_hosts = substr($allowed_hosts,0,-1);
        else
            $allowed_hosts = "NULL";


	
	// Retrieve access
	$console_access = (db_fetch_assoc("select realm_id from user_auth_realm where user_id='" . $_SESSION["sess_user_id"] . "' and user_auth_realm.realm_id=8"))?true:false;

	
	// Start
	$values = array();

	$query = "select * from plugin_intropage_panel order by priority desc";
	$panels = db_fetch_assoc($query);


	foreach ($panels as $panel)	{
	    $start = microtime(true);	
	    $pokus = $panel['panel'];
	    $values[$pokus] = $pokus();
	    $debug .= "$pokus: " . round(microtime(true) -$start,2) . " || \n";
	}



	// Display ----------------------------------

//	$display_important_first = on/off
//	$display_level   =  0 "Only errors", 1 "Errors and warnings", 2 => "All"
// 	0 chyby, 1 - chyby/warn, 2- all

    print '<ul id="obal" style="width: 100%; margin: 20px auto;">';

    // user changed order
    if (isset ($_SESSION['intropage_order']) && is_array($_SESSION['intropage_order']))	{
	    $order = "";
	    foreach ($_SESSION['intropage_order'] as $ord)	{
		$order .= $ord . ",";
	    }
	    $order = substr ($order,0,-1);    
    
        $query = "select * from plugin_intropage_panel order by field (id,$order)";
	$panels = db_fetch_assoc($query);

        foreach($panels as $panel) {
	    $pom = $panel['panel'];
            intropage_display_panel($panel['id'],$values[$pom]['alarm'],$values[$pom]['name'],$values[$pom]);
	}
	
    }
    elseif ($display_important_first == "on")	{  // important first
    
    
    

    	    foreach($panels as $panel) {	
    		$pom = $panel['panel'];
		if ($values[$pom]['alarm'] == "red")	{
		    intropage_display_panel($panel['id'],$values[$pom]['alarm'],$values[$pom]['name'],$values[$pom]);
		    $values[$pom]['displayed'] = true;
		}
	    }

	    // yellow (errors and warnings)
	    if ($display_level == 1 || ($display_level == 2 && !isset($values[$pom]['displayed'])))	{
    		foreach($panels as $panel) {	
    		    $pom = $panel['panel'];
		    if ($values[$pom]['alarm'] == "yellow")	{
			intropage_display_panel($panel['id'],$values[$pom]['alarm'],$values[$pom]['name'],$values[$pom]);
			$values[$pom]['displayed'] = true;
		    }
		}
	    }

	    // green (all)
	    if ($display_level == 2)	{
    		foreach($panels as $panel) {	
    		    $pom = $panel['panel'];
		    if ($values[$pom]['alarm'] == "green")	{

			intropage_display_panel($panel['id'],$values[$pom]['alarm'],$values[$pom]['name'],$values[$pom]);
			$values[$pom]['displayed'] = true;
		    }
		}

		// grey and without color
    		foreach($panels as $panel) {	
    		    $pom = $panel['panel'];
		    if (!isset($values[$pom]['displayed']))	{
			intropage_display_panel($panel['id'],$values[$pom]['alarm'],$values[$pom]['name'],$values[$pom]);
			$values[$pom]['displayed'] = true;
		    }
		}
	    }

    }
    else	{	// order by priority

	foreach ($panels as $panel)	{

	    $pom = $panel['panel'];
	
		if (
		    ($display_level == 2 ) ||
	            ($display_level == 1 && ($values[$pom]['alarm'] == "red" || $values[$pom]['alarm'] =="yellow") ) ||
	            ($display_level == 0 &&  $values[$pom]['alarm'] == "red") )	{

			if (isset ($values[$pom]))	// only active panels, not disable
				intropage_display_panel($panel['id'],$values[$pom]['alarm'],$values[$pom]['name'],$values[$pom]);
		}
	}

    }

	// display debug information in panel

    if ($intropage_debug) {
	unset($value);
	$value['data'] = $debug;
	intropage_display_panel(999,'grey','Debug',$value);
    }

// js for detail
?>
<script>
$(document).ready(function () {
 $('.article').hide();
  $('.maxim').click(function(){


    $(this).html( $(this).html() == '+' ? '-' :'+' );
//    $(this).attr('title', $(this).attr('title') == 'Show details' ? 'Hide details' : 'Show details');
    $(this).nextAll('.article').first().toggle();


    if ($('#' + this.name).css("display") == "none")	{
	$('#' + this.name).css("display","block");
        $(this).attr('title','Hide details');	
    }
    else		{
	$('#' + this.name).css("display","none");
        $(this).attr('title','Show details');	

    }

  });
});</script>


<?php


// end of detail js

    print "<div style='clear: both;'></div>";
    print "<div style=\"width: 100%\"> Generated: " . date("H:i:s") . " (" . round(microtime(true) - $debug_start)  . "s)</div>\n";



    print "</ul>\n";


	
	return true;
}

?>
