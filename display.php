<?php
/* vim: ts=4
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2023 The Cacti Group, Inc.                           |
 | Copyright (C) 2004-2023 Petr Macek                                      |
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
	global $config, $sql_where, $login_opts, $panels, $registry, $trend_timespans;

	include_once($config['base_path'] . '/plugins/intropage/include/functions.php');
	include_once($config['base_path'] . '/plugins/intropage/include/database.php');

	intropage_upgrade_database();

	$panels = initialize_panel_library();

	$login_opts = get_login_opts();

	if (!api_user_realm_auth('intropage.php')) {
		raise_message('intropage_permissions', __('Intropage - Permission Denied', 'intropage'), MESSAGE_LEVEL_ERROR);
		exit;
	}

	$debug_start = microtime(true);

	$logging = read_config_option('log_verbosity', true);

	// default actual user permissions
	$user_panels = cacti_sizeof(get_allowed_panels());

	if ($user_panels === false) {
		db_execute_prepared('INSERT INTO plugin_intropage_user_auth
			(user_id)
			VALUES (?)',
			array($_SESSION['sess_user_id']));

		$user_panels = 0;
	}

	$selectedTheme = get_selected_theme();

	if (get_filter_request_var('dashboard_id') > 0) {
	    $_SESSION['dashboard_id'] = get_filter_request_var('dashboard_id');
	} elseif (empty($_SESSION['dashboard_id'])) {
	    $_SESSION['dashboard_id'] = 1;
		set_request_var('dashboard_id', 1);
	} else {
		set_request_var('dashboard_id', $_SESSION['dashboard_id']);
	}

	$dashboard_id = get_request_var('dashboard_id');

	// Retrieve user settings and defaults
	$display_important_first = read_user_setting('intropage_display_important_first', read_config_option('intropage_display_important_first'));
	$display_wide            = read_user_setting('intropage_display_wide', read_config_option('intropage_display_wide'));
	$autorefresh             = read_user_setting('intropage_autorefresh', read_config_option('intropage_autorefresh'));
	$important_period        = read_user_setting('intropage_important_period', read_config_option('intropage_important_period'));
	$timespan                = read_user_setting('intropage_timespan', read_config_option('intropage_timespan'));
	$number_of_lines         = read_user_setting('intropage_number_of_lines', read_config_option('intropage_number_of_lines'));

	// number of dashboards
	$number_of_dashboards = db_fetch_cell_prepared('SELECT COUNT(*)
		FROM plugin_intropage_dashboard
		WHERE user_id = ?',
		array($_SESSION['sess_user_id']));

	// console access
	$console_access = api_plugin_user_realm_auth('index.php');

	// remove admin prohibited panels
	$panels = db_fetch_assoc_prepared ('SELECT pd.panel_id AS panel_name, pd.id AS id
		FROM plugin_intropage_panel_data AS pd
		INNER JOIN plugin_intropage_panel_dashboard AS pda
		ON pd.id = pda.panel_id
		WHERE pda.user_id = ?
		AND pda.dashboard_id = ?',
		array($_SESSION['sess_user_id'], $dashboard_id));

	if (cacti_sizeof($panels)) {

		$removed = 0;

		foreach ($panels as $one) {
			$allowed = is_panel_allowed($one['panel_name']);

			if (!$allowed) {
				db_execute_prepared('DELETE FROM plugin_intropage_panel_dashboard
					WHERE user_id = ?
					AND dashboard_id = ?
					AND panel_id = ?',
					array($_SESSION['sess_user_id'], $dashboard_id, $one['id']));

				db_execute_prepared('DELETE FROM plugin_intropage_panel_data
					WHERE user_id = ?
					AND panel_id = ?',
					array($_SESSION['sess_user_id'], $one['id']));

				$removed++;
			}
		}
		
		if ($removed > 0) {
			raise_message('intropage_permissions', __('One or more panels was removed due to insuficient permissions. Contact administrator.', 'intropage'), MESSAGE_LEVEL_ERROR);
			cacti_log('INTROPAGE WARNING: One or more panels was removed to user ' . $_SESSION['sess_user_id'] . ' due to insuficient permissons');
		}
	}

	// User allowed panels
	$panels = db_fetch_assoc_prepared("SELECT pd.*
		FROM plugin_intropage_panel_data AS pd
		INNER JOIN plugin_intropage_panel_dashboard AS pda
		ON pd.id = pda.panel_id
		WHERE pd.user_id in (0, ?)
		AND pda.dashboard_id = ?
		AND pd.panel_id != 'favourite_graph'
		UNION
		SELECT t3.*
		FROM plugin_intropage_panel_data as t3
		INNER JOIN plugin_intropage_panel_dashboard AS t4
		ON t3.id = t4.panel_id
		WHERE t3.user_id = ?
		AND t4.dashboard_id = ?
		AND t3.panel_id = 'favourite_graph'
		AND t3.fav_graph_id IS NOT NULL
		ORDER BY priority DESC",
		array(
			$_SESSION['sess_user_id'],
			$dashboard_id,
			$_SESSION['sess_user_id'],
			$dashboard_id
		)
	);

	// remove prohibited panels (for common panels (user_id=0))
	foreach ($panels as $key => $value) {
		if ($value['user_id'] == 0) {
			$allowed = is_panel_allowed($value['panel_id']);

			if (!$allowed) {
				unset ($panels[$key]);
			} else {
				// user has permission but no active panel
				$upanels = db_fetch_cell_prepared('SELECT COUNT(*)
					FROM plugin_intropage_panel_dashboard
					WHERE user_id = ?
					AND dashboard_id = ?
					AND panel_id = ?',
					array($_SESSION['sess_user_id'], $dashboard_id, $value['id']));

				if ($upanels == 0) {
					unset ($panels[$key]);
				}
			}
		}
	}

	// Notice about disable cacti dashboard
	if (read_config_option('hide_console') != 'on') {
	    print '<table class="cactiTable"><tr><td class="textAreaNotes">' . __('You can disable rows above in <b>Configure > Settings > General > Hide Cacti Dashboard</b> and use the whole page for Intropage ', 'intropage');
	    print '<a class="pic" href="' . $config['url_path'] . 'settings.php"><i class="intro_glyph fas fa-link"></i></a></td></tr></table></br>';
	}

	$dashboards = array_rekey(
		db_fetch_assoc_prepared ('SELECT dashboard_id, name
			FROM plugin_intropage_dashboard
			WHERE user_id = ?
			ORDER BY dashboard_id',
			array($_SESSION['sess_user_id'])),
		'dashboard_id', 'name'
	);

	if (!cacti_sizeof($dashboards)) {
		$dashboard_id  = 1;
		$dashboards[1] = __('Default', 'intropage');

		$_SESSION['dashboard_id'] = 1;

		db_execute_prepared('INSERT INTO plugin_intropage_dashboard
			(user_id, dashboard_id, name)
			VALUES (?, ?, ?)',
			array($_SESSION['sess_user_id'], $dashboard_id, $dashboards[1]));
	}

	// wide or normal number of panels on line
	if ($display_wide == 'on') {
		$width_quarter = 'calc(25% - 1em)';
		$width_third = 'calc(33% - 1em)';
		$width_half = 'calc(50% - 1em)';
	} else {
		$width_quarter = 'calc(33% - 1em)';
		$width_third = 'calc(50% - 1em)';
		$width_half = 'calc(66% - 1em)';
	}

	// Intropage Display ----------------------------------

	// overlay div for detail
	print '<div id="overlay"><div id="overlay_detail"></div></div>';

	// switch dahsboards and form
	print '<div>';
	print '<div class="float_left">';
	print "<div class='tabs'><nav><ul>";

	if (cacti_sizeof($dashboards)) {
		foreach ($dashboards as $dbid => $db_name) {
			print "<li><a class='tab pic" . ($dbid == $dashboard_id ? " selected'" : "'") .
				" href='" . html_escape($config['url_path'] .
				($login_opts == 4 ? 'plugins/intropage/intropage.php?':'index.php?') .
				'dashboard_id=' . $dbid . '&header=false') .
				"'>" . html_escape($db_name) . '</a></li>';
		}
	}

	print "</ul></nav></div>";
	print '</div>';
	print '<div class="float_right">';

	// settings
	print "<form method='post'>";

	print "<a href='#' class='pic' id='switch_copytext' title='" . __esc('Disable panel move/enable copy text from panel', 'intropage') . "'><i class='intro_glyph fa fa-clone'></i></a>";
	print '&nbsp; &nbsp; ';

	print '<a class="pic" href="' . html_escape($config['url_path'] . ($login_opts == 4 ? 'plugins/intropage/intropage.php?':'index.php?') . 'action=configure') . '"><i class="intro_glyph fa fa-cog"></i></a>';

	print '&nbsp; &nbsp; ';

	intropage_addpanel_select($dashboard_id);

	print "<select id='intropage_action'>";
	print '<option value="0">' . __('Actions ...', 'intropage') . '</option>';

	if ($number_of_dashboards < 9) {
		print '<option value="addpage_1">' . __('Add New Dashboard', 'intropage') . '</option>';
	}

	if ($dashboard_id > 1) {
		print '<option value="removepage_' . $dashboard_id . '">' . __('Remove current dashboard', 'intropage') . '</option>';
	}

	print '<option value="" disabled="disabled">─────────────────────────</option>';

	if ($number_of_lines == 5) {
		print "<option value='lines_5' disabled='disabled'>" . __('Number of lines - %d', 5, 'intropage') . '</option>';
	} else {
		print "<option value='lines_5'>" . __('Number of lines - %d', 5, 'intropage') . '</option>';
	}

	if ($number_of_lines == 10) {
		print "<option value='lines_10' disabled='disabled'>" . __('Number of lines - %d', 10, 'intropage') . '</option>';
	} else {
		print "<option value='lines_10'>" . __('Number of lines - %d', 10, 'intropage') . '</option>';
	}

	if ($number_of_lines == 15) {
		print "<option value='lines_15' disabled='disabled'>" . __('Number of lines - %d', 15, 'intropage') . '</option>';
	} else {
		print "<option value='lines_15'>" . __('Number of lines - %d', 15, 'intropage') . '</option>';
	}

	print '<option value="" disabled="disabled">─────────────────────────</option>';

	print "<option value=''>" . __('Refresh Now', 'intropage') . '</option>';

	if ($autorefresh > 0 || $autorefresh == -1) {
		print "<option value='refresh_0'>" . __('Refresh Disabled', 'intropage') . '</option>';
	} else {
		print "<option value='refresh_0' disabled='disabled'>" . __('Refresh Disabled', 'intropage') . '</option>';
	}

	if ($autorefresh == -1) {
		print "<option value='refresh_-1' disabled='disabled'>" . __('Refresh by Poller', 'intropage') . '</option>';
	} else {
		print "<option value='refresh_-1'>" . __('Refresh by Poller', 'intropage') . '</option>';
	}

	if ($autorefresh == 60) {
		print "<option value='refresh_60' disabled='disabled'>" . __('Refresh Every 1 Minute', 'intropage') . '</option>';
	} else {
		print "<option value='refresh_60'>" . __('Refresh Every 1 Minute', 'intropage') . '</option>';
	}

	if ($autorefresh == 300) {
		print "<option value='refresh_300' disabled='disabled'>" . __('Refresh Every 5 Minutes', 'intropage') . '</option>';
	} else {
		print "<option value='refresh_300'>" . __('Refresh Every 5 Minutes', 'intropage') . '</option>';
	}

	if ($autorefresh == 3600) {
		print "<option value='refresh_3600' disabled='disabled'>" . __('Refresh Every Hour', 'intropage') . '</option>';
	} else {
		print "<option value='refresh_3600'>" . __('Refresh Every Hour', 'intropage') . '</option>';
	}

	print '<option value="" disabled="disabled">─────────────────────────</option>';

	if ($important_period == -1) {
		print "<option value='period_-1' disabled='disabled'>" . __('Important period - disabled', 'intropage') . '</option>';
	} else {
		print "<option value='period_-1'>" . __('Important period - disabled', 'intropage') . '</option>';
	}

	if ($important_period == 900) {
		print "<option value='period_900' disabled='disabled'>" . __('Important period - 15 minutes', 'intropage') . '</option>';
	} else {
		print "<option value='period_900'>" . __('Important period - 15 minutes', 'intropage') . '</option>';
	}

	if ($important_period == 3600) {
		print "<option value='period_3600' disabled='disabled'>" . __('Important period - 1 hour', 'intropage') . '</option>';
	} else {
		print "<option value='period_3600'>" . __('Important period - 1 hour', 'intropage') . '</option>';
	}

	if ($important_period == 14400) {
		print "<option value='period_14400' disabled='disabled'>" . __('Important period - 4 hours', 'intropage') . '</option>';
	} else {
		print "<option value='period_14400'>" . __('Important period - 4 hours', 'intropage') . '</option>';
	}

	if ($important_period == 86400) {
		print "<option value='period_86400' disabled='disabled'>" . __('Important period - 1 day', 'intropage') . '</option>';
	} else {
		print "<option value='period_86400'>" . __('Important period - 1 day', 'intropage') . '</option>';
	}

	print '<option value="" disabled="disabled">─────────────────────────</option>';

	foreach($trend_timespans as $key => $value) {
		if ($timespan == $key) {
			print "<option value='timespan_$key' disabled='disabled'>" . $value . '</option>';
		} else {
			print "<option value='timespan_$key'>" . $value . '</option>';
		}
	}

	print '<option value="" disabled="disabled">─────────────────────────</option>';

	if ($display_important_first == 'on') {
		print "<option value='important_first' disabled='disabled'>" . __('Sort by Severity', 'intropage') . '</option>';
		print "<option value='important_no'>" . __('Sort by User Preference', 'intropage') . '</option>';
	} else {
		print "<option value='important_first'>" . __('Sort by Severity', 'intropage') . '</option>';
		print "<option value='important_no' disabled='disabled'>" . __('Sort by User Preference', 'intropage') . '</option>';
	}

	print '<option value="" disabled="disabled">─────────────────────────</option>';

	if (api_plugin_user_realm_auth('intropage_admin.php')) {
		print "<option value='forcereload'>" . __('Reload Panel Definitions', 'intropage') . '</option>';
	}

	if (!$console_access) {
		if ($login_opts < 4) {
			// intropage is not default
			print "<option value='loginopt_tab'>" . __('Set Intropage as Default Login Page', 'intropage') . '</option>';
		} elseif ($login_opts == 4) {
			print "<option value='loginopt_graph'>" . __('Set graph as Default Login Page', 'intropage') . '</option>';
		}
	} else {
		// intropage in console or in tab
		if ($login_opts == 4) {
			// in tab
			print "<option value='loginopt_console'>" . __('Display Intropage in Console', 'intropage') . '</option>';
		} else {
			print "<option value='loginopt_tab'>" . __('Display Intropage in Tab as Default Page', 'intropage') . '</option>';
		}
	}

	print '<option value="" disabled="disabled">─────────────────────────</option>';

	if ($display_wide == 'on') {
		print "<option value='displaywide'>" . __('Less panels on a line', 'intropage') . '</option>';
		print "<option value='displaywide_on' disabled='disabled'>" . __('More panels on a line', 'intropage') . '</option>';
	} else {
		print "<option value='displaywide' disabled='disabled'>" . __('Less panels on a line', 'intropage') . '</option>';
		print "<option value='displaywide_on'>" . __('More panels on a line', 'intropage') . '</option>';
	}

	print '<option value="" disabled="disabled">─────────────────────────</option>';

	$actual = db_fetch_row_prepared('SELECT shared,
		(SELECT COUNT(panel_id) FROM plugin_intropage_panel_dashboard WHERE dashboard_id = t2.dashboard_id) as panels
		FROM  plugin_intropage_dashboard AS t2
		WHERE user_id = ? AND dashboard_id = ?',
		array ($_SESSION['sess_user_id'], $_SESSION['dashboard_id']));

	if ($actual['shared']) {
		print "<option value='unshare'>" . __('Cancel sharing', 'intropage') . '</option>';	
	} else {
		if ($actual['panels'] > 0) {
			print "<option value='share'>" . __('Share this dashboard', 'intropage') . '</option>';	
		} else {
			print "<option value=''>" . __('Share empty dashboard not allowed', 'intropage') . '</option>';	
		}
	}

	print '<option value="" disabled="disabled">─────────────────────────</option>';

	$shared_dashboards = db_fetch_assoc_prepared('SELECT dashboard_id,name,user_id FROM plugin_intropage_dashboard
		WHERE shared = 1 AND user_id != ?',
		array ($_SESSION['sess_user_id']));

	if (cacti_sizeof($shared_dashboards) > 0) {

		foreach  ($shared_dashboards as $sd) {
			$text = ' (' . get_username($sd['user_id']) . ' - ' . $sd['name'] . ')' ; 
		
			if ($number_of_dashboards < 9) {
				print "<option value='useshared_" .  $sd['dashboard_id'] . "_" . $sd['user_id'] . "'>" . __('Use shared dashboard', 'intropage') . $text . '</option>';	
			} else {
				print "<option value='useshared_" .  $sd['dashboard_id'] . "_" . $sd['user_id'] . "' disabled='disabled'>" . __('Cannot use shared dashboard - dashboard limit reached.', 'intropage') . $text . '</option>';	
			}
		}
	} else {
		print '<option value="" disabled="disabled">' . __('No shared dashboards', 'intropage') . '</option>';
	}

	print '</select>';
	print '</form>';
	// end of settings

	print '</div>';
	print '<br style="clear: both" />';
	print '</div>';

	print '<div id="megaobal">';
	print '<ul id="obal">';

	if (cacti_sizeof($panels) == 0) {
		print '<table class="cactiTable">';
		print '<tr><td>';
		print '<h2>' . __('Welcome to Intropage!', 'intropage') . '</h2>';
		print '</td></tr>';

		print '<tr class="tableRow">';
		print '<td class="textAreaNotes top left">' . __('You can Add Dashboard Panels in more ways:', 'intropage');
		print '<ul>';
		print '<li>' . __('Select prepared panels from the menu to the right. Panel can be grayed out. It is due to permissions, ask administrator') . '</li>';
		print '<li>' . __('Add any Cacti Graph, use icon', 'intropage') . '<i class="fa fa-eye"></i></li>';
		print '<li>' . __('You can create own panels. More info in file <cacti_install_dir>/plugins/intropage/panellib/README.md') . '</li>';
		print '</ul><br/>';
		print '</td></tr>';

		print '<tr class="tableRow">';
		print '<td class="textAreaNotes top left">' . __('You can share dashboards to other users:', 'intropage');
		print '<ul>';
		print '<li>' . __('use "Share this dashboard" option in Actions menu - Every user can use it as template.') . '</li>';
		print '</ul><br/>';
		print '</td></tr>';

		print '<tr class="tableRow">';
		print '<td class="textAreaNotes top left">' . __('You can use shared dashboard using the Actions menu:', 'intropage');
		print '<ul>';
		print '<li>' . __('Use "Use shared dashboard (user/dashboard name) -  It prepares the same dashboard like shared but with your permissions.') . '</li>';
		print '</ul><br/>';
		print '</td></tr>';

		print '<tr class="tableRow">';
		print '<td class="textAreaNotes top left">' . __('Customization:', 'intropage');
		print '<ul>';
		print '<li>' . __('You can create up to 9 dashboards. Every dashboard can be named, use icon') . '<i class="intro_glyph fa fa-cog"></i></li>';
		print '<li>' . __('Intopage can be displayed in console or in separated tab. You can change it in Action menu') . '</li>';
		print '<li>' . __('If you want to copy text from panel, you have to disable drag and drop function, use icon') . '<i class="intro_glyph fa fa-clone"></i></li>';



		print '</ul><br/>';
		print '</td></tr>';



		html_end_box();
	}

	$first_db = db_fetch_cell_prepared('SELECT MIN(IFNULL(dashboard_id, 1))
		FROM plugin_intropage_dashboard
		WHERE user_id = ?',
		array($_SESSION['sess_user_id']));

	// extra maint plugin panel - always first
	if (api_plugin_is_enabled('maint') && (read_config_option('intropage_maint_plugin_days_before') >= 0)) {
		$row = db_fetch_row_prepared("SELECT id, data
			FROM plugin_intropage_panel_data
			WHERE panel_id = 'maint'
			AND user_id = ?",
			array($_SESSION['sess_user_id']));

		if ($row && strlen($row['data']) > 20 && $dashboard_id == $first_db) {
			intropage_display_panel($row['id'], $dashboard_id);
		}
	}
	// end of extra maint plugin panel

	// extra admin panel
	if (strlen(read_config_option('intropage_admin_alert')) > 3) {
		$id = db_fetch_cell("SELECT id
			FROM plugin_intropage_panel_data
			WHERE panel_id='admin_alert'");

		if ($id && $dashboard_id == $first_db) {
			intropage_display_panel($id, $dashboard_id);
		}
	}
	// end of admin panel

	if ($display_important_first == 'on') {  // important first
		foreach ($panels as $xkey => $xvalue) {
			if ($xvalue['alarm'] == 'red') {
				intropage_display_panel($xvalue['id'], $dashboard_id);
				$panels[$xkey]['displayed'] = true;
			}
		}

		// yellow (errors and warnings)
		foreach ($panels as $xkey => $xvalue) {
			if ($xvalue['alarm'] == 'yellow') {
				intropage_display_panel($xvalue['id'], $dashboard_id);
				$panels[$xkey]['displayed'] = true;
			}
		}

		// green (all)
		foreach ($panels as $xkey => $xvalue) {
			if ($xvalue['alarm'] == 'green') {
				intropage_display_panel($xvalue['id'], $dashboard_id);
				$panels[$xkey]['displayed'] = true;
			}
		}

		// grey and without color
		foreach ($panels as $xkey => $xvalue) {
			if (!isset($xvalue['displayed'])) {
				intropage_display_panel($xvalue['id'], $dashboard_id);
				$panels[$xkey]['displayed'] = true;
			}
		}
	} else {	// display only errors/errors and warnings/all - order by priority
		foreach ($panels as $xkey => $xvalue) {
			intropage_display_panel($xvalue['id'], $dashboard_id);
		}
	}

	print '</ul>';
	print '<ul class="cloned-slides"></ul>';
	print '</div>'; // end of megaobal

	?>
	<script type='text/javascript'>

	var refresh;
	var pollerRefresh;
	var intropage_autorefresh = <?php print $autorefresh;?>;
	var intropage_drag = true;
	var intropage_page = '';
	var dashboard_id = <?php print $dashboard_id;?>;
	var intropage_text_panel_details = '<?php print __('Panel Details', 'intropage');?>';
	var intropage_text_panel_disable = '<?php print __esc('Disable panel move/Enable copy text from panel', 'intropage');?>';
	var intropage_text_panel_enable = '<?php print __esc('Enable panel move/Disable copy text from panel', 'intropage');?>';
	var intropage_text_data_error = '<?php print __('Error reading new data', 'intropage');?>';
	var intropage_text_close = '<?php print __('Close', 'intropage');?>';

	var panels = {};

	var intropage_panel_quarter_width = '<?php echo $width_quarter; ?>';
	var intropage_panel_third_width = '<?php echo $width_third; ?>';
	var intropage_panel_half_width = '<?php echo $width_half; ?>';

	</script>

	<?php
	print get_md5_include_js($config['base_path'].'/plugins/intropage/include/intropage.js');

	return true;
}
