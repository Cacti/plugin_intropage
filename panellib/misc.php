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

function register_misc() {
	global $registry;

	$registry['misc'] = array(
		'name'        => __('Miscelaneous Panels', 'intropage'),
		'description' => __('Panels that general non-categorized data about Cacti\'s.', 'intropage')
	);

	$panels = array(
		'ntp' => array(
			'name'         => __('NTP Status', 'intropage'),
			'description'  => __('Checking your Cacti system clock for drift from a known baseline.', 'intropage'),
			'class'        => 'misc',
			'level'        => PANEL_SYSTEM,
			'refresh'      => 7200,
			'force'        => true,
			'width'        => 'quarter-panel',
			'priority'     => 30,
			'alarm'        => 'green',
			'requires'     => false,
			'update_func'  => 'ntp',
			'details_func' => false,
			'trends_func'  => false
		),
		'maint' => array(
			'name'         => __('Maint Plugin Details', 'intropage'),
			'description'  => __('Maint Plugin details on upcoming schedules', 'intropage'),
			'class'        => 'misc',
			'level'        => PANEL_USER,
			'refresh'      => 300,
			'force'        => true,
			'width'        => 'quarter-panel',
			'priority'     => 98,
			'alarm'        => 'red',
			'requires'     => 'maint',
			'update_func'  => 'maint',
			'details_func' => false,
			'trends_func'  => false
		),
		'webseer' => array(
			'name'         => __('Webseer Details', 'intropage'),
			'description'  => __('Plugin webseer URL Service Check Details', 'intropage'),
			'class'        => 'misc',
			'level'        => PANEL_SYSTEM,
			'refresh'      => 60,
			'force'        => true,
			'width'        => 'quarter-panel',
			'priority'     => 36,
			'alarm'        => 'green',
			'requires'     => 'webseer',
			'update_func'  => 'webseer',
			'details_func' => 'webseer_detail',
			'trends_func'  => false
		),
	);

	return $panels;
}

// -------------------------------------ntp-------------------------------------------
function ntp($panel, $user_id) {
	global $config;

	$ntp_server = read_config_option('intropage_ntp_server');

	$panel['data'] = '<table class="cactiTable">';

	$panel['alarm'] = 'green';

	if (empty($ntp_server)) {
		$panel['alarm'] = 'grey';
		$panel['data']  .= '<tr><td>' . __('No NTP server configured', 'intropage') . '</td></tr>';
	} elseif (!filter_var(trim($ntp_server), FILTER_VALIDATE_IP) && !filter_var(trim($ntp_server), FILTER_VALIDATE_DOMAIN)) {
		$panel['alarm'] = 'red';
		$panel['data']  .= '<tr><td>' . __('Wrong NTP server configured - %s<br/>Please fix it in settings', $ntp_server, 'intropage') . '</td></tr>';
	} else {
		$timestamp = ntp_time($ntp_server);

		if ($timestamp != 'error') {
			$diff_time = date('U') - $timestamp;

			$panel['data'] .= '<tr><td><span class="txt_big">' . date('Y-m-d') . '<br/>' . date('H:i:s') . '</span><br/><br/></td></tr>';

			if ($diff_time > 1400000000) {
				$panel['alarm'] = 'red';
				$panel['data'] .= '<tr><td>' . __('Failed to get NTP time from %s', $ntp_server, 'intropage') . '</td></tr>';
			} elseif ($diff_time < -600 || $diff_time > 600) {
				$panel['alarm'] = 'red';
			} elseif ($diff_time < -120 || $diff_time > 120) {
				$panel['alarm'] = 'yellow';
			}

			if ($panel['alarm'] != 'green') {
				$panel['data'] .= '<tr><td>' . __('Please check time as it is off by more', 'intropage') . '</td></tr>';
				$panel['data'] .= '<tr><td>' . __('than %s seconds from NTP server %s.', $diff_time, $ntp_server, 'intropage') . '</td></tr>';
			} else {
				$panel['data'] .= '<tr><td>' . __('Localtime is equal to NTP server', 'intropage') . '</td></tr>';
				$panel['data'] .= '<tr><td>' . $ntp_server . '</td></tr>';
			}
		} else {
			$panel['alarm'] = 'red';
			$panel['data']  .= '<tr><td>' . __('Unable to contact the NTP server indicated.', 'intropage') . '</td></tr>';
			$panel['data']  .= '<tr><td>' . __('Please check your configuration.', 'intropage') . '</td></tr>';
		}
	}

	$panel['data'] .= '</table>';

	save_panel_result($panel, $user_id);
}

