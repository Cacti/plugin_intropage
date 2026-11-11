<?php
/* vim: ts=4
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group, Inc.                           |
 | Copyright (C) 2004-2025 Petr Macek                                      |
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

chdir('../../');
include_once('./include/auth.php');

global $config;

include_once($config['base_path'] . '/plugins/intropage/includes/functions.php');

$panel_actions = [
	1 => __('Enable', 'intropage'),
	2 => __('Disable', 'intropage'),
];

set_default_action();

switch (get_request_var('action')) {
	case 'actions':
		intropage_panels_form_actions();

		break;
	default:
		top_header();

		intropage_panels();

		bottom_footer();

		break;
}

/**
 * Processes the Enable/Disable bulk action submitted from the panel library
 * list: validates the selected panel ids and toggles each panel's registration
 * `enabled` flag, then returns to the list.
 *
 * @return void
 *
 * @global array $panel_actions The action dropdown map (Enable/Disable).
 */
function intropage_panels_form_actions(): void {
	global $panel_actions;

	if (!api_user_realm_auth('intropage_panels.php')) {
		raise_message('intropage_permissions', __('Intropage - Permission Denied', 'intropage'), MESSAGE_LEVEL_ERROR);
		header('Location: intropage_panels.php?header=false');

		exit;
	}

	if (isset_request_var('selected_items')) {
		$selected_items = json_decode(get_nfilter_request_var('selected_items'), true);

		if (is_array($selected_items)) {
			$enable = (get_nfilter_request_var('drp_action') == '1');

			foreach ($selected_items as $panel_id) {
				// Panel ids are validated on registration to [A-Za-z0-9_]; re-check here.
				if (is_string($panel_id) && preg_match('/^[A-Za-z0-9_]{1,50}$/', $panel_id)) {
					intropage_set_panel_enabled($panel_id, $enable);
				}
			}
		}

		header('Location: intropage_panels.php?header=false');

		exit;
	}

	/* collect the panels selected on the previous page */
	$panel_list  = '';
	$panel_array = [];

	foreach ($_POST as $var => $val) {
		if (preg_match('/^chk_([a-z0-9_]+)$/i', $var, $matches)) {
			$panel_id = $matches[1];

			$name = db_fetch_cell_prepared('SELECT name
				FROM plugin_intropage_panel_registration
				WHERE panel_id = ?',
				[$panel_id]);

			$panel_list   .= '<li>' . html_escape(intropage_prefix_panel_name($panel_id, (string) $name)) . '</li>';
			$panel_array[] = $panel_id;
		}
	}

	top_header();

	form_start('intropage_panels.php');

	html_start_box($panel_actions[get_nfilter_request_var('drp_action')] ?? __('Panels', 'intropage'), '60%', '', '3', 'center', '');

	if (cacti_sizeof($panel_array)) {
		if (get_nfilter_request_var('drp_action') == '1') {
			$message = __n('Click \'Continue\' to Enable the following Panel.', 'Click \'Continue\' to Enable the following Panels.', cacti_sizeof($panel_array), 'intropage');
			$title   = __('Enable Panel', 'intropage');
		} else {
			$message = __n('Click \'Continue\' to Disable the following Panel. Disabled panels are hidden from dashboards and skipped by the poller, but their data is retained.', 'Click \'Continue\' to Disable the following Panels. Disabled panels are hidden from dashboards and skipped by the poller, but their data is retained.', cacti_sizeof($panel_array), 'intropage');
			$title   = __('Disable Panel', 'intropage');
		}

		print "<tr><td class='textArea'><p>$message</p><div class='itemlist'><ul>$panel_list</ul></div></td></tr>";

		$save_html = "<input type='button' class='ui-button ui-corner-all ui-widget cactiReturnTo' value='" . __esc('Cancel', 'intropage') . "'>&nbsp;<input type='submit' class='ui-button ui-corner-all ui-widget' value='" . __esc('Continue', 'intropage') . "' title='" . html_escape($title) . "'>";
	} else {
		raise_message(40);
		header('Location: intropage_panels.php?header=false');

		exit;
	}

	print "<tr><td class='saveRow'>
		<input type='hidden' name='action' value='actions'>
		<input type='hidden' name='selected_items' value='" . html_escape(json_encode($panel_array)) . "'>
		<input type='hidden' name='drp_action' value='" . html_escape(get_nfilter_request_var('drp_action')) . "'>
		$save_html
		</td></tr>";

	html_end_box();

	form_end();

	bottom_footer();
}

/**
 * Renders the Panel Library management page: a filterable, sortable table of
 * every registered panel (core and third-party) with a source-plugin dropdown
 * filter and Enable/Disable bulk actions.
 *
 * @return void
 *
 * @global array $panel_actions The action dropdown map (Enable/Disable).
 */
