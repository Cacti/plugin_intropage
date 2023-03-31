<?php
/* vim: ts=4
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2023 The Cacti Group, Inc.                           |
 | Copyright (C) 2004-2023 Petr Macek                                      |
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

$status_colors = array(
	'red'    => __('Red Status', 'intropage'),
	'yellow' => __('Yellow Status', 'intropage'),
	'green'  => __('Green Status', 'intropage')
);

$intropage_settings = array(
	'intropage_display_header' => array(
		'friendly_name' => __('Display Settings', 'intropage'),
		'method'        => 'spacer',
	),
	'intropage_display_important_first' => array(
		'friendly_name' => __('Important things will be at the top', 'intropage'),
		'description'   => __('If checked Intropage displays important (errors, warnings) information first', 'intropage'),
		'method'        => 'checkbox',
		'default'       => 'off',
	),
	'intropage_display_wide' => array(
		'friendly_name' => __('Display more panels on a line', 'intropage'),
		'description'   => __('For wide screen', 'intropage'),
		'method'        => 'checkbox',
		'default'       => 'off',
	),
	'intropage_unregister' => array(
		'friendly_name' => __('Automatically Uninstall Panels', 'intropage'),
		'description'   => __('If a Panel is installed and requried plugin is removed, automatically uninstall the panel too', 'intropage'),
		'method'        => 'checkbox',
		'default'       => '',
	),
	'intropage_autorefresh' => array(
		'friendly_name' => __('Automatic Page Refresh', 'intropage'),
		'description'   => __('How often', 'intropage'),
		'method'        => 'drop_array',
		'array'         => array(
			'-1'   => __('Automatically by poller', 'intropage'),
			'0'    => __('Never', 'intropage'),
			'60'   => __('Every Minute', 'intropage'),
			'300'  => __('Every %d Minutes', 5, 'intropage'),
			'3600' => __('Every Hour', 'intropage'),
		),
		'default'       => '60',
	),
	'intropage_important_period' => array(
		'friendly_name' => __('Important period', 'intropage'),
		'description'   => __('From now to past. Affects row coloring, older events will not be highlighted, only displayed', 'intropage'),
		'method'        => 'drop_array',
		'array'         => array(
			'-1'    => __('Disabled', 'intropage'),
			'900'   => __('15 minutes', 'intropage'),
			'3600'  => __('1 hour', 'intropage'),
			'14400' => __('4 hours', 'intropage'),
			'86400' => __('1 day', 'intropage'),
		),
		'default'       => '3600',
	),
	'intropage_number_of_lines' => array(
		'friendly_name' => __('Number of panel lines', 'intropage'),
		'description'   => __('How many lines in panel', 'intropage'),
		'method'        => 'drop_array',
		'array'         => array(
			'5'  => __('%d lines', 5, 'intropage'),
			'10' => __('%d lines', 10, 'intropage'),
			'15' => __('%d lines', 15, 'intropage'),
		),
		'default'       => '5',
	),
	'intropage_timespan' => array(
		'friendly_name' => __('Trend Timespan', 'intropage'),
		'description'   => __('For Trend charts, what should be the default timespan for those charts.', 'intropage'),
		'method'        => 'drop_array',
		'default'       => '14400',
		'array'         => $trend_timespans
	),
	'intropage_timeout' => array(
		'friendly_name' => __('Poller Timeout', 'intropage'),
		'description'   => __('The amount of time, in minutes, that the Intropage background poller can run before being interrupted and killed by Cacti.', 'intropage'),
		'method'        => 'drop_array',
		'default'       => '1800',
		'array'         => array(
			60   => __('%d Minute', 1, 'intropage'),
			300  => __('%d Minutes', 5, 'intropage'),
			600  => __('%d Minutes', 10, 'intropage'),
			900  => __('%d Minutes', 15, 'intropage'),
			1200 => __('%d Minutes', 20, 'intropage'),
			1800 => __('%d Minutes', 30, 'intropage'),
			2400 => __('%d Minutes', 40, 'intropage'),
			3600 => __('%d Hour', 1, 'intropage')
		)
	),
	'intropage_analyse_log_rows' => array(
		'friendly_name' => __('Analyze Log - number of lines', 'intropage'),
		'description'   => __('How many lines of log will be analysed. Lines = in panel, 2x lines = in detail. Big number may causes slow page load', 'intropage'),
		'method'        => 'textbox',
		'max_length' => 5,
		'default'       => '500',
	),
	'intropage_analyse_db_interval' => array(
		'friendly_name' => __('How often analyze DB', 'intropage'),
		'description'   => __('Poller runs this task. It could cause long poller run.', 'intropage'),
		'method'        => 'drop_array',
		'array'         => array(
			'0'    		=> __('Never', 'intropage'),
			'3600'  	=> __('Every Hour', 'intropage'),
			'86400' 	=> __('Every Day', 'intropage'),
			'604800' 	=> __('Every Week', 'intropage'),
			'2592000' 	=> __('Every Month', 'intropage')
		),
		'default'       => '604800',
	),
	'intropage_analyse_db_level' => array(
		'friendly_name' => __('Analyze DB - Level of db check', 'intropage'),
		'description'   => __('Quick - No Check rows for incorrect links<br/>Fast - check only not properly closed tables<br/>Changed - check tables changed from last check<br/>Medium - with rows scan<br/>Extended - full rows and keys<br/>Medium and extended may cause a slow page load!', 'intropage'),
		'method'        => 'drop_array',
		'array'         => array(
			'QUICK'    => __('Quick', 'intropage'),
			'FAST'     => __('Fast', 'intropage'),
			'CHANGED'  => __('Changed', 'intropage'),
			'MEDIUM'   => __('Medium', 'intropage'),
			'EXTENDED' => __('Extended', 'intropage')
		),
		'default'       => 'CHANGED',
	),
	'intropage_ntp_server' => array(
		'friendly_name' => __('NTP Time Check - IP or DNS name of NTP server', 'intropage'),
		'description'   => __('Insert IP or DNS name of NTP server', 'intropage'),
		'method'        => 'textbox',
		'max_length' => 50,
		'default'       => 'pool.ntp.org',
	),
	'intropage_ntp_interval' => array(
		'friendly_name' => __('How often check NTP', 'intropage'),
		'description'   => __('Poller runs this task. It could cause long poller run.', 'intropage'),
		'method'        => 'drop_array',
		'array'         => array(
			'900'   => __('Every %d Minutes', 15, 'intropage'),
			'3600'  => __('Every Hour', 'intropage'),
			'86400' => __('Every Day', 'intropage')
		),
		'default'       => '3600',
	),
	'intropage_dns_host' => array(
		'friendly_name' => __('DNS Check - Any DNS name', 'intropage'),
		'description'   => __('Insert DNS name for test', 'intropage'),
		'method'        => 'textbox',
		'max_length' => 50,
		'default'       => 'cacti.net',
	),
	'intropage_dns_interval' => array(
		'friendly_name' => __('How often check DNS', 'intropage'),
		'description'   => __('Poller runs this task. It could cause long poller run.', 'intropage'),
		'method'        => 'drop_array',
		'array'         => array(
			'900'   => __('Every %d Minutes', 15, 'intropage'),
			'3600'  => __('Every Hour', 'intropage'),
			'86400' => __('Every Day', 'intropage')
		),
		'default'       => '3600',
	),
	'intropage_admin_alert' => array(
		'friendly_name' => __('Admin Information Panel about Maintenance Tasks, Down Devices, ..', 'intropage'),
		'description'   => __('If isn\'t empty, Panel will be displayed on the top. You can use html tags (b, i, ...).', 'intropage'),
		'method'        => 'textarea',
		'max_length' => 1000,
		'textarea_rows' => '4',
		'textarea_cols' => '60',
		'default'       => '',
	),
	'intropage_maint_plugin_days_before' => array(
		'friendly_name' => __('Upcoming Maint Schedule warning days', 'intropage'),
		'description'   => __('How many days before a scheduled maintenance schedule should a warning be displayed?', 'intropage'),
		'method'        => 'drop_array',
		'array'         => array(
			'-1'     => __('Never warn', 'intropage'),
			'0'      => __('When maintenance starts', 'intropage'),
			'86400'  => __('%d Day Before', 1, 'intropage'),
			'259200' => __('%d Days Before', 3, 'intropage'),
			'604800' => __('%d Days Before', 7, 'intropage')
		),
		'default'       => '86400',
	),
	'intropage_mb' => array(
		'friendly_name' => __('Network stats in', 'intropage'),
		'description'   => __('bytes or bits', 'intropage'),
		'method'        => 'drop_array',
		'array'         => array(
			'B'   => __('Bytes', 'intropage'),
			'b'    => __('Bits', 'intropage'),
		),
		'default'       => 'b',
	),
	'intropage_display_header2' => array(
		'friendly_name' => __('Alarm Settings', 'intropage'),
		'method'        => 'spacer',
	),
	'intropage_alert_db_abort' => array(
		'friendly_name' => __('Alarm DB check Aborted Clients', 'intropage'),
		'description'   => __('If this event has occurred, trigger the selected Normal, Warning, or Alert status color.', 'intropage'),
		'method'        => 'drop_array',
		'array'         => $status_colors,
		'default'       => 'yellow',
	),
	'intropage_alert_same_description' => array(
		'friendly_name' => __('Alarm Host with the same Description', 'intropage'),
		'description'   => __('If this event has occurred, trigger the selected Normal, Warning, or Alert status color.', 'intropage'),
		'method'        => 'drop_array',
		'array'         => $status_colors,
		'default'       => 'yelow',
	),
	'intropage_alert_orphaned_ds' => array(
		'friendly_name' => __('Alarm Orphaned Data Source', 'intropage'),
		'description'   => __('If this event has occurred, trigger the selected Normal, Warning, or Alert status color.', 'intropage'),
		'method'        => 'drop_array',
		'array'         => $status_colors,
		'default'       => 'yellow',
	),
	'intropage_alert_poller_output' => array(
		'friendly_name' => __('Alarm non-empty Poller Output', 'intropage'),
		'description'   => __('If this event has occurred, trigger the selected Normal, Warning, or Alert status color.', 'intropage'),
		'method'        => 'drop_array',
		'array'         => $status_colors,
		'default'       => 'red',
	),
	'intropage_alert_bad_indexes' => array(
		'friendly_name' => __('Alarm Bad indexes Data Source', 'intropage'),
		'description'   => __('If this event has occurred, trigger the selected Normal, Warning, or Alert status color.', 'intropage'),
		'method'        => 'drop_array',
		'array'         => $status_colors,
		'default'       => 'red',
	),
	'intropage_alert_thold_logonly' => array(
		'friendly_name' => __('Alarm Thershold logonly action', 'intropage'),
		'description'   => __('If this event has occurred, trigger the selected Normal, Warning, or Alert status color.', 'intropage'),
		'method'        => 'drop_array',
		'array'         => $status_colors,
		'default'       => 'red',
	),
	'intropage_alert_same_ip' => array(
		'friendly_name' => __('Alarm Devices with the same IP/port', 'intropage'),
		'description'   => __('If this event has occurred, trigger the selected Normal, Warning, or Alert status color.', 'intropage'),
		'method'        => 'drop_array',
		'array'         => $status_colors,
		'default'       => 'yellow',
	),
	'intropage_alert_more_trees' => array(
		'friendly_name' => __('Alarm Device in more than one Tree', 'intropage'),
		'description'   => __('If this event has occurred, trigger the selected Normal, Warning, or Alert status color.', 'intropage'),
		'method'        => 'drop_array',
		'array'         => $status_colors,
		'default'       => 'yellow',
	),
	'intropage_alert_without_tree' => array(
		'friendly_name' => __('Alarm Device without a Tree', 'intropage'),
		'description'   => __('If this event has occurred, trigger the selected Normal, Warning, or Alert status color.', 'intropage'),
		'method'        => 'drop_array',
		'array'         => $status_colors,
		'default'       => 'yellow',
	),
	'intropage_alert_default_community' => array(
		'friendly_name' => __('Alarm Device with Default public/private SNMP Community', 'intropage'),
		'description'   => __('If this event has occurred, trigger the selected Normal, Warning, or Alert status color.', 'intropage'),
		'method'        => 'drop_array',
		'array'         => $status_colors,
		'default'       => 'yellow',
	),
	'intropage_alert_without_monitoring' => array(
		'friendly_name' => __('Alarm Device without Monitoring', 'intropage'),
		'description'   => __('If this event has occurred, trigger the selected Normal, Warning, or Alert status color.', 'intropage'),
		'method'        => 'drop_array',
		'array'         => $status_colors,
		'default'       => 'yellow',
	),
	'intropage_alert_without_graph' => array(
		'friendly_name' => __('Alarm Device without Graph', 'intropage'),
		'description'   => __('If this event has occurred, trigger the selected Normal, Warning, or Alert status color.', 'intropage'),
		'method'        => 'drop_array',
		'array'         => $status_colors,
		'default'       => 'yellow',
	),
	'intropage_alert_worst_polling_time' => array(
		'friendly_name' => __('Alarm Red/Yellow Polling Time', 'intropage'),
		'description'   => __('Polling times above these thresholds will trigger a Warning or Alert status color.', 'intropage'),
		'method'        => 'drop_array',
		'default'       => '20/10',
		'array'         => array(
			'10/5'  => __('5s  / 10s Yellow / Red', 'intropage'),
			'20/10' => __('10s / 20s Yellow / Red', 'intropage'),
			'40/20' => __('20s / 40s Yellow / Red', 'intropage')
		),
	),
	'intropage_alert_worst_polling_ratio' => array(
		'friendly_name' => __('Alarm Red/Yellow Failed/All Ratio', 'intropage'),
		'description'   => __('The ratio of failed availability checks to successful checks to trigger as Warning or Alert status color.', 'intropage'),
		'method'        => 'drop_array',
		'default'       => '0.4/0.2',
		'array'         => array(
			'0.2/0.1'  => __('0.1 / 0.2 Yellow / Red', 'intropage'),
			'0.4/0.2'  => __('0.2 / 0.4 Yellow / Red', 'intropage'),
			'0.5/more' => __('0.5 / 0.5++ Yellow / Red', 'intropage')
		),
	),
	'intropage_alert_worst_ping' => array(
		'friendly_name' => __('Alarm Red/Yellow Ping', 'intropage'),
		'description'   => __('Ping latency above these levels will trigger a Warning or Alert status color.', 'intropage'),
		'method'        => 'drop_array',
		'default'       => '20/10',
		'array'         => array(
			'20/10'   => __('10ms  / 20ms Yellow / Red', 'intropage'),
			'50/20'   => __('20ms  / 50ms Yellow / Red', 'intropage'),
			'200/100' => __('100ms / 200+ms Yellow / Red', 'intropage')
		),
	),
	'intropage_alert_worst_availability' => array(
		'friendly_name' => __('Alarm Red/Yellow Worst Availability', 'intropage'),
		'description'   => __('Availability below these levels will trigger either a Yellow or a Red status color.', 'intropage'),
		'method'        => 'drop_array',
		'default'       => '99/95',
		'array'         => array(
			'99/95' => '99 / 95% '   . __('Yellow / Red', 'intropage'),
			'95/85' => '95 / 85% '   . __('Yellow / Red', 'intropage'),
			'85/0'  => '85 / 84-0% ' . __('Yellow / Red', 'intropage')
		),
	),
);

