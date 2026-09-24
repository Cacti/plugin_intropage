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
 * Registers the 'top5' panel category and its 'Bottom Ping', 'Bottom
 * Availability', 'Bottom Polling Time', and 'Bottom Polling Ratio'
 * panels with the panel library. Called from
 * initialize_panel_library() while building the full set of available
 * dashboard panels.
 *
 * @return array The panel definitions provided by this file, keyed by
 *              panel id.
 *
 * @global array $registry Populated here with this file's 'top5'
 *                         category metadata.
 */
function register_top5() {
	global $registry;

	$registry['top5'] = [
		'name'        => __('Top/Bottom 5 Panels', 'intropage'),
		'description' => __('Panels that provide information trending information about Cacti data collection.', 'intropage')
	];

	$panels = [
		'top5_ping' => [
			'name'         => __('Bottom Ping', 'intropage'),
			'description'  => __('Devices with the worst ping response', 'intropage'),
			'class'        => 'top5',
			'level'        => PANEL_USER,
			'refresh'      => 300,
			'trefresh'     => false,
			'force'        => true,
			'width'        => 'quarter-panel',
			'height'       => 'normal',
			'height_fixed' => false,
			'priority'     => 60,
			'alarm'        => 'green',
			'requires'     => false,
			'update_func'  => 'top5_ping',
			'details_func' => 'top5_ping_detail',
			'trends_func'  => false
		],
		'top5_availability' => [
			'name'         => __('Bottom Availability', 'intropage'),
			'description'  => __('Devices with the worst availability/reachability', 'intropage'),
			'class'        => 'top5',
			'level'        => PANEL_USER,
			'refresh'      => 300,
			'trefresh'     => false,
			'force'        => true,
			'width'        => 'quarter-panel',
			'height'       => 'normal',
			'height_fixed' => false,
			'priority'     => 61,
			'alarm'        => 'green',
			'requires'     => false,
			'update_func'  => 'top5_availability',
			'details_func' => 'top5_availability_detail',
			'trends_func'  => false
		],
		'top5_polltime' => [
			'name'         => __('Bottom Polling Time', 'intropage'),
			'description'  => __('Devices with the worst polling time', 'intropage'),
			'class'        => 'top5',
			'level'        => PANEL_USER,
			'refresh'      => 300,
			'trefresh'     => false,
			'force'        => true,
			'width'        => 'quarter-panel',
			'height'       => 'double',
			'height_fixed' => false,
			'priority'     => 62,
			'alarm'        => 'green',
			'requires'     => false,
			'update_func'  => 'top5_polltime',
			'details_func' => 'top5_polltime_detail',
			'trends_func'  => false
		],
		'top5_pollratio' => [
			'name'         => __('Bottom Polling Ratio', 'intropage'),
			'description'  => __('Devices with the worst polling ratio', 'intropage'),
			'class'        => 'top5',
			'level'        => PANEL_USER,
			'refresh'      => 300,
			'trefresh'     => false,
			'force'        => true,
			'width'        => 'quarter-panel',
			'height'       => 'normal',
			'height_fixed' => false,
			'priority'     => 63,
			'alarm'        => 'green',
			'requires'     => false,
			'update_func'  => 'top5_pollratio',
			'details_func' => 'top5_pollratio_detail',
			'trends_func'  => false
		],
	];

	return $panels;
}

// ------------------------------------ top5_worst_ping -----------------------------------------------------
/**
 * Data-update function for the 'top5_ping' panel: lists the devices
 * within the user's device scope with the worst (highest) ping
 * response times. Called from intropage_gather_stats()/get_panel() via
 * the panel definition's 'update_func'.
 *
 * @param array $panel   The panel's current definition/data row.
 * @param int   $user_id The id of the user the panel is being rendered
 *                       for, used to resolve device scope and save the
 *                       result.
 *
 * @return void
 */
