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

$dir = dirname(__FILE__);
chdir($dir);

include('../../include/cli_check.php');
include_once($config['base_path'] . '/lib/reports.php');
include_once($config['base_path'] . '/plugins/intropage/include/functions.php');

/* let PHP run just as long as it has to */
ini_set('max_execution_time', '0');

error_reporting(E_ALL);

/* record the start time */
$poller_start = microtime(true);
$start_date   = date('Y-m-d H:i:s');
$force        = false;
$debug        = false;
$checks       = 0;

global $config, $database_default, $purged_r, $purged_n;

$run_from_poller = true;

/* process calling arguments */
$parms = $_SERVER['argv'];
array_shift($parms);

if (cacti_sizeof($parms)) {
	$shortopts = 'VvHh';

	$longopts = array(
		'force',
		'debug',
		'version',
		'help'
	);

	$options = getopt($shortopts, $longopts);

	foreach($options as $arg => $value) {
		switch($arg) {
		case 'force':
			$force = true;

			break;
		case 'debug':
			$debug = true;

			break;
		case 'version':
		case 'V':
		case 'v':
			display_version();
			exit(0);
		case 'help':
		case 'H':
		case 'h':
			display_help();
			exit(0);
		default:
			print "ERROR: Invalid Argument: ($arg)" . PHP_EOL . PHP_EOL;
			display_help();
			exit(1);
		}
	}
}

intropage_debug('Intropage Starting Checks');

/* silently end if the registered process is still running, or process table missing */
if (function_exists('register_process_start')) {
	if (!register_process_start('intropage', 'master', $config['poller_id'], read_config_option('intropage_timeout'))) {
		intropage_debug('Another Intropage Process Still Running');
		exit(0);
	}
}

$stats = intropage_gather_stats();

$poller_end = microtime(true);

$pstats = 'Time:' . round($poller_end-$poller_start, 2) . ', Details:' . $stats['details'] . ', Trends:' . $stats['trends'];

cacti_log('INTROPAGE STATS: ' . $pstats, false, 'SYSTEM');
set_config_option('stats_intropage', $pstats);

if (function_exists('unregister_process')) {
	unregister_process('intropage', 'master', $config['poller_id']);
}

exit(0);

function intropage_gather_stats() {
	global $config, $force, $checks, $run_from_poller;

	$logging = read_config_option('log_verbosity', true);
	$trends  = 0;
	$details = 0;

	$panels = initialize_panel_library();

	$upanels = db_fetch_assoc('SELECT pd.panel_id, ud.id, ud.user_id, level, pd.refresh, ud.refresh_interval
		FROM plugin_intropage_panel_definition AS pd
		LEFT JOIN plugin_intropage_panel_data AS ud
		ON pd.panel_id = ud.panel_id
		WHERE UNIX_TIMESTAMP(last_update) < UNIX_TIMESTAMP() - refresh_interval
		OR (last_update IS NULL AND level = 0)');

	foreach ($upanels as $upanel) {
   	    $start = microtime(true);

		// Fake a session variable
		if ($upanel['user_id'] > 0) {
			$_SESSION['sess_user_id'] = $upanel['user_id'];
		}

		// Required plugin is not installed, but entry not purged
		if (!isset($panels[$upanel['panel_id']])) {
			continue;
		}

		$panel = $panels[$upanel['panel_id']];

		if ($upanel['refresh_interval'] == 0) {
			db_execute_prepared('UPDATE plugin_intropage_panel_data
				SET refresh_interval = ?
				WHERE id = ?',
				array($panel['refresh'], $upanel['id']));

			$upanel['refresh_interval'] = $panel['refresh'];
		}

		// Get details first
		if (isset($panel['update_func']) && $panel['update_func'] != '') {
			$qpanel = get_panel_details($upanel['panel_id'], $upanel['user_id']);

			$function = $panel['update_func'];

			if (function_exists($function)) {
				$details += $function($qpanel, $upanel['user_id']);

				if ($logging >= 5) {
					cacti_log('DEBUG: gathering data - ' . $function . ' - duration ' . round(microtime(true) - $start, 2), false, 'INTROPAGE');
				}

				intropage_debug('gathering data - ' . $function . ' - duration ' . round(microtime(true) - $start, 2));
			} else {
				cacti_log('WARNING: Unable to find update function ' . $function . ' for panel ' . $panel['name'], false, 'INTROPAGE');
			}

			$details++;
		}

		// Get trends next
		if (isset($panel['trends_func']) && $panel['trends_func'] != '') {
			$function = $panel['trends_func'];

			if (function_exists($function)) {
				$trends += $function();

				if ($logging >= 5) {
					cacti_log('DEBUG: gathering trend data - ' . $function . ' - duration ' . round(microtime(true) - $start, 2), false, 'INTROPAGE');
				}

				intropage_debug('gathering data - ' . $function . ' - duration ' . round(microtime(true) - $start, 2));
			} else {
				cacti_log('WARNING: Unable to find update function ' . $function . ' for panel ' . $panel['name'], false, 'INTROPAGE');
			}
		}

  	    $checks++;
	}
	// end of gathering data

	// cleaning old data
	intropage_debug('Purging old Intropage Trends');

	db_execute("DELETE FROM plugin_intropage_trends
		WHERE cur_timestamp < date_sub(now(), INTERVAL 2 DAY) AND
		name IN ('poller','cpuload','failed_polls','host','thold','poller_output','syslog_incoming','syslog_total','syslog_alert')");

	// automatic autorefresh
	intropage_debug('Checking Triggered Thresholds');

	db_execute("UPDATE plugin_intropage_trends
		SET cur_timestamp = now()
		WHERE name = 'ar_poller_finish'");

	return array('details' => $details, 'trends' => $trends);
}

function intropage_debug($message) {
	global $debug;

	if ($debug) {
		print trim($message) . PHP_EOL;
	}
}

function display_version() {
	global $config;

	if (!function_exists('plugin_intropage_version')) {
		include_once($config['base_path'] . '/plugins/intropage/setup.php');
	}

	$info = plugin_intropage_version();
	print 'Cacti Intropage Poller, Version ' . $info['version'] . ', ' . COPYRIGHT_YEARS . PHP_EOL;
}

/*
 * display_help
 * displays the usage of the function
 */
function display_help() {
	display_version();

	print PHP_EOL;
	print 'usage: poller_intropage.php [--force] [--debug]' . PHP_EOL . PHP_EOL;
	print '  --force       - force execution, e.g. for testing' . PHP_EOL;
	print '  --debug       - debug execution, e.g. for testing' . PHP_EOL . PHP_EOL;
}

