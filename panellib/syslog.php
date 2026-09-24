<?php
/* vim: ts=4
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group, Inc.                           |
 | Copyright (C) 2004-2025 Petr Macek                                      |
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

/**
 * Registers the 'syslog' panel category and its 'Syslog Details' (rate
 * graph), 'Syslog Top Devices', and 'Syslog Message levels' panels with
 * the panel library. Called from initialize_panel_library() while
 * building the full set of available dashboard panels.
 *
 * @return array The panel definitions provided by this file, keyed by
 *              panel id.
 *
 * @global array $registry Populated here with this file's 'syslog'
 *                         category metadata.
 */
function register_syslog() {
	global $registry;

	$registry['syslog'] = [
		'name'        => __('Syslog Panels', 'intropage'),
		'description' => __('Panels that provide information about Cacti\'s Syslog message processing.', 'intropage')
	];

	$panels = [
		'plugin_syslog' => [
			'name'         => __('Syslog Details', 'intropage'),
			'description'  => __('Various Syslog Plugin statistics.', 'intropage'),
			'class'        => 'syslog',
			'level'        => PANEL_SYSTEM,
			'refresh'      => 900,
			'trefresh'     => read_config_option('poller_interval'),
			'force'        => true,
			'width'        => 'half-panel',
			'height'       => 'normal',
			'height_fixed' => true,
			'priority'     => 26,
			'alarm'        => 'grey',
			'requires'     => 'syslog',
			'update_func'  => 'plugin_syslog',
			'details_func' => false,
			'trends_func'  => 'plugin_syslog_trend'
		],
		'plugin_syslog_devices' => [
			'name'         => __('Syslog Top Devices', 'intropage'),
			'description'  => __('Devices with the most messages', 'intropage'),
			'class'        => 'syslog',
			'level'        => PANEL_SYSTEM,
			'refresh'      => 300,
			'trefresh'     => read_config_option('poller_interval'),
			'force'        => true,
			'width'        => 'quarter-panel',
			'height'       => 'normal',
			'height_fixed' => false,
			'priority'     => 27,
			'alarm'        => 'grey',
			'requires'     => 'syslog',
			'update_func'  => 'plugin_syslog_devices',
			'details_func' => 'plugin_syslog_devices_detail',
			'trends_func'  => false
		],
		'plugin_syslog_levels' => [
			'name'         => __('Syslog Message levels', 'intropage'),
			'description'  => __('Messages by level.', 'intropage'),
			'class'        => 'syslog',
			'level'        => PANEL_SYSTEM,
			'refresh'      => 300,
			'trefresh'     => read_config_option('poller_interval'),
			'force'        => true,
			'width'        => 'quarter-panel',
			'height'       => 'normal',
			'height_fixed' => true,
			'priority'     => 28,
			'alarm'        => 'grey',
			'requires'     => 'syslog',
			'update_func'  => 'plugin_syslog_levels',
			'details_func' => false,
			'trends_func'  => 'plugin_syslog_levels_trend'
		],
	];

	return $panels;
}

/**
 * Trend-collection function for the 'plugin_syslog' panel: records
 * point-in-time snapshots of the syslog_incoming/syslog table row
 * counts and recent alert message count into plugin_intropage_trends,
 * when the Syslog plugin is enabled. Called from
 * intropage_gather_stats() via the panel definition's 'trends_func'.
 *
 * @return void
 *
 * @global array $config Cacti global configuration array; used to
 *                       locate the Syslog plugin's database library.
 */
function plugin_syslog_trend() {
	global $config;

	if (api_plugin_is_enabled('syslog')) {
		include_once($config['base_path'] . '/plugins/syslog/database.php');

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
			[read_config_option('poller_interval')]);

		db_execute_prepared('INSERT INTO plugin_intropage_trends
			(name, value, user_id)
			VALUES ("syslog_incoming", ?, 0)',
			[$i_rows]);

		db_execute_prepared('INSERT INTO plugin_intropage_trends
			(name, value, user_id)
			VALUES ("syslog_total", ?, 0)',
			[$total_rows]);

		db_execute_prepared('INSERT INTO plugin_intropage_trends
			(name, value, user_id)
			VALUES ("syslog_alert", ?, 0)',
			[$alert_rows]);
	}
}

