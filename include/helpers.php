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

function intropage_display_panel($panel_id, $type, $header, $dispdata) {
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

	print '<li id="panel_' . $panel_id . '" class="ui-state-default flexchild">';
	print '<div class="cactiTable" style="text-align:left; float: left; box-sizing: border-box; ">';

	print '<div class="panel_header color_' . $type . '">';
	print $header;

	if ($panel_id > 990) {
		printf("<a href='#' title='" . __esc('You cannot disable this panel', 'intropage') . "' class='header_link'>&nbsp; <i class='fa fa-times'></i></a>\n");
	} else {
		printf("<a href='%s' title='" . __esc('Disable panel', 'intropage') . "' class='header_link'>&nbsp; <i class='fa fa-times'></i></a>\n", "?intropage_action=droppanel&panel_id=$panel_id");
	}

	printf("<a href='#' id='reloadid_" . $panel_id . "' title='" . __esc('Reload Panel', 'intropage') . "' class='header_link reload_panel_now'>&nbsp; <i class='fa fa-retweet'></i></a>\n");

	if (isset($dispdata['detail']) && !empty($dispdata['detail'])) {
		printf("<a href='#' title='" . __esc('Show Details', 'intropage') . "' class='header_link maxim' name='%s'><i class='fa fa-window-maximize'></i></a>\n", md5($panel_id));
	}

	print " </div>\n";
	print "	<table class='cactiTable'>\n";
	print "	    <tr><td class='textArea' style='vertical-align: top;'>\n";

	print "<div class='panel_data'>\n";

	print "</div>\n";	// end of panel_data
	print "</td></tr>\n\n";
	html_end_box(false);
	print "</li>\n\n";
}