function intropage_panels(): void {
	global $panel_actions;

	if (!api_user_realm_auth('intropage_panels.php')) {
		raise_message('intropage_permissions', __('Intropage - Permission Denied', 'intropage'), MESSAGE_LEVEL_ERROR);

		return;
	}

	$filters = [
		'rows' => [
			'filter'  => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1',
		],
		'page' => [
			'filter'  => FILTER_VALIDATE_INT,
			'default' => '1',
		],
		'filter' => [
			'filter'  => FILTER_DEFAULT,
			'pageset' => true,
			'default' => '',
		],
		'plugin' => [
			'filter'  => FILTER_CALLBACK,
			'pageset' => true,
			'default' => '0',
			'options' => ['options' => 'sanitize_search_string'],
		],
		'sort_column' => [
			'filter'  => FILTER_CALLBACK,
			'default' => 'name',
			'options' => ['options' => 'sanitize_search_string'],
		],
		'sort_direction' => [
			'filter'  => FILTER_CALLBACK,
			'default' => 'ASC',
			'options' => ['options' => 'sanitize_search_string'],
		],
	];

	validate_store_request_vars($filters, 'sess_intropage_panels');

	if (get_request_var('rows') == '-1') {
		$rows = read_config_option('num_rows_table');
	} else {
		$rows = get_request_var('rows');
	}

	intropage_panels_filter();

	/* build the where clause */
	$sql_where  = '';
	$sql_params = [];

	if (get_request_var('filter') != '') {
		$sql_where = 'WHERE (name LIKE ? OR panel_id LIKE ?)';

		$sql_params[] = '%' . get_request_var('filter') . '%';
		$sql_params[] = '%' . get_request_var('filter') . '%';
	}

	if (get_request_var('plugin') != '0' && get_request_var('plugin') != '') {
		$sql_where .= ($sql_where != '' ? ' AND ' : 'WHERE ') . 'plugin = ?';

		$sql_params[] = get_request_var('plugin');
	}

	$total_rows = db_fetch_cell_prepared("SELECT COUNT(*)
		FROM plugin_intropage_panel_registration
		$sql_where",
		$sql_params);

	$sql_order = get_order_string();
	$sql_limit = ' LIMIT ' . ($rows * (get_request_var('page') - 1)) . ',' . $rows;

	$panel_list = db_fetch_assoc_prepared("SELECT *
		FROM plugin_intropage_panel_registration
		$sql_where
		$sql_order
		$sql_limit",
		$sql_params);

	$nav = html_nav_bar('intropage_panels.php?filter=' . get_request_var('filter'), MAX_DISPLAY_PAGES, get_request_var('page'), $rows, $total_rows, 9, __('Panels', 'intropage'), 'page', 'main');

	form_start('intropage_panels.php', 'chk');

	print $nav;

	html_start_box('', '100%', '', '3', 'center', '');

	$display_text = [
		'name' => [
			'display' => __('Panel Name', 'intropage'),
			'align'   => 'left',
			'sort'    => 'ASC',
			'tip'     => __('The display name of this panel.', 'intropage'),
		],
		'plugin' => [
			'display' => __('Source', 'intropage'),
			'align'   => 'left',
			'sort'    => 'ASC',
			'tip'     => __('The plugin that provides this panel. Intropage\'s own panels show as Core.', 'intropage'),
		],
		'class' => [
			'display' => __('Category', 'intropage'),
			'align'   => 'left',
			'sort'    => 'ASC',
			'tip'     => __('The panel category.', 'intropage'),
		],
		'level' => [
			'display' => __('Level', 'intropage'),
			'align'   => 'left',
			'sort'    => 'ASC',
			'tip'     => __('System panels are shared by all users; User panels are per-user.', 'intropage'),
		],
		'requires' => [
			'display' => __('Requires', 'intropage'),
			'align'   => 'left',
			'sort'    => 'ASC',
			'tip'     => __('Plugins that must be enabled for this panel to render.', 'intropage'),
		],
		'refresh' => [
			'display' => __('Refresh', 'intropage'),
			'align'   => 'right',
			'sort'    => 'ASC',
			'tip'     => __('The panel data collection interval.', 'intropage'),
		],
		'update_func' => [
			'display' => __('Update Function', 'intropage'),
			'align'   => 'left',
			'sort'    => 'ASC',
			'tip'     => __('The function the poller calls to collect this panel\'s data.', 'intropage'),
		],
		'enabled' => [
			'display' => __('Status', 'intropage'),
			'align'   => 'right',
			'sort'    => 'DESC',
			'tip'     => __('Whether the panel is enabled.', 'intropage'),
		],
	];

	html_header_sort_checkbox($display_text, get_request_var('sort_column'), get_request_var('sort_direction'), false);

	if (cacti_sizeof($panel_list)) {
		foreach ($panel_list as $panel) {
			$source = ($panel['plugin'] === 'intropage') ? __('Core', 'intropage') : ucwords(str_replace(['_', '-'], ' ', $panel['plugin']));
			$level  = ((int) $panel['level'] === 0) ? __('System', 'intropage') : __('User', 'intropage');
			$status = ((int) $panel['enabled'] === 1) ? __('Enabled', 'intropage') : __('Disabled', 'intropage');

			form_alternate_row('line' . $panel['panel_id'], true, ((int) $panel['enabled'] !== 1));

			form_selectable_cell(filter_value(intropage_prefix_panel_name($panel['panel_id'], $panel['name']), get_request_var('filter')), $panel['panel_id']);
			form_selectable_cell(html_escape($source), $panel['panel_id']);
			form_selectable_cell(html_escape($panel['class']), $panel['panel_id']);
			form_selectable_cell($level, $panel['panel_id']);
			form_selectable_cell(html_escape($panel['requires'] != '' ? $panel['requires'] : __('None', 'intropage')), $panel['panel_id']);
			form_selectable_cell(intropage_readable_interval((float) $panel['refresh'], 0, false), $panel['panel_id'], '', 'right');
			form_selectable_cell(html_escape($panel['update_func']), $panel['panel_id']);
			form_selectable_cell($status, $panel['panel_id'], '', 'right');
			form_checkbox_cell($panel['name'], $panel['panel_id']);

			form_end_row();
		}
	} else {
		print "<tr class='tableRow odd'><td colspan='" . (cacti_sizeof($display_text) + 1) . "'><em>" . __('No Panels Found', 'intropage') . '</em></td></tr>';
	}

	html_end_box(false);

	if (cacti_sizeof($panel_list)) {
		print $nav;
	}

	draw_actions_dropdown($panel_actions);

	form_end();
}

