<?php

function display_information() {

	//global $config, $colors, $poller_options,$console_access,$allowed_hosts,$sql_where;
	// tyhle opravdu potrebuju jako globalni, pouzivaji se v data.php. Toto je totiz fce
	global $config, $allowed_hosts, $sql_where;

	if (!api_user_realm_auth('intropage.php')) {
		echo 'Intropage - permission denied<br/><br/>';
		return false;
	}

	$debug_start = microtime(true);

	$logging = read_config_option('log_verbosity', true);

	$selectedTheme = get_selected_theme();

//	$url_path = $config['url_path'] . 'plugins/intropage/';

	if (db_fetch_cell('SELECT intropage_opts FROM user_auth WHERE id=' . $_SESSION['sess_user_id']) == 1) {  // in tab
		$url_path = $config['url_path'] . 'plugins/intropage/intropage.php';
	} else {           // in console
		$url_path = $config['url_path'];
	}


	// actions
	include_once($config['base_path'] . '/plugins/intropage/include/actions.php');

	// functions
	include_once($config['base_path'] . '/plugins/intropage/include/helpers.php');
	include_once($config['base_path'] . '/plugins/intropage/include/data.php');

	// style for panels
	echo "<link type='text/css' href='" . $config['url_path'] . "plugins/intropage/themes/common.css' rel='stylesheet'>";
	echo "<link type='text/css' href='" . $config['url_path'] . 'plugins/intropage/themes/' . $selectedTheme . ".css' rel='stylesheet'>";


	print <<<EOF

<script type="text/javascript">

  function resizeObal() {
    if (navigator.userAgent.search('MSIE 10') > 0 ||
       (navigator.userAgent.search('Trident') > 0 && navigator.userAgent.search('rv:11') > 0 )) {
      $('#obal').css('max-width',($(window).width()-190));
    }
  }

  // drag and drop order
  $(function() {

    $(window).resize(function() {
      resizeObal();
    });

    resizeObal();
    $( "#obal" ).sortable({

      update: function( event, ui ) {
        //console.log($("#obal"));
        var xdata = new Array();
	$('#obal li').each(function() {
	    xdata.push($(this).attr("id"));
	});

	$.get('$url_path',{xdata:xdata, intropage_action:'order'});
      }
    });
    $( "#sortable" ).disableSelection();
  });

</script>

EOF;

	// Retrieve user settings and defaults

	$display_important_first = read_user_setting('intropage_display_important_first', read_config_option('intropage_display_important_first'));
	$display_level           = read_user_setting('intropage_display_level', read_config_option('intropage_display_level'));
	$autorefresh             = read_user_setting('intropage_autorefresh', read_config_option('intropage_autorefresh'));
//	$intropage_debug         = read_user_setting('intropage_debug', 0);

	$maint_days_before = read_config_option('intropage_maint_plugin_days_before');

	// need for thold - isn't any better solution? - moved to include/data/thold function
	//	$current_user  = db_fetch_row('SELECT * FROM user_auth WHERE id=' . $_SESSION['sess_user_id']);
	//	$sql_where     = get_graph_permissions_sql($current_user['policy_graphs'], $current_user['policy_hosts'], $current_user['policy_graph_templates']);

	$hosts = get_allowed_devices();
	$allowed_hosts = implode(',', array_column($hosts, 'id'));

	// Retrieve access
	$console_access = (db_fetch_assoc("select realm_id from user_auth_realm where user_id='" . $_SESSION['sess_user_id'] . "' and user_auth_realm.realm_id=8")) ? true : false;

	// retrieve user setting (and creating if not)

	if (db_fetch_cell('select count(*) from plugin_intropage_user_setting where fav_graph_id is null and user_id = ' . $_SESSION['sess_user_id']) == 0) {
		$all_panel = db_fetch_assoc('SELECT panel,priority from plugin_intropage_panel');

		// generating user setting
		foreach ($all_panel as $one) {
			if (db_fetch_cell('select ' . $one['panel'] . ' from user_auth where id=' . $_SESSION['sess_user_id']) == 'on') {
				db_execute('insert into plugin_intropage_user_setting (user_id,panel,priority) values (' . $_SESSION['sess_user_id'] . ",'" . $one['panel'] . "'," . $one['priority'] . ')');
			}
		}
	} else {	// revoke permissions
		$all_panel = db_fetch_assoc('SELECT panel from plugin_intropage_user_setting');

		foreach ($all_panel as $one) {
			if (db_fetch_cell('select ' . $one['panel'] . ' from user_auth where id=' . $_SESSION['sess_user_id']) != 'on') {
				db_execute('delete from plugin_intropage_user_setting where user_id= ' . $_SESSION['sess_user_id'] . " and panel ='" . $one['panel'] . "'");
			}
		}
	}

	// panels + favourite graphs (fav_graph_id is not null)
//	$xpanels = db_fetch_assoc ("select id,panel,priority from plugin_intropage_user_setting where user_id = " . $_SESSION['sess_user_id'] . "  and panel !='intropage_favourite_graph'  order by priority desc" );
//	$panels = array_merge($xpanels,db_fetch_assoc ("select id,concat(panel,'_',fav_graph_id) as panel,priority,fav_graph_id from plugin_intropage_user_setting where user_id = " . $_SESSION['sess_user_id'] . "  and (panel='intropage_favourite_graph' and fav_graph_id is not null)  order by priority desc" ));

	$order = ' priority desc';
	if (isset($_SESSION['intropage_order']) && is_array($_SESSION['intropage_order'])) {
		$order = 'field (id,';
		foreach ($_SESSION['intropage_order'] as $ord) {
			$order .= $ord . ',';
		}
		$order = substr($order, 0, -1);
		$order .= ')';
	}

//	$panels = db_fetch_assoc ("select id,panel,priority,fav_graph_id from plugin_intropage_user_setting where user_id = " . $_SESSION['sess_user_id'] . " and (panel !='intropage_favourite_graph' or panel='intropage_favourite_graph' and fav_graph_id is not null) order by $order");

	// zde pozor, mohl bych to selectovat v jednom dotazu, ale potrebuju, aby se fav grafy jmenovali jinak.
	// bez toho si je nize ve foreach presisuju,, protoze se oba jmenuji jen fav_graph

	$panels = db_fetch_assoc('select id,panel,priority,fav_graph_id from plugin_intropage_user_setting where user_id = ' .
	$_SESSION['sess_user_id'] . "  and panel !='intropage_favourite_graph' union select id,concat(panel,'_',fav_graph_id) as panel, priority,fav_graph_id from plugin_intropage_user_setting where ( panel='intropage_favourite_graph' and fav_graph_id is not null) order by  $order");

	// retrieve data for all panels
	foreach ($panels as $xkey => $xvalue) {
		$pokus = $xvalue['panel'];
		$start = microtime(true);

		if (isset($xvalue['fav_graph_id'])) { // fav_graph exception
			$panels[$xkey]['alldata'] = intropage_favourite_graph($xvalue['fav_graph_id']);
		} else {	// normal panel
			$panels[$xkey]['alldata'] = $pokus();
		}
		if($logging >= 5)
		    cacti_log('debug: ' . $pokus . ', duration ' . round(microtime(true) - $start, 2),true,'Intropage');
	}



	// Display ----------------------------------

//	$display_important_first = on/off
//	$display_level   =  0 "Only errors", 1 "Errors and warnings", 2 => "All"
	// 	0 chyby, 1 - chyby/warn, 2- all


	echo '<script type="text/javascript">';
	echo 'var intropage_autorefresh=' . read_user_setting('intropage_autorefresh') . ';';
	echo 'var intropage_drag=true;';

	echo '</script>';
	
	
	print '<div id="megaobal">';
	print '<ul id="obal">';

	// extra maint plugin panel

	if (db_fetch_cell("SELECT directory FROM plugin_config where directory='maint'")) {
		$start = microtime(true);

		$tmp['data'] = '';

		$schedules = db_fetch_assoc("select * from plugin_maint_schedules where enabled='on'");
		if (sizeof($schedules)) {
			foreach ($schedules as $sc) {
				$t = time();
				switch ($sc['mtype']) {

					case 1:

					if ($t > ($sc['stime'] - $maint_days_before) && $t < $sc['etime']) {
						$tmp['data'] .= '<b>' . date('d. m . Y  H:i', $sc['stime']) . ' - ' . date('d. m . Y  H:i', $sc['etime']) .
								' - ' . $sc['name'] . ' (One time)<br/>Affected hosts:</b> ';

						$hosts = db_fetch_assoc('select description from host join plugin_maint_hosts on host.id=plugin_maint_hosts.host where schedule = ' . $sc['id']);
						foreach ($hosts as $host) {
							$tmp['data'] .= $host['description'] . ', ';
						}
						$tmp['data'] = substr($tmp['data'], 0, -2) .'<br/><br/>';
					}
					break;

					case 2:
					while ($sc['etime'] < $t) {
						$sc['etime'] += $sc['minterval'];
						$sc['stime'] += $sc['minterval'];
					}

					if ($t > ($sc['stime'] - $maint_days_before) && $t < $sc['etime']) {
						$tmp['data'] .= '<b>' . date('d. m . Y  H:i', $sc['stime']) . ' - ' . date('d. m . Y  H:i', $sc['etime']) .
								' - ' . $sc['name'] . ' (Reoccurring)<br/>Affected hosts:</b> ';

						$hosts = db_fetch_assoc('select description from host join plugin_maint_hosts on host.id=plugin_maint_hosts.host where schedule = ' . $sc['id']);
						foreach ($hosts as $host) {
							$tmp['data'] .= $host['description'] . ', ';
						}
						$tmp['data'] = substr($tmp['data'], 0, -2) . '<br/><br/>';
					}

					break;
			}
			}
		}

		if ($tmp['data']) {
			intropage_display_panel(997, 'red', 'Plugin Maint alert', $tmp);
			$tmp['data'] = '';
		}
	
	        if($logging >= 5)
		    cacti_log('debug: maint, duration ' . round(microtime(true) - $start, 2),true,'Intropage');
	}

	// end of extra maint plugin panel


	// extra admin panel
	if (strlen(read_config_option('intropage_admin_alert')) > 3) {
		$tmp['data'] = nl2br(read_config_option('intropage_admin_alert'));

		intropage_display_panel(998, 'red', 'Admin alert', $tmp);
	}
	// end of admin panel



	// user changed order - new order is valid until logout
	/*	ted resim az dole, vyse vyresim order a zobrazovani je pak stejne
	if (isset ($_SESSION['intropage_order']) && is_array($_SESSION['intropage_order']))	{
	$order = '';
	foreach ($_SESSION['intropage_order'] as $ord)	{
		$order .= $ord . ',';
	}
	$order = substr ($order,0,-1);

	$panels = db_fetch_assoc  ("select id,panel,priority,fav_graph_id from plugin_intropage_user_setting where user_id = " . $_SESSION['sess_user_id'] . " and (panel !='intropage_favourite_graph' or panel='intropage_favourite_graph' and fav_graph_id is not null) order by field (id,$order)");

		foreach($panels as $xkey => $xvalue) {
			intropage_display_panel($xvalue['id'],$xvalue['alldata']['alarm'],$xvalue['alldata']['name'],$xvalue['alldata']);
	}
	}*/

	if ($display_important_first == 'on') {  // important first
		foreach ($panels as $xkey => $xvalue) {
			if ($xvalue['alldata']['alarm'] == 'red') {
				intropage_display_panel($xvalue['id'], $xvalue['alldata']['alarm'], $xvalue['alldata']['name'], $xvalue['alldata']);
				$panels[$xkey]['displayed'] = true;
			}
		}

		// yellow (errors and warnings)
		if ($display_level == 1 || ($display_level == 2 && !isset($xvalue['displayed']))) {
			foreach ($panels as $xkey => $xvalue) {
				if ($xvalue['alldata']['alarm'] == 'yellow') {
					intropage_display_panel($xvalue['id'], $xvalue['alldata']['alarm'], $xvalue['alldata']['name'], $xvalue['alldata']);
					$panels[$xkey]['displayed'] = true;
				}
			}
		}

		// green (all)
		if ($display_level == 2) {
			foreach ($panels as $xkey => $xvalue) {
				if ($xvalue['alldata']['alarm'] == 'green') {
					intropage_display_panel($xvalue['id'], $xvalue['alldata']['alarm'], $xvalue['alldata']['name'], $xvalue['alldata']);
					$panels[$xkey]['displayed'] = true;
				}
			}

			// grey and without color
			foreach ($panels as $xkey => $xvalue) {
				if (!isset($xvalue['displayed'])) {
					intropage_display_panel($xvalue['id'], $xvalue['alldata']['alarm'], $xvalue['alldata']['name'], $xvalue['alldata']);
					$panels[$xkey]['displayed'] = true;
				}
			}
		}
	} else {	// display only errors/errors and warnings/all - order by priority
		foreach ($panels as $xkey => $xvalue) {
			if (
		($display_level == 2) ||
			($display_level == 1 && ($xvalue['alldata']['alarm'] == 'red' || $xvalue['alldata']['alarm'] == 'yellow')) ||
			($display_level == 0 && $xvalue['alldata']['alarm'] == 'red')) {
				intropage_display_panel($xvalue['id'], $xvalue['alldata']['alarm'], $xvalue['alldata']['name'], $xvalue['alldata']);
			}
		}
	}

	// display debug information in panel
/*
	if ($intropage_debug) {
		unset($value);
		$value['data'] = $debug;
		intropage_display_panel(999, 'grey', 'Debug', $value);
	}
*/
?>
<script>
// display/hide detail
$(document).ready(function () {
 $('.article').hide();
  $('.maxim').click(function(){

    $(this).html( $(this).html() == '<i class="fa fa-window-maximize"></i>' ? '<i class="fa fa-window-minimize"></i>' : '<i class="fa fa-window-maximize"></i>' );
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
});


// enable/disable move panel/copy text
$(document).on('click','#switch_copytext',function() {

    if (!intropage_drag)	{
	$('#obal').sortable('enable');
	$('#switch_copytext').attr('title','Disable panel move/Enable copy text from panel');
	$('.flexchild').css('cursor','move');
	intropage_drag = true;
    }
    else	{
	$('#obal').sortable('disable');
	$('#switch_copytext').attr('title','Enable panel move/Disable copy text from panel');
	$('.flexchild').css('cursor','default');
	intropage_drag = false;

    }
});

function reload_panel (panel_id,by_hand)	{

    $('#panel_'+panel_id).find(".panel_data").css('opacity',0);
    $('#panel_'+panel_id).find('.panel_data').fadeIn('slow').delay(800);

    $.get(urlPath+'plugins/intropage/intropage_ajax.php?autom='+by_hand+'&reload_panel='+panel_id)
    .done(function(data) {
	$('#panel_'+panel_id).find(".panel_data").html(data) ;
	$('#panel_'+panel_id).find(".panel_data").css('opacity',1);
    })
    .fail(function(data) {
	$('#panel_'+panel_id).find(".panel_data").html('Error reading new data') ;
    });
}

function reload_all ()	{
    $('#obal li').each(function() {
        var panel_id = $(this).attr('id').split("_").pop();
        reload_panel (panel_id,false);
    });
}

// autorefresh
if (intropage_autorefresh > 0)	{
    setInterval(reload_all, intropage_autorefresh*1000);
}

// reload single panel
$(document).ready(function() {
    reload_all();

    $(document).on('click','.reload_panel_now',function() {
	var panel_id = $(this).attr('id').split("_").pop();
        reload_panel (panel_id,true);
    });
});
</script>

<?php

	print "<div style='clear: both;'></div>";
	print '</ul>';


	// settings
	echo "<form method='post'>";

	printf("<a href='#' id='switch_copytext' title='Disable panel move/enable copy text from panel'><i class='fa fa-clone'></i></a>\n");
	echo "&nbsp; &nbsp; ";

	echo "<select name='intropage_action' size='1'>";
	echo "<option value='0'>Select action ...</option>";

	$panels = db_fetch_assoc('select t1.panel as panel_name from plugin_intropage_panel as t1 left outer join plugin_intropage_user_setting as t2 on t1.panel = t2.panel where t2.user_id is null order by t1.priority');
	if (sizeof($panels) > 0) {
		// allowed panel?
		//if (read_config_option('intropage_' . $pom) == 'on')	{

		foreach ($panels as $panel) {
			if (db_fetch_cell('select ' . $panel['panel_name'] . ' from user_auth where id=' . $_SESSION['sess_user_id']) == 'on') {
				echo "<option value='addpanel_" . $panel['panel_name'] . "'>Add panel " . ucfirst(str_replace('_', ' ', $panel['panel_name'])) . '</option>';
			} else {
				echo "<option value='addpanel_" . $panel['panel_name'] . "' disabled=\"disabled\">Add panel " . ucfirst(str_replace('_', ' ', $panel['panel_name'])) . ' (admin prohibited)</option>';
			}
		}
	}

	// only submit :-)
	echo "<option value=''>Refresh now</option>";

	if ($autorefresh > 0) {
		echo "<option value='refresh_0'>Autorefresh disable</option>";
	} else {
		echo "<option value='refresh_0' disabled='disabled'>Autorefresh disable</option>";
	}


	if ($autorefresh == 90) {
		echo "<option value='refresh_90' disabled='disabled'>Autorefresh 1 minute</option>";
	} else {
		echo "<option value='refresh_90'>Autorefresh 1 minute</option>";
	}


	if ($autorefresh == 560) {
		echo "<option value='refresh_560' disabled='disabled'>Autorefresh 5 minutes</option>";
	} else {
		echo "<option value='refresh_560'>Autorefresh 5 minutes</option>";
	}


	if ($autorefresh == 3600) {
		echo "<option value='refresh_3600' disabled='disabled'>Autorefresh 1 hour</option>";
	} else {
		echo "<option value='refresh_3600'>Autorefresh 1 hour</option>";
	}



	if (read_user_setting('intropage_display_level') == 0) {
		echo "<option value='displaylevel_0' disabled='disabled'>Display only errors</option>";
	} else {
		echo "<option value='displaylevel_0'>Display only errors</option>";
	}


	if (read_user_setting('intropage_display_level') == 1) {
		echo "<option value='displaylevel_1' disabled='disabled'>Display errors and warnings</option>";
	} else {
		echo "<option value='displaylevel_1'>Display errors and warnings</option>";
	}


	if (read_user_setting('intropage_display_level') == 2) {
		echo "<option value='displaylevel_2' disabled='disabled'>Display all</option>";
	} else {
		echo "<option value='displaylevel_2'>Display all</option>";
	}


	if ($display_important_first == 'on') {
		echo "<option value='important_first' disabled='disabled'>Sort by - red-yellow-green-gray</option>";
		echo "<option value='important_no'>Sort by panel priority</option>";
	} else {
		echo "<option value='important_first'>Sort by - red-yellow-green-gray</option>";
		echo "<option value='important_no' disabled='disabled'>Sort by panel priority</option>";
	}


	if (isset($_SESSION['intropage_changed_order'])) {
		echo "<option value='reset_order'>Reset panel order to default</option>";
	}

	echo "<option value='reset_all'>Reset all to default</option>";
/*
	if ($intropage_debug == 0) {
		echo "<option value='debug_ena'>Enable debug</option>";
	} else {
		echo "<option value='debug_disa'>Disable debug</option>";
	}
*/

	$lopts           = db_fetch_cell('SELECT login_opts FROM user_auth WHERE id=' . $_SESSION['sess_user_id']);
	$lopts_intropage = db_fetch_cell_prepared('SELECT intropage_opts FROM user_auth WHERE id=?', array($_SESSION['sess_user_id']));
	// 0 = console, 1= tab

	// login options can change user group!

	// after login: 1=podle url, 2=console, 3=graphs, 4=intropage tab, 5=intropage in console !!!
	if (!$console_access) {
		if ($lopts < 4) {
			echo "<option value='loginopt_intropage'>Set intropage as default page</option>";
		} else {
			echo "<option value='loginopt_graph'>Set graph as default page</option>";
		}
	}

	echo '</select>';
	echo "<input type='submit' name='intropage_go' value='Go'>";
	
	echo '</form>';
	// end of settings

//	print "<div id='generated'> Generated: " . date('H:i:s') . ' (' . round(microtime(true) - $debug_start)  . 's)</div>';

	echo '</div>'; // konec megaobal

	return true;
}