function intropage_display_data($panel_id,$dispdata) {
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

//	print "	<table class='cactiTable'>\n";
//	print "	    <tr><td class='textArea' style='vertical-align: top;'>\n";

//	print "<div class='panel_data'>\n";

	// pie graph

	if (isset($dispdata['pie'])) {

	//---------zacatek kresleni grafu
		// Display PIE
		$labely = array();

		$xid = 'x'. substr(md5($dispdata['pie']['title']), 0, 7);

		foreach ($dispdata['pie']['label'] as $key => $val) {
			$labely[$key] = $val . ' (' . $dispdata['pie']['data'][$key] . ')';
		}

		print "<div style=\"background: $bgcolor;\"><canvas id=\"pie_$xid\"></canvas>\n";
		print "<script type='text/javascript'>\n";

		$pie_labels = implode('","', $labely);

		$pie_values = implode(',', $dispdata['pie']['data']);
		$pie_title  = $dispdata['pie']['title'];
		print <<<EOF
var $xid = document.getElementById("pie_$xid").getContext("2d");
new Chart($xid, {
    type: 'pie',
    data: {
	labels: ["$pie_labels"],
	datasets: [{
	    backgroundColor: [ "#2ecc71", "#e74c3c", "#3498db", "#9b59b6", "#f1c40f", "#33ffe6", ],
	    data: [$pie_values]
	}]
    },
    options: {
	responsive: false,
	title: { display: false, text: "$pie_title" },
	legend: {
	    display: true,
	    position: 'right',
	    labels: {
		usePointStyle: true,
	    }
	},
	tooltipTemplate: "<%= value %>%"
    }
});
EOF;
		print "</script></div>\n";
	}   // pie graph end ------------------------------------
	elseif (isset($dispdata['bar'])) {
		$xid = 'x' . substr(md5($dispdata['bar']['title1']), 0, 7);

		print "<div style=\"background: $bgcolor;\"><canvas id=\"bar_$xid\"></canvas>\n";
		print "<script type='text/javascript'>\n";
		$bar_labels1 = implode('","', $dispdata['bar']['label1']);
		$bar_values1 = implode(',', $dispdata['bar']['data1']);
		$bar_title1  = $dispdata['bar']['title1'];

		$bar_labels2 = implode('","', $dispdata['bar']['label1']);
		$bar_values2 = implode(',', $dispdata['bar']['data2']);
		$bar_title2  = $dispdata['bar']['title2'];

		print <<<EOF
var $xid = document.getElementById("bar_$xid").getContext("2d");
new Chart($xid, {
    type: 'bar',
    data: {
	labels: ["$bar_labels1"],
	datasets: [{
	    label: '$bar_title1',
	    data: [$bar_values1],
	    borderColor: 'rgba(220,220,220,0.5)',
	    backgroundColor: 'rgba(220,220,220,0.5)',
	},{
    	    type: 'line',
    	    label: '$bar_title2',
    	    data: [$bar_values2],
    	    fill: false,
    	    borderColor: 'red',
    	    pointStyle: 'line',
    	    pointBorderWidth: 1
	}
	]
    },
    options: {
	responsive: false,
	tooltipTemplate: "<%= value %>%"

    }
});
EOF;
		print "</script>\n";
		print "</div>\n";
	} // bar graph end

	// line graph
	elseif (isset($dispdata['line'])) {
		$xid = 'x' . substr(md5($dispdata['line']['title1']), 0, 7);

		print "<div style=\"background: $bgcolor;\"><canvas id=\"line_$xid\"></canvas>\n";
		print "<script type='text/javascript'>\n";
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

		print <<<EOF
var $xid = document.getElementById("line_$xid").getContext("2d");
new Chart($xid, {
    type: 'line',
    data: {
	labels: ["$line_labels"],
	datasets: [{
	    label: '$title1',
	    data: [$line_values],
	    borderColor: 'rgba(220,220,220,0.5)',
	    backgroundColor: 'rgba(220,220,220,0.5)',

	},
EOF;

		if (!empty($dispdata['line']['data2'])) {
			print <<<EOF
	{
	    label: '$title2',
    	    data: [$line_values2],
    	    borderColor: "#0f0f00",
	},
EOF;
		}

		if (!empty($dispdata['line']['data3'])) {
			print <<<EOF
	{
	    label: '$title3',
    	    data: [$line_values3],
    	    borderColor: "#f0000f",
	},
EOF;
		}

		if (!empty($dispdata['line']['data4'])) {
			print <<<EOF
	{
	    label: '$title4',
    	    data: [$line_values4],
    	    borderColor: "#0000ff",
	},
EOF;
		}


		if (!empty($dispdata['line']['data5'])) {
			print <<<EOF
	{
	    label: '$title5',
    	    data: [$line_values5],
    	    borderColor: "#00ff00",
	},
EOF;
		}

		print <<<EOF

	]
    },
    options: {
	responsive: false,
	tooltipTemplate: "<%= value %>%"
    }
});
EOF;
		print "</script>\n";

		print "</div>\n";
	} // line graph end

	elseif (isset($dispdata['data'])) {	// display text data
		print $dispdata['data'];
	}

	// end of graph

	if (isset($dispdata['detail'])) {
		printf("<div id=\"%s\" style=\"display: none\">\n", md5($panel_id));
		print($dispdata['detail']);
		print("</div>\n");
	}

//	print "</div>\n";	// end of panel_data

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

	return $timestamp;
}

function ntp_time2() {
	$ntp_time = ntp_time (read_config_option('intropage_ntp_server'));

	if ($ntp_time == 'error') {
		$diff_time = $ntp_time;
	} else {
		$diff_time = date('U') - $ntp_time;
	}

	db_execute_prepared("UPDATE plugin_intropage_trends SET value = ? WHERE name = ?", array($diff_time, 'ntp_diff_time'));
	db_execute_prepared("UPDATE plugin_intropage_trends SET value = ? WHERE name = ?", array(date('Y-m-d H:i:s', time()),'ntp__testdate'));
}

