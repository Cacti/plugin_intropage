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

function intropage_favourite_graph($fav_graph_id, $fav_graph_timespan) {
        global $config, $graph_timeshifts;


        if (isset($fav_graph_id)) {
                $result = array(
                        'name' => __('Favourite graph', 'intropage'),
                        'alarm' => 'gray',
                        'data' => '',
                );

		include_once($config['base_path'] . '/lib/time.php');

                $result['name'] .= ' ' . db_fetch_cell_prepared('SELECT title_cache
                        FROM graph_templates_graph
                        WHERE local_graph_id = ?',
                        array($fav_graph_id));

		$result['name'] .= ' - ' .  $graph_timeshifts[$fav_graph_timespan];

		$timespan = array();
		$first_weekdayid = read_user_setting('first_weekdayid');
		get_timespan( $timespan, time(),$fav_graph_timespan , $first_weekdayid);

                $result['data'] = '<img src="' . $config['url_path'] . 'graph_image.php?' .
                        'local_graph_id=' . $fav_graph_id . '&' .
                        'graph_height=105&' .
                        'graph_width=300&' .
                        'disable_cache=true&' .
                        'graph_start=' . $timespan['begin_now'] . '&' .
                        'graph_end=' . $timespan['end_now'] . '&' .
                        'graph_nolegend=true"/>';

                return $result;
	}
}

function intropage_prepare_graph($dispdata) {
	global $config;

	$selectedTheme = get_selected_theme();
	switch ($selectedTheme) {
		case 'dark':
		case 'paper-plane':
			$bgcolor = '#202020';
			break;
		case 'sunrise':
			$bgcolor = '';
			break;
		default:
			$bgcolor = '#f5f5f5';
	}

	$content = "";
	
	// line graph
	if (isset($dispdata['line'])) {
		$xid = 'x' . substr(md5($dispdata['line']['title1']), 0, 7);

		$content .= "<div style=\"background: $bgcolor;\"><canvas id=\"line_$xid\"></canvas>\n";
		$content .=  "<script type='text/javascript'>\n";
		
		$title1      = $dispdata['line']['title1'];
		$line_labels = implode('","', $dispdata['line']['label1']);
		$line_values = implode(',', $dispdata['line']['data1']);

		if (!empty($dispdata['line']['data2'])) {
			$line_values2 = implode(',', $dispdata['line']['data2']);
			$title2       = $dispdata['line']['title2'];
		}
		if (!empty($dispdata['line']['data3'])) {
			$line_values3 = implode(',', $dispdata['line']['data3']);
			$title3       = $dispdata['line']['title3'];
		}
		if (!empty($dispdata['line']['data4'])) {
			$line_values4 = implode(',', $dispdata['line']['data4']);
			$title4       = $dispdata['line']['title4'];
		}
		if (!empty($dispdata['line']['data5'])) {
			$line_values5 = implode(',', $dispdata['line']['data5']);
			$title5       = $dispdata['line']['title5'];
		}

		$content .= "var $xid = document.getElementById('line_" . $xid . "').getContext('2d');\n";
		$content .= "new Chart($xid, {\n";
    		$content .= "type: 'line',\n";
		$content .= "data: {\n";
		$content .= "labels: [\"" . $line_labels . "\"],\n";
		$content .= "datasets: [{\n";
	    	$content .= "label: '" . $title1 . "',\n";
	    	$content .= "data: [" . $line_values . "],\n";
	    	$content .= "borderColor: 'rgba(220,220,220,0.5)',\n";
	    	$content .= "backgroundColor: 'rgba(220,220,220,0.5)',\n";
		$content .= "},\n";

		if (!empty($dispdata['line']['data2'])) {
			$content .= "{\n";
	    		$content .= "label: '" . $title2 . "',\n";
    	    		$content .= "data: [" . $line_values2 . "],\n";
    	    		$content .= "borderColor: '#0f0f00',\n";
			$content .= "},\n";
		}

		if (!empty($dispdata['line']['data3'])) {
			$content .= "{\n";
	    		$content .= "label: '" . $title3 . "',\n";
    	    		$content .= "data: [" . $line_values3 . "],\n";
    	    		$content .= "borderColor: '#f0000f',\n";
			$content .= "},\n";
		}

		if (!empty($dispdata['line']['data4'])) {
			$content .= "{\n";
	    		$content .= "label: '" . $title4 . "',\n";
    	    		$content .= "data: [" . $line_values4 . "],\n";
    	    		$content .= "borderColor: '#f0000f',\n";
			$content .= "},\n";
		}

		if (!empty($dispdata['line']['data5'])) {
			$content .= "{\n";
	    		$content .= "label: '" . $title5 . "',\n";
    	    		$content .= "data: [" . $line_values5 . "],\n";
    	    		$content .= "borderColor: '#f0000f',\n";
			$content .= "},\n";
		}

	$content .= "]\n";
    	$content .= "},\n";
    	$content .= "options: {\n";
	$content .= "responsive: false,\n";
	$content .= "tooltipTemplate: '<%= value %>%'\n";
    	$content .= "}\n";
	$content .= "});\n";
	$content .= "</script>\n";
	$content .= "</div>\n";
	} // line graph end

	if (isset($dispdata['pie'])) {

		$labely = array();
		$xid = 'x'. substr(md5($dispdata['pie']['title']), 0, 7);
		foreach ($dispdata['pie']['label'] as $key => $val) {
			$labely[$key] = $val . ' (' . $dispdata['pie']['data'][$key] . ')';
		}

		$content .= "<div style=\"background: $bgcolor;\"><canvas id=\"pie_$xid\"></canvas>\n";
		$content .= "<script type='text/javascript'>\n";

		$pie_labels = implode('","', $labely);

		$pie_values = implode(',', $dispdata['pie']['data']);
		$pie_title  = $dispdata['pie']['title'];
		
		$content .= "var $xid = document.getElementById('pie_" . $xid . "').getContext('2d');\n";
		$content .= "new Chart($xid, {\n";
		$content .= "type: 'pie',\n";
		$content .= "data: {\n";
		$content .= "labels: [\"" . $pie_labels . "\"],\n";
		$content .= "datasets: [{\n";
		$content .= "backgroundColor: [ '#2ecc71', '#e74c3c', '#f1c40f', '#6b6966', '#3498db', '#33ffe6', ],\n";
		$content .= "data: [" . $pie_values . "]\n";  
		$content .= "}]\n";
		$content .= "},\n";
		$content .= "options: {\n";
		$content .= "responsive: false,\n";
		$content .= "title: { display: false, text: '" . $pie_title . "' },\n";
		$content .= "legend: {\n";
		$content .= "display: true,\n";
		$content .= "position: 'right',\n";
		$content .= "labels: {\n";
		$content .= "usePointStyle: true,\n";
		$content .= "}\n";
		$content .= "},\n";
		$content .= "tooltipTemplate: '<%= value %>%'\n";
		$content .= "}\n";
		$content .= "});\n";
		$content .= "</script>\n";
		$content .= "</div>\n";
	}   // pie graph end 

	return (addslashes($content));	
}

