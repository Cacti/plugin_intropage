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

function register_graphs() {
	global $registry;

	$registry['graphs'] = array(
		'name'        => __('Graphical Panels', 'intropage'),
		'description' => __('Panels that provide information about Cacti and it\'s plugins in a Graphical way.', 'intropage')
	);

	$panels = array(
		'graph_data_source' => array(
			'name'         => __('Data Sources', 'intropage'),
			'description'  => __('Graph of Data Sources', 'intropage'),
			'class'        => 'graphs',
			'level'        => PANEL_USER,
			'refresh'      => 300,
			'trefresh'     => false,
			'force'        => true,
			'width'        => 'quarter-panel',
			'priority'     => 78,
			'alarm'        => 'green',
			'requires'     => false,
			'update_func'  => 'graph_data_source',
			'details_func' => 'graph_data_source_detail',
			'trends_func'  => false
		),
		'graph_host_template' => array(
			'name'         => __('Device Templates', 'intropage'),
			'description'  => __('Graph of Device Templates', 'intropage'),
			'class'        => 'graphs',
			'level'        => PANEL_USER,
			'refresh'      => 7200,
			'trefresh'     => false,
			'force'        => true,
			'width'        => 'quarter-panel',
			'priority'     => 18,
			'alarm'        => 'grey',
			'requires'     => false,
			'update_func'  => 'graph_host_template',
			'details_func' => 'graph_host_template_detail',
			'trends_func'  => false
		),
		'graph_host' => array(
			'name'         => __('Devices by Status', 'intropage'),
			'description'  => __('Graph of Devices by Status (up,down,...)', 'intropage'),
			'class'        => 'graphs',
			'level'        => PANEL_USER,
			'refresh'      => 300,
			'trefresh'     => read_config_option('poller_interval'),
			'force'        => true,
			'width'        => 'quarter-panel',
			'priority'     => 19,
			'alarm'        => 'green',
			'requires'     => false,
			'update_func'  => 'graph_host',
			'details_func' => 'graph_host_detail',
			'trends_func'  => 'host_collect'
		),
	);

	return $panels;
}

