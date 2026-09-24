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
 * Registers the 'alert' panel category and its 'Host alerts' panel
 * (recent host up/down/recovering events) with the panel library.
 * Called from initialize_panel_library() while building the full set
 * of available dashboard panels.
 *
 * @return array The panel definitions provided by this file, keyed by
 *              panel id.
 *
 * @global array $registry Populated here with this file's 'alert'
 *                         category metadata.
 */
function register_alert() {
	global $registry;

	$registry['alert'] = [
		'name'        => __('Alerts', 'intropage'),
		'description' => __('Panels that provide alerting.', 'intropage')
	];

	$panels = [
		'alert_host' => [
			'name'         => __('Host alerts', 'intropage'),
			'description'  => __('Host alerts (up/down/recovering) recently', 'intropage'),
			'class'        => 'alert',
			'level'        => PANEL_USER,
			'refresh'      => 300,
			'trefresh'     => false,
			'force'        => true,
			'width'        => 'quarter-panel',
			'height'       => 'normal',
			'height_fixed' => false,
			'priority'     => 90,
			'alarm'        => 'green',
			'requires'     => false,
			'update_func'  => 'alert_host',
			'details_func' => 'alert_host_detail',
			'trends_func'  => false
		],
	];

	return $panels;
}

// ------------------------------------ alert host -----------------------------------------------------
/**
 * Data-update function for the 'alert_host' panel: gathers recently
 * recovering, up, and down/failing hosts (within the user's device
 * scope) and builds the panel's display rows, coloring the panel's
 * alarm state based on the presence of down hosts within the
 * configured 'important period'. Called from
 * intropage_gather_stats()/get_panel() via the panel definition's
 * 'update_func'.
 *
 * @param array $panel   The panel's current definition/data row.
 * @param int   $user_id The id of the user the panel is being rendered
 *                       for, used to resolve device scope and
 *                       settings.
 *
 * @return void
 *
 * @global array $config Reserved/declared for parity with other panel
 *                       functions in this file; not used directly
 *                       here.
 */