function tail_log($log_file, $nbr_lines = 1000, $adaptive = true) {
	if (!(file_exists($log_file) && is_readable($log_file))) {
		return false;
	}

	$f_handle = @fopen($log_file, 'rb');
	if ($f_handle === false) {
		return false;
	}

	if (!$adaptive) {
		$buffer = 4096;
	} else {
		$buffer = ($nbr_lines < 2 ? 64 : ($nbr_lines < 10 ? 512 : 4096));
	}

	fseek($f_handle, -1, SEEK_END);

	if (fread($f_handle, 1) != "\n") {
		$nbr_lines -= 1;
	}

	// Start reading
	$output = '';
	$chunk  = '';
	// While we would like more
	while (ftell($f_handle) > 0 && $nbr_lines >= 0) {
		// Figure out how far back we should jump
		$seek = min(ftell($f_handle), $buffer);
		// Do the jump (backwards, relative to where we are)
		fseek($f_handle, -$seek, SEEK_CUR);
		// Read a chunk and prepend it to our output
		$output = ($chunk = fread($f_handle, $seek)) . $output;
		// Jump back to where we started reading
		fseek($f_handle, -mb_strlen($chunk, '8bit'), SEEK_CUR);
		// Decrease our line counter
		$nbr_lines -= substr_count($chunk, "\n");
	}

	// While we have too many lines (Because of buffer size we might have read too many)
	while ($nbr_lines++ < 0) {
		// Find first newline and remove all text before that
		$output = substr($output, strpos($output, "\n") + 1);
	}

	// Close file
	fclose($f_handle);

	return explode("\n", $output);
}

function human_filesize($bytes, $decimals = 2) {
	$size   = array('B', 'kB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB');
	$factor = floor((strlen($bytes) - 1) / 3);
	return sprintf("%.{$decimals}f", $bytes / pow(1024, $factor)) . @$size[$factor];
}

