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
 * Registers the 'poller' panel category and its 'Poller Information',
 * 'Poller Statistics', and 'Poller Output Items' panels with the panel
 * library. Called from initialize_panel_library() while building the
 * full set of available dashboard panels.
 *
 * @return array The panel definitions provided by this file, keyed by
 *              panel id.
 *
 * @global array $registry Populated here with this file's 'poller'
 *                         category metadata.
 */
function register_poller() {
	global $registry;

	$registry['poller'] = [
		'name'        => __('Poller Panels', 'intropage'),
		'description' => __('Panels that provide information about Cacti\'s polling process.', 'intropage')
	];

	$panels = [
		'poller_info' => [
			'name'         => __('Poller Information', 'intropage'),
			'description'  => __('Various information about your Cacti poller.', 'intropage'),
			'class'        => 'poller',
			'level'        => PANEL_SYSTEM,
			'refresh'      => 900,
			'trefresh'     => read_config_option('poller_interval'),
			'force'        => true,
			'width'        => 'quarter-panel',
			'height'       => 'normal',
			'height_fixed' => true,
			'priority'     => 74,
			'alarm'        => 'green',
			'requires'     => false,
			'update_func'  => 'poller_info',
			'details_func' => 'poller_info_detail',
			'trends_func'  => 'poller_info_trend'
		],
		'poller_stat' => [
			'name'         => __('Poller Statistics', 'intropage'),
			'description'  => __('Various Cacti poller statistics.', 'intropage'),
			'class'        => 'poller',
			'level'        => PANEL_SYSTEM,
			'refresh'      => 900,
			'trefresh'     => read_config_option('poller_interval'),
			'force'        => true,
			'width'        => 'quarter-panel',
			'height'       => 'normal',
			'height_fixed' => true,
			'priority'     => 73,
			'alarm'        => 'green',
			'requires'     => false,
			'update_func'  => 'poller_stat',
			'details_func' => false,
			'trends_func'  => 'poller_stat_trend'
		],
		'poller_output_items' => [
			'name'         => __('Poller Output Items', 'intropage'),
			'description'  => __('Various Cacti poller statistics.', 'intropage'),
			'class'        => 'poller',
			'level'        => PANEL_SYSTEM,
			'refresh'      => 900,
			'trefresh'     => read_config_option('poller_interval'),
			'force'        => true,
			'width'        => 'quarter-panel',
			'height'       => 'normal',
			'height_fixed' => true,
			'priority'     => 83,
			'alarm'        => 'green',
			'requires'     => false,
			'update_func'  => 'poller_output_items',
			'details_func' => false,
			'trends_func'  => 'poller_output_items_trend'
		],
	];

	return $panels;
}

/**
 * Trend-collection function for the 'poller_info' panel. Currently a
 * no-op placeholder. Called from intropage_gather_stats() via the
 * panel definition's 'trends_func'.
 *
 * @return void
 */
function poller_info_trend() {
	// Not yet implemented
}

// ------------------------------------ poller info -----------------------------------------------------
/**
 * Data-update function for the 'poller_info' panel: summarizes current
 * Cacti poller configuration/status information (e.g. poller type,
 * interval, last run stats). Called from
 * intropage_gather_stats()/get_panel() via the panel definition's
 * 'update_func'.
 *
 * @param array $panel   The panel's current definition/data row.
 * @param int   $user_id The id of the user the panel is being rendered
 *                       for, used to save the result.
 *
 * @return void
 */