/**
 * Trend-collection function for the 'plugin_syslog_levels' panel:
 * records a snapshot of syslog message counts per priority level
 * (0-7) over the last poller interval into plugin_intropage_trends,
 * when the Syslog plugin is enabled. Called from
 * intropage_gather_stats() via the panel definition's 'trends_func'.
 *
 * @return void
 *
 * @global array $config Cacti global configuration array; used to
 *                       locate the Syslog plugin's database library.
 */
function plugin_syslog_levels_trend() {
	global $config;

	if (api_plugin_is_enabled('syslog')) {
		include_once($config['base_path'] . '/plugins/syslog/database.php');

		$data = [
			0 => 0,
			1 => 0,
			2 => 0,
			3 => 0,
			4 => 0,
			5 => 0,
			6 => 0,
			7 => 0,
		];

		$pi = read_config_option('poller_interval');

		$levels = syslog_db_fetch_assoc_prepared('SELECT
			priority_id, COUNT(*) AS mcount
			FROM syslog
			WHERE logtime BETWEEN (DATE_SUB(NOW(),INTERVAL ? SECOND)) AND (DATE_SUB(NOW(),INTERVAL ? SECOND))
			GROUP BY priority_id',
			[2 * $pi, $pi]);

		foreach ($levels as $level) {
			$l        = (int) $level['priority_id'];
			$data[$l] = $level['mcount'];
		}

		$insert = http_build_query($data);

		db_execute_prepared('INSERT INTO plugin_intropage_trends
			(name, value, user_id)
			VALUES ("syslog_levels", ?, 0)',
			[$insert]);
	}
}

/**
 * Data-update function for the 'plugin_syslog' panel: renders a
 * time-series line graph of incoming/alert/stored syslog message
 * counts over the panel's configured timespan from the recorded
 * trend snapshots, or reports that the Syslog plugin isn't installed.
 * Called from intropage_gather_stats()/get_panel() via the panel
 * definition's 'update_func'.
 *
 * @param array $panel    The panel's current definition/data row.
 * @param int   $user_id  The id of the user the panel is being
 *                        rendered for, used to resolve the timespan
 *                        setting and save the result.
 * @param int   $timespan Optional override for the graph's time
 *                        window in seconds; 0 uses the user's/panel's
 *                        configured timespan.
 *
 * @return void
 */