function intropage_display_panel($panel_id) {
	global $config;

	print '<li id="panel_' . $panel_id . '" class="ui-state-default flexchild">';
	print '<div class="cactiTable" style="text-align:left; float: left; box-sizing: border-box;">';

	print '<div class="panel_header color_gray">';
	print '<span class="panel_name"></span>';

	printf("<a href='%s' data-panel='panel_$panel_id' class='header_link droppanel' title='" . __esc('Disable panel', 'intropage') . "'><i class='fa fa-times'></i></a>\n", "?intropage_action=droppanel&panel_id=$panel_id");

	printf("<a href='#' id='reloadid_" . $panel_id . "' title='" . __esc('Reload Panel', 'intropage') . "' class='header_link reload_panel_now'><i class='fa fa-retweet'></i></a>\n");

	printf("<a href='#' title='" . __esc('Show Details', 'intropage') . "' class='header_link maxim' detail-panel='%s'><i class='fa fa-window-maximize'></i></a>\n", $panel_id);

	print " </div>\n";
	print "	<table class='cactiTable'>\n";
	print "	    <tr><td class='textArea' style='vertical-align: top;'>\n";

	print "<div class='panel_data'>\n";
	print __('Loading data ...', 'intropage');
	print "</div>\n";	// end of panel_data
	print "</td></tr>\n\n";
	html_end_box(false);
	print "</li>\n\n";
}

function intropage_display_data($panel_id,$dispdata) {
	global $config;

	if (isset($dispdata['data'])) {	// display text data
		print stripslashes($dispdata['data']);
		
		if (is_null(db_fetch_cell_prepared('SELECT fav_graph_id FROM plugin_intropage_panel_data where id= ?',
			array($panel_id))))	{
		
			print '<br/>Last update: ' . $dispdata['last_update'];
			print '<br/>' . 'Recheck every: ' . $dispdata['recheck'];
		}
		// !!!! tady pak udela lokalizaci
	}
}

function ntp_time($host) {

	$timestamp = -1;
	$sock      = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);

	$timeout = array('sec' => 1, 'usec' => 400000);
	socket_set_option($sock, SOL_SOCKET, SO_RCVTIMEO, $timeout);
	socket_clear_error();

	socket_connect($sock, $host, 123);
	if (socket_last_error() == 0) {
		// Send request
		$msg = "\010" . str_repeat("\0", 47);
		socket_send($sock, $msg, strlen($msg), 0);
		// Receive response and close socket

		if (@socket_recv($sock, $recv, 48, MSG_WAITALL)) {
			socket_close($sock);
			// Interpret response
			$data      = unpack('N12', $recv);
			$timestamp = sprintf('%u', $data[9]);
			// NTP is number of seconds since 0000 UT on 1 January 1900
			// Unix time is seconds since 0000 UT on 1 January 1970
			$timestamp -= 2208988800;
		} else {
		    $timestamp = "error";
		}
	} else {
	    $timestamp = "error";
	}

	return ($timestamp);
}


function intropage_graph_button($data) {
	global $config;

	if (db_fetch_cell_prepared('SELECT intropage_favourite_graph FROM user_auth 
		WHERE id= ?', array($_SESSION['sess_user_id'])) == 'on') {
		$local_graph_id = $data[1]['local_graph_id'];

		if (db_fetch_cell_prepared('SELECT COUNT(*) FROM plugin_intropage_panel_data 
			WHERE user_id= ? AND fav_graph_id= ? & fav_graph_timespan= ?', 
			array($_SESSION['sess_user_id'],$local_graph_id,$_SESSION['sess_current_timespan'] )) > 0) {       // already fav
			$fav = '<i class="fa fa-eye-slash" title="' . __esc('Remove from Dashboard', 'intropage') . '"></i>';
		} else {       // add to fav
			$fav = '<i class="fa fa-eye" title="' . __esc('Add to Dashboard', 'intropage') . '"></i>';
		}

		$lopts = db_fetch_cell_prepared('SELECT login_opts
			FROM user_auth
			WHERE id = ?',
			array($_SESSION['sess_user_id']));

		if ($lopts == 4) { // in tab
			print '<a class="iconLink" href="' . htmlspecialchars($config['url_path']) . 'plugins/intropage/intropage.php?intropage_action=favgraph&graph_id=' . $local_graph_id . '">' . $fav . '</a><br/>';
		} else {	// in console
			print '<a class="iconLink" href="' . htmlspecialchars($config['url_path']) . '?intropage_action=favgraph&graph_id=' . $local_graph_id . '">' . $fav . '</a><br/>';
		}
	}
}

