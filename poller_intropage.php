<?php
/* vim: ts=4
 +-------------------------------------------------------------------------+
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

intropage_gather_stats();

$poller_end = microtime(true);

$stats = 'Time:' . round($poller_end-$poller_start, 2) . ' Checks:' . $checks;

cacti_log('INTROPAGE STATS: ' . $stats, false, 'SYSTEM');
set_config_option('stats_intropage', $stats);

if (function_exists('unregister_process')) {
	unregister_process('intropage', 'master', $config['poller_id']);
}

exit(0);

function intropage_gather_stats() {
	global $config, $force, $checks, $run_from_poller;

	$logging = read_config_option('log_verbosity', true);

	// gather data for all panels
	$data = db_fetch_assoc('SELECT file,panel_id FROM plugin_intropage_panel_definition');

	foreach ($data as $one)	{

	    include_once($config['base_path'] . $one['file']);
    	    $start = microtime(true);

    	    $magic = $one['panel_id'];
            $magic(false,true,false);

    	    if ($logging >=5) {
        	cacti_log('Debug: gathering data - ' . $magic . ' - duration ' . round(microtime(true) - $start, 2),true,'Intropage');
	    }
    	    intropage_debug('gathering data - ' . $magic . ' - duration ' . round(microtime(true) - $start, 2));

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
		SET cur_timestamp=now() where name = 'ar_poller_finish'");
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

