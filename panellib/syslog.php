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

function register_syslog() {
	global $registry;

	$registry['syslog'] = array(
		'name'        => __('Syslog Panels', 'intropage'),
		'description' => __('Panels that provide information about Cacti\'s Syslog message processing.', 'intropage')
	);

	$panels = array(
		'plugin_syslog' => array(
			'name'         => __('Syslog Details', 'intropage'),
			'description'  => __('Various Syslog Plugin statistics.', 'intropage'),
			'class'        => 'syslog',
			'level'        => PANEL_USER,
			'refresh'      => 900,
			'force'        => true,
			'width'        => 'quarter-panel',
			'priority'     => 26,
			'alarm'        => 'grey',
			'requires'     => 'syslog',
			'update_func'  => 'plugin_syslog',
			'details_func' => false,
			'trends_func'  => 'plugin_syslog_trend'
		),
	);

	return $panels;
}

function plugin_syslog_trend() {
	if (api_plugin_is_enabled('syslog')) {
		// Grab row counts from the information schema, it's faster
		$i_rows = syslog_db_fetch_cell("SELECT TABLE_ROWS
			FROM information_schema.TABLES
			WHERE TABLE_NAME = 'syslog_incoming'");

		$total_rows = syslog_db_fetch_cell("SELECT TABLE_ROWS
			FROM information_schema.TABLES
			WHERE TABLE_NAME = 'syslog'");

		$alert_rows = syslog_db_fetch_cell_prepared('SELECT ifnull(sum(count),0)
			FROM syslog_logs WHERE
			logtime > date_sub(now(), INTERVAL ? SECOND)',
			array(read_config_option('poller_interval')));

		db_execute_prepared('INSERT INTO plugin_intropage_trends
			(name, value, user_id)
			VALUES ("syslog_incoming", ?, 0)',
			array($i_rows));

		db_execute_prepared('INSERT INTO plugin_intropage_trends
			(name, value, user_id)
			VALUES ("syslog_total", ?, 0)',
			array ($total_rows));

		db_execute_prepared('INSERT INTO plugin_intropage_trends
			(name, value, user_id)
			VALUES ("syslog_alert", ?, 0)',
			array ($alert_rows));
	}
}

// ----------------syslog----------------------
function plugin_syslog($panel, $user_id) {
	$panel['alarm'] = 'green';

	$graph = array (
		'line' => array(
			'title'  => $panel['name'],
			'title1' => '',
			'label1' => array(),
			'data1'  => array(),
			'title2' => '',
			'label2' => array(),
			'data2'  => array(),
			'title3' => '',
			'label3' => array(),
			'data3'  => array(),
		),
	);


	if (api_plugin_is_enabled('syslog')) {
		$sql = db_fetch_assoc("SELECT date_format(time(cur_timestamp),'%H:%i') AS `date`, name, value
			FROM plugin_intropage_trends
			WHERE name='syslog_total'
			ORDER BY cur_timestamp desc
			LIMIT 20");

		if (cacti_sizeof($sql)) {
			$val = 0;
			$graph['line']['title1'] = __('Total', 'intropage');

			foreach ($sql as $row) {
				array_push($graph['line']['label1'], $row['date']);
				array_push($graph['line']['data1'], $val - $row['value']);
				$val = $row['value'];
			}

			array_shift($graph['line']['label1']);
			array_shift($graph['line']['data1']);
		}

		$sql = db_fetch_assoc("SELECT date_format(time(cur_timestamp),'%H:%i') AS `date`, name, value
			FROM plugin_intropage_trends
			WHERE name='syslog_incoming'
			ORDER BY cur_timestamp desc
			LIMIT 20");

		if (cacti_sizeof($sql)) {
			$val = 0;
			$graph['line']['title2'] = __('Incoming', 'intropage');

			foreach ($sql as $row) {
				array_push($graph['line']['label2'], $row['date']);
				array_push($graph['line']['data2'], $val - $row['value']);
				$val = $row['value'];
			}

			array_shift($graph['line']['label2']);
			array_shift($graph['line']['data2']);
		}

		$sql = db_fetch_assoc("SELECT date_format(time(cur_timestamp),'%H:%i') AS `date`, name, value
			FROM plugin_intropage_trends
			WHERE name='syslog_alert'
			ORDER BY cur_timestamp desc
			LIMIT 20");

		if (cacti_sizeof($sql)) {
			$val = 0;
			$graph['line']['title3'] = __('Alerts', 'intropage');

			foreach ($sql as $row) {
				array_push($graph['line']['label3'], $row['date']);
				array_push($graph['line']['data3'], $val - $row['value']);

				if ($row['value']-$val > 0)     {
					$panel['alert'] = 'yellow';
				}

				$val = $row['value'];
			}

			array_shift($graph['line']['label3']);
			array_shift($graph['line']['data3']);
			$graph['line']['unit1'] = __('Messages', 'intropage');

			if (cacti_sizeof($sql) < 3) {
				unset($panel['line']);
				$panel['data'] = 'Waiting for data';
			} else {
				$graph['line']['data1'] = array_reverse($graph['line']['data1']);
				$graph['line']['data2'] = array_reverse($graph['line']['data2']);
				$graph['line']['data3'] = array_reverse($graph['line']['data3']);
				$graph['line']['label1'] = array_reverse($graph['line']['label1']);
				$graph['line']['label2'] = array_reverse($graph['line']['label2']);
				$graph['line']['label3'] = array_reverse($graph['line']['label3']);
				$panel['data'] = intropage_prepare_graph($graph);
			}

		}
	} else {
		$panel['data']  = __('Syslog plugin not installed/running', 'intropage');
		unset($graph['line']);
	}

	save_panel_result($panel, $user_id);
}

