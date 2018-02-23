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

	// actions
	include_once($config['base_path'] . '/plugins/intropage/include/actions.php');


	// functions
	include_once($config['base_path'] . '/plugins/intropage/include/helpers.php');
	include_once($config['base_path'] . '/plugins/intropage/include/data.php');	

	// style for panels
	print "<link type='text/css' href='$url_path/themes/common.css' rel='stylesheet'>\n";
	print "<link type='text/css' href='$url_path/themes/" . $selectedTheme . ".css' rel='stylesheet'>\n";

	
print <<<EOF

<script type="text/javascript">

// IE 10 & 11 hack for flex. Without this are all panels in one line
$(window).load(function() {
    if (  
	navigator.userAgent.search('MSIE 10') > 0 || // ie10
        (navigator.userAgent.search('Trident') > 0 && navigator.userAgent.search('rv:11') > 0 ) // ie11
	)	
	{
    	    $('#obal').css('max-width',($(window).width()-190));
        }
        
});



// drag and drop order 
  $(function() {

    $( "#obal" ).sortable({
	
    
      update: function( event, ui ) {
        //console.log($("#obal"));
        var xdata = new Array();
	$('#obal li').each(function() {
	    xdata.push($(this).attr("id"));
	});

	$.get('$url_path',{xdata:xdata});
      }
    });
    $( "#sortable" ).disableSelection();

  });

</script>

EOF;

	// Retrieve user settings and defaults
	
	$display_important_first = read_user_setting("intropage_display_important_first", read_config_option("intropage_display_important_first"));
	$display_level = read_user_setting("intropage_display_level",read_config_option("intropage_display_level"));
	$autorefresh = read_user_setting("intropage_autorefresh",read_config_option("intropage_autorefresh"));

	$intropage_debug = read_user_setting("intropage_debug",0);
	

	// Retrieve global configuration options
	
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

	// retrieve user setting (and creating if not)
	
	if (db_fetch_cell ("select count(*) from plugin_intropage_user_setting where user_id = " . $_SESSION['sess_user_id'] ) == 0)	{
	    // generating user setting
	    db_execute ("insert into plugin_intropage_user_setting (user_id,panel,priority) select " . $_SESSION['sess_user_id'] . ",panel,priority from plugin_intropage_panel");
	}

	$panels = db_fetch_assoc ("select * from plugin_intropage_user_setting where user_id = " . $_SESSION['sess_user_id'] . " order by priority desc" );


	// retrieve data for all panels
	foreach ($panels as $panel)	{
	    $pokus = $panel['panel'];

	    // read global setting is correct. Admin can disable panel for all users
	    if (read_config_option("intropage_" . $pokus) == "on")	{
		$start = microtime(true);	
		$values[$pokus] = $pokus();
		$debug .= "$pokus: " . round(microtime(true) -$start,2) . " || \n";
	    }
	}


	// Display ----------------------------------