// ------------------------- graph data source---------------------
function graph_data_source($panel, $user_id) {
	global $config, $input_types, $run_from_poller;

	$allowed_devices = intropage_get_allowed_devices($user_id);

	if ($allowed_devices !== false) {
		$graph = array (
			'pie' => array(
				'title' => __('Datasources: ', 'intropage'),
				'label' => array(),
				'data'  => array(),
			),
		);

		$sql_ds = db_fetch_assoc('SELECT data_input.type_id, COUNT(data_input.type_id) AS total
			FROM data_local
			INNER JOIN data_template_data
			ON (data_local.id = data_template_data.local_data_id)
			LEFT JOIN data_input
			ON (data_input.id=data_template_data.data_input_id)
			LEFT JOIN data_template
			ON (data_local.data_template_id=data_template.id)
			WHERE local_data_id<>0 AND data_local.host_id in (' . $allowed_devices . ' )
			GROUP BY type_id LIMIT 6');

		if (cacti_sizeof($sql_ds)) {
			foreach ($sql_ds as $item) {
				if (!is_null($item['type_id'])) {
					array_push($graph['pie']['label'], preg_replace('/script server/', 'SS', $input_types[$item['type_id']]));
					array_push($graph['pie']['data'], $item['total']);

					$panel['data'] .= preg_replace('/script server/', 'SS', $input_types[$item['type_id']]) . ': ';
					$panel['data'] .= $item['total'] . '<br/>';
				}
			}

			$panel['data'] = intropage_prepare_graph($graph, $user_id);

			unset($graph);
		}
	} else {
		$panel['data'] = __('You don\'t have permissions to any hosts', 'intropage');
	}

	save_panel_result($panel, $user_id);
}

// -----------------------graph_host template--------------------
function graph_host_template($panel, $user_id) {
	global $config;

	$allowed_devices = intropage_get_allowed_devices($user_id);

	if (defined('CACTI_VERSION')) {
		$cv = CACTI_VERSION;
	} else {
		$cv = get_cacti_version();
	}

	if ($allowed_devices !== false) {
		if (cacti_version_compare($cv, '1.2.24', '>=')) {
			$graph = array(
				'treemap' => array(
					'title' => __('Device Templates: ', 'intropage'),
					'label' => array(),
					'data'  => array(),
				),
			);

			$sql_ht = db_fetch_assoc("SELECT host_template.id as id, name,
				COUNT(host.host_template_id) AS total
				FROM host_template
				LEFT JOIN host
				ON host_template.id = host.host_template_id
				WHERE host.id IN ( " . $allowed_devices . ")
				GROUP BY host_template_id
				ORDER BY total DESC
				LIMIT 20");

			if (cacti_sizeof($sql_ht)) {
				foreach ($sql_ht as $item) {
					array_push($graph['treemap']['label'], substr($item['name'],0,20));
					array_push($graph['treemap']['data'], $item['total']);
				}

			}
		} else {

			$graph = array(
				'pie' => array(
					'title' => __('Device Templates: ', 'intropage'),
					'label' => array(),
					'data'  => array(),
				),
			);

			$sql_ht = db_fetch_assoc("SELECT host_template.id as id, name,
				COUNT(host.host_template_id) AS total
				FROM host_template
				LEFT JOIN host
				ON host_template.id = host.host_template_id
				WHERE host.id IN ( " . $allowed_devices . ")
				GROUP BY host_template_id
				ORDER BY total DESC
				LIMIT 6");

			if (cacti_sizeof($sql_ht)) {
				foreach ($sql_ht as $item) {
					array_push($graph['pie']['label'], substr($item['name'],0,15));
					array_push($graph['pie']['data'], $item['total']);
				}
			}
		}

		$panel['data'] = intropage_prepare_graph($graph, $user_id);

		unset($graph);


	} else {
		$panel['data'] = __('You don\'t have permissions to any hosts', 'intropage');
	}

	save_panel_result($panel, $user_id);
}

//--------------------------------------- graph host-----------------------------
function graph_host($panel, $user_id, $timespan = 0) {
	global $config;

        $panel['alarm'] = 'green';

	$allowed_devices = intropage_get_allowed_devices($user_id);

        if ($allowed_devices !== false) {
		$graph = array (
                	'line' => array(
				'title'  => __('Devices: ', 'intropage'),
				'label1' => array(),
				'data1'  => array(),
				'label2' => array(),
				'data2'  => array(),
				'label3' => array(),
				'data3'  => array(),
                       ),
		);

		$first = true;

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

		$rows = db_fetch_assoc_prepared("SELECT cur_timestamp AS `date`, value
			FROM plugin_intropage_trends
			WHERE cur_timestamp > date_sub(NOW(), INTERVAL ? SECOND)
			AND name = 'host_down'
			ORDER BY cur_timestamp ASC",
			array($timespan));

                if (cacti_sizeof($rows)) {

                        $graph['line']['title1'] = __('Down', 'intropage');
                        $graph['line']['unit1']['title'] = 'Down';

			foreach ($rows as $row) {
				if ($first) {
					if ($row['value'] > 0) {
						$panel['alarm'] = 'red';
					}
					$first = false;
				}

				$graph['line']['label1'][] = $row['date'];
				$graph['line']['data1'][]  = $row['value'];
                        }

			$first = true;

		} else {
                        unset($graph['line']['label1']);
                        unset($graph['line']['data1']);
		}

		$rows = db_fetch_assoc_prepared("SELECT cur_timestamp AS `date`, value
			FROM plugin_intropage_trends
			WHERE cur_timestamp > date_sub(NOW(), INTERVAL ? SECOND)
			AND name = 'host_reco'
			ORDER BY cur_timestamp ASC",
			array($timespan));

		if (cacti_sizeof($rows)) {

			$graph['line']['title2'] = __('Recovering', 'intropage');
			$graph['line']['unit2']['title'] = 'Recovering';

			foreach ($rows as $row) {
				if ($first) {
					if ($row['value'] > 0 && $panel['alarm'] == 'green') {
						$panel['alarm'] = 'yellow';
					}
					$first = false;
				}

				$graph['line']['label2'][] = $row['date'];
				$graph['line']['data2'][]  = $row['value'];
			}
		} else {
			unset($graph['line']['label2']);
			unset($graph['line']['data2']);
		}

		$rows = db_fetch_assoc_prepared("SELECT cur_timestamp AS `date`, value
			FROM plugin_intropage_trends
			WHERE cur_timestamp > date_sub(NOW(), INTERVAL ? SECOND)
			AND name = 'host_disa'
			ORDER BY cur_timestamp ASC",
			array($timespan));

		if (cacti_sizeof($rows)) {
			$graph['line']['title3'] = __('Disabled', 'intropage');
			$graph['line']['unit3']['title'] = 'Disabled';

			foreach ($rows as $row) {
				$graph['line']['label3'][] = $row['date'];
				$graph['line']['data3'][]  = $row['value'];
			}
		} else {
			unset($graph['line']['label3']);
			unset($graph['line']['data3']);
		}


                if (isset($graph['line']['data1']) || isset($graph['line']['data2']) || isset($graph['line']['data3'])) {
                        $panel['data'] = intropage_prepare_graph($graph, $user_id);
                } else {
                        unset($graph);
                        $panel['data'] = __('Waiting for data', 'intropage');
                }
	} else {
		$panel['data'] = __('You don\'t have permissions to any hosts', 'intropage');
	}

	save_panel_result($panel, $user_id);
}


//------------------------------------ graph_datasource -----------------------------------------------------
function graph_data_source_detail() {
	global $config, $input_types;

	$panel = array(
		'name'   => __('Data Sources', 'intropage'),
		'alarm'  => 'green',
		'detail' => ''
	);

	$sql_ds = db_fetch_assoc('SELECT di.type_id, COUNT(di.type_id) AS total
		FROM data_local AS dl
		INNER JOIN data_template_data AS dtd
		ON dl.id = dtd.local_data_id
		LEFT JOIN data_input AS di
		ON di.id = dtd.data_input_id
		LEFT JOIN data_template AS dt
		ON dl.data_template_id = dt.id
		WHERE dtd.local_data_id != 0
		GROUP BY type_id');

	$total = 0;

	$panel['detail'] .= '<table class="cactiTable">';

	if (cacti_sizeof($sql_ds)) {
		$panel['detail'] .= '<tr class="tableHeader">
			<th class="left">'  . __('Data Type', 'intropage') . '</th>
			<th class="right">' . __('Data Sources')           . '</th>
		</tr>';

		$i = 0;
		foreach ($sql_ds as $item) {
			if (!is_null($item['type_id'])) {
				$class = ($i % 2 == 0 ? 'odd':'even');
				$panel['detail'] .= '<tr class="' . $class . '"><td class="left">' . preg_replace('/script server/', 'SS', $input_types[$item['type_id']]) . '</td>';
				$panel['detail'] .= '<td class="right">' . number_format_i18n($item['total'], -1) . '</td></tr>';

				$total += $item['total'];
				$i++;
			}
		}

		$class = ($i % 2 == 0 ? 'odd':'even');

		$panel['detail'] .= '<tr class="' . $class . '" rowspan="2"><td class="left">' . __('Total', 'intropage') . '</td><td class="right">' . number_format_i18n($total, -1) . '</td></tr>';

		$panel['detail'] .= '</table>';
	} else {
		$panel['detail'] = __('No untemplated datasources found');
	}

	return $panel;
}

//------------------------------------ graph_host -----------------------------------------------------
function graph_host_detail() {
	global $config, $console_access;

	$panel = array(
		'name'   => __('Devices', 'intropage'),
		'alarm'  => 'green',
		'detail' => '',
	);

	$h_all = db_fetch_cell("SELECT COUNT(id) FROM host");

	$h_up = db_fetch_cell("SELECT COUNT(id)
		FROM host
		WHERE status = 3
		AND disabled = ''");

	$h_down = db_fetch_cell("SELECT COUNT(id)
		FROM host
		WHERE status = 1
		AND disabled = ''");

	$h_reco = db_fetch_cell("SELECT COUNT(id)
		FROM host
		WHERE status = 2
		AND disabled = ''");

	$h_disa = db_fetch_cell("SELECT COUNT(id)
		FROM host
		WHERE disabled = 'on'");

	$count = $h_all + $h_up + $h_down + $h_reco + $h_disa;

	$panel['detail']  = '<table class="cactiTable">';
	$panel['detail'] .= '<tr class="tableHeader"><th class="left">' . __esc('Status', 'intropage') . '</th><th class="right">' . __esc('Device Count', 'intropage') . '</th></tr>';

	$status = array(
		array(
			'class'  => 'odd',
			'status' => '-1',
			'text'   => __esc('All', 'intropage'),
			'value'  => $h_all
		),
		array(
			'class'  => 'even',
			'status' => '3',
			'text'   => __esc('Up', 'intropage'),
			'value'  => $h_up
		),
		array(
			'class'  => 'odd',
			'status' => '1',
			'text'   => __esc('Down', 'intropage'),
			'value'  => $h_down
		),
		array(
			'class'  => 'even',
			'status' => '2',
			'text'   => __esc('Recovering', 'intropage'),
			'value'  => $h_reco
		),
		array(
			'class'  => 'even',
			'status' => '-2',
			'text'   => __esc('Disabled', 'intropage'),
			'value'  => $h_disa
		)
	);

	foreach($status as $s) {
		if (api_plugin_user_realm_auth('host.php')) {
			$panel['detail'] .= '<tr class="' . $s['class'] . '">';
			$panel['detail'] .= '<td class="left">';
			$panel['detail'] .= '<a class="pic linkEditMain bold" href="' . html_escape($config['url_path'] . 'host.php?host_status=' . $s['status']) . '">' . $s['text'] . '</a>';
			$panel['detail'] .= '</td>';
			$panel['detail'] .= '<td class="right">' . number_format_i18n($s['value'], -1) . '</td>';
			$panel['detail'] .= '</tr>';
		} else {
			$panel['detail'] .= '<tr class="' . $s['class'] . ' bold">';
			$panel['detail'] .= '<td class="left">' . $s['text'] . '</td>';
			$panel['detail'] .= '<td class="right">' . number_format_i18n($s['value'], -1) . '</td>';
			$panel['detail'] .= '</tr>';
		}

		if (($s['status'] == 1 || $s['status'] == 2) && $s['value'] > 0) {
			$h = db_fetch_assoc("SELECT id, description, status_fail_date
				FROM host
				WHERE status = " . $s['status'] .
				" AND disabled = ''");

			$panel['detail'] .= '<tr class="' . $s['class'] . '"><td class="left" colspan="2">';

			foreach ($h as $r) {
				$panel['detail'] .= ' - ' . $r['description'] . ' (ID: ' . $r['id'] . ', Device Failed on: ' . $r['status_fail_date'] . ')<br/>';
			}

			$panel['detail'] .= '</td></tr>';
		}
	}

	$panel['detail'] .= '</table>';

	if ($h_reco > 0) {
		$panel['alarm'] = 'yellow';
	}

	if ($h_down > 0) {
		$panel['alarm'] = 'red';
	}

	return $panel;
}

//------------------------------------ graph host_template -----------------------------------------------------
function graph_host_template_detail() {
	global $config;

	$panel = array(
		'name' => __('Device Templates', 'intropage'),
		'alarm' => 'green',
		'detail' => '',
	);

	$rows = db_fetch_assoc("SELECT host_template.id as id, name, COUNT(host.host_template_id) AS total
		FROM host_template
		LEFT JOIN host
		ON (host_template.id = host.host_template_id)
		GROUP by host_template_id
		ORDER BY total desc");

	$total = 0;

	if (cacti_sizeof($rows)) {
		$panel['detail'] .= '<table class="cactiTable">
			<tr class="tableHeader">
				<th class="left">'  . __('Template Name', 'intropage') . '</th>
				<th class="right">' . __('Total Devices', 'intropage') . '</th>
			</tr>';

		$i = 0;
		foreach ($rows as $item) {
			$class = ($i % 2 == 0 ? 'odd':'even');
			$panel['detail'] .= '<tr class="' . $class . '"><td class="left">' . html_escape($item['name']) . '</td>';
			$panel['detail'] .= '<td class="right">'   . number_format_i18n($item['total'], -1)             . '</td></tr>';
			$total += $item['total'];
			$i++;
		}

		$class = ($i % 2 == 0 ? 'odd':'even');
		$panel['detail'] .= '<tr class="' . $class . '">
			<td class="left">'  . __('Total', 'intropage')       . '</td>
			<td class="right">' . number_format_i18n($total, -1) . '</td>
		</tr>';

		$panel['detail'] .= '</table>';
	} else {
		$panel['detail'] = __('No device templates found', 'intropage');
	}

	return $panel;
}


//------------------------------------ host collect -----------------------------------------------------
function host_collect() {
	global $config;

	// update in poller
	$users = get_user_list();

	foreach ($users as $user) {
		$allowed_devices = intropage_get_allowed_devices($user['id']);

		if ($allowed_devices !== false) {

			db_execute_prepared("REPLACE INTO plugin_intropage_trends
				(name,value,user_id)
				SELECT 'host_down', COUNT(*),?
				FROM host
				WHERE id in (" . $allowed_devices . ")
				AND status='1'
				AND disabled=''",
				array($user['id']));

			db_execute_prepared("REPLACE INTO plugin_intropage_trends
				(name,value,user_id)
				SELECT 'host_reco', COUNT(*),?
				FROM host
				WHERE id in (" . $allowed_devices . ")
				AND status='2'
				AND disabled=''",
				array($user['id']));

			db_execute_prepared("REPLACE INTO plugin_intropage_trends
				(name,value,user_id)
				SELECT 'host_disa', COUNT(*),?
				FROM host
				WHERE id in (" . $allowed_devices . ")
				AND disabled='on'",
				array($user['id']));
		} else {
			db_execute_prepared("REPLACE INTO plugin_intropage_trends
				(name,value,user_id)
				VALUES ('host_down', 0, ?)",
				array($user['id']));

			db_execute_prepared("REPLACE INTO plugin_intropage_trends
				(name,value,user_id)
				VALUES ('host_down', 0, ?)",
				array($user['id']));

                       db_execute_prepared("REPLACE INTO plugin_intropage_trends
				(name,value,user_id)
				VALUES ('host_reco', 0, ?)",
				array($user['id']));

                       db_execute_prepared("REPLACE INTO plugin_intropage_trends
				(name,value,user_id)
				VALUES ('host_disa', 0, ?)",
				array($user['id']));
		}
	}
}
