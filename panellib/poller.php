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
			'refresh'      => 60,
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
			'refresh'      => 60,
			'force'        => true,
			'width'        => 'quarter-panel',
			'priority'     => 73,
			'alarm'        => 'green',
			'requires'     => false,
			'update_func'  => 'poller_stat',
			'details_func' => false,
			'trends_func'  => 'poller_stat_trend'
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

	$panel['data'] = '<b>' . __('ID/Name/Total Time/State', 'intropage') . '</b><br/>';

	$panel['alarm'] = 'green';

	$sql_pollers = db_fetch_assoc('SELECT p.id, name, status, last_update, total_time
		FROM poller p
		LEFT JOIN poller_time pt
		ON pt.poller_id = p.id
		WHERE p.disabled = ""
		GROUP BY p.id
		ORDER BY p.id
		LIMIT 5');

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
			} elseif ($poller['status'] == 4) {
				$status = __('Disabled', 'intropage');
			} elseif ($poller['status'] == 5) {
				$status = __('Recovering', 'intropage');
			}

			$details .= '<tr>' .
				'<td class="left">'  . $poller['id']                . '</td>' .
				'<td class="left">'  . html_escape($poller['name']) . '</td>' .
				'<td class="left">'  . $status . '</td>' .
				'<td class="right">' . __('%s Secs', round($poller['total_time'], 2), 'intropage') . ' </td>' .
			'</tr>';
		}

		$details .= '</table>';
	}

	$panel['data'] =
		'<center><span class="txt_huge">' . $ok    . '</span> ' . __('(ok)', 'intropage') . ' / ' .
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
	$stats = db_fetch_assoc('SELECT id, total_time, date_sub(last_update, interval round(total_time) second) AS start
		FROM poller
		ORDER BY id
		LIMIT 5');

	foreach ($stats as $stat) {
		db_execute_prepared("REPLACE INTO plugin_intropage_trends
			(name, cur_timestamp, value, user_id) VALUES
			('poller', ?, ?, ?)",
			array($stat['start'], $stat['id'] . ':' . round($stat['total_time']),0));
	}
}

//------------------------------------ poller stat -----------------------------------------------------
function poller_stat($panel, $user_id) {
	global $config, $run_from_poller;

	$poller_interval = read_config_option('poller_interval');

	$panel['alarm'] = 'green';

	$graph = array (
		'line' => array(
			'title1' => '',
			'label1' => array(),
			'data1' => array(),
			'title2' => '',
			'label2' => array(),
			'data2' => array(),
			'title3' => '',
			'label3' => array(),
			'data3' => array(),
			'title4' => '',
			'label4' => array(),
			'data4' => array(),
			'title5' => '',
			'label5' => array(),
			'data5' => array(),
		),
	);

	$pollers = db_fetch_assoc('SELECT p.id
		FROM poller p
		LEFT JOIN poller_time pt
		ON pt.poller_id = p.id
		WHERE p.disabled = ""
		GROUP BY p.id
		ORDER BY id
		LIMIT 5');

	if (cacti_sizeof($pollers)) {
		$new_index = 1;

		foreach ($pollers as $xpoller) {
			$poller_time = db_fetch_assoc_prepared("SELECT date_format(time(cur_timestamp),'%H:%i') AS `date`, value
				FROM plugin_intropage_trends
				WHERE name='poller'
				AND value LIKE ?
				ORDER BY cur_timestamp DESC
				LIMIT 10",
				array($xpoller['id'] . ':%'));

			$poller_time = array_reverse($poller_time);

			foreach ($poller_time as $one_poller) {
				list($id, $time) = explode(':', $one_poller['value']);

				if ($time > ($poller_interval - 10)) {
					$panel['alarm'] = 'red';
					$panel['data'] .= '<b>' . $one_poller['date'] . __(' Poller ID: ', 'intropage') . $xpoller['id'] . ' ' . $time . 's</b><br/>';
				} else {
					$panel['data'] .= $one_poller['date'] . __(' Poller ID: ', 'intropage') . $xpoller['id'] . ' ' . $time . 's<br/>';
				}

				// graph data
				array_push($graph['line']['label' . $new_index], $one_poller['date']);
				array_push($graph['line']['data' . $new_index], $time);

				$graph['line']['title' . $new_index] = __('ID: ', 'intropage') . $xpoller['id'];
			}

			$new_index++;
		}
	}

	if (count($graph['line']['data1']) < 3) {
		$panel['data'] = __('Waiting for data', 'intropage');
		unset($graph);
	} else {
		$panel['data'] = intropage_prepare_graph($graph);
		unset($graph);
	}

	save_panel_result($panel, $user_id);
}

//------------------------------------ poller_info -----------------------------------------------------
function poller_info_detail() {
	global $config;

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
		ORDER BY p.id');

	$count    = $pollers === false ? __('N/A', 'intropage') : cacti_count($pollers);
	$ok       = 0;
	$running  = 0;

	if (cacti_sizeof($pollers)) {
		foreach ($pollers as $poller) {
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
				$row .= '<td class="left">' . __('Unkn/down', 'intropage')  . '</td>';
			} elseif ($poller['status'] == 4) {
				$row .= '<td class="left">' . __('Disabled', 'intropage')   . '</td>';
			} elseif ($poller['status'] == 5) {
				$row .= '<td class="left">' . __('Recovering', 'intropage') . '</td>';
			}

			$row .= '<td class="right">' . round($poller['total_time'], 2) . 's</td>';
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

