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
			'refresh'      => 7200,
			'force'        => true,
			'width'        => 'quarter-panel',
			'priority'     => 19,
			'alarm'        => 'green',
			'requires'     => false,
			'update_func'  => 'graph_host',
			'details_func' => 'graph_host_detail',
			'trends_func'  => false
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

			$panel['data'] = intropage_prepare_graph($graph);

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

	if ($allowed_devices !== false) {
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

				$panel['data'] .= $item['name'] . ': ';
				$panel['data'] .= $item['total'] . '<br/>';
			}

			$panel['data'] = intropage_prepare_graph($graph);

			unset($graph);
		}
	} else {
		$panel['data'] = __('You don\'t have permissions to any hosts', 'intropage');
	}

	save_panel_result($panel, $user_id);
}

//--------------------------------------- graph host-----------------------------
function graph_host($panel, $user_id) {
	global $config;

	$allowed_devices = intropage_get_allowed_devices($user_id);

	$panel['alarm'] = 'green';

	if ($allowed_devices !== false) {
		$graph = array(
			'pie' => array(
				'title' => __('Hosts: ', 'intropage'),
				'label' => array(),
				'data'  => array(),
			),
		);

		$console_access = get_console_access($user_id);

		$h_all = db_fetch_cell('SELECT COUNT(id)
			FROM host
			WHERE id IN (' . $allowed_devices . ')');

		$h_up = db_fetch_cell('SELECT COUNT(id)
			FROM host
			WHERE id IN (' . $allowed_devices . ')
			AND status = 3
			AND disabled = ""');

		$h_down = db_fetch_cell('SELECT COUNT(id)
			FROM host
			WHERE id IN (' . $allowed_devices . ')
			AND status = 1
			AND disabled = ""');

		$h_reco = db_fetch_cell('SELECT COUNT(id)
			FROM host
			WHERE id IN (' . $allowed_devices . ')
			AND status = 2
			AND disabled = ""');

		$h_disa = db_fetch_cell('SELECT COUNT(id)
			FROM host
			WHERE id IN (' . $allowed_devices . ')
			AND disabled = "on"');

		$count = $h_all + $h_up + $h_down + $h_reco + $h_disa;
		$url_prefix = $console_access ? '<a href="' . html_escape($config['url_path']) . 'host.php?host_status=%s">' : '';
		$url_suffix = $console_access ? '</a>' : '';

		$panel['data']  = sprintf($url_prefix,'-1')  . __('All', 'intropage')        . ": $h_all$url_suffix<br/>";
		$panel['data'] .= sprintf($url_prefix,'=3')  . __('Up', 'intropage')         . ": $h_up$url_suffix<br/>";
		$panel['data'] .= sprintf($url_prefix,'=1')  . __('Down', 'intropage')       . ": $h_down$url_suffix<br/>";
		$panel['data'] .= sprintf($url_prefix,'=-2') . __('Disabled', 'intropage')   . ": $h_disa$url_suffix<br/>";
		$panel['data'] .= sprintf($url_prefix,'=2')  . __('Recovering', 'intropage') . ": $h_reco$url_suffix";

		if ($count > 0) {
			$graph['pie'] = array(
				'title' => __('Hosts', 'intropage'),
				'label' => array(
					__('Up', 'intropage'),
					__('Down', 'intropage'),
					__('Recovering', 'intropage'),
					__('Disabled', 'intropage'),
				),
				'data' => array($h_up, $h_down, $h_reco, $h_disa)
			);

			$panel['data'] = intropage_prepare_graph($graph);

			unset($graph);
		}

		// alarms and details
		if ($h_reco > 0) {
			$panel['alarm'] = 'yellow';
		}

		if ($h_down > 0) {
			$panel['alarm'] = 'red';
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
		'alarm'  => 'grey',
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

	if (cacti_sizeof($sql_ds)) {
		foreach ($sql_ds as $item) {
			if (!is_null($item['type_id'])) {
				$panel['detail'] .= preg_replace('/script server/', 'SS', $input_types[$item['type_id']]) . ': ';
				$panel['detail'] .= $item['total'] . '<br/>';
				$total += $item['total'];
			}
		}

		$panel['detail'] .= '<br/><b> Total: ' . $total . '</b><br/>';
	} else {
		$panel['detail'] = __('No untemplated datasources found');
	}

	return $panel;
}

//------------------------------------ graph_host -----------------------------------------------------
function graph_host_detail() {
	global $config, $console_access;

	$panel = array(
		'name'   => __('Hosts', 'intropage'),
		'alarm'  => 'green',
		'detail' => '',
	);

	$h_all  = db_fetch_cell("SELECT COUNT(id) FROM host");

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

	$url_prefix = $console_access ? '<a class="linkEditMain" href="' . html_escape($config['url_path'] . 'host.php?host_status=%s') . '">' : '';
	$url_suffix = $console_access ? '</a>' : '';

	$panel['detail']  = sprintf($url_prefix, '-1')  . __('All', 'intropage')        . ": $h_all$url_suffix<br/>";
	$panel['detail'] .= sprintf($url_prefix, '=3')  . __('Up', 'intropage')         . ": $h_up$url_suffix<br/>";
	$panel['detail'] .= sprintf($url_prefix, '=1')  . __('Down', 'intropage')       . ": $h_down$url_suffix<br/>";
	$panel['detail'] .= sprintf($url_prefix, '=-2') . __('Disabled', 'intropage')   . ": $h_disa$url_suffix<br/>";
	$panel['detail'] .= sprintf($url_prefix, '=2')  . __('Recovering', 'intropage') . ": $h_reco$url_suffix";

	// alarms and details
	if ($h_reco > 0) {
		$panel['alarm'] = 'yellow';

		$hosts = db_fetch_assoc("SELECT description
			FROM host
			WHERE status = 2
			AND disabled = ''");

		$panel['detail'] .= '<br/><br/><b>' . __('Recovering:', 'intropage') . '</b><br/>';

		if (cacti_sizeof($hosts)) {
			foreach ($hosts as $host) {
				$panel['detail'] .= html_escape($host['description']) . '<br/>';
			}
		}

		$panel['detail'] .= '<br/><br/>';
	}

	if ($h_down > 0) {
		$panel['alarm'] = 'red';

		$hosts = db_fetch_assoc("SELECT description
			FROM host
			WHERE status = 1
			AND disabled = ''");

		$panel['detail'] .= '<br/><br/><b>' . __('Down:', 'intropage') . '</b><br/>';

		if (cacti_sizeof($hosts)) {
			foreach ($hosts as $host) {
				$panel['detail'] .= html_escape($host['description']) . '<br/>';
			}
		}

		$panel['detail'] .= '<br/><br/>';
	}

	return $panel;
}

//------------------------------------ graph host_template -----------------------------------------------------
function graph_host_template_detail() {
	global $config;

	$panel = array(
		'name' => __('Device Templates', 'intropage'),
		'alarm' => 'grey',
		'detail' => '',
	);

	$sql_ht = db_fetch_assoc("SELECT host_template.id as id, name, COUNT(host.host_template_id) AS total
		FROM host_template
		LEFT JOIN host
		ON (host_template.id = host.host_template_id)
		GROUP by host_template_id
		ORDER BY total desc");

	$total = 0;

	if (cacti_sizeof($sql_ht)) {
		foreach ($sql_ht as $item) {
			$panel['detail'] .= $item['name'] . ': ';
			$panel['detail'] .= $item['total'] . '<br/>';
			$total += $item['total'];
		}

		$panel['detail'] .= '<br/><b> Total: ' . $total . '</b><br/>';

	} else {
		$panel['detail'] = __('No device templates found', 'intropage');
	}

	return $panel;
}

