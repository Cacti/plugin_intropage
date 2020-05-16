<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2015-2020 Petr Macek                                      |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | https://github.com/xmacan/                                              |
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

function display_information() {
	global $config, $sql_where;

	include_once($config['base_path'] . '/plugins/intropage/include/database.php');

	intropage_upgrade_database();

	if (!api_user_realm_auth('intropage.php')) {
		print __('Intropage - permission denied', 'intropage') . '<br/><br/>';
		return false;
	}

	$debug_start = microtime(true);

	$logging = read_config_option('log_verbosity', true);

	$selectedTheme = get_selected_theme();

	if (get_filter_request_var('dashboard_id')) {
	    $_SESSION['dashboard_id'] = get_filter_request_var('dashboard_id');
	}

	if (empty($_SESSION['dashboard_id'])) {
	    $_SESSION['dashboard_id'] = 1; 
	}
	
	if (empty($_SESSION['login_opts']))	{   // potrebuju to mit v session, protoze treba mi zmeni z konzole na tab a pak spatne vykresluju
		$login_opts = db_fetch_cell_prepared('SELECT login_opts
			FROM user_auth
			WHERE id = ?',
			array($_SESSION['sess_user_id']));

		$_SESSION['login_opts'] = $login_opts;
	}

	if ($_SESSION['login_opts'] == 4) {  // in tab
		$url_path = $config['url_path'] . 'plugins/intropage/intropage.php';
	} else { // in console
		$url_path = $config['url_path'];
	}

	// actions
	include_once($config['base_path'] . '/plugins/intropage/include/actions.php');

	// functions
	include_once($config['base_path'] . '/plugins/intropage/include/functions.php');
	//include_once($config['base_path'] . '/plugins/intropage/include/data.php');

	// Retrieve user settings and defaults
	$display_important_first = read_user_setting('intropage_display_important_first', read_config_option('intropage_display_important_first'));
	$autorefresh             = read_user_setting('intropage_autorefresh', read_config_option('intropage_autorefresh'));

	// number of dashboards
	if (!user_setting_exists('intropage_number_of_dashboards',$_SESSION['sess_user_id'])) {
		set_user_setting('intropage_number_of_dashboards',1);
		$number_of_dashboards = 1;
	}
	else {
		$number_of_dashboards = read_user_setting('intropage_number_of_dashboards',1);
	}
	

/*
	$hosts = get_allowed_devices();
	if (count($hosts) > 0) {
		$_SESSION['allowed_hosts'] = implode(',', array_column($hosts, 'id'));
	} else {
		$_SESSION['allowed_hosts'] = false;
	}
*/

	// Retrieve access
	$console_access = api_plugin_user_realm_auth('index.php');


	// each favourite graph must have unique name
	// without this fav_graph is overwritten

// !!! tady musim jeste resit dashboard_id !!!!
/*
	$panels = db_fetch_assoc_prepared("SELECT *
		FROM plugin_intropage_panel_data
		WHERE dashboard_id = ? AND user_id in (0,?) 
		AND panel_id != 'intropage_favourite_graph'
		UNION
		SELECT *
		FROM plugin_intropage_panel_data
		WHERE dashboard_id = ? AND user_id = ?
		AND panel_id = 'intropage_favourite_graph'
		AND fav_graph_id IS NOT NULL
		ORDER BY priority desc",
		array($_SESSION['dashboard_id'], $_SESSION['sess_user_id'], $_SESSION['dashboard_id'], $_SESSION['sess_user_id']));

*/

//!!!! ted tu vubec neni priorita
	$panels = db_fetch_assoc_prepared("SELECT t1.*
		FROM plugin_intropage_panel_data as t1
		join plugin_intropage_panel_dashboard as t2
		on t1.id = t2.panel_id  
		WHERE t1.user_id in (0,?) AND t2.dashboard_id = ? 
		AND t1.panel_id != 'intropage_favourite_graph'
		UNION
		SELECT t3.*
		FROM plugin_intropage_panel_data as t3
		join plugin_intropage_panel_dashboard as t4
		on t3.id = t4.panel_id  
		WHERE t3.user_id = ? and t4.dashboard_id = ?
		AND t3.panel_id = 'intropage_favourite_graph'
		AND t3.fav_graph_id IS NOT NULL
		",
		array( $_SESSION['sess_user_id'], $_SESSION['dashboard_id'], $_SESSION['sess_user_id'], $_SESSION['dashboard_id']));
//echo db_error();


//!!! tohle je tu asi uplne zbytecne, contac je blbost
/*
	foreach ($panels as &$one) {	// remove not allowed panels
	    if (db_fetch_cell_prepared("SELECT concat('intropage_','?') FROM user_auth WHERE id = '?'", array($one['panel_id'],$_SESSION['sess_user_id'])) != 'on') {
//		$one['dashboard_id'] = 0;	// 0 = no display, 1,2,.... page id
		unset ($one);
		//!!!! tohle otestovat - zakazane panely
	    }
	    
	}
*/


	// retrieve data for all panels
/*
	 include_once($config['base_path'] . '/plugins/intropage/include/data.php');
	
	if (cacti_sizeof($panels)) {
		foreach ($panels as $xkey => $xvalue) {
			$pokus = $xvalue['panel_id'];

			if (isset($xvalue['fav_graph_id'])) { // fav_graph exception
				$panels[$xkey]['alldata'] = intropage_favourite_graph($xvalue['fav_graph_id']);
			} else {	// normal panel
				$panels[$xkey]['alldata'] = $pokus(true,false);
			}

		}
	}
*/	
	//!!!! tady jsem skoncil. Musim zobrazit panely se spravnym dashboard_id. Asi udelat funkci, ktera vykresli jen prazdne panely
	// !!! pak zkusit, ze bych tady jen vykreslil prazdne panely a zacal je hned obcerstvovat pomoci javascriptu

	// Notice about disable cacti dashboard
	if (read_config_option('hide_console') != 'on')	{
	    print __('You can disable rows above in <b>Configure -> Settings -> General -> Hide Cacti Dashboard</b> and use the whole page for Intropage ', 'intropage');
	    print '<a href="' . $config['url_path'] . 'settings.php"><i class="fas fa-link"></i></a><br/><br/>';
	}

	// Intropage Display ----------------------------------

	// overlay div for detail
	print '<div id="overlay"><div id="overlay_detail"></div></div>';

	// switch dahsboards and form
	print '<div>';
	print '<div class="float_left">';
	for ($f = 1; $f <= $number_of_dashboards; $f++)	{
	    if ($f == $_SESSION['dashboard_id']) {
		print '<a class="db_href db_href_active" href="?dashboard_id=' . $f . '">' . $f . '</a>';
	    }
	    else {
		print '<a class="db_href" href="?dashboard_id=' . $f . '">' . $f . '</a>';	    
	    }
	}
	//read_user_setting('intropage_dashboard_name',1)
	print '</div>';
	print '<div class="float_right">';	
////////////
	// settings
	print "<form method='post'>";

	print "<a href='#' id='switch_copytext' title='" . __esc('Disable panel move/enable copy text from panel', 'intropage') . "'><i class='fa fa-clone'></i></a>";
	print '&nbsp; &nbsp; ';

	print "<select name='intropage_action' size='1' onchange='this.form.submit();'>";
	print '<option value="0">' . __('Select action ...', 'intropage') . '</option>';


	if ($number_of_dashboards == 1) {
	    print '<option value="addpage_2">' . __('Add second dashboard', 'intropage') . '</option>';
	}
	if ($number_of_dashboards == 2) {
	    print '<option value="addpage_3">' . __('Add third dashboard', 'intropage') . '</option>';
	}
	
	if ($_SESSION['dashboard_id'] > 1) {
	    print '<option value="removepage_' .  $_SESSION['dashboard_id'] . '">' . __('Remove current dashboard', 'intropage') . '</option>';
	}

//!!! tady predtim byla i priorita

/* 
	$panels = db_fetch_assoc_prepared('select panel_id from plugin_intropage_panel_definition where panel_id not in 
			(select t1.panel_id from plugin_intropage_panel_data as t1 join plugin_intropage_panel_dashboard as t2 
			on t1.panel_id=t2.panel_id where t2.user_id= ?)',
			array($_SESSION['sess_user_id']));
*/

	$add_panels = db_fetch_assoc_prepared('select panel_id from plugin_intropage_panel_definition where panel_id  not in (select t1.panel_id 
		from plugin_intropage_panel_data as t1 join  plugin_intropage_panel_dashboard as t2 on t1.id=t2.panel_id where  t2.user_id = ?)',			
		array($_SESSION['sess_user_id']));

	if (cacti_sizeof($add_panels)) {
//	echo "</select>";
		foreach ($add_panels as $panel) {
			$uniqid = db_fetch_cell_prepared('select id from plugin_intropage_panel_data 
			where user_id in (0, ?) and panel_id = ?',
			array($_SESSION['sess_user_id'],$panel['panel_id']));

			if ($panel['panel_id'] != 'maint' && $panel['panel_id'] != 'admin_alert')	{
				if (db_fetch_cell_prepared('SELECT count(*) FROM plugin_intropage_panel_data 
						WHERE user_id  in (0, ?) and panel_id= ? ',
						array($_SESSION['sess_user_id'],$panel['panel_id'])) == 0) {
					print "<option value='addpanel_" .  $uniqid . "' disabled=\"disabled\">" . __('Add panel %s %s', ucwords(str_replace('_', ' ', $panel['panel_id'])), '(wait one poller cycle)', 'intropage') . '</option>';
				}
				elseif (db_fetch_cell_prepared('SELECT intropage_' . $panel['panel_id'] . ' FROM user_auth 
						WHERE id = ?', array($_SESSION['sess_user_id'])) == 'on') {
					print "<option value='addpanel_" . $uniqid . "'>" . __('Add panel %s', ucwords(str_replace('_', ' ', $panel['panel_id'])), 'intropage') . '</option>';

				} else {
					print "<option value='addpanel_" .  $uniqid . "' disabled=\"disabled\">" . __('Add panel %s %s', ucwords(str_replace('_', ' ', $panel['panel_id'])), '(admin prohibited)', 'intropage') . '</option>';
				}
			}

		}
	}

	// only submit :-)
	print "<option value=''>" . __('Refresh Now', 'intropage') . '</option>';

	if ($autorefresh > 0 || $autorefresh == -1) {
		print "<option value='refresh_0'>" . __('Autorefresh Disabled', 'intropage') . '</option>';
	} else {
		print "<option value='refresh_0' disabled='disabled'>" . __('Autorefresh Disabled', 'intropage') . '</option>';
	}

	if ($autorefresh == -1) {
		print "<option value='refresh_-1' disabled='disabled'>" . __('Autorefresh automatic by poller', 'intropage') . '</option>';
	} else {
		print "<option value='refresh_-1'>" . __('Autorefresh automatic by poller', 'intropage') . '</option>';
	}

	if ($autorefresh == 60) {
		print "<option value='refresh_60' disabled='disabled'>" . __('Autorefresh 1 Minute', 'intropage') . '</option>';
	} else {
		print "<option value='refresh_60'>" . __('Autorefresh 1 Minute', 'intropage') . '</option>';
	}

	if ($autorefresh == 300) { 
		print "<option value='refresh_300' disabled='disabled'>" . __('Autorefresh 5 Minutes', 'intropage') . '</option>';
	} else {
		print "<option value='refresh_300'>" . __('Autorefresh 5 Minutes', 'intropage') . '</option>';
	}

	if ($autorefresh == 3600) {
		print "<option value='refresh_3600' disabled='disabled'>" . __('Autorefresh 1 Hour', 'intropage') . '</option>';
	} else {
		print "<option value='refresh_3600'>" . __('Autorefresh 1 Hour', 'intropage') . '</option>';
	}

	if ($display_important_first == 'on') {
		print "<option value='important_first' disabled='disabled'>" . __('Sort by - red-yellow-green-gray', 'intropage') . '</option>';
		print "<option value='important_no'>" . __('Sort by user preference', 'intropage') . '</option>';
	} else {
		print "<option value='important_first'>" . __('Sort by - red-yellow-green-gray', 'intropage') . '</option>';
		print "<option value='important_no' disabled='disabled'>" . __('Sort by user preference', 'intropage') . '</option>';
	}

	print "<option value='reset_all'>" . __('Reset All to Default', 'intropage') . '</option>';

	$lopts           = db_fetch_cell_prepared('SELECT login_opts FROM user_auth WHERE id = ?', array($_SESSION['sess_user_id']));
//	$lopts_intropage = db_fetch_cell_prepared('SELECT intropage_opts FROM user_auth WHERE id = ?', array($_SESSION['sess_user_id']));

	// 0 = console, 1= tab
	// login options can change user group!
	// after login: 1=url, 2=console, 3=graphs, 4=intropage tab, 5=intropage in console !!!

	if (!$console_access) {
		//
		if ($lopts < 4) {	// intropage is not default
        		print "<option value='loginopt_tab'>" . __('Set intropage as default login page', 'intropage') . '</option>';
                }

		if ($lopts == 4)  {
			print "<option value='loginopt_graph'>" . __('Set graph as default login page', 'intropage') . '</option>';
		}
	}
	else	{	// intropage in console or in tab
		if ($lopts == 4) {	// in tab
        		print "<option value='loginopt_console'>" . __('Display intropage in console', 'intropage') . '</option>';
                }
		else {
			print "<option value='loginopt_tab'>" . __('Display intropage in tab as default page', 'intropage') . '</option>';
		}
	}

	print '</select>';
//	print "<input type='submit' name='intropage_go' value='" . __esc('Go', 'intropage') . "'>";

	print '</form>';
	// end of settings


////////////	
	print '</div>';	
	print '<br style="clear: both" />';
	print '</div>';	

	print '<div id="megaobal">';
	print '<ul id="obal">';

	if (cacti_sizeof($panels) == 0)	{
		print '<div><b>' . __('Welcome!') . '</b><br/><br/>';
		print __('You can add panels in two ways:') . '<br/>';
		print ' - ' . __('select prepared panels from menu') . '<br/>';
		print ' - ' . __('add any graph - click to \'Eye Icon\' which is next to each graph. Graph with actual timespan will be added to current dashboard') . '<br/><br/>';
		print __('You can add more dashboards from menu, too') . '<br/><br/></div>';
	}


//!!!! tady bude podminka - kdyz dashboard_id=1

	// extra maint plugin panel - always first

	if (db_fetch_cell("SELECT directory FROM plugin_config WHERE directory='maint'")) {

		$row = db_fetch_row("SELECT id, data FROM plugin_intropage_panel_data WHERE panel_id='maint'");
		if (strlen($row['data']) > 20) {
			intropage_display_panel($row['id']);
		}
	}
	// end of extra maint plugin panel

	// extra admin panel
	if (strlen(read_config_option('intropage_admin_alert')) > 3) {
		$id = db_fetch_cell("SELECT id FROM plugin_intropage_panel_data WHERE panel_id='admin_alert'");
		if ($id) {
			intropage_display_panel($id);
		}

//		$tmp['data'] = nl2br(read_config_option('intropage_admin_alert'));
//		intropage_display_panel(998, 'red', 'Admin alert', $tmp);
	}
	// end of admin panel


	if ($display_important_first == 'on') {  // important first
		foreach ($panels as $xkey => $xvalue) {
			if ($xvalue['alarm'] == 'red') {
				intropage_display_panel($xvalue['id']);
				$panels[$xkey]['displayed'] = true;
			}
		}

		// yellow (errors and warnings)
		foreach ($panels as $xkey => $xvalue) {
			if ($xvalue['alarm'] == 'yellow') {
				intropage_display_panel($xvalue['id']);
				$panels[$xkey]['displayed'] = true;
			}
		}

		// green (all)
			foreach ($panels as $xkey => $xvalue) {
				if ($xvalue['alarm'] == 'green') {
					intropage_display_panel($xvalue['id']);
					$panels[$xkey]['displayed'] = true;
				}
			}

		// gray and without color
		foreach ($panels as $xkey => $xvalue) {
			if (!isset($xvalue['displayed'])) {
				intropage_display_panel($xvalue['id']);
				$panels[$xkey]['displayed'] = true;
			}
		}

	} else {	// display only errors/errors and warnings/all - order by priority
		foreach ($panels as $xkey => $xvalue) {
			intropage_display_panel($xvalue['id']);
		}
	}

	?>
	<script type='text/javascript'>

	var refresh;
	var intropage_autorefresh = <?php print $autorefresh;?>;
	var intropage_drag = true;

	// display/hide detail
	$(function () {
		resizeObal();

		initPage();

		reload_all();
	});

	function initPage() {
		// autorefresh
		if (intropage_autorefresh > 0) {
			if (refresh !== null) {
				clearTimeout(refresh);
			}

			refresh = setInterval(reload_all, intropage_autorefresh*1000);
		}

		// automatic autorefresh after poller end
		if (intropage_autorefresh == -1) {
			if (refresh !== null) {
				clearTimeout(refresh);
			}
			setTimeout(function() {		// fix first double load
			    refresh = setInterval(testPoller, 10000);
			},30000);
		}


		$('.article').hide();

		$(window).resize(function() {
			resizeObal();
		});

		$('#obal').sortable({
			update: function( event, ui ) {	// change order
				var xdata = new Array();
				$('#obal li').each(function() {
					xdata.push($(this).attr('id'));
				});

				$.get('<?php print $url_path;?>', { xdata:xdata, intropage_action:'order' });
			}
		});

		$('#sortable').disableSelection();

		$('.droppanel').click(function(event) {
			event.preventDefault();
			panel_div_id = $(this).attr('data-panel');
			$('#'+panel_div_id).remove();
			$.get($(this).attr('href'));
		});

		// enable/disable move panel/copy text
		$('#switch_copytext').off('click').on('click', function() {
			if (!intropage_drag) {
				$('#obal').sortable('enable');
				$('#switch_copytext').attr('title', '<?php print __esc('Disable panel move/Enable copy text from panel', 'intropage');?>');
				$('.flexchild').css('cursor','move');
				intropage_drag = true;
			} else {
				$('#obal').sortable('disable');
				$('#switch_copytext').attr('title', '<?php print __esc('Enable panel move/Disable copy text from panel', 'intropage');?>');
				$('.flexchild').css('cursor','default');
				intropage_drag = false;
			}
		});

		// reload single panel function
		$('.reload_panel_now').off('click').on('click', function() {
			if ($(this).data('lastClick') + 1000 > new Date().getTime()) {
				e.stopPropagation();
				return false;
			}

			$(this).data('lastClick', new Date().getTime());

			var panel_id = $(this).attr('id').split('_').pop();

			reload_panel(panel_id, true, false);
		});
	}

	function testPoller() {
		$.get(urlPath+'plugins/intropage/intropage_ajax.php?&autoreload=true')
		.done(function(data) {
			if (data == 1)	{
			    $('#obal li').each(function() {
				var panel_id = $(this).attr('id').split('_').pop();
				reload_panel(panel_id, false, false);
			    });
			}
			//     reload_all();  - it is without reload effect
		});
	}

	function resizeObal() {
		if (navigator.userAgent.search('MSIE 10') > 0 ||
			(navigator.userAgent.search('Trident') > 0 && navigator.userAgent.search('rv:11') > 0 )) {
			$('#obal').css('max-width',($(window).width()-190));
		}
	}

	function reload_panel(panel_id, forced_update, refresh) {
		if (!refresh) {
			$('#panel_'+panel_id).find('.panel_data').css('opacity',0);
			$('#panel_'+panel_id).find('.panel_data').fadeIn('slow');
		}

		$.get(urlPath+'plugins/intropage/intropage_ajax.php?force='+forced_update+'&reload_panel='+panel_id)
		.done(function(data) {
			$('#panel_'+panel_id).find('.panel_data').html(data) ;

			if (!refresh) {
				$('#panel_'+panel_id).find('.panel_data').css('opacity',1);
			}
		})
		.fail(function(data) {
			$('#panel_'+panel_id).find('.panel_data').html('<?php print __('Error reading new data', 'intropage');?>');
		});
	}

	function reload_all()	{
		$('#obal li').each(function() {
			var panel_id = $(this).attr('id').split('_').pop();
			reload_panel(panel_id, false, true);
		});
	}

	// detail to the new window
	$('.maxim').click(function(event) {
   	    event.preventDefault();
	    panel_id = $(this).attr('detail-panel');

		$.get(urlPath+'plugins/intropage/intropage_ajax.php?detail_panel='+panel_id, function(data) {
			$('#overlay_detail').html(data);
			width = $('#overlay_detail').textWidth() + 150;
			windowWidth = $(window).width();
			if (width > 1200) {
				width = 1200;
			}

			if (width > windowWidth) {
				width = windowWidth - 50;
			}

			$('#overlay').dialog({
				modal: true,
				autoOpen: true,
				buttons: [
					{
						text: '<?php print __('Close', 'intropage');?>',
						click: function() {
							$(this).dialog('destroy');
							$('#overlay_detail').empty();
						},
						icon: 'ui-icon-heart'
					}
				],
				width: width,
				maxHeight: 650,
				resizable: true,
				title: '<?php print __('Panel Details', 'intropage');?>',
			});

			$('#block').click(function() {
				$('#overlay').dialog('close');
			});
		});
	});

	</script>

	<?php

	print "<div style='clear: both;'></div>";
	print '</ul>';


	print '</div>'; // end of megaobal

	return true;
}

