<?php

function intropage_display_panel ($size,$type,$header,$dispdata)	{

    print "<div id='item" . $_SESSION['intropage_cur_panel'] ."' class='flexchild'>\n";
	echo "<br/><br/>Ja jsem obsah divu " . $_SESSION['intropage_cur_panel'] . "<br/><br/>\n";


    print "</div>\n\n";

    $_SESSION['intropage_cur_panel']++;


/*    
    if (!empty($dispdata))	{	// empty? Typical for no console access
	

    $graph_height = 160;

    $selectedTheme = get_selected_theme();
    switch ($selectedTheme)	{
	case "dark":
	case "paper-plane":
	
	    $bgcolor = "#202020";
	break;
    
	case "sunrise":
	    $bgcolor = "";
	break;
    
	default:
	    $bgcolor = "#f5f5f5";
    }

    
    print "<div id='item" . $_SESSION['intropage_cur_panel'] ."' class='flexchild'>";


    print "<div class='cactiTable' style='text-align:left; float: left; box-sizing: border-box; padding-bottom: 5px;padding-right: 5px;'>\n";
    print "<div>\n";
    print "	    <div class='cactiTableTitle color_$type'><span class=\"pokus\">$header</span></div>\n";
    print "	    <div class='cactiTableButton2 color_$type'><span>";
    


    if (isset($dispdata['detail']))	{
        printf("<a href='#' onclick=\"hide_display('block_%s');\" title='View details'>&#8599;</a>\n",md5($header));
    }

    
    
    print "</span></div>\n";
    print "	</div>\n";
    
    print "	<table class='cactiTable' style='padding:3px;'>\n";
    print "	    <tr><td class='textArea' style='vertical-align: top;'>\n";

    print "<div class=\"panel_data\" style=\"min-height: " . $graph_height . "px; padding-right: 15px; padding-left: 5px;\">\n";


    // graph
    
    if (isset($dispdata['pie']))	{

	//---------zacatek kresleni grafu
	// Display PIE
		$labely = array();

		$xid = "x". substr(md5($dispdata['pie']['title']),0,7);

		foreach ($dispdata['pie']['label'] as $key=>$val)	{
		    $labely[$key] = $val . " (" . $dispdata['pie']['data'][$key] . ")";
		}

		print "<div style=\"background: $bgcolor;\"><canvas id=\"pie_$xid\" height=\"$graph_height\"></canvas>\n";
		print "<script type='text/javascript'>\n";
		
		$pie_labels = implode('","',$labely);

		$pie_values = implode(',',$dispdata['pie']['data']);
		$pie_title = $dispdata['pie']['title'];
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
	xxxbackgroundColor:'rgb(10,10,10)',
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
    elseif (isset($dispdata['bar']))	{

		$xid = "x" . substr(md5($dispdata['bar']['title1']),0,7);

		print "<div style=\"background: $bgcolor;\"><canvas id=\"bar_$xid\" height=\"$graph_height\"></canvas>\n";
		print "<script type='text/javascript'>\n";
		$bar_labels1 = implode('","',$dispdata['bar']['label1']);
		$bar_values1 = implode(',',$dispdata['bar']['data1']);
		$bar_title1 = $dispdata['bar']['title1'];

		$bar_labels2 = implode('","',$dispdata['bar']['label1']);
		$bar_values2 = implode(',',$dispdata['bar']['data2']);
		$bar_title2 = $dispdata['bar']['title2'];


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


///////////// line graph
    elseif (isset($dispdata['line']))	{

		$xid = "x" . substr(md5($dispdata['line']['title1']),0,7);

		print "<div style=\"background: $bgcolor;\"><canvas id=\"line_$xid\" height=\"$graph_height\"></canvas>\n";
		print "<script type='text/javascript'>\n";
		$title1 = $dispdata['line']['title1'];
		$line_labels = implode('","',$dispdata['line']['label1']);
		$line_values = implode(',',$dispdata['line']['data1']);

		if (!empty($dispdata['line']['data2']))	{
		    $line_values2 = implode(',',$dispdata['line']['data2']);
		    $title2 = $dispdata['line']['title2'];
		}
		if (!empty($dispdata['line']['data3']))	{
		    $line_values3 = implode(',',$dispdata['line']['data3']);
		    $title3 = $dispdata['line']['title3'];
		}
		if (!empty($dispdata['line']['data4']))	{
		    $line_values4 = implode(',',$dispdata['line']['data4']);
		    $title4 = $dispdata['line']['title4'];
		}
		if (!empty($dispdata['line']['data5']) )	{
		    $line_values5 = implode(',',$dispdata['line']['data5']);
		    $title5 = $dispdata['line']['title5'];
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
	
if (!empty($dispdata['line']['data2']))	{
	print <<<EOF
	{
	    label: '$title2',
    	    data: [$line_values2],
    	    borderColor: "#0f0f00",
	},
EOF;
}

if (!empty($dispdata['line']['data3']))	{
	print <<<EOF
	{
	    label: '$title3',
    	    data: [$line_values3],
    	    borderColor: "#f0000f",
	},
EOF;
}

if (!empty($dispdata['line']['data4']))	{
	print <<<EOF
	{
	    label: '$title4',
    	    data: [$line_values4],
    	    borderColor: "#0000ff",
	},
EOF;
}


if (!empty($dispdata['line']['data5']))	{
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

    if (isset($dispdata['detail']))	{
//        printf("<span style='float: right'><a href='#' onclick=\"hide_display('block_%s');\">View/hide details</a></span><br/>\n",md5($header));
        printf("<div id=\"block_%s\" style=\"display: none\">\n",md5($header));
        print($dispdata['detail']);
        print("</div>\n");
    }

    print "</div>\n";	// obalovy div kvuli min-height
    print "</td></tr>\n\n";
    html_end_box(false);
    print "</div>\n\n\n";

    $_SESSION['intropage_cur_panel']++;

    
    } // have console access
*/
}

?>
