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

function register_poller() {
	global $registry;

	$registry['poller'] = array(
		'name'        => __('Poller Panels', 'intropage'),
		'description' => __('Panels that provide information about Cacti\'s polling process.', 'intropage')
	);

	$panels = array(
		'poller_info' => array(
			'name'         => __('Poller Information', 'intropage'),
			'description'  => __('Various information about your Cacti poller.', 'intropage'),
			'class'        => 'poller',
			'level'        => PANEL_SYSTEM,
			'refresh'      => 900,
			'trefresh'     => read_config_option('poller_interval'),
			'force'        => true,
			'width'        => 'quarter-panel',
			'priority'     => 74,
			'alarm'        => 'green',
			'requires'     => false,
			'update_func'  => 'poller_info',
			'details_func' => 'poller_info_detail',
			'trends_func'  => 'poller_info_trend'
		),
		'poller_stat' => array(
			'name'         => __('Poller Statistics', 'intropage'),
			'description'  => __('Various Cacti poller statistics.', 'intropage'),
			'class'        => 'poller',
			'level'        => PANEL_SYSTEM,
			'refresh'      => 900,
			'trefresh'     => read_config_option('poller_interval'),
			'force'        => true,
			'width'        => 'quarter-panel',
			'priority'     => 73,
			'alarm'        => 'green',
			'requires'     => false,
			'update_func'  => 'poller_stat',
			'details_func' => false,
			'trends_func'  => 'poller_stat_trend'
		),
		'poller_output_items' => array(
			'name'         => __('Poller Output Items', 'intropage'),
			'description'  => __('Various Cacti poller statistics.', 'intropage'),
			'class'        => 'poller',
			'level'        => PANEL_SYSTEM,
			'refresh'      => 900,
			'trefresh'     => read_config_option('poller_interval'),
			'force'        => true,
			'width'        => 'quarter-panel',
			'priority'     => 83,
			'alarm'        => 'green',
			'requires'     => false,
			'update_func'  => 'poller_output_items',
			'details_func' => false,
			'trends_func'  => 'poller_output_items_trend'
		),

	);

	return $panels;
}

function poller_info_trend() {
	// Not yet implemented
}

