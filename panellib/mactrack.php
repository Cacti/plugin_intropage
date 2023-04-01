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

function register_mactrack() {
	global $registry;

	$registry['mactrack'] = array(
		'name'        => __('MacTrack Panels', 'intropage'),
		'description' => __('Panels that provide information about Cacti\'s MacTrack plugins.', 'intropage')
	);

	$panels = array(
		'mactrack' => array(
			'name'         => __('MacTrack Plugin', 'intropage'),
			'description'  => __('Various MacTrack collection and site statistics.', 'intropage'),
			'class'        => 'mactrack',
			'level'        => PANEL_USER,
			'refresh'      => 900,
			'trefresh'     => false,
			'force'        => true,
			'width'        => 'quarter-panel',
			'priority'     => 28,
			'alarm'        => 'green',
			'requires'     => 'mactrack',
			'update_func'  => 'mactrack',
			'details_func' => false,
			'trends_func'  => false
		),
		'mactrack_sites' => array(
			'name'         => __('MacTrack Sites', 'intropage'),
			'description'  => __('Various MacTrack Site statistics.', 'intropage'),
			'class'        => 'mactrack',
			'level'        => PANEL_USER,
			'refresh'      => 900,
			'trefresh'     => false,
			'force'        => true,
			'width'        => 'quarter-panel',
			'priority'     => 27,
			'alarm'        => 'grey',
			'requires'     => 'mactrack',
			'update_func'  => 'mactrack_sites',
			'details_func' => 'mactrack_sites_detail',
			'trends_func'  => false
		),
	);

	return $panels;
}

//------------------------------------ mactrack -----------------------------------------------------
function mactrack($panel, $user_id) {
	global $config;

	$panel['alarm'] = 'green';

	if (!api_plugin_is_enabled('mactrack')) {
		$panel['alarm'] = 'grey';
		$panel['data']  = __('MacTrack Plugin not Installed/Running', 'intropage');
	} elseif (api_plugin_user_realm_auth('mactrack_view_sites.php') || api_plugin_user_realm_auth('mactrack_devices.php')) {
		// mactrack is running and you have permission
		$m_all  = db_fetch_cell('SELECT COUNT(host_id) FROM mac_track_devices');
		$m_up   = db_fetch_cell("SELECT COUNT(host_id) FROM mac_track_devices WHERE snmp_status='3'");
		$m_down = db_fetch_cell("SELECT COUNT(host_id) FROM mac_track_devices WHERE snmp_status='1'");
		$m_disa = db_fetch_cell("SELECT COUNT(host_id) FROM mac_track_devices WHERE snmp_status='-2'");
		$m_err  = db_fetch_cell("SELECT COUNT(host_id) FROM mac_track_devices WHERE snmp_status='4'");
		$m_unkn = db_fetch_cell("SELECT COUNT(host_id) FROM mac_track_devices WHERE snmp_status='0'");

		if ($m_down > 0 || $m_err > 0 || $m_unkn > 0) {
			$panel['alarm'] = 'red';
		} elseif ($m_disa > 0) {
			$panel['alarm'] = 'yellow';
		}

		$panel['data']  = __('All: %s', $m_all, 'intropage')       . ' | ';
		$panel['data'] .= __('Up: %s', $m_up, 'intropage')         . ' | ';
		$panel['data'] .= __('Down: %s', $m_down, 'intropage')     . ' | ';
		$panel['data'] .= __('Error: %s', $m_err, 'intropage')     . ' | ';
		$panel['data'] .= __('Unknown: %s', $m_unkn, 'intropage')  . ' | ';
		$panel['data'] .= __('Disabled: %s', $m_disa, 'intropage') . ' | ';

		$graph = array ('pie' => array(
			'title' => __('MacTrack', 'intropage'),
			'label' => array(
				__('Up', 'intropage'),
				__('Down', 'intropage'),
				__('Error', 'intropage'),
				__('Unknown', 'intropage'),
				__('Disabled', 'intropage'),
			),
			'data' => array($m_up, $m_down, $m_err, $m_unkn, $m_disa))
		);

		$panel['data'] = intropage_prepare_graph($graph, $user_id);
	} else {
		$panel['data'] =  __('You don\'t have plugin permission', 'intropage');
	}

	save_panel_result($panel, $user_id);
}