function alert_host($panel, $user_id) {
	global $config;

	$lines = get_panel_lines_count($panel['height'], $user_id);

	$important_period = read_user_setting('intropage_important_period', read_config_option('intropage_important_period'), false, $user_id);

	if ($important_period == -1) {
		$important_period = time();
	}

	$panel['alarm'] = 'green';

	$scope           = intropage_device_scope($user_id);
	$simple_perms    = $scope['simple'];
	$allowed_devices = $scope['allowed'];
	$host_cond       = $simple_perms ? '' : 'AND host.id IN (' . $allowed_devices . ')';

	if ($allowed_devices !== false || $simple_perms) {
		$console_access = get_console_access($user_id);

		$sql_host_reco = db_fetch_assoc("SELECT id, description, status_rec_date as chdate, UNIX_TIMESTAMP(status_rec_date) AS secs, 'Recovering' AS state
			FROM host
			WHERE disabled != 'on'
			$host_cond
			AND status_event_count > 0 AND status = 2 AND status_rec_date > DATE_SUB(now(), INTERVAL 10 DAY)
			ORDER BY status_rec_date DESC
			LIMIT " . $lines);

		$sql_host_up = db_fetch_assoc("SELECT id, description, status_rec_date as chdate, UNIX_TIMESTAMP(status_rec_date) AS secs, 'Up' AS state
			FROM host
			WHERE disabled != 'on'
			$host_cond
			AND status_event_count = 0 AND status = 3 AND status_rec_date > DATE_SUB(now(), INTERVAL 10 DAY)
			AND disabled != 'on'
			ORDER BY status_rec_date DESC
			LIMIT " . $lines);

		$sql_host_fall = db_fetch_assoc("SELECT id, description, status_fail_date as chdate, UNIX_TIMESTAMP(status_fail_date) AS secs, 'Falling' AS state
			FROM host
			WHERE disabled != 'on'
			$host_cond
			AND status = 3 AND status_fail_date > DATE_SUB(now(), INTERVAL " . $important_period . ' SECOND)
			ORDER BY status_fail_date DESC
			LIMIT ' . $lines);

		$sql_host_down = db_fetch_assoc("SELECT id, description, status_fail_date as chdate, UNIX_TIMESTAMP(status_fail_date) AS secs, 'Down' AS state
			FROM host
			WHERE disabled != 'on'
			$host_cond
			AND status = 1 AND status_fail_date > DATE_SUB(now(), INTERVAL " . $important_period . ' SECOND)
			ORDER BY status_fail_date DESC
			LIMIT ' . $lines);

		$result = array_merge($sql_host_reco, $sql_host_up, $sql_host_fall, $sql_host_down);

		$panel['data'] = '<table class="cactiTable">' .
			'<tr class="tableHeader">' .
				'<th>' . __('Date', 'intropage') . '</th>' .
				'<th>' . __('Host', 'intropage') . '</th>' .
				'<th>' . __('State', 'intropage') . '</th>' .
			'</tr>';

		$i = 0;

		if (cacti_sizeof($result)) {
			foreach ($result as $line) {
				if ($console_access) {
					$row = '<tr class="' . ($i % 2 == 0 ? 'even' : 'odd') . '">';
				} else {
					$row = '<tr class="' . ($i % 2 == 0 ? 'even' : 'odd') . '">';
				}

				$row .= '<td>' . $line['chdate'] . '</td><td>';

				$color = 'grey';

				if ($line['secs'] > (time() - ($important_period))) {
					if (preg_match('/(UP)/i', $line['state'])) {
						$color = 'green';
					} elseif (preg_match('/(DOWN)/i', $line['state'])) {
						$color = 'red';
					} elseif (preg_match('/(RECOVERING|FALLING)/i', $line['state'])) {
						$color = 'yellow';
					}
				}

				if ($panel['alarm'] == 'grey' && $color == 'green') {
					$panel['alarm'] = 'green';
				}

				if ($panel['alarm'] == 'green' && $color == 'yellow') {
					$panel['alarm'] = 'yellow';
				}

				if ($panel['alarm'] == 'yellow' && $color == 'red') {
					$panel['alarm'] = 'red';
				}

				$row .= '<span class="inpa_sq color_' . $color . '"></span>';

				if ($console_access) {
					$row .= '<a class="linkEditMain" href="' . html_escape($config['url_path'] . 'host.php?action=edit&id=' . $line['id']) . '">' . html_escape(substr($line['description'],0,37)) . '</a></td>';
				} else {
					$row .= html_escape(substr($line['description'],0,37)) . '</td>';
				}

				$row .= '<td>' . $line['state'] . '</td></tr>';

				$panel['data'] .= $row;

				$i++;

				if ($i > $lines) {
					$panel['data'] .= '</table>';
					$panel['data'] .= '<br/>' . __('More records, use detail window', 'intropage');

					break;
				}
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

// ------------------------------------ alert host detail -----------------------------------------------------
/**
 * Detail-view renderer for the 'alert_host' panel, showing an expanded
 * view of recent host alert events. Called via the panel definition's
 * 'details_func' when the user opens the panel's detail view.
 *
 * @return void
 */
function alert_host_detail() {
	global $config, $console_access;

	$important_period = read_user_setting('intropage_important_period', read_config_option('intropage_important_period'), false, $_SESSION['sess_user_id']);

	if ($important_period == -1) {
		$important_period = time();
	}

	$panel = [
		'name'   => __('Host alerts', 'intropage'),
		'alarm'  => 'green',
		'detail' => '',
	];

	$lines = 20;

	$scope           = intropage_device_scope($_SESSION['sess_user_id']);
	$simple_perms    = $scope['simple'];
	$allowed_devices = $scope['allowed'];
	$host_cond       = $simple_perms ? '' : 'AND host.id IN (' . $allowed_devices . ')';

	if ($allowed_devices !== false || $simple_perms) {
		$console_access = get_console_access($_SESSION['sess_user_id']);

		$sql_host_reco = db_fetch_assoc("SELECT id, description, status_rec_date as chdate, UNIX_TIMESTAMP(status_rec_date) AS secs, 'Recovering' AS state
			FROM host
			WHERE disabled != 'on'
			$host_cond
			AND status_event_count > 0 AND status = 2 AND status_rec_date > DATE_SUB(now(), INTERVAL " . $important_period . ' SECOND)
			ORDER BY status_rec_date DESC
			LIMIT ' . $lines);

		$sql_host_up = db_fetch_assoc("SELECT id, description, status_rec_date as chdate, UNIX_TIMESTAMP(status_rec_date) AS secs, 'Up' AS state
			FROM host
			WHERE disabled != 'on'
			$host_cond
			AND status_event_count = 0 AND status = 3 AND status_rec_date > DATE_SUB(now(), INTERVAL " . $important_period . ' SECOND)
			ORDER BY status_rec_date DESC
			LIMIT ' . $lines);

		$sql_host_fall = db_fetch_assoc("SELECT id, description, status_fail_date as chdate, UNIX_TIMESTAMP(status_fail_date) AS secs, 'Falling' AS state
			FROM host
			WHERE disabled != 'on'
			$host_cond
			AND status = 3 AND status_fail_date > DATE_SUB(now(), INTERVAL " . $important_period . ' SECOND)
			ORDER BY status_fail_date DESC
			LIMIT ' . $lines);

		$sql_host_down = db_fetch_assoc("SELECT id, description, status_fail_date as chdate, UNIX_TIMESTAMP(status_fail_date) AS secs, 'Down' AS state
			FROM host
			WHERE disabled != 'on'
			$host_cond
			AND status = 1 AND status_fail_date > DATE_SUB(now(), INTERVAL " . $important_period . ' SECOND)
			ORDER BY status_fail_date DESC
			LIMIT ' . $lines);

		$result = array_merge($sql_host_reco, $sql_host_up, $sql_host_fall, $sql_host_down);

		if (cacti_sizeof($result)) {
			$panel['detail'] = '<table class="cactiTable">' .
				'<tr class="tableHeader">' .
					'<th>' . __('Date', 'intropage') . '</th>' .
					'<th>' . __('Host', 'intropage') . '</th>' .
					'<th>' . __('State', 'intropage') . '</th>' .
				'</tr>';

			$i = 0;

			foreach ($result as $line) {
				if ($console_access) {
					$row = '<tr class="' . ($i % 2 == 0 ? 'even' : 'odd') . '">';
				} else {
					$row = '<tr class="' . ($i % 2 == 0 ? 'even' : 'odd') . '">';
				}

				$row .= '<td>' . $line['chdate'] . '</td><td>';

				$color = 'grey';

				if ($line['secs'] > (time() - ($important_period))) {
					if (preg_match('/(UP)/i', $line['state'])) {
						$color = 'green';
					} elseif (preg_match('/(DOWN)/i', $line['state'])) {
						$color = 'red';
					} elseif (preg_match('/(RECOVERING|FALLING)/i', $line['state'])) {
						$color = 'yellow';
					}
				}

				if ($panel['alarm'] == 'grey' && $color == 'green') {
					$panel['alarm'] = 'green';
				}

				if ($panel['alarm'] == 'green' && $color == 'yellow') {
					$panel['alarm'] = 'yellow';
				}

				if ($panel['alarm'] == 'yellow' && $color == 'red') {
					$panel['alarm'] = 'red';
				}

				$row .= '<span class="inpa_sq color_' . $color . '"></span>';

				if ($console_access) {
					$row .= '<a class="linkEditMain" href="' . html_escape($config['url_path'] . 'host.php?action=edit&id=' . $line['id']) . '">' . html_escape(substr($line['description'],0,37)) . '</a></td>';
				} else {
					$row .= html_escape(substr($line['description'],0,37)) . '</td>';
				}

				$row .= '<td>' . $line['state'] . '</td></tr>';

				$panel['detail'] .= $row;

				$i++;

				if ($i > $lines) {
					$panel['detail'] .= '</table>';
					$panel['detail'] .= '<br/>' . __('More records, use detail window', 'intropage');

					break;
				}
			}

			$panel['detail'] .= '</table>';
		} else {
			$panel['detail'] = __('Waiting for data', 'intropage');
		}
	} else {
		$panel['detail'] = __('You don\'t have permissions to any hosts', 'intropage');
	}

	return $panel;
}