function plugin_syslog($panel, $user_id, $timespan = 0) {
	$panel['alarm'] = 'green';

	$graph =  [
		'line' => [
			'title'  => $panel['name'],
			'title1' => '',
			'label1' => [],
			'data1'  => [],
			'title2' => '',
			'label2' => [],
			'data2'  => [],
			'title3' => '',
			'label3' => [],
			'data3'  => [],
		],
	];

	if ($timespan == 0) {
		if (isset($_SESSION['sess_user_id'])) {
			$timespan = read_user_setting('intropage_timespan', read_config_option('intropage_timespan'), false, $_SESSION['sess_user_id']);
		} else {
			$timespan = $panel['refresh'];
		}
	}

	if (!isset($panel['refresh_interval'])) {
		$refresh = db_fetch_cell_prepared('SELECT refresh_interval
			FROM plugin_intropage_panel_data
			WHERE id = ?',
			[$panel['id']]);
	} else {
		$refresh = $panel['refresh'];
	}

	if (api_plugin_is_enabled('syslog')) {
		$seconds = floor($timespan / 60);

		// Get the syslog records
		$rows = db_fetch_assoc_prepared("SELECT cur_timestamp AS `date`,
			MAX(CASE WHEN name='syslog_total' THEN value ELSE NULL END) AS syslog_total,
			SUM(CASE WHEN name='syslog_incoming' THEN value ELSE NULL END) AS syslog_incoming,
			SUM(CASE WHEN name='syslog_alert' THEN value ELSE NULL END) AS syslog_alert
			FROM plugin_intropage_trends
			WHERE cur_timestamp > date_sub(NOW(), INTERVAL ? SECOND)
			AND name IN ('syslog_total', 'syslog_incoming', 'syslog_alert')
			GROUP BY UNIX_TIMESTAMP(cur_timestamp) DIV $seconds
			ORDER BY cur_timestamp ASC",
			[$timespan]);

		if (cacti_sizeof($rows)) {
			// Converted syslog_total to total new rows;
			$nrows      = [];
			$last_total = 0;

			foreach ($rows as $index => $row) {
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
			$graph['line']['unit1']['series'] = ['data1', 'data2', 'data3'];

			foreach ($nrows as $row) {
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

/**
 * Data-update function for the 'plugin_syslog_levels' panel: renders a
 * graph/summary of syslog message counts broken down by priority level
 * over the panel's configured timespan, or reports that the Syslog
 * plugin isn't installed. Called from
 * intropage_gather_stats()/get_panel() via the panel definition's
 * 'update_func'.
 *
 * @param array $panel    The panel's current definition/data row.
 * @param int   $user_id  The id of the user the panel is being
 *                        rendered for, used to resolve the timespan
 *                        setting and save the result.
 * @param int   $timespan Optional override for the graph's time
 *                        window in seconds; 0 uses the user's/panel's
 *                        configured timespan.
 *
 * @return void
 */
function plugin_syslog_levels($panel, $user_id, $timespan = 0) {
	$panel['alarm'] = 'green';

	$graph =  [
		'bar' => [
			'title'  => $panel['name'],
			'title1' => 'Emergency',
			'label1' => [],
			'data1'  => [],
			'title2' => 'Alert',
			'label2' => [],
			'data2'  => [],
			'title3' => 'Critical',
			'label3' => [],
			'data3'  => [],
			'title4' => 'Error',
			'label4' => [],
			'data4'  => [],
			'title5' => 'Warning',
			'label5' => [],
			'data5'  => [],
			'title6' => 'Notice',
			'label6' => [],
			'data6'  => [],
			'title7' => 'Info',
			'label7' => [],
			'data7'  => [],
			'title8' => 'Debug',
			'label8' => [],
			'data8'  => [],
		],
	];

	if ($timespan == 0) {
		if (isset($_SESSION['sess_user_id'])) {
			$timespan = read_user_setting('intropage_timespan', read_config_option('intropage_timespan'), false, $_SESSION['sess_user_id']);
		} else {
			$timespan = $panel['refresh'];
		}
	}

	if (!isset($panel['refresh_interval'])) {
		$refresh = db_fetch_cell_prepared('SELECT refresh_interval
			FROM plugin_intropage_panel_data
			WHERE id = ?',
			[$panel['id']]);
	} else {
		$refresh = $panel['refresh'];
	}

	if (api_plugin_is_enabled('syslog')) {
		$seconds = floor($timespan / 60);

		$rows = db_fetch_assoc_prepared("SELECT *
			FROM plugin_intropage_trends
			WHERE cur_timestamp > date_sub(NOW(), INTERVAL ? SECOND)
			AND name = 'syslog_levels'
			GROUP BY UNIX_TIMESTAMP(cur_timestamp) DIV ?
			ORDER BY cur_timestamp ASC",
			[$timespan, $seconds]);

		if (cacti_sizeof($rows)) {
			foreach ($rows as $row) {
				$all = explode('&', $row['value']);

				$graph['bar']['label1'][] = $row['cur_timestamp'];

				foreach ($all as $item) {
					[$lev, $count] = explode('=', $item);
					$lev++;
					$graph['bar']["data$lev"][]  = $count;
				}
			}

			$graph['bar']['unit1']['title']  = __('Messages', 'intropage');
			$graph['bar']['unit1']['series'] = ['data1', 'data2', 'data3', 'data4', 'data5', 'data6', 'data7', 'data8'];

			$panel['data'] = intropage_prepare_graph($graph, $user_id);
		} else {
			$panel['data'] = 'Waiting for data';
		}
	} else {
		$panel['data']  = __('Syslog plugin not installed/running', 'intropage');
		unset($graph['bar']);
	}

	save_panel_result($panel, $user_id);
}

/**
 * Data-update function for the 'plugin_syslog_devices' panel: renders a
 * table of the top devices by syslog message count over the panel's
 * configured timespan, or reports that the Syslog plugin isn't
 * enabled. Called from intropage_gather_stats()/get_panel() via the
 * panel definition's 'update_func'.
 *
 * @param array $panel    The panel's current definition/data row.
 * @param int   $user_id  The id of the user the panel is being
 *                        rendered for, used to determine the row
 *                        limit, resolve the timespan setting, and save
 *                        the result.
 * @param int   $timespan Optional override for the query's time window
 *                        in seconds; 0 uses the user's/panel's
 *                        configured timespan.
 *
 * @return void
 *
 * @global array $config Cacti global configuration array; used to
 *                       locate the Syslog plugin's database library.
 */
function plugin_syslog_devices($panel, $user_id, $timespan = 0) {
	global $config;

	$panel['alarm'] = 'grey';

	if ($timespan == 0) {
		if (isset($_SESSION['sess_user_id'])) {
			$timespan = read_user_setting('intropage_timespan', read_config_option('intropage_timespan'), false, $_SESSION['sess_user_id']);
		} else {
			$timespan = $panel['refresh'];
		}
	}

	if (api_plugin_is_enabled('syslog')) {
		include_once($config['base_path'] . '/plugins/syslog/database.php');

		$lines = get_panel_lines_count($panel['height'], $user_id);

		$devices = syslog_db_fetch_assoc_prepared('SELECT sh.host AS ip ,count(*) AS hcount
			FROM syslog AS s
			LEFT JOIN syslog_hosts AS sh
			ON s.host_id = sh.host_id
			WHERE logtime > (DATE_SUB(NOW(),INTERVAL ? SECOND))
			GROUP BY s.host_id
			ORDER BY hcount DESC
			LIMIT ' . $lines,
			[$timespan]);

		if (cacti_sizeof($devices)) {
			$panel['data'] = '<table class="cactiTable">' .
				'<tr class="tableHeader">' .
					'<th class="left">' . __('Device', 'intropage') . '</th>' .
					'<th class="right">' . __('Messages', 'intropage') . '</th>' .
				'</tr>';

			$i = 0;

			foreach ($devices as $device) {
				$row = '<tr class="' . ($i % 2 == 0 ? 'even' : 'odd') . '"><td class="left">' . html_escape(substr($device['ip'],0,37)) . '</td>';
				$row .= "<td class='right'>" . $device['hcount'] . '</td>';

				$panel['data'] .= $row;
				$i++;
			}

			$panel['data'] .= '</table>';
		} else {
			$panel['data'] = __('Waiting for data', 'intropage');
		}
	} else {
		$panel['data'] = __('Syslog plugin is not enabled', 'intropage');
	}
	save_panel_result($panel, $user_id);
}

/**
 * Detail-view renderer for the 'plugin_syslog_devices' panel, showing
 * an expanded top-20 table of devices by syslog message count. Called
 * via the panel definition's 'details_func' when the user opens the
 * panel's detail view.
 *
 * @return void
 *
 * @global array $config          Cacti global configuration array;
 *                                used to locate the Syslog plugin's
 *                                database library.
 * @global bool  $console_access  Reserved/declared for parity with
 *                                other panel functions in this file;
 *                                not used directly here.
 */
function plugin_syslog_devices_detail() {
	global $config, $console_access;

	$panel = [
		'name'   => __('Top 20 Hosts with the most messages', 'intropage'),
		'alarm'  => 'grey',
		'detail' => '',
	];

	if (isset($_SESSION['sess_user_id'])) {
		$timespan = read_user_setting('intropage_timespan', read_config_option('intropage_timespan'), false, $_SESSION['sess_user_id']);
	} else {
		$timespan = $panel['refresh'];
	}

	if (api_plugin_is_enabled('syslog')) {
		include_once($config['base_path'] . '/plugins/syslog/database.php');

		$devices = syslog_db_fetch_assoc_prepared('SELECT sh.host AS ip ,count(*) AS hcount
			FROM syslog AS s
			LEFT JOIN syslog_hosts AS sh
			ON s.host_id = sh.host_id
			WHERE logtime > (DATE_SUB(NOW(),INTERVAL ? SECOND))
			GROUP BY s.host_id
			ORDER BY hcount desc
			LIMIT 20',
			[$timespan]);

		if (cacti_sizeof($devices)) {
			$panel['detail'] = '<table class="cactiTable">' .
				'<tr class="tableHeader">' .
					'<td class="left">' . __('Device', 'intropage') . '</td>' .
					'<td class="right">' . __('Messages', 'intropage') . '</td>' .
				'</tr>';

			$i = 0;

			foreach ($devices as $device) {
				$row = '<tr class="' . ($i % 2 == 0 ? 'odd' : 'even') . '"><td class="rleft">' . html_escape($device['ip']) . '</td>';
				$row .= '<td class="right">' . $device['hcount'] . '</td></tr>';

				$panel['detail'] .= $row;
				$i++;
			}

			$panel['detail'] .= '</table>';
		} else {
			$panel['detail'] = __('No messages', 'intropage');
		}
	} else {
		$panel['detail'] = __('Syslog plugin is not enabled', 'intropage');
	}

	return $panel;
}