function db_check() {
	$damaged   = 0;
	$memtables = 0;

	$db_check_level = read_config_option('intropage_analyse_db_level');

	$text_result = '';
	$text_detail = '';

	$alarm = 'green';

	$tables = db_fetch_assoc('SHOW TABLES');
	foreach ($tables as $key => $val) {
		$row = db_fetch_row('check table ' . current($val) . ' ' . $db_check_level);

		if (preg_match('/^note$/i', $row['Msg_type']) && preg_match('/doesn\'t support/i', $row['Msg_text'])) {
			$memtables++;
		} elseif (!preg_match('/OK/i', $row['Msg_text']) && !preg_match('/Table is already up to date/i', $row['Msg_text'])) {
			$damaged++;
			$text_detail .= 'Table ' . $row['Table'] . ' status ' . $row['Msg_text'] . '<br/>';
		}
	}

	if ($damaged > 0) {
		$alarm = 'red';
		$text_result = '<span class="txt_big">' . __('DB problem', 'intropage') . '</span><br/><br/>';
	} else {
		$text_result = '<span class="txt_big">' . __('DB OK', 'intropage') . '</span><br/><br/>';
	}

	// connection errors
	$cerrors = 0;
	$con_err = db_fetch_assoc("SHOW GLOBAL STATUS LIKE '%Connection_errors%'");

	foreach ($con_err as $key => $val) {
		$cerrors = $cerrors + $val['Value'];
	}

	if ($cerrors > 0) {     // only yellow
		$text_detail .= __('Connection errors - try to restart SQL service.', 'intropage') . '<br/>';

		if ($alarm == 'green') {
			$alarm = 'yellow';
		}
	}

	$text_result .= __('Connection errors: %s', $cerrors, 'intropage') . '<br/>';
	$text_result .= __('Damaged tables: %s', $damaged, 'intropage') . '<br/>' .
		__('Memory tables: %s', $memtables, 'intropage') . '<br/>' .
		__('All tables: %s', count($tables), 'intropage');

	db_execute_prepared("UPDATE plugin_intropage_trends SET value = ? WHERE name = ?", array($text_result, 'db_check_result'));
	db_execute_prepared("UPDATE plugin_intropage_trends SET value = ? WHERE name = ?", array($alarm, 'db_check_alarm'));
	db_execute_prepared("UPDATE plugin_intropage_trends SET value = ? WHERE name = ?", array($text_detail, 'db_check_detail'));
	db_execute_prepared("UPDATE plugin_intropage_trends SET value = ? WHERE name = ?", array(date('Y-m-d H:i:s', time()), 'db_check_testdate'));

}

function intropage_graph_button($data) {
	global $config;

	if (db_fetch_cell('SELECT intropage_favourite_graph FROM user_auth WHERE id=' . $_SESSION['sess_user_id']) == 'on') {
		$local_graph_id = $data[1]['local_graph_id'];

		if (db_fetch_cell('SELECT COUNT(*) FROM plugin_intropage_user_setting WHERE user_id=' . $_SESSION['sess_user_id'] .
			' and fav_graph_id=' . $local_graph_id) > 0) {       // already fav
			$fav = '<i class="fa fa-eye-slash" title="' . __esc('Remove from Dashboard', 'intropage') . '"></i>';
		} else {       // add to fav
			$fav = '<i class="fa fa-eye" title="' . __esc('Add to Dashboard', 'intropage') . '"></i>';
		}

		$lopts = db_fetch_cell_prepared('SELECT intropage_opts
			FROM user_auth
			WHERE id = ?',
			array($_SESSION['sess_user_id']));

		if ($lopts == 1) { // in tab
			print '<a class="iconLink" href="' . htmlspecialchars($config['url_path']) . 'plugins/intropage/intropage.php?intropage_action=favgraph&graph_id=' . $local_graph_id . '">' . $fav . '</a><br/>';
		} else {	// in console
			print '<a class="iconLink" href="' . htmlspecialchars($config['url_path']) . '?intropage_action=favgraph&graph_id=' . $local_graph_id . '">' . $fav . '</a><br/>';
		}
	}
}

