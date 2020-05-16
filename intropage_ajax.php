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

chdir('../../');
include_once('./include/auth.php');

if (!function_exists("array_column")) {
	function array_column($array,$column_name) {
		return array_map(function($element) use($column_name){return $element[$column_name];}, $array);
	}
}

if (get_filter_request_var('reload_panel', FILTER_VALIDATE_REGEXP, array('options' => array('regexp' => '/^[0-9]{1,3}$/'))))	{
	$panel_id = get_request_var('reload_panel');
}

if (get_filter_request_var('detail_panel', FILTER_VALIDATE_REGEXP, array('options' => array('regexp' => '/^[0-9]{1,3}$/'))))	{
	$panel_id = get_request_var('detail_panel');
}

$forced_update = filter_var(get_request_var('force'), FILTER_VALIDATE_BOOLEAN);

// automatic reload when poller ends
if (isset_request_var('autoreload')) {
    $last_poller = db_fetch_cell("SELECT unix_timestamp(cur_timestamp)
		FROM plugin_intropage_trends
		WHERE name='ar_poller_finish'");

    $last_disp = db_fetch_cell_prepared('SELECT unix_timestamp(cur_timestamp)
		FROM plugin_intropage_trends
		WHERE name = ?',
		array('ar_displayed_' . $_SESSION['sess_user_id']));

    if (!$last_disp) {
		db_execute_prepared('INSERT INTO plugin_intropage_trends (name,value)
			VALUES (?, NOW())',
			array('ar_displayed_' . $_SESSION['sess_user_id']));

		$last_disp = $last_poller;
    }

	if ($last_poller > $last_disp)	{  // fix first double reload (login and poller finish after few seconds
		db_execute_prepared("UPDATE plugin_intropage_trends
			SET cur_timestamp = NOW(), value = NOW()
			WHERE name = ?",
			array('ar_displayed_' . $_SESSION['sess_user_id']));

		print '1';
	} else {
		print '0';
	}
}

// few requered variables
$maint_days_before = read_config_option('intropage_maint_plugin_days_before');

// Retrieve access
$console_access = api_plugin_user_realm_auth('index.php');

include_once($config['base_path'] . '/plugins/intropage/include/functions.php');

if (isset_request_var('reload_panel') && isset($panel_id)) {

	$file = db_fetch_cell_prepared('SELECT t1.file AS file FROM plugin_intropage_panel_definition AS t1
			JOIN plugin_intropage_panel_data AS t2 on t1.panel_id=t2.panel_id where t2.id=?',
			array($panel_id));

	include_once($config['base_path'] . $file);

	$panel = db_fetch_row_prepared('SELECT * FROM plugin_intropage_panel_data
		WHERE id = ? AND user_id IN (0,?)', array($panel_id,$_SESSION['sess_user_id']));
	if ($panel)	{
		if (isset($panel['fav_graph_id'])) { // fav_graph exception
			$data = intropage_favourite_graph($panel['fav_graph_id'],$panel['fav_graph_timespan']);
		} else { // normal panel
			$data = $panel['panel_id'](true,false,$forced_update);
		}

		if (isset_request_var('reload_panel')) {
			intropage_display_data(get_request_var('reload_panel'),$data);

			// change panel color or ena/disa detail 
			// !!!!!  jestli ma detail - to uz brat z definice panelu

			?>

			<script type='text/javascript'>
				$('#panel_'+<?php print get_request_var('reload_panel');?>).find('.panel_name').html('<?php echo $data['name'];?>');
				$('#panel_'+<?php print get_request_var('reload_panel');?>).find('.panel_header').removeClass('color_green');
				$('#panel_'+<?php print get_request_var('reload_panel');?>).find('.panel_header').removeClass('color_yellow');
				$('#panel_'+<?php print get_request_var('reload_panel');?>).find('.panel_header').removeClass('color_red');
				$('#panel_'+<?php print get_request_var('reload_panel');?>).find('.panel_header').removeClass('color_gray');
				$('#panel_'+<?php print get_request_var('reload_panel');?>).find('.panel_header').addClass('color_<?php print $data['alarm'];?>');

			<?php

			if (db_fetch_cell("SELECT has_detail FROM plugin_intropage_panel_definition WHERE panel_id='" . $panel['panel_id'] . "'") == 'yes') {
				print "$('#panel_'+" . get_request_var('reload_panel') . ").find('.maxim').show();";
			} else {
				print "$('#panel_'+" . get_request_var('reload_panel') . ").find('.maxim').hide();";
			}
			?>
			</script>
			<?php
			// end ofchange panel color or ena/disa detail
		}
	} elseif ($panel_id == 998) {	// exception for admin alert panel
		print nl2br(read_config_option('intropage_admin_alert'));
	} elseif ($panel_id == 997) {	// exception for maint panel
		print intropage_maint();
	} else {
		print __('Panel not found');
	}
}


//!!!! tady bych mel osetrovat panel_id
if (isset_request_var('detail_panel') && isset($panel_id)) {
    include_once($config['base_path'] . '/plugins/intropage/include/data_detail.php');

    $panel = db_fetch_cell_prepared('SELECT panel_id
		FROM plugin_intropage_panel_data
		WHERE id = ?',
		array($panel_id));

	if ($panel)	{
	    $pokus = $panel . '_detail';
	    $data = $pokus();

	    print '<div id="block" class="color_' . $data['alarm'] . '" ></div>';
	    print '<h3 style="display: inline">' . $data['name'] . '</h3>';
	    print '<br/>' . $data['detail'];
	} else {
		print __('Panel not found');
	}
}