//------------------------------------ poller info -----------------------------------------------------
function poller_info($panel, $user_id) {
	global $config;

	$lines = read_user_setting('intropage_number_of_lines', read_config_option('intropage_number_of_lines'), false, $user_id);
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
				'<th class="left">'  . __('ID', 'intropage')         . '</th>' .
				'<th class="left">'  . __('Name', 'intropage')       . '</th>' .
				'<th class="left">'  . __('State', 'intropage')      . '</th>' .
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
				$color = 'red';
			} elseif ($poller['status'] == 4) {
				$status = __('Disabled', 'intropage');
			} elseif ($poller['status'] == 5) {
				$status = __('Recovering', 'intropage');
				$color = 'yellow';
			}



			$details .= '<tr>' .
				'<td class="left">'  . $poller['id']                . '</td>' .
				'<td class="left">'  . html_escape($poller['name']) . '</td>' .
				'<td class="left"><span class="inpa_sq color_' . $color . '"></span>'  . $status . '</td>';

			$color = 'green';
			
			if (($poller['total_time']/$poller_interval) > 0.9) {
				$color = 'red';
			} elseif (($poller['total_time']/$poller_interval) > 0.7) {
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

function poller_stat_trend() {
	$stats = db_fetch_assoc('SELECT id, total_time, DATE_SUB(last_update, INTERVAL ROUND(total_time) SECOND) AS start
		FROM poller
		ORDER BY avg_time DESC');

	foreach ($stats as $stat) {
		db_execute_prepared("REPLACE INTO plugin_intropage_trends
			(name, cur_timestamp, value, user_id) VALUES
			('poller', ?, ?, ?)",
			array($stat['start'], $stat['id'] . ':' . round($stat['total_time']),4));
	}
}

//------------------------------------ poller stat -----------------------------------------------------
function poller_stat($panel, $user_id, $timespan = 0) {
	global $config, $run_from_poller;

	$lines = read_user_setting('intropage_number_of_lines', read_config_option('intropage_number_of_lines'), false, $user_id);
	$poller_interval = read_config_option('poller_interval');

	$panel['alarm'] = 'green';

	$graph = array (
		'line' => array(
			'title1' => '',
			'label1' => array(),
			'data1'  => array(),
			'title2' => '',
			'label2' => array(),
			'data2'  => array(),
			'title3' => '',
			'label3' => array(),
			'data3'  => array(),
			'title4' => '',
			'label4' => array(),
			'data4'  => array(),
			'title5' => '',
			'label5' => array(),
			'data5'  => array(),
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

	if (cacti_sizeof($pollers)) {
		$new_index = 1;

		foreach ($pollers as $xpoller) {
	
			$rows = db_fetch_assoc_prepared("SELECT cur_timestamp AS `date`, AVG(SUBSTRING_INDEX(value, ':', -1)) AS value
				FROM plugin_intropage_trends
				WHERE cur_timestamp > date_sub(NOW(), INTERVAL ? SECOND)
				AND name = 'poller'
				AND value LIKE ?
				GROUP BY UNIX_TIMESTAMP(cur_timestamp) DIV $refresh
				ORDER BY cur_timestamp ASC",
				array($timespan, $xpoller['id'] . ':%'));

			foreach ($rows as $row) {
				if ($row['value'] > ($poller_interval - 10)) {
					$panel['alarm'] = 'red';
				}

				// graph data
				$graph['line']['label' . $new_index][] = $row['date'];
				$graph['line']['data'  . $new_index][] = round($row['value'], 2);
				$graph['line']['title' . $new_index]   = __('ID: ', 'intropage') . $xpoller['id'];
				$graph['line']['unit1']['title']       = __('Seconds', 'intropage');
			}

			$new_index++;
		}

		$panel['data'] = intropage_prepare_graph($graph, $user_id);
	} else {
		$panel['data'] = __('Waiting for data', 'intropage');
	}

	save_panel_result($panel, $user_id);
}

//------------------------------------ poller_info -----------------------------------------------------
function poller_info_detail() {
	global $config;

	$lines = read_user_setting('intropage_number_of_lines', read_config_option('intropage_number_of_lines'), false, $_SESSION['sess_user_id']);
	$poller_interval = read_config_option('poller_interval');	

	$panel = array(
		'name'   => __('Poller Details', 'intropage'),
		'alarm'  => 'green',
		'detail' => '',
	);

	$row = '<table class="cactiTable">' .
		'<tr class="tableHeader">' .
		'<td class="left">'  . __('ID', 'intropage')           . '</td>' .
		'<td class="left">'  . __('Name', 'intropage')         . '</td>' .
		'<td class="left">'  . __('State', 'intropage')        . '</td>' .
		'<td class="right">' . __('Total Time', 'intropage')   . '</td>' .
		'<td class="right">' . __('Average Time', 'intropage') . '</td>' .
		'<td class="right">' . __('Max Time', 'intropage')     . '</td>' .
	'</tr>';

	$pollers = db_fetch_assoc('SELECT p.*
		FROM poller p
		LEFT JOIN poller_time pt
		ON pt.poller_id = p.id
		WHERE p.disabled = ""
		GROUP BY p.id
		ORDER BY p.id
		LIMIT ' . $lines);

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
				$row .= '<td class="left">' . __('New/Idle', 'intropage')   . '</td>';
			} elseif ($poller['status'] == 1) {
				$row .= '<td class="left">' . __('Running', 'intropage')    . '</td>';
			} elseif ($poller['status'] == 2) {
				$row .= '<td class="left">' . __('Idle', 'intropage')       . '</td>';
			} elseif ($poller['status'] == 3) {
				$row .= '<td class="left">' . __('Unkn/down', 'intropage')  . '<span class="inpa_sq color_red"></span></td>';
			} elseif ($poller['status'] == 4) {
				$row .= '<td class="left">' . __('Disabled', 'intropage')   . '</td>';
			} elseif ($poller['status'] == 5) {
				$row .= '<td class="left">' . __('Recovering', 'intropage') . '<span class="inpa_sq color_yellow"></span></td>';
			}

			if (($poller['total_time']/$poller_interval) > 0.9) {
				$color = 'red';
			} elseif (($poller['total_time']/$poller_interval) > 0.7) {
				$color = 'yellow';
			}

			$row .= '<td class="right">' . round($poller['total_time'], 2) . 's <span class="inpa_sq color_' . $color . '"></span></td>';
			$row .= '<td class="right">' . round($poller['avg_time'], 2)   . 's</td>';
			$row .= '<td class="right">' . round($poller['max_time'], 2)   . 's</td>';

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

//------------------------------------ poller_output_items -----------------------------------------------------

function poller_output_items_trend() {

	$count = db_fetch_cell("SELECT COUNT(local_data_id) FROM poller_output");

	db_execute_prepared('REPLACE INTO plugin_intropage_trends
		(name, value, user_id)
		VALUES (?, ?, 0)',
		array('poller_output', $count));
}

function poller_output_items($panel, $user_id, $timespan = 0) {
	global $config, $run_from_poller;

	$poller_interval = read_config_option('poller_interval');
	$color = read_config_option('intropage_alert_poller_output');

	$panel['alarm'] = 'green';

	$graph = array (
		'line' => array(
			'title1' => '',
			'label1' => array(),
			'data1'  => array(),
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
		$refresh = $panel['refresh_interval'];
	}

	$rows = db_fetch_assoc_prepared("SELECT cur_timestamp AS `date`, value
		FROM plugin_intropage_trends
		WHERE cur_timestamp > date_sub(NOW(), INTERVAL ? SECOND)
		AND name = 'poller_output'
		GROUP BY UNIX_TIMESTAMP(cur_timestamp) DIV $refresh
		ORDER BY cur_timestamp ASC",
		array($timespan));

	if (cacti_sizeof($rows)) {
		foreach ($rows as $row) {
			if ($row['value'] > 0) {
				if ($color == 'red') {
					$panel['alarm'] = 'red';
				} elseif ($panel['alarm'] == 'green' && $color == "yellow") {
					$panel['alarm'] = 'yellow';
				}
			}

			// graph data
			$graph['line']['label1'][] = $row['date'];
			$graph['line']['data1'][] = $row['value'];
			$graph['line']['title1'] = __('Poller output items ', 'intropage');
			$graph['line']['unit1']['title']       = __('Items', 'intropage');
		}

		$panel['data'] = intropage_prepare_graph($graph, $user_id);
	} else {
		$panel['data'] = __('Waiting for data', 'intropage');
	}

	save_panel_result($panel, $user_id);
}