//------------------------------------ mactrack sites -----------------------------------------------------
function mactrack_sites($panel, $user_id) {
	global $config;

	$lines = read_user_setting('intropage_number_of_lines', read_config_option('intropage_number_of_lines'), false, $user_id);

	$panel['alarm'] = 'green';

	if (!api_plugin_is_enabled('mactrack')) {
		$panel['alarm'] = 'grey';
		$panel['data']  = __('MacTrack Plugin not Installed/Running', 'intropage');
	} elseif (api_plugin_user_realm_auth('mactrack_view_sites.php') || api_plugin_user_realm_auth('mactrack_devices.php')) {
		$panel['data'] = '<table class="cactiTable">' .
			'<tr class="tableHeader">' .
				'<td class="left">'  . __('Site', 'intropage')          . '</td>' .
				'<td class="right">' . __('Devices', 'intropage')       . '</td>' .
				'<td class="right">' . __('IPs', 'intropage')           . '</td>' .
				'<td class="right">' . __('Ports', 'intropage')         . '</td>' .
				'<td class="right">' . __('Ports up', 'intropage')      . '</td>' .
				'<td class="right">' . __('MACs', 'intropage')          . '</td>' .
				'<td class="right">' . __('Device Errors', 'intropage') . '</td>' .
			'</tr>';

		$data = db_fetch_assoc('SELECT site_name, total_devices, total_device_errors,
			total_macs, total_ips, total_oper_ports, total_user_ports
			FROM mac_track_sites
			ORDER BY total_devices DESC
			LIMIT ' . $lines);

		if (sizeof($data) > 0) {
			foreach ($data as $site) {
				$row = '<tr>' .
					'<td class="left">'  . html_escape($site['site_name']) . '</td>' .
					'<td class="right">' . $site['total_devices']          . '</td>' .
					'<td class="right">' . $site['total_ips']              . '</td>' .
					'<td class="right">' . $site['total_user_ports']       . '</td>' .
					'<td class="right">' . $site['total_oper_ports']       . '</td>' .
					'<td class="right">' . $site['total_macs']             . '</td>' .
					'<td class="right">' . $site['total_device_errors']    . '<span class="inpa_sq color_red"></span></td>' .
				'</tr>';

				$panel['data'] .= $row;
			}

			$panel['data'] .= '</table>';
		} else {
			$panel['data'] = __('No mactrack sites found', 'intropage');
		}
	} else {
		$panel['data'] =  __('You don\'t have plugin permission', 'intropage');
	}

	save_panel_result($panel, $user_id);
}

//------------------------------------ mactrack sites -----------------------------------------------------
function mactrack_sites_detail() {
	global $config, $console_access;

	$panel = array(
		'name'   => __('MacTrack Sites', 'intropage'),
		'alarm'  => 'green',
		'detail' => '',
	);

	$panel['detail'] = '<table class="cactiTable">' .
		'<tr class="tableHeader">' .
		'<th class="left">'  . __('Site', 'intropage')          . '</td>' .
		'<th class="right">' . __('Devices', 'intropage')       . '</td>' .
		'<th class="right">' . __('IPs', 'intropage')           . '</td>' .
		'<th class="right">' . __('Ports', 'intropage')         . '</td>' .
		'<th class="right">' . __('Ports up', 'intropage')      . '</td>' .
		'<th class="right">' . __('MACs', 'intropage')          . '</td>' .
		'<th class="right">' . __('Device Errors', 'intropage') . '</td>' .
	'</tr>';

	$data = db_fetch_assoc('SELECT *
		FROM mac_track_sites
		ORDER BY total_devices DESC');

	if (sizeof($data) > 0) {
		foreach ($data as $site) {
			$row = '<tr>' .
				'<td class="left">'  . html_escape($site['site_name']) . '</td>' .
				'<td class="right">' . $site['total_devices']          . '</td>' .
				'<td class="right">' . $site['total_ips']              . '</td>' .
				'<td class="right">' . $site['total_user_ports']       . '</td>' .
				'<td class="right">' . $site['total_oper_ports']       . '</td>' .
				'<td class="right">' . $site['total_macs']             . '</td>' .
				'<td class="right">' . $site['total_device_errors']    . '<span class="inpa_sq color_red"></span></td>' .
			'</tr>';

			if ($site['total_device_errors'] > 0) {
				$panel['alarm'] = 'red';
			}

			$panel['detail'] .= $row;
		}

		$panel['detail'] .= '</table>';
	} else {
	    $panel['detail'] = __('No mactrack sites found', 'intropage');
	}

	return $panel;
}
