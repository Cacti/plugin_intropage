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
			'level'        => PANEL_SYSTEM,
			'refresh'      => 900,
			'trefresh'     => read_config_option('poller_interval'),
			'force'        => true,
			'width'        => 'half-panel',
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

		$alert_rows = syslog_db_fetch_cell_prepared('SELECT IFNULL(SUM(count),0)
			FROM syslog_logs WHERE
			logtime > DATE_SUB(NOW(), INTERVAL ? SECOND)',
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
function plugin_syslog($panel, $user_id, $timespan = 0) {
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

	if ($timespan == 0) {
		if (isset($_SESSION['sess_user_id'])) {
			$timespan = read_user_setting('intropage_timespan', read_config_option('intropage_timespan'), $_SESSION['sess_user_id']);
		} else {
			$timespan = $panel['refresh'];
		}
	}

	if (!isset($panel['refresh_interval'])) {
		$refresh = db_fetch_cell_prepared('SELECT refresh_interval
			FROM plugin_intropage_panel_data
			WHERE id = ?',
			array($panel['id']));
	} else {
		$refresh = $panel['refresh'];
	}

	if (api_plugin_is_enabled('syslog')) {
		// Get the syslog records
		$rows = db_fetch_assoc_prepared("SELECT cur_timestamp AS `date`,
			MAX(CASE WHEN name='syslog_total' THEN value ELSE NULL END) AS syslog_total,
			SUM(CASE WHEN name='syslog_incoming' THEN value ELSE NULL END) AS syslog_incoming,
			SUM(CASE WHEN name='syslog_alert' THEN value ELSE NULL END) AS syslog_alert
			FROM plugin_intropage_trends
			WHERE cur_timestamp > date_sub(NOW(), INTERVAL ? SECOND)
			AND name IN ('syslog_total', 'syslog_incoming', 'syslog_alert')
			GROUP BY UNIX_TIMESTAMP(cur_timestamp) DIV $refresh
			ORDER BY cur_timestamp ASC",
			array($timespan));

		if (cacti_sizeof($rows)) {
			// Converted syslog_total to total new rows;
			$nrows      = array();
			$last_total = 0;

			foreach($rows as $index => $row) {
				$total  = $row['syslog_total'];
				$totali = $row['syslog_incoming'];

				if ($index > 0) {
					$row['syslog_total'] = $total - $last_total;

					if ($row['syslog_total'] < 0) {
						$row['syslog_total'] = 0;
					}

					$row['syslog_incoming'] = $totali + $last_totali;

					$nrows[] = $row;

					$last_totali = 0;
				} else {
					$last_totali = $totali;
				}

				$last_total  = $total;
			}

			$graph['line']['title1'] = __('Incoming', 'intropage');
			$graph['line']['title2'] = __('Alerts', 'intropage');
			$graph['line']['title3'] = __('Stored', 'intropage');

			$graph['line']['unit1']['title']  = __('Messages', 'intropage');
			$graph['line']['unit1']['series'] = array('data1', 'data2', 'data3');

//			$graph['line']['unit2']['title']  = __('Messages', 'intropage');
//			$graph['line']['unit2']['series'] = array('data3');

			foreach($nrows as $row) {
				$graph['line']['label1'][] = $row['date'];
				$graph['line']['data1'][]  = $row['syslog_incoming'];
				$graph['line']['data2'][]  = $row['syslog_alert'];
				$graph['line']['data3'][]  = $row['syslog_total'];

				if ($row['syslog_alert'] > 0) {
					$panel['alert'] = 'yellow';
				}
			}

			$panel['data'] = intropage_prepare_graph($graph, $user_id);
		} else {
			$panel['data'] = 'Waiting for data';
		}
	} else {
		$panel['data']  = __('Syslog plugin not installed/running', 'intropage');
		unset($graph['line']);
	}

	save_panel_result($panel, $user_id);
}

