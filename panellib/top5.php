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

function register_top5() {
	global $registry;

	$registry['top5'] = array(
		'name'        => __('Top 5 Panels', 'intropage'),
		'description' => __('Panels that provide information trending information about Cacti data collection.', 'intropage')
	);

	$panels = array(
		'top5_ping' => array(
			'name'         => __('Top 5 Ping', 'intropage'),
			'description'  => __('Devices with the worst ping response', 'intropage'),
			'class'        => 'top5',
			'level'        => PANEL_USER,
			'refresh'      => 300,
			'force'        => true,
			'width'        => 'quarter-panel',
			'priority'     => 60,
			'alarm'        => 'green',
			'requires'     => false,
			'update_func'  => 'top5_ping',
			'details_func' => 'top5_ping_detail',
			'trends_func'  => false
		),
		'top5_availability' => array(
			'name'         => __('Top 5 Worst Availability', 'intropage'),
			'description'  => __('Devices with the worst availability/reachability', 'intropage'),
			'class'        => 'top5',
			'level'        => PANEL_USER,
			'refresh'      => 300,
			'force'        => true,
			'width'        => 'quarter-panel',
			'priority'     => 61,
			'alarm'        => 'green',
			'requires'     => false,
			'update_func'  => 'top5_availability',
			'details_func' => 'top5_availability_detail',
			'trends_func'  => false
		),
		'top5_polltime' => array(
			'name'         => __('Top 5 Worst Polling Time', 'intropage'),
			'description'  => __('Devices with the worst polling time', 'intropage'),
			'class'        => 'top5',
			'level'        => PANEL_USER,
			'refresh'      => 300,
			'force'        => true,
			'width'        => 'quarter-panel',
			'priority'     => 62,
			'alarm'        => 'green',
			'requires'     => false,
			'update_func'  => 'top5_polltime',
			'details_func' => 'top5_polltime_detail',
			'trends_func'  => false
		),
		'top5_pollratio' => array(
			'name'         => __('Top 5 Worst Polling Ratio', 'intropage'),
			'description'  => __('Devices with the worst polling ratio', 'intropage'),
			'class'        => 'top5',
			'level'        => PANEL_USER,
			'refresh'      => 300,
			'force'        => true,
			'width'        => 'quarter-panel',
			'priority'     => 63,
			'alarm'        => 'green',
			'requires'     => false,
			'update_func'  => 'top5_pollratio',
			'details_func' => 'top5_pollratio_detail',
			'trends_func'  => false
		),
	);

	return $panels;
}