//---------------------------maint plugin--------------------
function maint($panel, $user_id) {
	global $config;

	$panel['alarm'] = 'green';

	$maint_days_before = read_config_option('intropage_maint_plugin_days_before');

	if (api_plugin_is_enabled('maint') && $maint_days_before >= 0) {
		$allowed_devices = intropage_get_allowed_devices($user_id);

		if ($allowed_devices !== false) {
			$schedules = db_fetch_assoc("SELECT *
				FROM plugin_maint_schedules
				WHERE enabled = 'on'");

			if (cacti_sizeof($schedules)) {
				foreach ($schedules as $sc) {
					$t = time();

					switch ($sc['mtype']) {
					case 1:
						if ($t > ($sc['stime'] - $maint_days_before) && $t < $sc['etime']) {
							$hosts = db_fetch_assoc_prepared('SELECT description FROM host
								INNER JOIN plugin_maint_hosts
								ON host.id=plugin_maint_hosts.host
								WHERE host.id in (' . $allowed_devices . ') AND schedule = ?',
								array($sc['id']));

							if (cacti_sizeof($hosts)) {
								$panel['data'] .= '<b>' . date('d. m . Y  H:i', $sc['stime']) .
									' - ' . date('d. m . Y  H:i', $sc['etime']) .
									' - ' . $sc['name'] . ' (One time)<br/>Affected hosts:</b> ';

								foreach ($hosts as $host) {
									$panel['data'] .= $host['description'] . ', ';
								}
							}

							$panel['data'] = substr($panel['data'], 0, -2) .'<br/><br/>';
						}

						break;
					case 2:
						while ($sc['etime'] < $t) {
							$sc['etime'] += $sc['minterval'];
							$sc['stime'] += $sc['minterval'];
						}

						if ($t > ($sc['stime'] - $maint_days_before) && $t < $sc['etime']) {
							$hosts = db_fetch_assoc_prepared('SELECT description FROM host
								INNER JOIN plugin_maint_hosts
								ON host.id=plugin_maint_hosts.host
								WHERE host.id in (' . $allowed_devices . ') AND schedule = ?',
								array($sc['id']));

							if (cacti_sizeof($hosts)) {
								$panel['data'] .= '<b>' . date('d. m . Y  H:i', $sc['stime']) .
									' - ' . date('d. m . Y  H:i', $sc['etime']) .
									' - ' . $sc['name'] . ' (Reoccurring)<br/>Affected hosts:</b> ';

								foreach ($hosts as $host) {
									$panel['data'] .= $host['description'] . ', ';
								}
							}

							$panel['data'] = substr($panel['data'], 0, -2) . '<br/><br/>';
						}

						break;
					}
				}
			}
		}
	} else {
		$panel['data'] = __('Maint plugin is not installed/enabled', 'intropage');
	}

	save_panel_result($panel, $user_id);
}

// -------------------------------------plugin webseer-------------------------------------------
function webseer($panel, $user_id) {
	global $config;

	$panel['alarm'] = 'green';

	if (!api_plugin_is_enabled('webseer')) {
		$panel['alarm']  = 'yellow';
		$panel['data']   = __('Plugin Webseer isn\'t installed or started', 'intropage');
		$panel['detail'] = FALSE;
	} else {
		$all  = db_fetch_cell('SELECT COUNT(*) FROM plugin_webseer_urls');
		$disa = db_fetch_cell("SELECT COUNT(*) FROM plugin_webseer_urls WHERE enabled != 'on'");
		$ok   = db_fetch_cell("SELECT COUNT(*) FROM plugin_webseer_urls WHERE enabled = 'on' AND result = 1");
		$ko   = db_fetch_assoc("SELECT * FROM plugin_webseer_urls WHERE enabled = 'on' AND result != 1");

		$panel['data']  = __('Number of checks: ', 'intropage') . $all . '<br/>';
		$panel['data'] .= __('Disabled: ', 'intropage') . $disa . '<br/>';
		$panel['data'] .= __('Status up: ', 'intropage') . $ok . '<br/>';
		$panel['data'] .= __('Status down: ', 'intropage') . cacti_sizeof($ko) . '<br/><br/>';

		if (cacti_sizeof($ko) > 0) {
			$count = 0;

			$panel['alarm'] = 'red';
			$panel['data'] .= '<table class="cactiTable">';
			$panel['data'] .= '<tr><td colspan="3"><strong>' . __('First 3 failed sites', 'intropage') . '</td></tr>';
			$panel['data'] .= '<tr><td class="rpad">' . __('url', 'intropage') . '</td>' .
				'<td class="rpad">' . __('Status', 'intropage') . '</td>' .
				'<td class="rpad">' . __('HTTP code', 'intropage') . '</td></tr>';

			foreach ($ko as $row) {
				if ($count < 3) {
					$panel['data'] .= '<td class="rpad">' . $row['url'] . '</td>' .
						'<td class="rpad">' . $row['display_name'] . '</td>' .
						'<td class="rpad">' . $row['http_code'] . '</td></tr>';
				}

				$count++;
			}

			$panel['data'] .= '</table>';
		}
	}

	save_panel_result($panel, $user_id);
}

//------------------------------------ webseer_plugin -----------------------------------------------------
function webseer_detail() {
	global $config, $log;

	$panel = array(
		'name'   => __('Webseer Plugin - Details', 'intropage'),
		'alarm'  => 'green',
		'detail' => '',
	);

	$logs = db_fetch_assoc ('SELECT pwul.lastcheck, pwul.result, pwul.http_code, pwul.error, pwu.url
		FROM plugin_webseer_urls_log AS pwul
		INNER JOIN plugin_webseer_urls AS pwu
		ON pwul.url_id=pwu.id
		WHERE pwu.id = 1
		ORDER BY pwul.lastcheck DESC
		LIMIT 40');

	$panel['detail'] = '<table class="cactiTable"><tr class="tableHeader">';

	$panel['detail'] .=
		'<th class="left">'  . __('Date', 'intropage')      . '</th>' .
		'<th class="left">'  . __('URL', 'intropage')       . '</th>' .
		'<th class="left">'  . __('Result', 'intropage')    . '</th>' .
		'<th class="right">' . __('HTTP code', 'intropage') . '</th>' .
		'<th class="right">' . __('Error', 'intropage')     . '</th>' .
	'</tr>';

	foreach ($logs as $log)	{
		$panel['detail'] .= '<tr>';
		$panel['detail'] .= '<td class="left">' . $log['lastcheck'] . '</td>';
		$panel['detail'] .= '<td class="left">' . $log['url'] . '</td>';

		if ($log['result'] == 1) {
			$panel['detail'] .= '<td class="left">' . __('OK') . '</td>';
		} else {
			$panel['detail'] .= '<td class="left">' . __('Failed') . '</td>';
		}
		$panel['detail'] .= '<td class="right">' . $log['http_code'] . '</td>';
		$panel['detail'] .= '<td class="right">' . $log['error'] . '</td></tr>';

		if ($log['result'] != 1)	{
			$panel['alarm'] = 'red';
		}
	}

	$panel['detail'] .= '</table>';

	return $panel;
}

