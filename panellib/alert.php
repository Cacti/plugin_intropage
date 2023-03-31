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

function register_alert() {
	global $registry;

	$registry['alert'] = array(
		'name'        => __('Alerts', 'intropage'),
		'description' => __('Panels that provide alerting.', 'intropage')
	);

	$panels = array(
		'alert_host' => array(
			'name'         => __('Host alerts', 'intropage'),
			'description'  => __('Host alerts (up/down/recovering) in last 30 minutes', 'intropage'),
			'class'        => 'alert',
			'level'        => PANEL_USER,
			'refresh'      => 300,
			'trefresh'     => false,
			'force'        => true,
			'width'        => 'quarter-panel',
			'priority'     => 90,
			'alarm'        => 'green',
			'requires'     => false,
			'update_func'  => 'alert_host',
			'details_func' => 'alert_host_detail',
			'trends_func'  => false
		),
	);

	return $panels;
}

//------------------------------------ alert host -----------------------------------------------------
function alert_host($panel, $user_id) {
	global $config;

	$lines = read_user_setting('intropage_number_of_lines', read_config_option('intropage_number_of_lines'), false, $user_id);
        $important_period = read_user_setting('intropage_important_period', read_config_option('intropage_important_period'), false, $user_id);
        if ($important_period == -1) {
                $important_period = time();
        }

	$panel['alarm'] = 'green';

	$allowed_devices = intropage_get_allowed_devices($user_id);

	if ($allowed_devices != '') {
		$console_access = get_console_access($user_id);

		$sql_host_reco = db_fetch_assoc("SELECT id, description, status_rec_date as chdate, UNIX_TIMESTAMP(status_rec_date) AS secs, 'Recovering' AS state
			FROM host
			WHERE host.id in (" . $allowed_devices . ")
			AND status_event_count > 0 AND status = 2 AND status_rec_date > DATE_SUB(now(), INTERVAL 10 DAY)
			AND disabled != 'on'
			ORDER BY status_rec_date DESC
			LIMIT " . $lines);

		$sql_host_up = db_fetch_assoc("SELECT id, description, status_rec_date as chdate, UNIX_TIMESTAMP(status_rec_date) AS secs, 'Up' AS state
			FROM host
			WHERE host.id in (" . $allowed_devices . ")
			AND status_event_count = 0 AND status = 3 AND status_rec_date > DATE_SUB(now(), INTERVAL 10 DAY)
			AND disabled != 'on'
			ORDER BY status_rec_date DESC
			LIMIT " . $lines);

		$sql_host_fall = db_fetch_assoc("SELECT id, description, status_fail_date as chdate, UNIX_TIMESTAMP(status_fail_date) AS secs, 'Falling' AS state
			FROM host
			WHERE host.id in (" . $allowed_devices . ")
			AND status = 3 AND status_fail_date > DATE_SUB(now(), INTERVAL " . $important_period . " SECOND)
			AND disabled != 'on'
			ORDER BY status_fail_date DESC
			LIMIT " . $lines);

		$sql_host_down = db_fetch_assoc("SELECT id, description, status_fail_date as chdate, UNIX_TIMESTAMP(status_fail_date) AS secs, 'Down' AS state
			FROM host
			WHERE host.id in (" . $allowed_devices . ")
			AND status = 1 AND status_fail_date > DATE_SUB(now(), INTERVAL " . $important_period . " SECOND)
			AND disabled != 'on'
			ORDER BY status_fail_date DESC
			LIMIT " . $lines);


		$result = $sql_host_reco + $sql_host_up + $sql_host_fall + $sql_host_down;
		
		$panel['data'] = '<table class="cactiTable">' .
			'<tr class="tableHeader">' .
				'<th>'  . __('Date', 'intropage')    . '</th>' .
				'<th>' . __('Host', 'intropage') . '</th>' .
				'<th>' . __('State', 'intropage') . '</th>' .
			'</tr>';

		$i = 0;

		if (cacti_sizeof($result)) {

			foreach ($result as $line) {
				if ($console_access) {
					$row = '<tr class="' . ($i % 2 == 0 ? 'even':'odd') . '">';
				} else {
					$row = '<tr class="' . ($i % 2 == 0 ? 'even':'odd') . '">';
				}

				$row .= '<td>' . $line['chdate'] . '</td><td>';

                                $color = 'grey';

                                if ($line['secs'] > (time()-($important_period))) {
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

				$row .= '<td>'  . $line['state'] . '</td></tr>';

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


//------------------------------------ alert host detail -----------------------------------------------------
function alert_host_detail() {
	global $config, $console_access;

        $important_period = read_user_setting('intropage_important_period', read_config_option('intropage_important_period'), false, $user_id);
        if ($important_period == -1) {
                $important_period = time();
        }

	$panel = array(
		'name'   => __('Host alerts', 'intropage'),
		'alarm'  => 'green',
		'detail' => '',
	);

	$lines = 20;

	$allowed_devices = intropage_get_allowed_devices($_SESSION['sess_user_id']);

	if ($allowed_devices != '') {

		$console_access = get_console_access($_SESSION['sess_user_id']);

		$sql_host_reco = db_fetch_assoc("SELECT id, description, status_rec_date as chdate, UNIX_TIMESTAMP(status_rec_date) AS secs, 'Recovering' AS state
			FROM host
			WHERE host.id in (" . $allowed_devices . ")
			AND status_event_count > 0 AND status = 2 AND status_rec_date > DATE_SUB(now(), INTERVAL " . $important_period . " SECOND)
			AND disabled != 'on'
			ORDER BY status_rec_date DESC
			LIMIT " . $lines);

		$sql_host_up = db_fetch_assoc("SELECT id, description, status_rec_date as chdate, UNIX_TIMESTAMP(status_rec_date) AS secs, 'Up' AS state
			FROM host
			WHERE host.id in (" . $allowed_devices . ")
			AND status_event_count = 0 AND status = 3 AND status_rec_date > DATE_SUB(now(), INTERVAL " . $important_period . " SECOND)
			AND disabled != 'on'
			ORDER BY status_rec_date DESC
			LIMIT " . $lines);

		$sql_host_fall = db_fetch_assoc("SELECT id, description, status_fail_date as chdate, UNIX_TIMESTAMP(status_fail_date) AS secs, 'Falling' AS state
			FROM host
			WHERE host.id in (" . $allowed_devices . ")
			AND status = 3 AND status_fail_date > DATE_SUB(now(), INTERVAL " . $important_period . " SECOND)
			AND disabled != 'on'
			ORDER BY status_fail_date DESC
			LIMIT " . $lines);

		$sql_host_down = db_fetch_assoc("SELECT id, description, status_fail_date as chdate, UNIX_TIMESTAMP(status_fail_date) AS secs, 'Down' AS state
			FROM host
			WHERE host.id in (" . $allowed_devices . ")
			AND status = 1 AND status_fail_date > DATE_SUB(now(), INTERVAL " . $important_period . " SECOND)
			AND disabled != 'on'
			ORDER BY status_fail_date DESC
			LIMIT " . $lines);

		$result = $sql_host_reco + $sql_host_up + $sql_host_fall + $sql_host_down;
		
		if (cacti_sizeof($result)) {

			$panel['detail'] = '<table class="cactiTable">' .
				'<tr class="tableHeader">' .
					'<th>'  . __('Date', 'intropage')    . '</th>' .
					'<th>' . __('Host', 'intropage') . '</th>' .
					'<th>' . __('State', 'intropage') . '</th>' .
				'</tr>';

			$i = 0;
			foreach ($result as $line) {
				if ($console_access) {
					$row = '<tr class="' . ($i % 2 == 0 ? 'even':'odd') . '">';
				} else {
					$row = '<tr class="' . ($i % 2 == 0 ? 'even':'odd') . '">';
				}

				$row .= '<td>' . $line['chdate'] . '</td><td>';

                                $color = 'grey';

                                if ($line['secs'] > (time()-($important_period))) {
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

				$row .= '<td>'  . $line['state'] . '</td></tr>';

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

