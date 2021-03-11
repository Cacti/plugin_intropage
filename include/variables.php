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

$intropage_settings = array(	// default values
	'intropage_display_header' => array(
		'friendly_name' => __('Display Settings', 'intropage'),
		'method' => 'spacer',
	),
	'intropage_display_important_first' => array(
		'friendly_name' => __('Important things will be at the top', 'intropage'),
		'description' => __('If checked Intropage displays important (errors, warnings) information first', 'intropage'),
		'method' => 'checkbox',
		'default' => 'off',
	),
	'intropage_unregister' => array(
		'friendly_name' => __('Automatically Uninstall Panels', 'intropage'),
		'description' => __('If a Panel is installed and requried plugin is removed, automatically uninstall the panel too', 'intropage'),
		'method' => 'checkbox',
		'default' => '',
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
		'default' => '1800',
		'array' => array(
			60   => __('%d Minute', 1),
			300  => __('%d Minutes', 5),
			600  => __('%d Minutes', 10),
			900  => __('%d Minutes', 15),
			1200 => __('%d Minutes', 20),
			1800 => __('%d Minutes', 30),
			2400 => __('%d Minutes', 40),
			3600 => __('%d Hour', 1)
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
		'friendly_name' => __('Admin information panel about maintenance tasks, down Devices, ..', 'intropage'),
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
	'intropage_display_header2' => array(
		'friendly_name' => __('Alarm Settings', 'intropage'),
		'method' => 'spacer',
	),
	'intropage_alert_db_abort' => array(
		'friendly_name' => __('Alarm DB check aborted clients', 'intropage'),
		'description' => __('<strong>How to be notified?</strong>', 'intropage'),
		'method' => 'drop_array',
		'array' => array(
			'red'   => __('Red alarm', 'intropage'),
			'yellow'  => __('Yellow alarm', 'intropage'),
			'green' => __('Green alarm', 'intropage')
		),
		'default' => 'red',
	),
	'intropage_alert_same_description' => array(
		'friendly_name' => __('Alarm Host with the same description', 'intropage'),
		'description' => __('<strong>How to be notified?</strong>', 'intropage'),
		'method' => 'drop_array',
		'array' => array(
			'red'   => __('Red alarm', 'intropage'),
			'yellow'  => __('Yellow alarm', 'intropage'),
			'green' => __('Green alarm', 'intropage')
		),
		'default' => 'yelow',
	),
	'intropage_alert_orphaned_ds' => array(
		'friendly_name' => __('Alarm orphaned data source', 'intropage'),
		'description' => __('<strong>How to be notified?</strong>', 'intropage'),
		'method' => 'drop_array',
		'array' => array(
			'red'   => __('Red alarm', 'intropage'),
			'yellow'  => __('Yellow alarm', 'intropage'),
			'green' => __('Green alarm', 'intropage')
		),
		'default' => 'yellow',
	),
	'intropage_alert_poller_output' => array(
		'friendly_name' => __('Alarm non-empty poller output', 'intropage'),
		'description' => __('<strong>How to be notified?</strong>', 'intropage'),
		'method' => 'drop_array',
		'array' => array(
			'red'   => __('Red alarm', 'intropage'),
			'yellow'  => __('Yellow alarm', 'intropage'),
			'green' => __('Green alarm', 'intropage')
		),
		'default' => 'red',
	),
	'intropage_alert_bad_indexes' => array(
		'friendly_name' => __('Alarm Bad indexes data source', 'intropage'),
		'description' => __('<strong>How to be notified?</strong>', 'intropage'),
		'method' => 'drop_array',
		'array' => array(
			'red'   => __('Red alarm', 'intropage'),
			'yellow'  => __('Yellow alarm', 'intropage'),
			'green' => __('Green alarm', 'intropage')
		),
		'default' => 'red',
	),
	'intropage_alert_thold_logonly' => array(
		'friendly_name' => __('Alarm Thershold logonly action', 'intropage'),
		'description' => __('<strong>How to be notified?</strong>', 'intropage'),
		'method' => 'drop_array',
		'array' => array(
			'red'   => __('Red alarm', 'intropage'),
			'yellow'  => __('Yellow alarm', 'intropage'),
			'green' => __('Green alarm', 'intropage')
		),
		'default' => 'red',
	),
	'intropage_alert_same_ip' => array(
		'friendly_name' => __('Alarm Device with the same IP/port', 'intropage'),
		'description' => __('<strong>How to be notified?</strong>', 'intropage'),
		'method' => 'drop_array',
		'array' => array(
			'red'   => __('Red alarm', 'intropage'),
			'yellow'  => __('Yellow alarm', 'intropage'),
			'green' => __('Green alarm', 'intropage')
		),
		'default' => 'yellow',
	),
	'intropage_alert_more_trees' => array(
		'friendly_name' => __('Alarm Device in more Trees', 'intropage'),
		'description' => __('<strong>How to be notified?</strong>', 'intropage'),
		'method' => 'drop_array',
		'array' => array(
			'red'   => __('Red alarm', 'intropage'),
			'yellow'  => __('Yellow alarm', 'intropage'),
			'green' => __('Green alarm', 'intropage')
		),
		'default' => 'yellow',
	),
	'intropage_alert_without_tree' => array(
		'friendly_name' => __('Alarm Device without Tree', 'intropage'),
		'description' => __('<strong>How to be notified?</strong>', 'intropage'),
		'method' => 'drop_array',
		'array' => array(
			'red'   => __('Red alarm', 'intropage'),
			'yellow'  => __('Yellow alarm', 'intropage'),
			'green' => __('Green alarm', 'intropage')
		),
		'default' => 'yellow',
	),
	'intropage_alert_default_community' => array(
		'friendly_name' => __('Alarm Device with default public/private community', 'intropage'),
		'description' => __('<strong>How to be notified?</strong>', 'intropage'),
		'method' => 'drop_array',
		'array' => array(
			'red'   => __('Red alarm', 'intropage'),
			'yellow'  => __('Yellow alarm', 'intropage'),
			'green' => __('Green alarm', 'intropage')
		),
		'default' => 'yellow',
	),
	'intropage_alert_without_monitoring' => array(
		'friendly_name' => __('Alarm Device without monitoring', 'intropage'),
		'description' => __('<strong>How to be notified?</strong>', 'intropage'),
		'method' => 'drop_array',
		'array' => array(
			'red'   => __('Red alarm', 'intropage'),
			'yellow'  => __('Yellow alarm', 'intropage'),
			'green' => __('Green alarm', 'intropage')
		),
		'default' => 'yellow',
	),
	'intropage_alert_without_graph' => array(
		'friendly_name' => __('Alarm Device without Graph', 'intropage'),
		'description' => __('<strong>How to be notified?</strong>', 'intropage'),
		'method' => 'drop_array',
		'array' => array(
			'red'   => __('Red alarm', 'intropage'),
			'yellow'  => __('Yellow alarm', 'intropage'),
			'green' => __('Green alarm', 'intropage')
		),
		'default' => 'yellow',
	),

	'intropage_alert_worst_polling_time' => array(
		'friendly_name' => __('Alarm red/yellow polling time', 'intropage'),
		'description' => __('<strong>How to be notified?</strong>', 'intropage'),
		'method' => 'drop_array',
		'array' => array(
			'10/5' => __('10s/5s red/yellow', 'intropage'),
			'20/10'  => __('20s/10s red/yellow', 'intropage'),
			'40/20' => __('40s/20s red/yellow', 'intropage')
		),
		'default' => '20/10',
	),
	'intropage_alert_worst_polling_ratio' => array(
		'friendly_name' => __('Alarm red/yellow failed/all ratio', 'intropage'),
		'description' => __('<strong>How to be notified?</strong>', 'intropage'),
		'method' => 'drop_array',
		'array' => array(
			'0.2/0.1' => __('0.1/0.2 red/yellow', 'intropage'),
			'0.4/0.2'  => __('20s/10s red/yellow', 'intropage'),
			'0.5/more' => __('more red/yellow', 'intropage')
		),
		'default' => '0.4/0.2',
	),
	'intropage_alert_worst_ping' => array(
		'friendly_name' => __('Alarm red/yellow ping', 'intropage'),
		'description' => __('<strong>How to be notified?</strong>', 'intropage'),
		'method' => 'drop_array',
		'array' => array(
			'20/10' => __('20/10ms red/yellow', 'intropage'),
			'50/20'  => __('50/20ms red/yellow', 'intropage'),
			'100/200' => __('100/200+ms red/yellow', 'intropage')
		),
		'default' => '20/10',
	),
	'intropage_alert_worst_availability' => array(
		'friendly_name' => __('Alarm red/yellow worst availability', 'intropage'),
		'description' => __('<strong>How to be notified?</strong>', 'intropage'),
		'method' => 'drop_array',
		'array' => array(
			'99/95' => '99/95% ' . __('red/yellow', 'intropage'),
			'95/85'  => '95/85% ' . __('red/yellow', 'intropage'),
			'85/0' => '85/84-0% ' . __('red/yellow', 'intropage')
		),
		'default' => '99/95',
	),

);

