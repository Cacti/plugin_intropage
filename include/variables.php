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

// priority (order) (bigger number =  highest priority)

// maint plugin and admin alert panels are always visible
/*
$panel                                                  = array();
$panel['intropage_analyse_log']['priority']             = 60;
$panel['intropage_analyse_login']['priority']           = 61;
$panel['intropage_thold_event']['priority']             = 90;
$panel['intropage_analyse_db']['priority']              = 62;
$panel['intropage_analyse_tree_host_graph']['priority'] = 63;
$panel['intropage_trend']['priority']                   = 40;
$panel['intropage_extrem']['priority']                  = 41;
$panel['intropage_ntp']['priority']                     = 50;
$panel['intropage_poller_info']['priority']             = 51;
$panel['intropage_poller_stat']['priority']             = 52;
$panel['intropage_graph_host']['priority']              = 20;
$panel['intropage_graph_thold']['priority']             = 21;
$panel['intropage_graph_data_source']['priority']       = 22;
$panel['intropage_graph_host_template']['priority']     = 23;
$panel['intropage_cpu']['priority']                     = 53;
$panel['intropage_mactrack']['priority']                = 20;
$panel['intropage_mactrack_sites']['priority']          = 21;
$panel['intropage_top5_ping']['priority']               = 22;
$panel['intropage_top5_availability']['priority']       = 23;
$panel['intropage_info']['priority']                    = 10;
$panel['intropage_boost']['priority']                   = 55;
$panel['intropage_top5_polltime']['priority']           = 24;
$panel['intropage_top5_pollratio']['priority']          = 25;
$panel['intropage_syslog']['priority']         		= 42;
*/


$intropage_settings = array(	// default values
	'intropage_display_header' => array(
		'friendly_name' => __('Display settings', 'intropage'),
		'method' => 'spacer',
	),
	'intropage_display_important_first' => array(
		'friendly_name' => __('Important things will be at the top', 'intropage'),
		'description' => __('If checked Intropage displays important (errors, warnings) information first', 'intropage'),
		'method' => 'checkbox',
		'default' => 'off',
	),
	'intropage_autorefresh' => array(
		'friendly_name' => __('Automatic refresh page', 'intropage'),
		'description' => __('How often', 'intropage'),
		'method' => 'drop_array',
		'array' => array(
			'-1'   => __('Automatically by poller', 'intropage'),
			'0'    => __('Never', 'intropage'),
			'60'   => __('Every Minute', 'intropage'),
			'300'  => __('Every %d Minutes', 5, 'intropage'),
			'3600' => __('Every Hour', 'intropage'),
		),
		'default' => '60',
	),
	'intropage_timeout' => array(
		'friendly_name' => __('Poller Timeout'),
		'description' => __('The amount of time, in minutes, that the Intropage background poller can run before being interrupted and killed by Cacti.'),
		'method' => 'drop_array',
		'default' => '300',
		'array' => array(
			60   => __('%d Minute', 1),
			300  => __('%d Minutes', 5),
			600  => __('%d Minutes', 10),
			900  => __('%d Seconds', 15),
			1200 => __('%d Seconds', 20)
		)
	),
	'intropage_analyse_log_rows' => array(
		'friendly_name' => __('Analyze log -  number of lines', 'intropage'),
		'description' => __('How many lines of log will be analysed. Lines = in panel, 2x lines = in detail. Big number may causes slow page load', 'intropage'),
		'method' => 'textbox',
		'max_length' => 5,
		'default' => '500',
	),

	'intropage_analyse_db_interval' => array(
		'friendly_name' => __('How often analyze DB', 'intropage'),
		'description' => __('Poller runs this task. It could cause long poller run.', 'intropage'),
		'method' => 'drop_array',
		'array' => array(
			'0'    		=> __('Never', 'intropage'),
			'3600'  	=> __('Every Hour', 'intropage'),
			'86400' 	=> __('Every Day', 'intropage'),
			'604800' 	=> __('Every Week', 'intropage'),
			'2592000' 	=> __('Every Month', 'intropage')
		),
		'default' => '604800',
	),
	'intropage_analyse_db_level' => array(
		'friendly_name' => __('Analyze DB - Level of db check', 'intropage'),
		'description' => __('Quick - No Check rows for inforccert links<br/>Fast - check only not properly closed tables<br/>Changed - check tables changed from last check<br/>Medium - with rows scan<br/>Extended - full rows and keys<br/><strong>Medium and extended may causes slow page load!</strong>', 'intropage'),
		'method' => 'drop_array',
		'array' => array(
			'QUICK'    => __('Quick', 'intropage'),
			'FAST'     => __('Fast', 'intropage'),
			'CHANGED'  => __('Changed', 'intropage'),
			'MEDIUM'   => __('Medium', 'intropage'),
			'EXTENDED' => __('Extended', 'intropage')
		),
		'default' => 'CHANGED',
	),

	'intropage_ntp_server' => array(
		'friendly_name' => __('NTP (time) check - IP or DNS name of NTP server', 'intropage'),
		'description' => __('Insert IP or DNS name of NTP server', 'intropage'),
		'method' => 'textbox',
		'max_length' => 50,
		'default' => 'pool.ntp.org',
	),

	'intropage_ntp_interval' => array(
		'friendly_name' => __('How often check NTP', 'intropage'),
		'description' => __('<strong>Poller runs this task. It could cause long poller run.</strong>', 'intropage'),
		'method' => 'drop_array',
		'array' => array(
			'900'   => __('Every %d Minutes', 15, 'intropage'),
			'3600'  => __('Every Hour', 'intropage'),
			'86400' => __('Every Day', 'intropage')
		),
		'default' => '3600',
	),


	'intropage_admin_alert' => array(
		'friendly_name' => __('Admin information panel about maintenance tasks, down devices, ..', 'intropage'),
		'description' => __('If isn\'t empty, panel will be displayed on the top. You can use html tags (b, i, ...).', 'intropage'),
		'method' => 'textarea',
		'max_length' => 1000,
		'textarea_rows' => '4',
		'textarea_cols' => '60',
		'default' => '',
	),

	'intropage_maint_plugin_days_before' => array(
		'friendly_name' => __('Maint plugin - how many days before display alert', 'intropage'),
		'description' => __('How many days?</strong>', 'intropage'),
		'method' => 'drop_array',
		'array' => array(
			'0'      => __('When maintenance starts', 'intropage'),
			'86400'  => __('%d Day Before', 1, 'intropage'),
			'259200' => __('%d Days Before', 3, 'intropage'),
			'604800' => __('%d Days Before', 7, 'intropage')
		),
		'default' => '86400',
	),
	
);

