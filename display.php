<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2015-2019 Petr Macek                                      |
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
	global $config, $allowed_hosts, $sql_where;

	if (!api_user_realm_auth('intropage.php')) {
		print __('Intropage - permission denied', 'intropage') . '<br/><br/>';
		return false;
	}

	$debug_start = microtime(true);

	$logging = read_config_option('log_verbosity', true);

	$selectedTheme = get_selected_theme();

	if (db_fetch_cell_prepared('SELECT intropage_opts FROM user_auth WHERE id = ?', array($_SESSION['sess_user_id'])) == 1) {  // in tab
		$url_path = $config['url_path'] . 'plugins/intropage/intropage.php';
	} else { // in console
		$url_path = $config['url_path'];
	}

	// actions
	include_once($config['base_path'] . '/plugins/intropage/include/actions.php');

	// functions
	include_once($config['base_path'] . '/plugins/intropage/include/helpers.php');
	include_once($config['base_path'] . '/plugins/intropage/include/data.php');

	// style for panels
	print "<link type='text/css' href='" . $config['url_path'] . "plugins/intropage/themes/common.css' rel='stylesheet'>";

	if (file_exists($config['base_path'] . '/plugins/intropage/themes/' . $selectedTheme . '.css')) {
		print "<link type='text/css' href='" . $config['url_path'] . 'plugins/intropage/themes/' . $selectedTheme . ".css' rel='stylesheet'>";
	}

	// Retrieve user settings and defaults

	$display_important_first = read_user_setting('intropage_display_important_first', read_config_option('intropage_display_important_first'));
	$display_level           = read_user_setting('intropage_display_level', read_config_option('intropage_display_level'));
	$autorefresh             = read_user_setting('intropage_autorefresh', read_config_option('intropage_autorefresh'));

	$maint_days_before = read_config_option('intropage_maint_plugin_days_before');

	$hosts = get_allowed_devices();
	$allowed_hosts = implode(',', array_column($hosts, 'id'));

	// Retrieve access
	$console_access = (db_fetch_assoc_prepared('SELECT realm_id FROM user_auth_realm
		WHERE user_id = ?
		AND user_auth_realm.realm_id=8',
		array($_SESSION['sess_user_id']))) ? true : false;

	// retrieve user setting (and creating if not)
	if (db_fetch_cell_prepared('SELECT count(*) FROM plugin_intropage_user_setting WHERE fav_graph_id IS NULL AND user_id = ?', array($_SESSION['sess_user_id'])) == 0) {
		$all_panel = db_fetch_assoc('SELECT panel,priority FROM plugin_intropage_panel');

		// generating user setting
		foreach ($all_panel as $one) {
			if (db_fetch_cell_prepared('SELECT ' . $one['panel'] . ' FROM user_auth WHERE id = ?', array($_SESSION['sess_user_id'])) == 'on') {
				db_execute_prepared('INSERT INTO plugin_intropage_user_setting
					(user_id, panel, priority)
					VALUES (?, ?, ?)',
					array($_SESSION['sess_user_id'], $one['panel'],$one['priority']));
			}
		}
	} else { // revoke permissions
		$all_panel = db_fetch_assoc('SELECT panel FROM plugin_intropage_user_setting');

		foreach ($all_panel as $one) {
			if (db_fetch_cell_prepared('SELECT ' . $one['panel'] . ' FROM user_auth WHERE id = ?', array($_SESSION['sess_user_id'])) != 'on') {
				db_execute_prepared('DELETE FROM plugin_intropage_user_setting
					WHERE user_id = ?
					AND panel = ?',
					array($_SESSION['sess_user_id'], $one['panel']));
			}
		}
	}

	$order = ' priority desc';
	if (isset($_SESSION['intropage_order']) && is_array($_SESSION['intropage_order'])) {
		$order = ' field (id,';
		$order .= implode(',', $_SESSION['intropage_order']);
		$order .= ')';
	}

	// each favourite graph must have unique name
	// without this fav_graph is overwritten

	$panels = db_fetch_assoc_prepared("SELECT id, panel, priority, fav_graph_id
		FROM plugin_intropage_user_setting
		WHERE user_id = ?
		AND panel != 'intropage_favourite_graph'
		UNION
		SELECT id,concat(panel,'_',fav_graph_id) AS panel, priority, fav_graph_id
		FROM plugin_intropage_user_setting
		WHERE user_id = ?
		AND panel = 'intropage_favourite_graph'
		AND fav_graph_id IS NOT NULL
		ORDER BY $order",
		array($_SESSION['sess_user_id'], $_SESSION['sess_user_id']));

	// retrieve data for all panels
	if (cacti_sizeof($panels)) {
		foreach ($panels as $xkey => $xvalue) {
			$pokus = $xvalue['panel'];
			$start = microtime(true);

			if (isset($xvalue['fav_graph_id'])) { // fav_graph exception
				$panels[$xkey]['alldata'] = intropage_favourite_graph($xvalue['fav_graph_id']);
			} else {	// normal panel
				$panels[$xkey]['alldata'] = $pokus();
			}

			if ($logging >= 5) {
				cacti_log('debug: ' . $pokus . ', duration ' . round(microtime(true) - $start, 2),true,'Intropage');
			}
		}
	}

	// Notice about disable cacti dashboard
	if (read_config_option('hide_console') != 'on')	{
	    print __('You can disable rows above in <b>Settings -> General -> Hide Cacti Dashboard </b> and use the whole page for Intropage<br/><br/>', 'intropage');
	}

	// Intropage Display ----------------------------------

	// $display_important_first = on/off
	// $display_level   =  0 "Only errors", 1 "Errors and warnings", 2 => "All"

	print '<div id="megaobal">';
	print '<ul id="obal">';

	// extra maint plugin panel - always first

	if (db_fetch_cell("SELECT directory FROM plugin_config WHERE directory='maint'")) {
		$start = microtime(true);

		$tmp['data'] = '';

		$schedules = db_fetch_assoc("SELECT * FROM plugin_maint_schedules WHERE enabled='on'");
		if (cacti_sizeof($schedules)) {
			foreach ($schedules as $sc) {
				$t = time();

				switch ($sc['mtype']) {
				case 1:
					if ($t > ($sc['stime'] - $maint_days_before) && $t < $sc['etime']) {
						$tmp['data'] .= '<b>' . date('d. m . Y  H:i', $sc['stime']) . ' - ' . date('d. m . Y  H:i', $sc['etime']) .
								' - ' . $sc['name'] . ' (One time)<br/>Affected hosts:</b> ';

						$hosts = db_fetch_assoc_prepared('SELECT description FROM host
							INNER JOIN plugin_maint_hosts
							ON host.id=plugin_maint_hosts.host
							WHERE schedule = ?',
							array($sc['id']));

						if (cacti_sizeof($hosts)) {
							foreach ($hosts as $host) {
								$tmp['data'] .= $host['description'] . ', ';
							}
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

						$hosts = db_fetch_assoc_prepared('SELECT description FROM host
							INNER JOIN plugin_maint_hosts
							ON host.id=plugin_maint_hosts.host
							WHERE schedule = ?',
							array($sc['id']));

						if (cacti_sizeof($hosts)) {
							foreach ($hosts as $host) {
								$tmp['data'] .= $host['description'] . ', ';
							}
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

		if ($logging >= 5) {
			cacti_log('debug: maint, duration ' . round(microtime(true) - $start, 2),true,'Intropage');
		}
	}

	// end of extra maint plugin panel

	// extra admin panel
	if (strlen(read_config_option('intropage_admin_alert')) > 3) {
		$tmp['data'] = nl2br(read_config_option('intropage_admin_alert'));

		intropage_display_panel(998, 'red', 'Admin alert', $tmp);
	}
	// end of admin panel

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
		if (intropage_autorefresh > 0)	{
			if (refresh !== null) {
				clearTimeout(refresh);
			}

			refresh = setInterval(reload_all, intropage_autorefresh*1000);
		}

		$('.article').hide();

		$(window).resize(function() {
			resizeObal();
		});

		$('#obal').sortable({
			update: function( event, ui ) {
				//console.log($('#obal'));
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

		$('.maxim').off('click').on('click', function() {
			$(this).html( $(this).html() == '<i class="fa fa-window-maximize"></i>' ? '<i class="fa fa-window-minimize"></i>' : '<i class="fa fa-window-maximize"></i>' );
			$(this).nextAll('.article').first().toggle();

			if ($('#' + this.name).css('display') == 'none') {
				$('#' + this.name).css('display','block');
				$(this).attr('title','Hide details');
			} else {
				$('#' + this.name).css('display','none');
				$(this).attr('title','Show details');
			}
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

			reload_panel(panel_id,true);
		});
	}

	function resizeObal() {
		if (navigator.userAgent.search('MSIE 10') > 0 ||
			(navigator.userAgent.search('Trident') > 0 && navigator.userAgent.search('rv:11') > 0 )) {
			$('#obal').css('max-width',($(window).width()-190));
		}
	}

	function reload_panel(panel_id,by_hand) {
		$.get(urlPath+'plugins/intropage/intropage_ajax.php?autom='+by_hand+'&reload_panel='+panel_id)
		.done(function(data) {
			$('#panel_'+panel_id).find('.panel_data').html(data) ;
		})
		.fail(function(data) {
			$('#panel_'+panel_id).find('.panel_data').html('<?php print __('Error reading new data', 'intropage');?>') ;
		});
	}

	function reload_all()	{
		$('#obal li').each(function() {
			var panel_id = $(this).attr('id').split('_').pop();
			reload_panel(panel_id,false);
		});
	}

	</script>

	<?php

	print "<div style='clear: both;'></div>";
	print '</ul>';

	// settings
	print "<form method='post'>";

	print "<a href='#' id='switch_copytext' title='" . __esc('Disable panel move/enable copy text from panel', 'intropage') . "'><i class='fa fa-clone'></i></a>";
	print '&nbsp; &nbsp; ';

	print "<select name='intropage_action' size='1'>";
	print '<option value="0">' . __('Select action ...', 'intropage') . '</option>';

	$panels = db_fetch_assoc_prepared('SELECT panel FROM plugin_intropage_panel
	    WHERE panel NOT IN
	    (SELECT panel FROM plugin_intropage_user_setting WHERE user_id = ?)
	    ORDER BY priority',
		array($_SESSION['sess_user_id']));

	if (cacti_sizeof($panels)) { // allowed panel?
		foreach ($panels as $panel) {
			if (db_fetch_cell_prepared('SELECT ' . $panel['panel'] . ' FROM user_auth WHERE id = ?', array($_SESSION['sess_user_id'])) == 'on') {
				print "<option value='addpanel_" . $panel['panel'] . "'>" . __('Add panel %s', ucwords(str_replace('_', ' ', $panel['panel'])), 'intropage') . '</option>';
			} else {
				print "<option value='addpanel_" . $panel['panel'] . "' disabled=\"disabled\">" . __('Add panel %s %s', ucwords(str_replace('_', ' ', $panel['panel'])), '(admin prohibited)', 'intropage') . '</option>';
			}
		}
	}

	// only submit :-)
	print "<option value=''>" . __('Refresh Now', 'intropage') . '</option>';

	if ($autorefresh > 0) {
		print "<option value='refresh_0'>" . __('Autorefresh Disabled', 'intropage') . '</option>';
	} else {
		print "<option value='refresh_0' disabled='disabled'>" . __('Autorefresh Disabled', 'intropage') . '</option>';
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

	if (read_user_setting('intropage_display_level') == 0) {
		print "<option value='displaylevel_0' disabled='disabled'>" . __('Display only Errors', 'intropage') . '</option>';
	} else {
		print "<option value='displaylevel_0'>" . __('Display only Errors', 'intropage') . '</option>';
	}

	if (read_user_setting('intropage_display_level') == 1) {
		print "<option value='displaylevel_1' disabled='disabled'>" . __('Display Errors and Warnings', 'intropage') . '</option>';
	} else {
		print "<option value='displaylevel_1'>" . __('Display Errors and Warnings', 'intropage') . '</option>';
	}

	if (read_user_setting('intropage_display_level') == 2) {
		print "<option value='displaylevel_2' disabled='disabled'>" . __('Display All', 'intropage') . '</option>';
	} else {
		print "<option value='displaylevel_2'>" . __('Display All', 'intropage') . '</option>';
	}

	if ($display_important_first == 'on') {
		print "<option value='important_first' disabled='disabled'>" . __('Sort by - red-yellow-green-gray', 'intropage') . '</option>';
		print "<option value='important_no'>" . __('Sort by panel priority', 'intropage') . '</option>';
	} else {
		print "<option value='important_first'>" . __('Sort by - red-yellow-green-gray', 'intropage') . '</option>';
		print "<option value='important_no' disabled='disabled'>" . __('Sort by panel Priority', 'intropage') . '</option>';
	}

	if (isset($_SESSION['intropage_changed_order'])) {
		print "<option value='reset_order'>" . __('Reset panel Order to Default', 'intropage') . '</option>';
	}

	print "<option value='reset_all'>" . __('Reset All to Default', 'intropage') . '</option>';

	$lopts           = db_fetch_cell_prepared('SELECT login_opts FROM user_auth WHERE id = ?', array($_SESSION['sess_user_id']));
	$lopts_intropage = db_fetch_cell_prepared('SELECT intropage_opts FROM user_auth WHERE id = ?', array($_SESSION['sess_user_id']));

	// 0 = console, 1= tab

	// login options can change user group!

	// after login: 1=podle url, 2=console, 3=graphs, 4=intropage tab, 5=intropage in console !!!
	if (!$console_access) {
		if ($lopts < 4) {
			print "<option value='loginopt_intropage'>" . __('Set intropage as default page', 'intropage') . '</option>';
		} else {
			print "<option value='loginopt_graph'>" . __('Set graph as default page', 'intropage') . '</option>';
		}
	}

	print '</select>';
	print "<input type='submit' name='intropage_go' value='" . __esc('Go', 'intropage') . "'>";

	print '</form>';
	// end of settings

	print '</div>'; // end of megaobal

	return true;
}