function top5_ping($panel, $user_id) {
	global $config;

	$lines = get_panel_lines_count($panel['height'], $user_id);

	$panel['alarm'] = 'green';

	$simple_perms = get_simple_device_perms($user_id);

	if (!$simple_perms) {
		$allowed_devices = intropage_get_allowed_devices($user_id);
		$host_cond       = 'IN (' . $allowed_devices . ')';
	} else {
		$allowed_devices = false;
		$q_host_cond     = '';
	}

	if (!$simple_perms) {
		$q_host_cond = 'AND id ' . $host_cond;
	}

	if ($allowed_devices !== false || $simple_perms) {
		$console_access = get_console_access($user_id);

		$sql_worst_host = db_fetch_assoc("SELECT description, id, avg_time, cur_time
			FROM host
			WHERE disabled != 'on'
			$q_host_cond
			ORDER BY cur_time desc
			LIMIT " . $lines);

		if (cacti_sizeof($sql_worst_host)) {
			$color          = read_config_option('intropage_alert_worst_ping');
			[$red, $yellow] = explode('/', $color);

			$panel['data'] = '<table class="cactiTable">' .
				'<tr class="tableHeader">' .
					'<th class="left">' . __('Host', 'intropage') . '</th>' .
					'<th class="right">' . __('Average', 'intropage') . '</th>' .
					'<th class="right">' . __('Current', 'intropage') . '</th>' .
				'</tr>';

			$i = 0;

			foreach ($sql_worst_host as $host) {
				if ($host['cur_time'] > $red) {
					$panel['alarm'] = 'red';
					$color          = 'red';
				} elseif ($host['cur_time'] > $yellow) {
					if ($panel['alarm'] == 'green') {
						$panel['alarm'] = 'yellow';
					}
					$color = 'yellow';
				} else {
					$color = 'green';
				}

				if ($console_access) {
					$row = '<tr class="' . ($i % 2 == 0 ? 'even' : 'odd') . '"><td class="left"><a class="linkEditMain" href="' . html_escape($config['url_path'] . 'host.php?action=edit&id=' . $host['id']) . '">' . html_escape(substr($host['description'],0,37)) . '</a></td>';
				} else {
					$row = '<tr class="' . ($i % 2 == 0 ? 'even' : 'odd') . '"><td class="left">' . html_escape(substr($host['description'],0,37)) . '</td>';
				}

				$row .= "<td class='right'>" . round($host['avg_time'], 2) . ' ms</td>';
				$row .= "<td class='right'>" . round($host['cur_time'], 2) . " ms <span class='inpa_sq color_" . $color . "'></span></td></tr>";

				$panel['data'] .= $row;

				$i++;
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

// ------------------------------------ top5_availability -----------------------------------------------------
/**
 * Data-update function for the 'top5_availability' panel: lists the
 * devices within the user's device scope with the worst
 * availability/reachability. Called from
 * intropage_gather_stats()/get_panel() via the panel definition's
 * 'update_func'.
 *
 * @param array $panel   The panel's current definition/data row.
 * @param int   $user_id The id of the user the panel is being rendered
 *                       for, used to resolve device scope and save the
 *                       result.
 *
 * @return void
 */
function top5_availability($panel, $user_id) {
	global $config;

	$lines = get_panel_lines_count($panel['height'], $user_id);

	$panel['alarm'] = 'green';

	$simple_perms = get_simple_device_perms($user_id);

	if (!$simple_perms) {
		$allowed_devices = intropage_get_allowed_devices($user_id);
		$host_cond       = 'IN (' . $allowed_devices . ')';
	} else {
		$allowed_devices = false;
		$q_host_cond     = '';
	}

	if (!$simple_perms) {
		$q_host_cond = 'AND id ' . $host_cond;
	}

	if ($allowed_devices !== false || $simple_perms) {
		$console_access = get_console_access($user_id);

		$sql_worst_host = db_fetch_assoc("SELECT description, id, availability
			FROM host
			WHERE disabled != 'on'
			$q_host_cond
			ORDER BY availability
			LIMIT " . $lines);

		if (cacti_sizeof($sql_worst_host)) {
			$color          = read_config_option('intropage_alert_worst_availability');
			[$red, $yellow] = explode('/', $color);

			$panel['data'] = '<table class="cactiTable">' .
				'<tr class="tableHeader">' .
					'<th class="left">' . __('Hostname', 'intropage') . '</th>' .
					'<th class="right">' . __('Availability/Reachability', 'intropage') . '</th>' .
				'</tr>';

			$i = 0;

			foreach ($sql_worst_host as $host) {
				if ($host['availability'] < $red) {
					$panel['alarm'] = 'red';
					$color          = 'red';
				} elseif ($host['availability'] < $yellow) {
					if ($panel['alarm'] == 'green') {
						$panel['alarm'] = 'yellow';
					}
					$color = 'yellow';
				} else {
					$color = 'green';
				}

				if ($console_access) {
					$row = '<tr class="' . ($i % 2 == 0 ? 'even' : 'odd') . '"><td class="left"><a class="linkEditMain" href="' . html_escape($config['url_path'] . 'host.php?action=edit&id=' . $host['id']) . '">' . html_escape(substr($host['description'], 0, 37)) . '</a></td>';
				} else {
					$row = '<tr class="' . ($i % 2 == 0 ? 'even' : 'odd') . '"><td class="left">' . html_escape(substr($host['description'], 0, 37)) . '</td>';
				}

				$row .= "<td class='right'>" . round($host['availability'],2) . " % <span class='inpa_sq color_" . $color . "'></span></td></tr>";

				$panel['data'] .= $row;

				$i++;
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

// ------------------------------------ top5_worst_polltime -----------------------------------------------------
/**
 * Data-update function for the 'top5_polltime' panel: lists the devices
 * within the user's device scope with the worst (slowest) individual
 * polling time. Called from intropage_gather_stats()/get_panel() via
 * the panel definition's 'update_func'.
 *
 * @param array $panel   The panel's current definition/data row.
 * @param int   $user_id The id of the user the panel is being rendered
 *                       for, used to resolve device scope and save the
 *                       result.
 *
 * @return void
 */
function top5_polltime($panel, $user_id) {
	global $config;

	$lines = get_panel_lines_count($panel['height'], $user_id);

	$panel['alarm'] = 'green';

	$simple_perms = get_simple_device_perms($user_id);

	if (!$simple_perms) {
		$allowed_devices = intropage_get_allowed_devices($user_id);
		$host_cond       = 'IN (' . $allowed_devices . ')';
	} else {
		$allowed_devices = false;
		$q_host_cond     = '';
	}

	if (!$simple_perms) {
		$q_host_cond = 'AND id ' . $host_cond;
	}

	if ($allowed_devices !== false || $simple_perms) {
		$console_access = get_console_access($user_id);

		$sql_worst_host = db_fetch_assoc("SELECT id, description, polling_time
			FROM host
			WHERE disabled != 'on'
			$q_host_cond
			ORDER BY polling_time desc
			LIMIT " . $lines);

		if (cacti_sizeof($sql_worst_host)) {
			$color          = read_config_option('intropage_alert_worst_polling_time');
			[$red, $yellow] = explode('/', $color);

			$panel['data'] = '<table class="cactiTable">' .
				'<tr class="tableHeader">' .
					'<th class="left">' . __('Host', 'intropage') . '</th>' .
					'<th class="right">' . __('Polling Time', 'intropage') . '</th>' .
				'</tr>';

			$i = 0;

			foreach ($sql_worst_host as $host) {
				if ($host['polling_time'] > $red) {
					$panel['alarm'] = 'red';
					$color          = 'red';
				} elseif ($host['polling_time'] > $yellow) {
					if ($panel['alarm'] == 'green') {
						$panel['alarm'] = 'yellow';
					}
					$color = 'yellow';
				} else {
					$color = 'green';
				}

				if ($console_access) {
					$row = '<tr class="' . ($i % 2 == 0 ? 'even' : 'odd') . '"><td class="left"><a class="linkEditMain" href="' . html_escape($config['url_path'] . 'host.php?action=edit&id=' . $host['id']) . '">' . html_escape(substr($host['description'], 0, 37)) . '</a></td>';
				} else {
					$row = '<tr class="' . ($i % 2 == 0 ? 'even' : 'odd') . '"><td class="left">' . html_escape(substr($host['description'], 0, 37)) . '</td>';
				}

				$row .= "<td class='right'>" . __('%s Secs', round($host['polling_time'], 2), 'intropage') . "<span class='inpa_sq color_" . $color . "'></span></td></tr>";

				$panel['data'] .= $row;

				$i++;
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

// ------------------------------------ top5_worst_pollratio -----------------------------------------------------
/**
 * Data-update function for the 'top5_pollratio' panel: lists the
 * devices within the user's device scope with the worst polling ratio
 * (e.g. successful vs. expected polls). Called from
 * intropage_gather_stats()/get_panel() via the panel definition's
 * 'update_func'.
 *
 * @param array $panel   The panel's current definition/data row.
 * @param int   $user_id The id of the user the panel is being rendered
 *                       for, used to resolve device scope and save the
 *                       result.
 *
 * @return void
 */
function top5_pollratio($panel, $user_id) {
	global $config;

	$lines = get_panel_lines_count($panel['height'], $user_id);

	$panel['alarm'] = 'green';

	$simple_perms = get_simple_device_perms($user_id);

	if (!$simple_perms) {
		$allowed_devices = intropage_get_allowed_devices($user_id);
		$host_cond       = 'IN (' . $allowed_devices . ')';
	} else {
		$allowed_devices = false;
		$q_host_cond     = '';
	}

	if (!$simple_perms) {
		$q_host_cond = 'AND id ' . $host_cond;
	}

	if ($allowed_devices !== false || $simple_perms) {
		$console_access = get_console_access($user_id);

		$sql_worst_host = db_fetch_assoc("SELECT id, description, failed_polls,
			total_polls, CAST(failed_polls/total_polls AS DECIMAL(5,4)) AS ratio
			FROM host
			WHERE disabled != 'on'
			$q_host_cond
			ORDER BY ratio DESC
			LIMIT " . $lines);

		if (cacti_sizeof($sql_worst_host)) {
			$color          = read_config_option('intropage_alert_worst_polling_ratio');
			[$red, $yellow] = explode('/', $color);

			$panel['data'] = '<table class="cactiTable">' .
				'<tr class="tableHeader">' .
					'<th class="left">' . __('Host', 'intropage') . '</th>' .
					'<th class="right">' . __('Failed', 'intropage') . '</th>' .
					'<th class="right">' . __('Total', 'intropage') . '</th>' .
					'<th class="right">' . __('Ratio', 'intropage') . '</th>' .
				'</tr>';

			$i = 0;

			foreach ($sql_worst_host as $host) {
				if ($host['ratio'] > $red) {
					$panel['alarm'] = 'red';
					$color          = 'red';
				} elseif ($host['ratio'] > $yellow) {
					if ($panel['alarm'] == 'green') {
						$panel['alarm'] = 'yellow';
					}
					$color = 'yellow';
				} else {
					$color = 'green';
				}

				if ($console_access) {
					$row = '<tr class="' . ($i % 2 == 0 ? 'even' : 'odd') . '"><td class="left"><a class="linkEditMain" href="' . html_escape($config['url_path'] . 'host.php?action=edit&id=' . $host['id']) . '">' . html_escape(substr($host['description'], 0, 37)) . '</a></td>';
				} else {
					$row = '<tr class="' . ($i % 2 == 0 ? 'even' : 'odd') . '"><td class="left">' . html_escape(substr($host['description'], 0, 37)) . '</td>';
				}

				$row .= "<td class='right'>" . number_format_i18n($host['failed_polls'], 0) . '</td>';
				$row .= "<td class='right'>" . number_format_i18n($host['total_polls'], 0) . '</td>';
				$row .= "<td class='right'>" . round($host['ratio'] * 100, 3) . " % <span class='inpa_sq color_" . $color . "'></span></td></tr>";

				$panel['data'] .= $row;

				$i++;
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

// ------------------------------------ top5_worst_ping -----------------------------------------------------
/**
 * Detail-view renderer for the 'top5_ping' panel, showing an expanded
 * list of devices by worst ping response time. Called via the panel
 * definition's 'details_func' when the user opens the panel's detail
 * view.
 *
 * @return void
 */
function top5_ping_detail() {
	global $config, $console_access;

	$panel = [
		'name'   => __('Top 20 Hosts with Worst Ping', 'intropage'),
		'alarm'  => 'green',
		'detail' => '',
	];

	$simple_perms = get_simple_device_perms($_SESSION['sess_user_id']);

	if (!$simple_perms) {
		$allowed_devices = intropage_get_allowed_devices($_SESSION['sess_user_id']);
		$host_cond       = 'IN (' . $allowed_devices . ')';
	} else {
		$allowed_devices = false;
		$q_host_cond     = '';
	}

	if (!$simple_perms) {
		$q_host_cond = 'AND id ' . $host_cond;
	}

	if ($allowed_devices !== false || $simple_perms) {
		$sql_worst_host = db_fetch_assoc("SELECT description, id, avg_time, cur_time
			FROM host
			WHERE disabled != 'on'
			$q_host_cond
			ORDER BY cur_time desc
			LIMIT 40");
	} else {
		$sql_worst_host = [];
	}

	if (cacti_sizeof($sql_worst_host)) {
		$color          = read_config_option('intropage_alert_worst_ping');
		[$red, $yellow] = explode('/', $color);

		$panel['detail'] = '<table class="cactiTable">' .
			'<tr class="tableHeader">' .
				'<td class="left">' . __('Host', 'intropage') . '</td>' .
				'<td class="right">' . __('Average', 'intropage') . '</td>' .
				'<td class="right">' . __('Current', 'intropage') . '</td>' .
			'</tr>';

		$i = 0;

		foreach ($sql_worst_host as $host) {
			if ($host['cur_time'] > $red) {
				$panel['alarm'] = 'red';
				$color          = 'red';
			} elseif ($host['cur_time'] > $yellow) {
				if ($panel['alarm'] == 'green') {
					$panel['alarm'] = 'yellow';
				}
				$color = 'yellow';
			} else {
				$color = 'green';
			}

			if ($console_access) {
				$row = '<tr class="' . ($i % 2 == 0 ? 'odd' : 'even') . '"><td class="left"><a class="linkEditMain" href="' . html_escape($config['url_path'] . 'host.php?action=edit&id=' . $host['id']) . '">' . html_escape($host['description']) . '</a></td>';
			} else {
				$row = '<tr class="' . ($i % 2 == 0 ? 'odd' : 'even') . '"><td class="rleft">' . html_escape($host['description']) . '</td>';
			}

			$row .= '<td class="right">' . round($host['avg_time'], 2) . ' ms</td>';
			$row .= "<td class='right'>" . round($host['cur_time'], 2) . " ms <span class='inpa_sq color_" . $color . "'></span></td></tr>";

			$panel['detail'] .= $row;

			$i++;
		}

		$panel['detail'] .= '</table>';
	} else {
		$panel['detail'] = __('You don\'t have permissions to any hosts', 'intropage');
	}

	return $panel;
}

// ------------------------------------ top5_availability -----------------------------------------------------
/**
 * Detail-view renderer for the 'top5_availability' panel, showing an
 * expanded list of devices by worst availability. Called via the panel
 * definition's 'details_func' when the user opens the panel's detail
 * view.
 *
 * @return void
 */
function top5_availability_detail() {
	global $config, $console_access;

	$panel = [
		'name'   => __('Top 20 Hosts with the Worst Availability', 'intropage'),
		'alarm'  => 'green',
		'detail' => '',
	];

	$simple_perms = get_simple_device_perms($_SESSION['sess_user_id']);

	if (!$simple_perms) {
		$allowed_devices = intropage_get_allowed_devices($_SESSION['sess_user_id']);
		$host_cond       = 'IN (' . $allowed_devices . ')';
	} else {
		$allowed_devices = false;
		$q_host_cond     = '';
	}

	if (!$simple_perms) {
		$q_host_cond = 'AND id ' . $host_cond;
	}

	if ($allowed_devices !== false || $simple_perms) {
		$sql_worst_host = db_fetch_assoc("SELECT description, id, availability
			FROM host
			WHERE disabled != 'on'
			$q_host_cond
			ORDER BY availability
			LIMIT 40");
	} else {
		$sql_worst_host = [];
	}

	if (cacti_sizeof($sql_worst_host)) {
		$color          = read_config_option('intropage_alert_worst_availability');
		[$red, $yellow] = explode('/', $color);

		$panel['detail'] = '<table class="cactiTable">' .
			'<tr class="tableHeader">' .
				'<th class="left">' . __('Host', 'intropage') . '</th>' .
				'<th class="right">' . __('Availability', 'intropage') . '</th>' .
			'</tr>';

		$i = 0;

		foreach ($sql_worst_host as $host) {
			if ($host['availability'] < $red) {
				$panel['alarm'] = 'red';
				$color          = 'red';
			} elseif ($host['availability'] < $yellow) {
				if ($panel['alarm'] == 'green') {
					$panel['alarm'] = 'yellow';
				}
				$color = 'yellow';
			} else {
				$color = 'green';
			}

			if ($console_access) {
				$row = '<tr class="' . ($i % 2 == 0 ? 'odd' : 'even') . '"><td class="left"><a class="linkEditMain" href="' . html_escape($config['url_path'] . 'host.php?action=edit&id=' . $host['id']) . '">' . html_escape($host['description']) . '</a></td>';
			} else {
				$row = '<tr class="' . ($i % 2 == 0 ? 'odd' : 'even') . '"><td class="left">' . html_escape($host['description']) . '</td>';
			}

			$row .= "<td class='right'>" . round($host['availability'], 2) . " % <span class='inpa_sq color_" . $color . "'></span></td></tr>";

			$panel['detail'] .= $row;

			$i++;
		}

		$panel['detail'] .= '</table>';
	} else {
		$panel['detail'] = __('You don\'t have permissions to any hosts', 'intropage');
	}

	return $panel;
}

// ------------------------------------ top5_polltime -----------------------------------------------------
/**
 * Detail-view renderer for the 'top5_polltime' panel, showing an
 * expanded list of devices by worst polling time. Called via the panel
 * definition's 'details_func' when the user opens the panel's detail
 * view.
 *
 * @return void
 */
function top5_polltime_detail() {
	global $config, $console_access;

	$panel = [
		'name'   => __('Top 20 Hosts Worst Polling Time', 'intropage'),
		'alarm'  => 'green',
		'detail' => '',
	];

	$simple_perms = get_simple_device_perms($_SESSION['sess_user_id']);

	if (!$simple_perms) {
		$allowed_devices = intropage_get_allowed_devices($_SESSION['sess_user_id']);
		$host_cond       = 'IN (' . $allowed_devices . ')';
	} else {
		$allowed_devices = false;
		$q_host_cond     = '';
	}

	if (!$simple_perms) {
		$q_host_cond = 'AND id ' . $host_cond;
	}

	if ($allowed_devices !== false || $simple_perms) {
		$sql_worst_host = db_fetch_assoc("SELECT id, description, polling_time
			FROM host
			WHERE disabled != 'on'
			$q_host_cond
			ORDER BY polling_time DESC
			LIMIT 40");
	} else {
		$sql_worst_host = [];
	}

	if (cacti_sizeof($sql_worst_host)) {
		$color         = read_config_option('intropage_alert_worst_polling_time');
		[$red,$yellow] = explode('/',$color);

		$panel['detail'] = '<table class="cactiTable">' .
			'<tr class="tableHeader">' .
				'<th class="left">' . __('Host', 'intropage') . '</th>' .
				'<th class="right">' . __('Polling Time', 'intropage') . '</th>' .
			'</tr>';

		$i = 0;

		foreach ($sql_worst_host as $host) {
			if ($host['polling_time'] > $red) {
				$panel['alarm'] = 'red';
				$color          = 'red';
			} elseif ($host['polling_time'] > $yellow) {
				if ($panel['alarm'] == 'green') {
					$panel['alarm'] = 'yellow';
				}
				$color = 'yellow';
			} else {
				$color = 'green';
			}

			if ($console_access) {
				$row = '<tr class="' . ($i % 2 == 0 ? 'odd' : 'even') . '"><td class="left"><a class="linkEditMain" href="' . html_escape($config['url_path'] . 'host.php?action=edit&id=' . $host['id']) . '">' . html_escape($host['description']) . '</a></td>';
			} else {
				$row = '<tr class="' . ($i % 2 == 0 ? 'odd' : 'even') . '"><td class="left">' . html_escape($host['description']) . '</td>';
			}

			$row .= "<td class='right'>" . __('%s Secs', round($host['polling_time'], 2), 'intropage') . " <span class='inpa_sq color_" . $color . "'></span></td></tr>";

			$panel['detail'] .= $row;

			$i++;
		}

		$panel['detail'] .= '</table>';
	} else {
		$panel['detail'] = __('You don\'t have permissions to any hosts', 'intropage');
	}

	return $panel;
}

// ------------------------------------ top5_pollratio -----------------------------------------------------
/**
 * Detail-view renderer for the 'top5_pollratio' panel, showing an
 * expanded list of devices by worst polling ratio. Called via the
 * panel definition's 'details_func' when the user opens the panel's
 * detail view.
 *
 * @return void
 */
function top5_pollratio_detail() {
	global $config, $console_access;

	$panel = [
		'name'   => __('Top 20 Hosts with the Worst Polling Ratio', 'intropage'),
		'alarm'  => 'grey',
		'detail' => '',
	];

	$simple_perms = get_simple_device_perms($_SESSION['sess_user_id']);

	if (!$simple_perms) {
		$allowed_devices = intropage_get_allowed_devices($_SESSION['sess_user_id']);
		$host_cond       = 'IN (' . $allowed_devices . ')';
	} else {
		$allowed_devices = false;
		$q_host_cond     = '';
	}

	if (!$simple_perms) {
		$q_host_cond = 'AND id ' . $host_cond;
	}

	if ($allowed_devices !== false || $simple_perms) {
		$sql_worst_host = db_fetch_assoc("SELECT id, description, failed_polls,
			total_polls, CAST(failed_polls/total_polls AS DECIMAL(5,4)) AS ratio
			FROM host
			WHERE disabled != 'on'
			$q_host_cond
			ORDER BY ratio DESC
			LIMIT 40");
	} else {
		$sql_worst_host = [];
	}

	if (cacti_sizeof($sql_worst_host)) {
		$color         = read_config_option('intropage_alert_worst_polling_ratio');
		[$red,$yellow] = explode('/',$color);

		$panel['detail'] = '<table class="cactiTable">' .
			'<tr class="tableHeader">' .
				'<th class="left">' . __('Host', 'intropage') . '</th>' .
				'<th class="right">' . __('Failed', 'intropage') . '</th>' .
				'<th class="right">' . __('Total', 'intropage') . '</th>' .
				'<th class="right">' . __('Ratio', 'intropage') . '</th>' .
			'</tr>';

		$i = 0;

		foreach ($sql_worst_host as $host) {
			if ($host['ratio'] > $red) {
				$panel['alarm'] = 'red';
				$color          = 'red';
			} elseif ($host['ratio'] > $yellow) {
				if ($panel['alarm'] == 'green') {
					$panel['alarm'] = 'yellow';
				}
				$color = 'yellow';
			} else {
				$color = 'green';
			}

			if ($console_access) {
				$row = '<tr class="' . ($i % 2 == 0 ? 'odd' : 'even') . '"><td class="left"><a class="linkEditMain" href="' . html_escape($config['url_path'] . 'host.php?action=edit&id=' . $host['id']) . '">' . html_escape($host['description']) . '</a></td>';
			} else {
				$row = '<tr class="' . ($i % 2 == 0 ? 'odd' : 'even') . '"><td class="rpad">' . html_escape($host['description']) . '</td>';
			}

			$row .= "<td class='right'>" . number_format_i18n($host['failed_polls'], 0) . '</td>';
			$row .= "<td class='right'>" . number_format_i18n($host['total_polls'], 0) . '</td>';
			$row .= "<td class='right'>" . round($host['ratio'] * 100, 3) . " % <span class='inpa_sq color_" . $color . "'></span></td></tr>";

			$panel['detail'] .= $row;

			$i++;
		}

		$panel['detail'] .= '</table>';
	} else {	// no data
		$panel['detail'] = __('You don\'t have permissions to any hosts', 'intropage');
	}

	return $panel;
}