function poller_info($panel, $user_id) {
	global $config;

	$lines = get_panel_lines_count($panel['height'], $user_id);

	$poller_interval = read_config_option('poller_interval');

	$panel['alarm'] = 'green';

	$sql_pollers = db_fetch_assoc('SELECT p.id, name, status, last_update, total_time
		FROM poller p
		LEFT JOIN poller_time pt
		ON pt.poller_id = p.id
		WHERE p.disabled = ""
		GROUP BY p.id
		ORDER BY p.id
		LIMIT ' . $lines);

	$count    = $sql_pollers === false ? __('N/A', 'intropage') : count($sql_pollers);
	$ok       = 0;
	$running  = 0;

	if (cacti_sizeof($sql_pollers)) {
		$details = '<table class="cactiTable">' .
			'<tr class="tableHeader">' .
				'<th class="left">' . __('ID', 'intropage') . '</th>' .
				'<th class="left">' . __('Name', 'intropage') . '</th>' .
				'<th class="left">' . __('State', 'intropage') . '</th>' .
				'<th class="right">' . __('Total Time', 'intropage') . '</th>' .
			'</tr>';

		foreach ($sql_pollers as $poller) {
			$color = 'green';

			if ($poller['status'] == 0 || $poller['status'] == 1 || $poller['status'] == 2 || $poller['status'] == 5) {
				$ok++;
			}

			if ($poller['status'] == 0) {
				$status = __('New/Idle', 'intropage');
			} elseif ($poller['status'] == 1) {
				$status = __('Running', 'intropage');
			} elseif ($poller['status'] == 2) {
				$status = __('Idle', 'intropage');
			} elseif ($poller['status'] == 3) {
				$status = __('Unkn/down', 'intropage');
				$color  = 'red';
			} elseif ($poller['status'] == 4) {
				$status = __('Disabled', 'intropage');
			} elseif ($poller['status'] == 5) {
				$status = __('Recovering', 'intropage');
				$color  = 'yellow';
			}

			$details .= '<tr>' .
				'<td class="left">' . $poller['id'] . '</td>' .
				'<td class="left">' . html_escape($poller['name']) . '</td>' .
				'<td class="left"><span class="inpa_sq color_' . $color . '"></span>' . $status . '</td>';

			$color = 'green';

			if (($poller['total_time'] / $poller_interval) > 0.9) {
				$color = 'red';
			} elseif (($poller['total_time'] / $poller_interval) > 0.7) {
				$color = 'yellow';
			}

			$details .= '<td class="right"><span class="inpa_sq color_' . $color . '"></span>' . __('%s Secs', round($poller['total_time'], 2), 'intropage') . ' </td></tr>';
		}

		$details .= '</table>';
	}

	$panel['data'] =
		'<center><span class="txt_huge">' . $ok . '</span> ' . __('(ok)', 'intropage') . ' / ' .
		'<span class="txt_huge">' . $count . '</span> ' . __('(all)', 'intropage') . '<br/><br/></center>' .
		$details;

	if ($sql_pollers === false || $count > $ok) {
		$panel['alarm'] = 'red';
	} else {
		$panel['alarm'] = 'green';
	}

	save_panel_result($panel, $user_id);
}

/**
 * Trend-collection function for the 'poller_stat' panel: records a
 * snapshot of each poller's total collection time into
 * plugin_intropage_trends. Called from intropage_gather_stats() via
 * the panel definition's 'trends_func'.
 *
 * @return void
 */
function poller_stat_trend() {
	$stats = db_fetch_assoc('SELECT id, total_time, DATE_SUB(last_update, INTERVAL ROUND(total_time) SECOND) AS start
		FROM poller
		ORDER BY avg_time DESC');

	foreach ($stats as $stat) {
		db_execute_prepared("REPLACE INTO plugin_intropage_trends
			(name, cur_timestamp, value, user_id) VALUES
			('poller', ?, ?, ?)",
			[$stat['start'], $stat['id'] . ':' . round($stat['total_time']), 4]);
	}
}

// ------------------------------------ poller stat -----------------------------------------------------
/**
 * Data-update function for the 'poller_stat' panel: renders a
 * time-series line graph of each active poller's collection time over
 * the panel's configured timespan (plus a 24-hour average line when
 * few pollers exist), flagging the panel red if any poller's time
 * approaches the poller interval. Called from
 * intropage_gather_stats()/get_panel() via the panel definition's
 * 'update_func'.
 *
 * @param array $panel    The panel's current definition/data row.
 * @param int   $user_id  The id of the user the panel is being
 *                        rendered for, used to determine the row
 *                        limit, resolve the timespan setting, and save
 *                        the result.
 * @param int   $timespan Optional override for the graph's time
 *                        window in seconds; 0 uses the user's/panel's
 *                        configured timespan.
 *
 * @return void
 *
 * @global array $config          Reserved/declared for parity with
 *                                other panel functions in this file;
 *                                not used directly here.
 * @global bool  $run_from_poller Reserved/declared for parity with
 *                                other panel functions in this file;
 *                                not used directly here.
 */
function poller_stat($panel, $user_id, $timespan = 0) {
	global $config, $run_from_poller;

	$lines = get_panel_lines_count($panel['height'], $user_id);

	$poller_interval = read_config_option('poller_interval');

	$panel['alarm'] = 'green';

	$graph =  [
		'line' => [
			'title1' => '',
			'label1' => [],
			'data1'  => [],
			'title2' => '',
			'label2' => [],
			'data2'  => [],
			'title3' => '',
			'label3' => [],
			'data3'  => [],
			'title4' => '',
			'label4' => [],
			'data4'  => [],
			'title5' => '',
			'label5' => [],
			'data5'  => [],
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
		$refresh = $panel['refresh_interval'];
	}

	$pollers = db_fetch_assoc('SELECT p.id
		FROM poller AS p
		LEFT JOIN poller_time pt
		ON pt.poller_id = p.id
		WHERE p.disabled = ""
		GROUP BY p.id
		ORDER BY avg_time DESC
		LIMIT ' . $lines);

	$pcount = cacti_sizeof($pollers);

	if ($pcount > 0) {
		$new_index = 1;

		foreach ($pollers as $xpoller) {
			$seconds = floor($timespan / 60);

			$rows = db_fetch_assoc_prepared("SELECT cur_timestamp AS `date`, AVG(SUBSTRING_INDEX(value, ':', -1)) AS value
				FROM plugin_intropage_trends
				WHERE cur_timestamp > date_sub(NOW(), INTERVAL ? SECOND)
				AND name = 'poller'
				AND value LIKE ?
				GROUP BY UNIX_TIMESTAMP(cur_timestamp) DIV $seconds
				ORDER BY cur_timestamp ASC",
				[$timespan, $xpoller['id'] . ':%']);

			if ($pcount < 3) {
				$avg = db_fetch_cell_prepared("SELECT (SUBSTRING_INDEX(value, ':', -1)) AS value
					FROM plugin_intropage_trends
					WHERE cur_timestamp > date_sub(NOW(), INTERVAL 24 HOUR) AND
					name = 'poller' AND
					value LIKE ?",
					[$xpoller['id'] . ':%']);
				$avg_label = [];
				$avg_data  = [];
			}

			foreach ($rows as $row) {
				if ($row['value'] > ($poller_interval - 10)) {
					$panel['alarm'] = 'red';
				}

				// graph data
				$graph['line']['label' . $new_index][] = $row['date'];
				$graph['line']['data' . $new_index][]  = round($row['value'], 2);
				$graph['line']['title' . $new_index]   = __('ID: ', 'intropage') . $xpoller['id'];
				$graph['line']['unit1']['title']       = __('Seconds', 'intropage');

				if ($pcount < 3) {
					$avg_label[] = $row['date'];
					$avg_data[]  = $avg;
				}
			}

			$new_index++;

			// add 24 hours avg if we have enough lines
			if ($pcount < 3) {
				$graph['line']['label' . $new_index] = $avg_label;
				$graph['line']['data' . $new_index]  = $avg_data;
				$graph['line']['title' . $new_index] = __('24h avg ID: ', 'intropage') . $xpoller['id'];
				$graph['line']['unit1']['title']     = __('Seconds', 'intropage');

				$new_index++;
			}
		}

		$panel['data'] = intropage_prepare_graph($graph, $user_id);
	} else {
		$panel['data'] = __('Waiting for data', 'intropage');
	}

	save_panel_result($panel, $user_id);
}

// ------------------------------------ poller_info -----------------------------------------------------
/**
 * Detail-view renderer for the 'poller_info' panel, showing an
 * expanded table of each active poller's id, name, status, and timing
 * statistics, flagging pollers whose total time is close to the poller
 * interval. Called via the panel definition's 'details_func' when the
 * user opens the panel's detail view.
 *
 * @return array The panel's detail data (name/alarm/detail html), for
 *              display in the panel's detail view.
 *
 * @global array $config Reserved/declared for parity with other panel
 *                       functions in this file; not used directly
 *                       here.
 */
function poller_info_detail() {
	global $config;

	$poller_interval = read_config_option('poller_interval');

	$panel = [
		'name'   => __('Poller Details', 'intropage'),
		'alarm'  => 'green',
		'detail' => '',
	];

	$row = '<table class="cactiTable">' .
		'<tr class="tableHeader">' .
		'<td class="left">' . __('ID', 'intropage') . '</td>' .
		'<td class="left">' . __('Name', 'intropage') . '</td>' .
		'<td class="left">' . __('State', 'intropage') . '</td>' .
		'<td class="right">' . __('Total Time', 'intropage') . '</td>' .
		'<td class="right">' . __('Average Time', 'intropage') . '</td>' .
		'<td class="right">' . __('Max Time', 'intropage') . '</td>' .
	'</tr>';

	$pollers = db_fetch_assoc('SELECT p.*
		FROM poller p
		LEFT JOIN poller_time pt
		ON pt.poller_id = p.id
		WHERE p.disabled = ""
		GROUP BY p.id
		ORDER BY p.id
		LIMIT 20');

	$count    = $pollers === false ? __('N/A', 'intropage') : cacti_count($pollers);
	$ok       = 0;
	$running  = 0;

	if (cacti_sizeof($pollers)) {
		foreach ($pollers as $poller) {
			$color = 'green';

			if ($poller['status'] == 0 || $poller['status'] == 1 || $poller['status'] == 2 || $poller['status'] == 5) {
				$ok++;
			}

			$row .= '</tr>';

			$row .= '<td class="left">' . $poller['id'] . '</td>';
			$row .= '<td class="left">' . html_escape($poller['name']) . '</td>';

			if ($poller['status'] == 0) {
				$row .= '<td class="left">' . __('New/Idle', 'intropage') . '</td>';
			} elseif ($poller['status'] == 1) {
				$row .= '<td class="left">' . __('Running', 'intropage') . '</td>';
			} elseif ($poller['status'] == 2) {
				$row .= '<td class="left">' . __('Idle', 'intropage') . '</td>';
			} elseif ($poller['status'] == 3) {
				$row .= '<td class="left">' . __('Unkn/down', 'intropage') . '<span class="inpa_sq color_red"></span></td>';
			} elseif ($poller['status'] == 4) {
				$row .= '<td class="left">' . __('Disabled', 'intropage') . '</td>';
			} elseif ($poller['status'] == 5) {
				$row .= '<td class="left">' . __('Recovering', 'intropage') . '<span class="inpa_sq color_yellow"></span></td>';
			}

			if (($poller['total_time'] / $poller_interval) > 0.9) {
				$color = 'red';
			} elseif (($poller['total_time'] / $poller_interval) > 0.7) {
				$color = 'yellow';
			}

			$row .= '<td class="right">' . round($poller['total_time'], 2) . 's <span class="inpa_sq color_' . $color . '"></span></td>';
			$row .= '<td class="right">' . round($poller['avg_time'], 2) . 's</td>';
			$row .= '<td class="right">' . round($poller['max_time'], 2) . 's</td>';

			$row .= '</tr>';
		}
	}

	$panel['detail'] = '<span class="txt_huge">' . $ok . '</span> ' . __('(ok)', 'intropage') . ' / ' . '<span class="txt_huge">' . $count . '</span> ' . __('(all)', 'intropage') . '</span><br/><br/>';

	$panel['detail'] = '<br/><br/>' . $row;

	if ($pollers === false || $count > $ok) {
		$panel['alarm'] = 'red';
	} else {
		$panel['alarm'] = 'green';
	}

	return $panel;
}

// ------------------------------------ poller_output_items -----------------------------------------------------

/**
 * Trend-collection function for the 'poller_output_items' panel:
 * records a snapshot of the current poller_output table row count into
 * plugin_intropage_trends. Called from intropage_gather_stats() via
 * the panel definition's 'trends_func'.
 *
 * @return void
 */
function poller_output_items_trend() {
	$count = db_fetch_cell('SELECT COUNT(local_data_id) FROM poller_output');

	db_execute_prepared('REPLACE INTO plugin_intropage_trends
		(name, value, user_id)
		VALUES (?, ?, 0)',
		['poller_output', $count]);
}

/**
 * Data-update function for the 'poller_output_items' panel: renders a
 * time-series/summary view of pending poller_output table row counts
 * over the panel's configured timespan, flagging the panel based on
 * the configured alert threshold. Called from
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
 *
 * @global array $config          Reserved/declared for parity with
 *                                other panel functions in this file;
 *                                not used directly here.
 * @global bool  $run_from_poller Reserved/declared for parity with
 *                                other panel functions in this file;
 *                                not used directly here.
 */
function poller_output_items($panel, $user_id, $timespan = 0) {
	global $config, $run_from_poller;

	$poller_interval = read_config_option('poller_interval');
	$color           = read_config_option('intropage_alert_poller_output');

	$panel['alarm'] = 'green';

	$graph =  [
		'line' => [
			'title1' => '',
			'label1' => [],
			'data1'  => [],
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
		$refresh = $panel['refresh_interval'];
	}

	$seconds = floor($timespan / 60);

	$rows = db_fetch_assoc_prepared("SELECT cur_timestamp AS `date`, value
		FROM plugin_intropage_trends
		WHERE cur_timestamp > date_sub(NOW(), INTERVAL ? SECOND)
		AND name = 'poller_output'
		GROUP BY UNIX_TIMESTAMP(cur_timestamp) DIV $seconds
		ORDER BY cur_timestamp ASC",
		[$timespan]);

	if (cacti_sizeof($rows)) {
		foreach ($rows as $row) {
			if ($row['value'] > 0) {
				if ($color == 'red') {
					$panel['alarm'] = 'red';
				} elseif ($panel['alarm'] == 'green' && $color == 'yellow') {
					$panel['alarm'] = 'yellow';
				}
			}

			// graph data
			$graph['line']['label1'][]             = $row['date'];
			$graph['line']['data1'][]              = $row['value'];
			$graph['line']['title1']               = __('Poller output items ', 'intropage');
			$graph['line']['unit1']['title']       = __('Items', 'intropage');
		}

		$panel['data'] = intropage_prepare_graph($graph, $user_id);
	} else {
		$panel['data'] = __('Waiting for data', 'intropage');
	}

	save_panel_result($panel, $user_id);
}
