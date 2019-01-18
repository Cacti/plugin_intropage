<?php

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


// last parameter full = false - send only content of div 'panel_data', it is for single panel reload
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
		printf("<a href='#' title='You cannot disable this panel' class='header_link'>&nbsp; <i class='fa fa-times'></i></a>\n");
	} else {
		printf("<a href='%s' title='Disable panel' class='header_link'>&nbsp; <i class='fa fa-times'></i></a>\n", "?intropage_action=droppanel&panel_id=$panel_id");
	}

	printf("<a href='#' id='reloadid_" . $panel_id . "' title='Reload panel - not fully implemented' class='header_link reload_panel_now'>&nbsp; <i class='fa fa-retweet'></i></a>\n");


	if (isset($dispdata['detail']) && !empty($dispdata['detail'])) {
		printf("<a href='#' title='Show details' class='header_link maxim' name='%s'><i class='fa fa-window-maximize'></i></a>\n", md5($header));
	}

	print " </div>\n";
	print "	<table class='cactiTable'>\n";
	print "	    <tr><td class='textArea' style='vertical-align: top;'>\n";

	print "<div class='panel_data'>\n";

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
		printf("<div id=\"%s\" style=\"display: none\">\n", md5($header));
		print($dispdata['detail']);
		print("</div>\n");
	}

	print "</div>\n";	// end of panel_data

	print "</td></tr>\n\n";
	html_end_box(false);
	print "</li>\n\n";
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
		}
		else {
		    $timestamp = "error";
		}
	}
	else {
	    $timestamp = "error";
	}
	
	return $timestamp;
}

function intropage_graph_button($data) {
	global $config;

	if (db_fetch_cell('select intropage_favourite_graph from user_auth where id=' . $_SESSION['sess_user_id']) == 'on') {
		$local_graph_id = $data[1]['local_graph_id'];

		if (db_fetch_cell('select count(*) from plugin_intropage_user_setting where user_id=' . $_SESSION['sess_user_id'] .
			' and fav_graph_id=' . $local_graph_id) > 0) {       // already fav
			$fav = '<i class="fa fa-eye-slash" title="remove from dashboard"></i>';
		} else {       // add to fav
			$fav = '<i class="fa fa-eye" title="add to dashboard"></i>';
		}

		$lopts = db_fetch_cell('SELECT intropage_opts FROM user_auth WHERE id=' . $_SESSION['sess_user_id']);
		if ($lopts == 1) { // in tab
			print '<a class="iconLink" href="' . htmlspecialchars($config['url_path']) . 'plugins/intropage/intropage.php?intropage_action=favgraph&graph_id=' . $local_graph_id . '">' . $fav . '</a><br/>';
		} else {	// in console
			print '<a class="iconLink" href="' . htmlspecialchars($config['url_path']) . '?intropage_action=favgraph&graph_id=' . $local_graph_id . '">' . $fav . '</a><br/>';
		}
	}
}