//------------------------------------ top5_worst_ping -----------------------------------------------------
function top5_ping($panel, $user_id) {
	global $config;

	$panel['alarm'] = 'green';

	$allowed_devices = intropage_get_allowed_devices($user_id);

	if ($allowed_devices != '') {
		$console_access = get_console_access($user_id);

		$sql_worst_host = db_fetch_assoc("SELECT description, id, avg_time, cur_time
			FROM host
			WHERE host.id in (" . $allowed_devices . ")
			AND disabled != 'on'
			ORDER BY cur_time desc
			LIMIT 5");

		if (cacti_sizeof($sql_worst_host)) {
			$color = read_config_option('intropage_alert_worst_ping');
			list($red, $yellow) = explode ('/', $color);

			$panel['data'] = '<table class="cactiTable">' .
				'<tr class="tableHeader">' .
					'<th class="left">'  . __('Host', 'intropage')    . '</th>' .
					'<th class="right">' . __('Average', 'intropage') . '</th>' .
					'<th class="right">' . __('Current', 'intropage') . '</th>' .
				'</tr>';

			foreach ($sql_worst_host as $host) {
				if ($host['cur_time'] > $red) {
					$panel['alarm'] = 'red';
					$class = 'deviceAlertBg';
				} elseif ($host['cur_time'] > $yellow) {
					if ($panel['alarm'] == 'green') {
						$panel['alarm'] = 'yellow';
					}
					$class = 'deviceWarningBg';
				} else {
					$class = '';
				}

				if ($console_access) {
					$row = '<tr class="' . $class . '"><td class="left"><a class="linkEditMain" href="' . html_escape($config['url_path'] . 'host.php?action=edit&id=' . $host['id']) . '">' . html_escape($host['description']) . '</a></td>';
				} else {
					$row = '<tr class="' . $class . '"><td class="left">' . html_escape($host['description']) . '</td>';
				}

				$row .= '<td class="right">' . round($host['avg_time'], 2) . 'ms</td>';
				$row .= '<td class="right">' . round($host['cur_time'], 2) . ' ms</td></tr>';

				$panel['data'] .= $row;
			}

			$panel['data'] .= '</table>';
		} else {
			$panel['data'] = __('Waiting for data', 'intropage');
		}
	} else {
		$panel['data'] = __('You don\'t have permissions to any hosts', 'intropage');
	}

	save_panel_result($panel, $user_id);
}

//------------------------------------ top5_availability -----------------------------------------------------
function top5_availability($panel, $user_id) {
	global $config;

	$panel['alarm'] = 'green';

	$allowed_devices = intropage_get_allowed_devices($user_id);

	if ($allowed_devices != '') {
		$console_access = get_console_access($user_id);

		$sql_worst_host = db_fetch_assoc("SELECT description, id, availability
			FROM host
			WHERE host.id IN (" . $allowed_devices . ")
			AND disabled != 'on'
			ORDER BY availability
			LIMIT 5");

		if (cacti_sizeof($sql_worst_host)) {
			$color = read_config_option('intropage_alert_worst_availability');
			list($red, $yellow) = explode ('/', $color);

			$panel['data'] = '<table class="cactiTable">' .
				'<tr class="tableHeader">' .
					'<th class="left">'  . __('Hostname', 'intropage') . '</th>' .
					'<th class="right">' . __('Availability/Reachability', 'intropage') . '</th>' .
				'</tr>';

			foreach ($sql_worst_host as $host) {
				if ($host['availability'] < $red) {
					$panel['alarm'] = 'red';
					$class = 'deviceAlertBg';
				} elseif ($panel['alarm'] == 'green' && $host['availability'] < $yellow) {
					$panel['alarm'] = 'yellow';
					$class = 'deviceWarningBg';
				} else {
					$class = '';
				}

				if ($console_access) {
					$row = '<tr class="' . $class . '"><td class="left"><a class="linkEditMain" href="' . html_escape($config['url_path'] . 'host.php?action=edit&id=' . $host['id']) . '">' . html_escape(substr($host['description'], 0, 40)) . '</a></td>';
				} else {
					$row = '<tr class="' . $class . '"><td class="left">' . html_escape(substr($host['description'], 0, 40)) . '</td>';
				}

				$row .= '<td class="right">' . round($host['availability'],2) . ' %</td></tr>';

				$panel['data'] .= $row;
			}

			$panel['data'] .= '</table>';
		} else {
			$panel['data'] = __('Waiting for data', 'intropage');
		}
	} else {
		$panel['data'] = __('You don\'t have permissions to any hosts', 'intropage');
	}

	save_panel_result($panel, $user_id);
}

//------------------------------------ top5_worst_polltime -----------------------------------------------------
function top5_polltime($panel, $user_id) {
	global $config;

	$panel['alarm'] = 'green';

	$allowed_devices = intropage_get_allowed_devices($user_id);

	if ($allowed_devices != '') {
		$console_access = get_console_access($user_id);

		$sql_worst_host = db_fetch_assoc("SELECT id, description, polling_time
			FROM host
			WHERE host.id in (" . $allowed_devices . ")
			AND disabled != 'on'
			ORDER BY polling_time desc
			LIMIT 5");

		if (cacti_sizeof($sql_worst_host)) {
			$color = read_config_option('intropage_alert_worst_polling_time');
			list($red, $yellow) = explode ('/', $color);

			$panel['data'] = '<table class="cactiTable">' .
				'<tr class="tableHeader">' .
					'<th class="left">'  . __('Host', 'intropage')         . '</th>' .
					'<th class="right">' . __('Polling Time', 'intropage') . '</th>' .
				'</tr>';

			foreach ($sql_worst_host as $host) {
				if ($host['polling_time'] > $red) {
					$panel['alarm'] = 'red';
					$class = 'deviceAlertBg';
				} elseif ($panel['alarm'] == 'green' && $host['polling_time'] > $yellow) {
					$panel['alarm'] = 'yellow';
					$class = 'deviceWarningBg';
				} else {
					$class = '';
				}

				if ($console_access) {
					$row = '<tr class="' . $class . '"><td class="left"><a class="linkEditMain" href="' . html_escape($config['url_path'] . 'host.php?action=edit&id=' . $host['id']) . '">' . html_escape(substr($host['description'], 0, 40)) . '</a></td>';
				} else {
					$row = '<tr class="' . $class . '"><td class="left">' . html_escape(substr($host['description'], 0, 40)) . '</td>';
				}

				$row .= '<td class="right">' . __('%s Secs', round($host['polling_time'], 2), 'intropage') . '</td></tr>';

				$panel['data'] .= $row;
			}

			$panel['data'] .= '</table>';
		} else {
			$panel['data'] = __('Waiting for data', 'intropage');
		}
	} else {
		$panel['data'] = __('You don\'t have permissions to any hosts', 'intropage');
	}

	save_panel_result($panel, $user_id);
}

//------------------------------------ top5_worst_pollratio -----------------------------------------------------
function top5_pollratio($panel, $user_id) {
	global $config;

	$panel['alarm'] = 'green';

	$allowed_devices = intropage_get_allowed_devices($user_id);

	if ($allowed_devices != '') {
		$console_access = get_console_access($user_id);

		$sql_worst_host = db_fetch_assoc("SELECT id, description, failed_polls,
			total_polls, CAST(failed_polls/total_polls AS DOUBLE) AS ratio
			FROM host
			WHERE host.id in (" . $allowed_devices . ")
			AND disabled != 'on'
			ORDER BY ratio DESC
			LIMIT 5");

		if (cacti_sizeof($sql_worst_host)) {
			$color = read_config_option('intropage_alert_worst_polling_ratio');
			list($red, $yellow) = explode ('/', $color);

			$panel['data'] = '<table class="cactiTable">' .
				'<tr class="tableHeader">' .
					'<th class="left">'  . __('Host', 'intropage')   . '</th>' .
					'<th class="right">' . __('Failed', 'intropage') . '</th>' .
					'<th class="right">' . __('Total', 'intropage')  . '</th>' .
					'<th class="right">' . __('Ratio', 'intropage')  . '</th>' .
				'</tr>';

			foreach ($sql_worst_host as $host) {
				if ($host['ratio'] > $red) {
					$panel['alarm'] = 'red';
					$class = 'deviceAlertBg';
				} elseif ($panel['alarm'] == 'green' && $host['ratio'] > $yellow) {
					$panel['alarm'] = 'yellow';
					$class = 'deviceWarningBg';
				} else {
					$class = '';
				}

				if ($console_access) {
					$row = '<tr class="' . $class . '"><td class="left"><a class="linkEditMain" href="' . html_escape($config['url_path'] . 'host.php?action=edit&id=' . $host['id']) . '">' . html_escape(substr($host['description'], 0, 40)) . '</a></td>';
				} else {
					$row = '<tr class="' . $class . '"><td class="left">' . html_escape(substr($host['description'], 0, 40)) . '</td>';
				}

				$row .= '<td class="right">' . number_format_i18n($host['failed_polls'], 0) . '</td>';
				$row .= '<td class="right">' . number_format_i18n($host['total_polls'], 0)  . '</td>';
				$row .= '<td class="right">' . round($host['ratio'] * 100, 3)               . ' %</td></tr>';

				$panel['data'] .= $row;
			}

			$panel['data'] .= '</table>';
		} else {
			$panel['data'] = __('Waiting for data', 'intropage');
		}
	} else {
		$panel['data'] = __('You don\'t have permissions to any hosts', 'intropage');
	}

	save_panel_result($panel, $user_id);
}

//------------------------------------ top5_worst_ping -----------------------------------------------------
function top5_ping_detail() {
	global $config, $console_access;

	$panel = array(
		'name'   => __('Top 20 Hosts with Worst Ping', 'intropage'),
		'alarm'  => 'green',
		'detail' => '',
	);

	$allowed_devices = intropage_get_allowed_devices($user_id);

	if ($allowed_devices != '') {
		$sql_worst_host = db_fetch_assoc("SELECT description, id, avg_time, cur_time
			FROM host
			WHERE host.id in (" . $allowed_devices . ")
			AND disabled != 'on'
			ORDER BY cur_time desc
			LIMIT 20");
	} else {
		$sql_worst_host = array();
	}

	if (cacti_sizeof($sql_worst_host)) {
		$color = read_config_option('intropage_alert_worst_ping');
		list($red, $yellow) = explode ('/', $color);

		$panel['detail'] = '<table class="cactiTable">' .
			'<tr class="tableHeader">' .
				'<td class="left">'  . __('Host', 'intropage')    . '</td>' .
				'<td class="right">' . __('Average', 'intropage') . '</td>' .
				'<td class="right">' . __('Current', 'intropage') . '</td>' .
			'</tr>';

		foreach ($sql_worst_host as $host) {
			if ($host['cur_time'] > $red) {
				$panel['alarm'] = 'red';
			} elseif ($panel['alarm'] == 'green' && $host['cur_time'] > $yellow)     {
				$panel['alarm'] = 'yellow';
			}

			if ($console_access) {
				$row = '<tr><td class="left"><a class="linkEditMain" href="' . html_escape($config['url_path'] . 'host.php?action=edit&id=' . $host['id']) . '">' . html_escape($host['description']) . '</a></td>';
			} else {
				$row = '<tr><td class="rleft">' . html_escape($host['description']) . '</td>';
			}

			$row .= '<td class="right">' . round($host['avg_time'], 2) . ' ms</td>';

			if ($host['cur_time'] > 1000) {
				$panel['alarm'] = 'yellow';
				$row .= '<td class="right">' . round($host['cur_time'], 2) . ' ms</td></tr>';
			} else {
				$row .= '<td class="right">' . round($host['cur_time'], 2) . ' ms</td></tr>';
			}

			$panel['detail'] .= $row;
		}

		$panel['detail'] .= '</table>';
	} else {
		$panel['detail'] = __('Waiting for data', 'intropage');
	}

	return $panel;
}

//------------------------------------ top5_availability -----------------------------------------------------
function top5_availability_detail() {
	global $config, $console_access;

	$panel = array(
		'name'   => __('Top 20 Hosts with the Worst Availability', 'intropage'),
		'alarm'  => 'green',
		'detail' => '',
	);

	$sql_worst_host = db_fetch_assoc("SELECT description, id, availability
		FROM host
		WHERE disabled != 'on'
		ORDER BY availability
		LIMIT 20");

	if (cacti_sizeof($sql_worst_host)) {
		$color = read_config_option('intropage_alert_worst_availability');
		list($red, $yellow) = explode ('/', $color);

		$panel['detail'] = '<table class="cactiTable">' .
			'<tr class="tableHeader">' .
				'<th class="left">'  . __('Host', 'intropage')         . '</th>' .
				'<th class="right">' . __('Availability', 'intropage') . '</th>' .
			'</tr>';

		foreach ($sql_worst_host as $host) {
			if ($host['availability'] < $red) {
   				$panel['alarm'] = 'red';
			} elseif ($panel['alarm'] == 'green' && $host['availability'] < $yellow) {
				$panel['alarm'] = 'yellow';
			}

			if ($console_access) {
				$row = '<tr><td class="left"><a class="linkEditMain" href="' . html_escape($config['url_path'] . 'host.php?action=edit&id=' . $host['id']) . '">' . html_escape($host['description']) . '</a></td>';
			} else {
				$row = '<tr><td class="left">' . html_escape($host['description']) . '</td>';
			}

			if ($host['availability'] < $yellow) {
				$panel['alarm'] = 'yellow';
				$row .= '<td class="right">' . round($host['availability'], 2) . ' %</td></tr>';
			} else {
				$row .= '<td class="right">' . round($host['availability'], 2) . ' %</td></tr>';
			}

			$panel['detail'] .= $row;

		}

		$panel['detail'] .= '</table>';
	} else {
		$panel['detail'] = __('Waiting for data', 'intropage');
	}

	return $panel;
}

//------------------------------------ top5_polltime -----------------------------------------------------
function top5_polltime_detail() {
	global $config, $console_access;

	$panel = array(
		'name'   => __('Top 20 Hosts Worst Polling Time', 'intropage'),
		'alarm'  => 'green',
		'detail' => '',
	);

	$allowed_devices = intropage_get_allowed_devices($_SESSION['sess_user_id']);

	if ($allowed_devices != '') {
		$sql_worst_host = db_fetch_assoc("SELECT id, description, polling_time
			FROM host
			WHERE host.id in (" . $allowed_devices . ")
			AND disabled != 'on'
			ORDER BY polling_time DESC
			LIMIT 20");
	} else {
		$sql_worst_host = array();
	}

	if (cacti_sizeof($sql_worst_host)) {
		$color = read_config_option('intropage_alert_worst_polling_time');
		list($red,$yellow) = explode ('/',$color);

		$panel['detail'] = '<table class="cactiTable">' .
			'<tr class="tableHeader">' .
				'<th class="left">'  . __('Host', 'intropage')         . '</th>' .
				'<th class="right">' . __('Polling Time', 'intropage') . '</th>' .
			'</tr>';

		foreach ($sql_worst_host as $host) {
			if ($host['polling_time'] > $red) {
				$panel['alarm'] = 'red';
			} elseif ($panel['alarm'] == 'green' && $host['polling_time'] > $yellow) {
				$panel['alarm'] = 'yellow';
			}

			if ($console_access) {
				$row = '<tr><td class="left"><a class="linkEditMain" href="' . html_escape($config['url_path'] . 'host.php?action=edit&id=' . $host['id']) . '">' . html_escape($host['description']) . '</a></td>';
			} else {
				$row = '<tr><td class="left">' . html_escape($host['description']) . '</td>';
			}

			if ($host['polling_time'] > 30) {
				$panel['alarm'] = 'yellow';
				$row .= '<td class="right">' . __('%s Secs', round($host['polling_time'], 2), 'intropage') . '</td></tr>';
			} else {
				$row .= '<td class="right">' . __('%s Secs', round($host['polling_time'], 2), 'intropage') . '</td></tr>';
			}

			$panel['detail'] .= $row;
		}

		$panel['detail'] .= '</table>';
	} else {
		$panel['detail'] = __('Waiting for data', 'intropage');
	}

	return $panel;
}

//------------------------------------ top5_pollratio -----------------------------------------------------
function top5_pollratio_detail() {
	global $config, $console_access;

	$panel = array(
		'name'   => __('Top 20 Hosts with the Worst Polling Ratio', 'intropage'),
		'alarm'  => 'grey',
		'detail' => '',
	);

	$sql_worst_host = db_fetch_assoc("SELECT id, description, failed_polls,
		total_polls, CAST(failed_polls/total_polls AS DOUBLE) AS ratio
		FROM host
		WHERE disabled != 'on'
		ORDER BY ratio DESC
		LIMIT 20");

	if (cacti_sizeof($sql_worst_host)) {
		$color = read_config_option('intropage_alert_worst_polling_ratio');
		list($red,$yellow) = explode ('/',$color);

		$panel['detail'] = '<table class="cactiTable">' .
			'<tr class="tableHeader">' .
				'<th class="left">'  . __('Host', 'intropage')   . '</th>' .
				'<th class="right">' . __('Failed', 'intropage') . '</th>' .
				'<th class="right">' . __('Total', 'intropage')  . '</th>' .
				'<th class="right">' . __('Ratio', 'intropage')  . '</th>' .
			'</tr>';

		foreach ($sql_worst_host as $host) {
			if ($host['ratio'] > $red) {
				$panel['alarm'] = 'red';
			} elseif ($panel['alarm'] == 'green' && $host['ratio'] > $yellow)        {
				$panel['alarm'] = 'yellow';
			}

			if ($console_access) {
				$row = '<tr><td class="left"><a class="linkEditMain" href="' . html_escape($config['url_path'] . 'host.php?action=edit&id=' . $host['id']) . '">' . html_escape($host['description']) . '</a></td>';
			} else {
				$row = '<tr><td class="rpad">' . html_escape($host['description']) . '</td>';
			}

			$row .= '<td class="right">' . number_format_i18n($host['failed_polls'], 0) . '</td>';
			$row .= '<td class="right">' . number_format_i18n($host['total_polls'], 0)  . '</td>';
			$row .= '<td class="right">' . round($host['ratio']* 100, 3)                . ' %</td></tr>';

			$panel['detail'] .= $row;
		}

		$panel['detail'] . '</table>';
	} else {	// no data
		$panel['detail'] = __('Waiting for data', 'intropage');
	}

	return $panel;
}
