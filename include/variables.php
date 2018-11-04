<?php

// priority (order) (bigger number =  highest priority)

// maint plugin and admin alert panels are always visible 

$panel = array();
$panel['intropage_analyse_log']['priority'] = 60;
$panel['intropage_analyse_login']['priority'] = 75;
$panel['intropage_thold_event']['priority'] = 77;
$panel['intropage_analyse_db']['priority'] = 70;
$panel['intropage_analyse_tree_host_graph']['priority'] = 50;
$panel['intropage_trend']['priority'] = 30;
$panel['intropage_extrem']['priority'] = 30;
$panel['intropage_ntp']['priority'] = 60;
$panel['intropage_poller_info']['priority'] = 50;
$panel['intropage_poller_stat']['priority'] = 50;
$panel['intropage_graph_host']['priority'] = 14;
$panel['intropage_graph_thold']['priority'] = 13;
$panel['intropage_graph_data_source']['priority'] = 12;
$panel['intropage_graph_host_template']['priority'] = 11;
$panel['intropage_cpu']['priority'] = 45;
$panel['intropage_mactrack']['priority'] = 42;
$panel['intropage_mactrack_sites']['priority'] = 43;
$panel['intropage_top5_ping']['priority'] = 30;
$panel['intropage_top5_availability']['priority'] = 31;
$panel['intropage_info']['priority'] = 1;
$panel['intropage_boost']['priority'] = 55;
$panel['intropage_favourite_graph']['priority'] = 95;

$intropage_settings = array(	// default values

	'intropage_display_header' => array(
		'friendly_name' => 'Display settings',
		'method' => 'spacer',
	),
	'intropage_display_important_first' => array(
		'friendly_name' => 'Important things will be at the top',
		'description' => 'If checked Intropage displays important (errors, warnings) information first',
		'method' => 'checkbox',
		'default' => 'off',
	),
	'intropage_autorefresh' => array(
		'friendly_name' => 'Automatic refresh page',
		'description' => 'How often',
		'method' => 'drop_array',
		'array' => array('0' => 'Never', '60' => 'Every minute', '180' => 'Every 3 minutes', '600' => 'Every 10 minutes',),
		'default' => '0',
	),
	'intropage_display_level' => array(
		'friendly_name' => 'Display',
		'description' => 'What will you see',
		'method' => 'drop_array',
		'array' => array('0' => 'Only errors', '1' => 'Errors and warnings', '2' => 'All',),
		'default' => '2',
	),
	'intropage_analyse_log_rows' => array(
		'friendly_name' => 'Analyse log -  number of lines',
		'description' => 'How many lines of log will be analysed. Big number may causes slow page load',
		'method' => 'textbox',
		'max_length' => 5,
		'default' => '1000',
	),
	
	'intropage_analyse_db_level' => array(
		'friendly_name' => 'Analyse DB - Level of db check',
		'description' => 'Quick - no check rows for inforccert links<br/>Fast - check only not properly closed tables<br/>Changed - check tables changed from last check<br/>Medium - with rows scan<br/>Extended - full rows and keys<br/><strong>Medium and extended may causes slow page load!</strong>',
		'method' => 'drop_array',
		'array' => array('QUICK' => 'Quick', 'FAST' => 'Fast', 'CHANGED' => 'Changed', 'MEDIUM' => 'Medium', 'EXTENDED'  => 'Extended'),
		'default' => 'Changed',
	),

	'intropage_ntp_server' => array(
		'friendly_name' => 'NTP (time) check - IP or DNS name of NTP server',
		'description' => 'Insert IP or DNS name of NTP server',
		'method' => 'textbox',
		'max_length' => 50,
		'default' => 'pool.ntp.org',
	),

	'intropage_admin_alert' => array(
		'friendly_name' => 'Admin information panel about maintenance tasks, down devices, ..',
		'description' => 'If isn\'t empty, panel will be displayed on the top. You can use html tags (b, i, ...).',
		'method' => 'textarea',
		'max_length' => 1000,
                'textarea_rows' => '4',
                'textarea_cols' => '60',
		'default' => '',
	),

	'intropage_maint_plugin_days_before' => array(
		'friendly_name' => 'Maint plugin - how many days before display alert',
		'description' => 'How many days?</strong>',
		'method' => 'drop_array',
		'array' => array('0' => 'When maintenance starts', '86400' => '1 day before', '259200' => '3 days before', '604800' => '7 days before'),
		'default' => '86400',
	),


);

?>
