<?php
/* vim: ts=4
 +-------------------------------------------------------------------------+
 | Copyright (C) 2021 The Cacti Group, Inc.                                |
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
	global $config, $sql_where, $login_opts, $panels, $registry;

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

	if ($user_panels == 0) {
		db_execute_prepared('INSERT INTO plugin_intropage_user_auth
			(user_id)
			VALUES (?)',
			array($_SESSION['sess_user_id']));
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
	$autorefresh             = read_user_setting('intropage_autorefresh', read_config_option('intropage_autorefresh'));

	// number of dashboards
	$number_of_dashboards = db_fetch_cell_prepared('SELECT COUNT(*)
		FROM plugin_intropage_dashboard
		WHERE user_id = ?',
		array($_SESSION['sess_user_id']));

	// console access
	$console_access = api_plugin_user_realm_auth('index.php');

	// remove admin prohibited panels
	$panels = db_fetch_assoc_prepared ('SELECT t1.panel_id AS panel_name, t1.id AS id
		FROM plugin_intropage_panel_data AS t1
		INNER JOIN plugin_intropage_panel_dashboard AS t2
		ON t1.id = t2.panel_id
		WHERE t2.user_id = ?
		AND t2.dashboard_id = ?',
		array($_SESSION['sess_user_id'], $dashboard_id));

	if (cacti_sizeof($panels)) {
		foreach ($panels as $one) {
			$allowed = is_panel_allowed($one['panel_name']);

			if (!$allowed) {
				db_execute_prepared('DELETE FROM plugin_intropage_panel_dashboard
					WHERE user_id = ?
					AND dashboard_id = ?
					AND panel_id = ?',
					array($_SESSION['sess_user_id'], $dashboard_id, $one['id']));
			}
		}
	}

	// User allowed panels
	$panels = db_fetch_assoc_prepared("SELECT t1.*
		FROM plugin_intropage_panel_data as t1
		INNER JOIN plugin_intropage_panel_dashboard as t2
		ON t1.id = t2.panel_id
		WHERE t1.user_id in (0, ?)
		AND t2.dashboard_id = ?
		AND t1.panel_id != 'favourite_graph'
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

	print "<select id='intropage_addpanel'>";
	print '<option value="0">' . __('Panels ...', 'intropage') . '</option>';

	$add_panels = db_fetch_assoc_prepared('SELECT ppd.id, pd.panel_id, pd.name
		FROM plugin_intropage_panel_definition AS pd
		LEFT JOIN plugin_intropage_panel_data AS ppd
		ON pd.panel_id = ppd.panel_id
		WHERE pd.panel_id NOT IN (
			SELECT t1.panel_id
			FROM plugin_intropage_panel_data AS t1
			INNER JOIN plugin_intropage_panel_dashboard AS t2
			ON t1.id = t2.panel_id
			WHERE t2.user_id = ?
			AND t2.dashboard_id = ?
		)
		ORDER BY pd.name',
		array($_SESSION['sess_user_id'], $dashboard_id));

	if (cacti_sizeof($add_panels)) {
		foreach ($add_panels as $panel) {
			$uniqid = db_fetch_cell_prepared('SELECT id
				FROM plugin_intropage_panel_data
				WHERE user_id IN (0, ?)
				AND panel_id = ?',
				array($_SESSION['sess_user_id'],$panel['panel_id']));

			if ($panel['panel_id'] != 'maint' && $panel['panel_id'] != 'admin_alert') {
				$allowed = is_panel_allowed($panel['panel_id']);

				$enabled = is_panel_enabled($panel['panel_id']);

				if ($uniqid > 0) {
					if ($allowed) {
						if ($enabled) {
							print "<option value='" . $uniqid . "'>" . html_escape($panel['name']) . '</option>';
						}
					} else {
						print "<option value='addpanel_" .  $uniqid . "' disabled='disabled'>" . __('%s (no permission)', $panel['name'], 'intropage') . '</option>';
					}
				} else {
					if ($allowed) {
						if ($enabled) {
							print "<option value='" . $panel['panel_id'] . "'>" . html_escape($panel['name']) . '</option>';
						}
					} else {
						print "<option value='addpanel_" .  $uniqid . "' disabled='disabled'>" . __('%s (no permission)', $panel['name'], 'intropage') . '</option>';
					}
				}
			}
		}
	}

	print '</select>';
	print '&nbsp; &nbsp; ';

	print "<select id='intropage_action'>";
	print '<option value="0">' . __('Actions ...', 'intropage') . '</option>';

	if ($number_of_dashboards < 9) {
		print '<option value="addpage_1">' . __('Add New Dashboard', 'intropage') . '</option>';
	}

	if ($dashboard_id > 1) {
		print '<option value="removepage_' . $dashboard_id . '">' . __('Remove current dashboard', 'intropage') . '</option>';
	}

	// only submit :-)
	print "<option value=''>" . __('Refresh Now', 'intropage') . '</option>';

	if ($autorefresh > 0 || $autorefresh == -1) {
		print "<option value='refresh_0'>" . __('Autorefresh Disabled', 'intropage') . '</option>';
	} else {
		print "<option value='refresh_0' disabled='disabled'>" . __('Autorefresh Disabled', 'intropage') . '</option>';
	}

	if ($autorefresh == -1) {
		print "<option value='refresh_-1' disabled='disabled'>" . __('Autorefresh ly Poller', 'intropage') . '</option>';
	} else {
		print "<option value='refresh_-1'>" . __('Autorefresh by Poller', 'intropage') . '</option>';
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
		print "<option value='important_first' disabled='disabled'>" . __('Sort by Severity', 'intropage') . '</option>';
		print "<option value='important_no'>" . __('Sort by User Preference', 'intropage') . '</option>';
	} else {
		print "<option value='important_first'>" . __('Sort by Severity', 'intropage') . '</option>';
		print "<option value='important_no' disabled='disabled'>" . __('Sort by User Preference', 'intropage') . '</option>';
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
		print '<td class="textAreaNotes top left">' . __('You can Add Dashboard Panels in two ways:', 'intropage');
		print '<ul>';
		print '<li>' . __('Select prepared Dashboard Panels from the menu to the right.') . '</li>';
		print '<li>' . __('Add any Cacti Graph by Clicking the \'Eye Icon\' which is next to each Cacti Graph. Graph with actual timespan will be added to current dashboard', 'intropage') . '</li>';
		print '</ul>';
		print '</td></tr>';

		html_end_box();
	}

	$first_db = db_fetch_cell_prepared('SELECT MIN(IFNULL(dashboard_id, 1))
		FROM plugin_intropage_dashboard
		WHERE user_id = ?',
		array($_SESSION['sess_user_id']));

	// extra maint plugin panel - always first
	if (api_plugin_is_enabled('maint')) {
		$row = db_fetch_row_prepared("SELECT id, data
			FROM plugin_intropage_panel_data
			WHERE panel_id = 'maint'
			AND user_id = ?",
			array($_SESSION['sess_user_id']));

		if ($row && strlen($row['data']) > 20 && $dashboard_id == $first_db) {
			intropage_display_panel($row['id']);
		}
	}
	// end of extra maint plugin panel

	// extra admin panel
	if (strlen(read_config_option('intropage_admin_alert')) > 3) {
		$id = db_fetch_cell("SELECT id
			FROM plugin_intropage_panel_data
			WHERE panel_id='admin_alert'");

		if ($id && $dashboard_id == $first_db) {
			intropage_display_panel($id);
		}
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

		// grey and without color
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

	print '</ul>';
	print '<ul class="cloned-slides"></ul>';
	print '</div>'; // end of megaobal

	?>
	<script type='text/javascript'>

	var refresh;
	var intropage_autorefresh = <?php print $autorefresh;?>;
	var intropage_drag = true;
	var intropage_page = '';
	var dashboard_id = <?php print $dashboard_id;?>;

	// display/hide detail
	$(function () {
		$('.flexchild').css('background-color', $('body').css('background-color'));

		$('#intropage_addpanel').unbind().change(function() {
			addPanel();
		});

		$('#intropage_action').unbind().change(function() {
			actionPanel();
		});

		$(window).resize(function() {
			resizeCharts();
		});

		if (pageName == 'index.php') {
			intropage_page = urlPath + pageName;
		} else {
			intropage_page = urlPath + 'plugins/intropage/intropage.php';
		}

		initPage();
		reload_all();
		setupHidden();
		resizeCharts();
	});

	function resizeCharts() {
		$('.chart_wrapper > canvas[id^="line_"]').each(function() {
			var width  = $(this).closest('.panel_wrapper').width() - 10;
			var height = $(this).closest('.panel_wrapper').height() - 34;
			$(this).css({ height: height });
			$(this).css({ width: width });
		});
	}

	function setupHidden() {
		$('.flexchild').each(function(i) {
			var item = $(this);
			var item_clone = item.clone();
			item.data('clone', item_clone);
			var position = item.position();
			item_clone.css({ left: position.left, top: position.top, visibility: 'hidden' }).attr('data-pos', i+1);
			$('#cloned-slides').append(item_clone);
		});
	}

	function addPanel() {
		$.post(intropage_page, {
			header: 'false',
			dashboard_id: dashboard_id,
			__csrf_magic: csrfMagicToken,
			intropage_addpanel: $('#intropage_addpanel').val()
		}).done(function(data) {
			$('#main').html(data);
			applySkin();
			initPage();
		});
	}

	function actionPanel() {
		var option = $('#intropage_action').val();

		if (option == 'loginopt_tab' || option == 'loginopt_console') {
			document.location = intropage_page+'?dashboard_id='+dashboard_id+'&intropage_action='+option;
		} else {
			$.post(intropage_page, {
				header: 'false',
				dashboard_id: dashboard_id,
				__csrf_magic: csrfMagicToken,
				intropage_action: option
			}).done(function(data) {
				$('#main').html(data);
				applySkin();
				initPage();
			});
		}
	}

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
			setTimeout(function() {
				// fix first double load
				refresh = setInterval(testPoller, 10000);
			},30000);
		}

		$('.article').hide();

		$('#obal').sortable({
			tolerance: 'pointer',
			forcePlaceholderSize: true,
			forceHelperSize: false,
			placeholder: '.flexchild',
			handle: '.panel_header',
			helpler: 'clone',
			delay: 500,
			revert: 'invalid',
			scroll: false,
			dropOnEmpty: false,
			start: function(e, ui){
				var minWidth = Math.min.apply(null,
					$('.flexchild').map(function() {
						return $(this).width();
					}).get()
				);

				ui.helper.width(minWidth);
				$('#obal .flexchild').css({'width': minWidth, 'flex-grow': '0'});

				ui.helper.addClass('exclude-me');
				ui.helper.data('clone').hide();
				$('.cloned-slides .flexchild').css('visibility', 'visible');
			},
			stop: function(event, ui) {
				$('#obal .flexchild.exclude-me').each(function() {
					var item = $(this);
					var clone = item.data('clone');
					var position = item.position();

					clone.css('left', position.left);
					clone.css('top', position.top);
					clone.show();

					item.removeClass('exclude-me');
					$('.flexchild').css('width', '');
				});

				$('#obal .flexchild').each(function() {
					var item = $(this);
					var clone = item.data('clone');

					clone.attr('data-pos', item.index());
				});

				$('#obal .flexchild').css('visibility', 'visible');
				$('.cloned-slides .flexchild').css('visibility', 'hidden');
				$('#obal .flexchild').css({'width': '', 'flex-grow': '1'});
			},
			change: function(event, ui) {
            	$('#obal li:not(.exclude-me, .ui-sortable-placeholder)').each(function() {
					var item = $(this);
					var clone = item.data('clone');
					clone.stop(true, false);
					var position = item.position();
					clone.animate({ left: position.left, top:position.top}, 500);
				});
			},
			update: function(event, ui) {
				// change order
				var xdata = new Array();
				$('#obal li').each(function() {
					xdata.push($(this).attr('id'));
				});

				$.get(intropage_page, { xdata:xdata, intropage_action:'order' });
			}
		}).disableSelection();

		$('.droppanel').click(function(event) {
			event.preventDefault();
			panel_div_id = $(this).attr('data-panel');
			$('#'+panel_div_id).remove();
			$('#intropage_addpanel option[value="add'+panel_div_id+'"]').removeAttr('disabled');
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
		$.get(urlPath+'plugins/intropage/intropage.php?&action=autoreload')
		.done(function(data) {
			if (data == 1) {
				$('#obal li').each(function() {
					var panel_id = $(this).attr('id').split('_').pop();
					reload_panel(panel_id, false, false);
			    });
			}
		});
	}

	function reload_panel(panel_id, forced_update, refresh) {
		if (!refresh) {
			$('#panel_'+panel_id).find('.panel_data').css('opacity',0);
			$('#panel_'+panel_id).find('.panel_data').fadeIn('slow');
		}

		$.get(urlPath+'plugins/intropage/intropage.php?action=reload&force='+forced_update+'&panel_id='+panel_id)
		.done(function(data) {
			$('#panel_'+panel_id).find('.panel_data').html(data);

			if (!refresh) {
				$('#panel_'+panel_id).find('.panel_data').css('opacity', 1);
			}

			resizeCharts();
		})
		.fail(function(data) {
			$('#panel_'+panel_id).find('.panel_data').html('<?php print __('Error reading new data', 'intropage');?>');
		});
	}

	function reload_all() {
		$('#obal li.flexchild').each(function() {
			var panel_id = $(this).attr('id').split('_').pop();
			reload_panel(panel_id, false, true);
		});
	}

	// detail to the new window
	$('.maxim').click(function(event) {
		event.preventDefault();
		panel_id = $(this).attr('detail-panel');

		$.get(urlPath+'plugins/intropage/intropage.php?action=details&panel_id='+panel_id, function(data) {
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
	return true;
}