//	$display_important_first = on/off
//	$display_level   =  0 "Only errors", 1 "Errors and warnings", 2 => "All"
// 	0 chyby, 1 - chyby/warn, 2- all

    print '<div id="megaobal">';
    print '<ul id="obal">';
    

    // user changed order - new order is valid until logout
    
    if (isset ($_SESSION['intropage_order']) && is_array($_SESSION['intropage_order']))	{
	$order = "";
	foreach ($_SESSION['intropage_order'] as $ord)	{
	    $order .= $ord . ",";
	}
	$order = substr ($order,0,-1);    
    
        $query = "select * from plugin_intropage_user_setting order by field (id,$order)";
	$panels = db_fetch_assoc($query);

        foreach($panels as $panel) {
	    $pom = $panel['panel'];
	    if (read_config_option("intropage_" . $pom) == "on")	
        	intropage_display_panel($panel['id'],$values[$pom]['alarm'],$values[$pom]['name'],$values[$pom]);
	}
	
    }
    elseif ($display_important_first == "on")	{  // important first

    	    foreach($panels as $panel) {	
    		$pom = $panel['panel'];
		if (read_config_option("intropage_" . $pom) == "on")	{

		    if ($values[$pom]['alarm'] == "red")	{
			intropage_display_panel($panel['id'],$values[$pom]['alarm'],$values[$pom]['name'],$values[$pom]);
			$values[$pom]['displayed'] = true;
		    }
		}
	    }

	    // yellow (errors and warnings)
	    if ($display_level == 1 || ($display_level == 2 && !isset($values[$pom]['displayed'])))	{
    		foreach($panels as $panel) {	
    		    $pom = $panel['panel'];
		    if (read_config_option("intropage_" . $pom) == "on")	{

		        if ($values[$pom]['alarm'] == "yellow")	{
			    intropage_display_panel($panel['id'],$values[$pom]['alarm'],$values[$pom]['name'],$values[$pom]);
			    $values[$pom]['displayed'] = true;
			}
		    }
		}
	    }

	    // green (all)
	    if ($display_level == 2)	{
    		foreach($panels as $panel) {	
    		    $pom = $panel['panel'];
		    if (read_config_option("intropage_" . $pom) == "on")	{

			if ($values[$pom]['alarm'] == "green")	{

			    intropage_display_panel($panel['id'],$values[$pom]['alarm'],$values[$pom]['name'],$values[$pom]);
			    $values[$pom]['displayed'] = true;
			}
		    }
		}

		// grey and without color
    		foreach($panels as $panel) {	
    		    $pom = $panel['panel'];
		    if (read_config_option("intropage_" . $pom) == "on")		{

	    		if (!isset($values[$pom]['displayed']))	{
			    intropage_display_panel($panel['id'],$values[$pom]['alarm'],$values[$pom]['name'],$values[$pom]);
			    $values[$pom]['displayed'] = true;
			}
		    }
		}
	    }

    }
    else	{	// display only errors/errors and warnings/all - order by priority
	foreach ($panels as $panel)	{

	    $pom = $panel['panel'];
	
	    if (read_config_option("intropage_" . $pom) == "on")	{

		if (
		    ($display_level == 2 ) ||
	            ($display_level == 1 && ($values[$pom]['alarm'] == "red" || $values[$pom]['alarm'] =="yellow") ) ||
	            ($display_level == 0 &&  $values[$pom]['alarm'] == "red") )	{

			if (isset ($values[$pom]))	// only active panels, not disable
				intropage_display_panel($panel['id'],$values[$pom]['alarm'],$values[$pom]['name'],$values[$pom]);
		}
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
    print "</ul>\n";


    // settings

    echo "<form method=\"post\">\n";
    echo "<select name=\"intropage_action\" size=\"1\">";
    echo "<option value=\"0\">Select action ...</option>";

    $panels = db_fetch_assoc ("select t1.panel as panel_name from plugin_intropage_panel as t1 left outer join plugin_intropage_user_setting as t2 on t1.panel = t2.panel where t2.user_id is null order by t1.priority");
    if (sizeof($panels) > 0)	{
	// allowed panel?
        if (read_config_option("intropage_" . $pom) == "on")	{

	    foreach ($panels as $panel)	{
		echo "<option value=\"addpanel_" . $panel['panel_name'] . "\">Add panel " . $panel['panel_name'] . "</option>\n";
    
	    }
	}
    }

    // only submit :-)
    echo "<option value=\"\">Refresh now</option>";

    if ($autorefresh > 0)
	echo "<option value=\"refresh_0\">Autorefresh disable</option>";
    else
	echo "<option value=\"refresh_0\" disabled=\"disabled\">Autorefresh disable</option>";

        
    if ($autorefresh == 60)
	echo "<option value=\"refresh_60\" disabled=\"disabled\">Autorefresh 1 minute</option>";
    else
	echo "<option value=\"refresh_60\">Autorefresh 1 minute</option>";


    if ($autorefresh == 180)
	echo "<option value=\"refresh_180\" disabled=\"disabled\">Autorefresh 3 minutes</option>";
    else
	echo "<option value=\"refresh_180\">Autorefresh 3 minutes</option>";


    if ($autorefresh == 600)
	echo "<option value=\"refresh_600\" disabled=\"disabled\">Autorefresh 10 minutes</option>";
    else
	echo "<option value=\"refresh_600\">Autorefresh 10 minutes</option>";



    if (read_user_setting("intropage_display_level") == 0)
	echo "<option value=\"displaylevel_0\" disabled=\"disabled\">Display only errors</option>";
    else
	echo "<option value=\"displaylevel_0\">Display only errors</option>";
    

    if (read_user_setting("intropage_display_level") == 1)
	echo "<option value=\"displaylevel_1\" disabled=\"disabled\">Display errors and warnings</option>";
    else
	echo "<option value=\"displaylevel_1\">Display errors and warnings</option>";

	
    if (read_user_setting("intropage_display_level") == 2)
	echo "<option value=\"displaylevel_2\" disbaled=\"disabled\">Display all</option>";
    else
	echo "<option value=\"displaylevel_2\">Display all</option>";


    if ($display_important_first == "on")	{
	echo "<option value=\"important_first\" disabled=\"disabled\">Sort by - red-yellow-green-gray</option>";
	echo "<option value=\"important_no\">Sort by panel priority</option>";
    
    }
    else	{
	echo "<option value=\"important_first\">Sort by - red-yellow-green-gray</option>";
	echo "<option value=\"important_no\" disabled=\"disabled\">Sort by panel priority</option>";
    }
    

    if (isset($_SESSION['intropage_changed_order']))
	echo "<option value=\"reset_order\">Reset panel order to default</option>";

    echo "<option value=\"reset_all\">Reset all to default</option>";






    $lopts = db_fetch_cell('SELECT login_opts FROM user_auth WHERE id=' . $_SESSION['sess_user_id']);
    $lopts_intropage = db_fetch_cell_prepared('SELECT intropage_opts FROM user_auth WHERE id=?',array($_SESSION['sess_user_id']));
    // 0 = console, 1= tab
    
    // login options can change user group!
    


    switch ($lopts)     { // after login: 1=podle url, 2=console, 3=graphs, 4=intropage tab, 5=intropage in console !!! 4 a 5 uz asi neplati
        case "2" :  // ma konzoli, dam mu dve moznosti - v tabu, v konzoli -- nebo mu nedam nic? Muze si to prenastavit
            if ($console_access)        {
        	if ($lopts_intropage == 0)	{
            	    echo "<option value=\"loginopt_console\" disabled=\"disabled\">View intropage as default page in console</option>";
            	    echo "<option value=\"loginopt_tab\">View intropage as default page in tab</option>";
		}
		else	{
            	    echo "<option value=\"loginopt_console\">View intropage as default page in console</option>";
            	    echo "<option value=\"loginopt_tab\" disabled=\"disabled\">View intropage as default page in tab</option>";

            	}
            }
        break;

        case "3": // vychozi ma graf - nabidnout mu to do roletky
            if ($console_access)	{
        	if ($lopts_intropage == 0)	{
            	    echo "<option value=\"loginopt_console\" disabled=\"disabled\">View intropage as default page in console</option>";
            	    echo "<option value=\"loginopt_tab\">View intropage as default page in tab</option>";
		}
		else	{
            	    echo "<option value=\"loginopt_console\">View intropage as default page in console</option>";
            	    echo "<option value=\"loginopt_tab\" disabled=\"disabled\">View intropage as default page in tab</option>";

            	}
            	
            }
            else
    		echo "<option value=\"loginopt_tab\">View intropage as default page in tab</option>";

        break;

	// !!!!! tady by jeste mela byt moznost, kdyz nema konzoli a jako vychozi si dal intropage prepnout zpet na graf
    }

    
    echo "</select>\n";
    echo "<input type=\"submit\" name=\"intropage_go\" value=\"Go\">\n";
    echo "</form>\n";
    // end of settings

    print "<div style=\"width: 100%\"> Generated: " . date("H:i:s") . " (" . round(microtime(true) - $debug_start)  . "s)</div>\n";

    echo "</div>\n"; // konec megaobal

    return true;
}

?>