/**
 * Renders the Panel Library filter box: a text search, a source-plugin
 * dropdown and a rows-per-page selector, matching Cacti's standard filter UI
 * (as used by cdef.php).
 *
 * @return void
 */
function intropage_panels_filter(): void {
	$plugins = db_fetch_assoc('SELECT DISTINCT plugin
		FROM plugin_intropage_panel_registration
		ORDER BY plugin');

	html_start_box(__('Intropage Panel Library', 'intropage'), '100%', '', '3', 'center', '');

	?>
	<tr class='even'>
		<td>
			<form id='form_intropage_panels' action='intropage_panels.php'>
			<table role='presentation' class='filterTable'>
				<tr>
					<td><?php print __('Search', 'intropage'); ?></td>
					<td>
						<input type='text' class='ui-state-default ui-corner-all' id='filter' size='25' value='<?php print html_escape(get_request_var('filter')); ?>'>
					</td>
					<td><?php print __('Source', 'intropage'); ?></td>
					<td>
						<select id='plugin' onChange='applyFilter()'>
							<option value='0'<?php print (get_request_var('plugin') == '0' ? ' selected' : ''); ?>><?php print __('Any', 'intropage'); ?></option>
							<?php
							if (cacti_sizeof($plugins)) {
								foreach ($plugins as $p) {
									$label = ($p['plugin'] === 'intropage') ? __('Core', 'intropage') : ucwords(str_replace(['_', '-'], ' ', $p['plugin']));
									print "<option value='" . html_escape($p['plugin']) . "'" . (get_request_var('plugin') == $p['plugin'] ? ' selected' : '') . '>' . html_escape($label) . '</option>';
								}
							}
							?>
						</select>
					</td>
					<td><?php print __('Panels', 'intropage'); ?></td>
					<td>
						<select id='rows' onChange='applyFilter()'>
							<option value='-1'<?php print (get_request_var('rows') == '-1' ? ' selected' : ''); ?>><?php print __('Default', 'intropage'); ?></option>
							<?php
							$row_options = [10, 20, 30, 50, 100, 500];
							foreach ($row_options as $ro) {
								print "<option value='$ro'" . (get_request_var('rows') == $ro ? ' selected' : '') . ">$ro</option>";
							}
							?>
						</select>
					</td>
					<td>
						<span>
							<input type='submit' class='ui-button ui-corner-all ui-widget' id='refresh' value='<?php print __esc('Go', 'intropage'); ?>' title='<?php print __esc('Set/Refresh Filters', 'intropage'); ?>'>
							<input type='button' class='ui-button ui-corner-all ui-widget' id='clear' value='<?php print __esc('Clear', 'intropage'); ?>' title='<?php print __esc('Clear Filters', 'intropage'); ?>'>
						</span>
					</td>
				</tr>
			</table>
			</form>
			<script type='text/javascript' <?php print plugin_intropage_csp_nonce(); ?>>
			function applyFilter() {
				strURL  = 'intropage_panels.php?header=false';
				strURL += '&filter='+$('#filter').val();
				strURL += '&plugin='+$('#plugin').val();
				strURL += '&rows='+$('#rows').val();
				loadPageNoHeader(strURL);
			}

			function clearFilter() {
				strURL = 'intropage_panels.php?clear=1&header=false';
				loadPageNoHeader(strURL);
			}

			$(function() {
				$('#clear').on('click', function() {
					clearFilter();
				});

				$('#form_intropage_panels').on('submit', function(event) {
					event.preventDefault();
					applyFilter();
				});
			});
			</script>
		</td>
	</tr>
	<?php

	html_end_box();
}
