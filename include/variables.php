<?php

// priority (order) (bigger number =  highest priority)
$panel = array();

$panel['analyse_log']['priority'] = 60;

$panel['analyse_login']['priority'] = 75;

$panel['thold_events']['priority'] = 77;

$panel['analyse_db']['priority'] = 70;

$panel['analyse_tree_host_graph']['priority'] = 50;

$panel['trend']['priority'] = 30;

$panel['extrem']['priority'] = 30;

$panel['ntp']['priority'] = 60;

$panel['poller_info']['priority'] = 50;

$panel['poller_stat']['priority'] = 50;

$panel['graph_host']['priority'] = 14;

$panel['graph_thold']['priority'] = 13;

$panel['graph_data_source']['priority'] = 12;

$panel['graph_host_template']['priority'] = 11;

$panel['cpu']['priority'] = 45;

$panel['top5_ping']['priority'] = 30;

$panel['top5_availability']['priority'] = 31;

$panel['info']['priority'] = 1;

$panel['boost']['priority'] = 55;



$intropage_settings = array(

	"intropage_display_header" => array(
		"friendly_name" => "Display settings",
		"method" => "spacer",
	),
	"intropage_display_important_first" => array(
		"friendly_name" => "Important things will be at the top",
		"description" => "If checked Intropage displays imporatnt (errors, warnings) information first",
		"method" => "checkbox",
		"default" => "off",
	),
	"intropage_autorefresh" => array(
		"friendly_name" => "Automatic refresh page",
		"description" => "How often",
		"method" => "drop_array",
		"array" => array("0" => "Never", "60" => "Every minute", "180" => "Every 3 minutes", "600" => "Every 10 minutes",),
		"default" => "0",
	),
	"intropage_display_level" => array(
		"friendly_name" => "Display",
		"description" => "What will you see",
		"method" => "drop_array",
		"array" => array("0" => "Only errors", "1" => "Errors and warnings", "2" => "All",),
		"default" => "2",
	),

// analyse_log
	"intropage_analyse_header" => array(
		"friendly_name" => "Logs",
		"method" => "spacer",
	),

	"intropage_analyse_log" => array(
		"friendly_name" => "Allow panel Log analyse (size of log, errors, ...) for users",
		"description" => "if checked panel is allowed for users",
		"method" => "checkbox",
		"default" => "on",
	),
	"intropage_analyse_log_rows" => array(
		"friendly_name" => "Number of lines",
		"description" => "How many lines of log will be analysed. Big number may causes slow page load",
		"method" => "textbox",
		"max_length" => 5,
		"default" => "1000",
	),
	
	
// analyse_login	
	"intropage_analyse_login_header" => array(
		"friendly_name" => "Login analyse settings",
		"method" => "spacer",
	),
	"intropage_analyse_login" => array(
		"friendly_name" => "Allow panel logins analyse (last logins, imcorrect password)",
		"description" => "if checked panel is allowed for users",
		"method" => "checkbox",
		"default" => "on",
	),


// plugin thold - log	
	"intropage_thold_header" => array(
		"friendly_name" => "Allow Analyse Thold log",
		"method" => "spacer",
	),
	"intropage_thold_events" => array(
		"friendly_name" => "Allow panel Analyse Thold log for users",
		"description" => "if checked panel is allowed for users",
		"method" => "checkbox",
		"default" => "on",
	),


// analyse_db	
	"intropage_analyse_db_header" => array(
		"friendly_name" => "Analyse MySQL DB",
		"method" => "spacer",
	),
	"intropage_analyse_db" => array(
		"friendly_name" => "Allow panel Analyse DB (check tables, connections, ...) for users ",
		"description" => "if checked panel is allowed for users",
		"method" => "checkbox",
		"default" => "on",
	),
	"intropage_analyse_db_level" => array(
		"friendly_name" => "Level of db check",
		"description" => "Quick - no check rows for inforccert links<br/>Fast - check only not properly closed tables<br/>Changed - check tables changed from last check<br/>Medium - with rows scan<br/>Extended - full rows and keys<br/><strong>Medium and extended may causes slow page load!</strong>",
		"method" => "drop_array",
		"array" => array("QUICK" => "Quick", "FAST" => "Fast", "CHANGED" => "Changed", "MEDIUM" => "Medium", "EXTENDED"  => "Extended"),
		"default" => "Medium",
	),
	
	"intropage_analyse_tree_header" => array(
		"friendly_name" => "Analyse trees, hosts, graphs, orphaned DS, ...",
		"method" => "spacer",
	),
	
	
// analyse_tree_host_graph
	"intropage_analyse_tree_host_graph" => array(
		"friendly_name" => "Allow Panel Analyse tree_host_graph (find host with the same description, without graph, in more than one tree, orphaned DS) for users",
		"description" => "if checked panel is allowed for users",
		"method" => "checkbox",
		"default" => "on",
	),


// trend	
	"intropage_trend" => array(
		"friendly_name" => "Allow panel Trends (dispaly graph fordown  host and thold) for users",
		"description" => "if checked panel is allowed for users",
		"method" => "checkbox",
		"default" => "on",
	),
	
// 24h extrem
	"intropage_extrem" => array(
		"friendly_name" => "Allow panel Last 24 hour extrems",
		"description" => "if checked panel is allowed for users",
		"method" => "checkbox",
		"default" => "on",
	),

// ntp
	"intropage_ntp_header" => array(
		"friendly_name" => "NTP settings",
		"method" => "spacer",
	),
	"intropage_ntp" => array(
		"friendly_name" => "Allow panel NTP",
		"description" => "if checked panel is allowed for users",
		"method" => "checkbox",
		"default" => "on",
	),
	"intropage_ntp_server" => array(
		"friendly_name" => "IP or DNS name of NTP server",
		"description" => "Insert IP or DNS name of NTP server",
		"method" => "textbox",
		"max_length" => 50,
		"default" => "pool.ntp.org",
	),
	"intropage_poller_header" => array(
		"friendly_name" => "Poller",
		"method" => "spacer",
	),

// poller
	"intropage_poller_info" => array(
		"friendly_name" => "Allow panel Poller info (how many poller and how long pollers run) for users",
		"description" => "if checked panel is allowed for users",
		"method" => "checkbox",
		"default" => "on",
	),


	"intropage_poller_stat" => array(
		"friendly_name" => "Allow panel Poller Stats (poller history graph) for users",
		"description" => "if checked panel is allowed for users",
		"method" => "checkbox",
		"default" => "on",
	),


// boost 
	"intropage_boost" => array(
		"friendly_name" => "Allow Panel Boost stats for users",
		"description" => "if checked panel is allowed for users",
		"method" => "checkbox",
		"default" => "on",
	),

	"intropage_graphs_header" => array(
		"friendly_name" => "Graphs",
		"method" => "spacer",
	),

	
// graph_host
	"intropage_graph_host" => array(
		"friendly_name" => "Allow panel Graph hosts (up/down/recovering/..) for users",
		"description" => "if checked panel is allowed for users",
		"method" => "checkbox",
		"default" => "on",
	),
	
// graph threshold	
	"intropage_graph_thold" => array(
		"friendly_name" => "Allow panel Graph thresholds (ok/trigerred/..) for users",
		"description" => "if checked panel is allowed for users",
		"method" => "checkbox",
		"default" => "on",
	),
	
// graph_data_source	
	"intropage_graph_data_source" => array(
		"friendly_name" => "Allow panel Graph datasources (SNMP/script/ ..) for users",
		"description" => "if checked panel is allowed for users",
		"method" => "checkbox",
		"default" => "on",
	),
	
// graph template	
	"intropage_graph_host_template" => array(
		"friendly_name" => "Allow panel Graph templates (generic/win/printer/..) for users",
		"description" => "if checked panel is allowed for users",
		"method" => "checkbox",
		"default" => "on",
	),

	"intropage_top5_header" => array(
		"friendly_name" => "Top5",
		"method" => "spacer",
	),
	
	
	"intropage_top5_ping" => array(
		"friendly_name" => "Allow panel Top 5 worst ping for users",
		"description" => "if checked panel is allowed for users",
		"method" => "checkbox",
		"default" => "on",
	),

	"intropage_top5_availability" => array(
		"friendly_name" => "Allow panel Display top 5 worst availability for users",
		"description" => "if checked panel is allowed for users",
		"method" => "checkbox",
		"default" => "on",
	),



// info - os, php apod
	"intropage_info_header" => array(
		"friendly_name" => "Info",
		"method" => "spacer",
	),


	"intropage_info" => array(
		"friendly_name" => "Allow panel Info (OS and poller type) for users",
		"description" => "if checked panel is allowed for users",
		"method" => "checkbox",
		"default" => "on",
	),

	"intropage_cpu" => array(
		"friendly_name" => "Allow panel CPU utilization (Linux/Unix only) for users",
		"description" => "if checked panel is allowed for users",
		"method" => "checkbox",
		"default" => "on",
	),
	"intropage_debug" => array(
		"friendly_name" => "Allow panel Debug for users",
		"description" => "if checked panel is allowed for users",
		"method" => "checkbox",
		"default" => "on",
	),

);

?>
