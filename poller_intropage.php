<?php
/*
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
	global $config, $force, $checks;
	
	$logging = read_config_option('log_verbosity', true);

	// gather data for all panels
	$data = db_fetch_assoc('SELECT file,panel_id FROM plugin_intropage_panel_definition');
	foreach ($data as $one)	{
	
	    include_once($config['base_path'] . $one['file']);
    	    $start = microtime(true);

    	    $magic = $one['panel_id'];
            $magic(false,true,false);
        
    	    if ($logging >=5) {    
        	cacti_log('Debug: gathering data - $magic - duration ' . round(microtime(true) - $start, 2),true,'Intropage');
	    }
    	    intropage_debug('gathering data - $magic - duration ' . round(microtime(true) - $start, 2));

	}
	// end of gathering data 


	// cleaning old data
	intropage_debug('Purging old Intropage Trends');

	db_execute("DELETE FROM plugin_intropage_trends
		WHERE cur_timestamp < date_sub(now(), INTERVAL 2 DAY) AND
		name IN ('poller','cpuload','failed_polls','host','thold','poller_output','syslog_incoming','syslog_total','syslog_alert')");

	// poller stats
	intropage_debug('Checking Data Collector Statistics');

	$stats = db_fetch_assoc('SELECT id, total_time, date_sub(last_update, interval round(total_time) second) AS start
		FROM poller
		ORDER BY id
		LIMIT 5');

	foreach ($stats as $stat) {
		db_execute_prepared("REPLACE INTO plugin_intropage_trends
			(name, cur_timestamp, value) VALUES
			('poller', ?, ?)",
			array($stat['start'], $stat['id'] . ':' . round($stat['total_time'])));

		$checks++;
	}

	// CPU load - linux only
	if (!stristr(PHP_OS, 'win')) {
		intropage_debug('Checking Cacti Server Load Statistics');

		$load    = sys_getloadavg();
		$load[0] = round($load[0], 2);

		db_execute_prepared('REPLACE INTO plugin_intropage_trends
			(name, cur_timestamp, value) VALUES
			("cpuload", ?, ?)',
			array($stat['start'], $load[0]));

		$checks++;
	}

	// failed polls
	intropage_debug('Checking Cacti Device Failed Poll Statistics');

	$count = db_fetch_cell('SELECT SUM(failed_polls) FROM host;');
	db_execute_prepared('REPLACE INTO plugin_intropage_trends
		(name, value) VALUES (?, ?)',
		array('failed_polls', $count));

	$checks++;

	// trends - all hosts that are down!!!
	intropage_debug('Checking Down Host Counts');

	db_execute("REPLACE INTO plugin_intropage_trends
		(name, value)
		SELECT 'host', COUNT(id)
		FROM host
		WHERE status='1'
		AND disabled=''");

	$checks++;

	if (db_fetch_cell("SELECT directory FROM plugin_config WHERE directory='thold' AND status=1")) {
		intropage_debug('Checking Triggered Thresholds');

		db_execute("REPLACE INTO plugin_intropage_trends
			(name,value)
			SELECT 'thold', COUNT(*)
			FROM thold_data
			WHERE thold_data.thold_alert!=0
			OR thold_data.bl_fail_count >= thold_data.bl_fail_trigger");

		$checks++;
	}

	// automatic autorefresh
	intropage_debug('Checking Triggered Thresholds');

	db_execute("UPDATE plugin_intropage_trends
		SET cur_timestamp=now() where name = 'ar_poller_finish'");

	// check NTP
	$last = db_fetch_cell("SELECT UNIX_TIMESTAMP(value)
		FROM plugin_intropage_trends
		WHERE name='ntp_testdate'");

	if (time() > ($last + read_config_option('intropage_ntp_interval')) || $force) {
		intropage_debug('Checking NTP Statistics');

	    include_once($config['base_path'] . '/plugins/intropage/include/functions.php');
	    ntp_time2();

		$checks++;
	} else {
		intropage_debug('Not Time to Check NTP Statistics');
	}

	// plugin syslog
	if (db_fetch_cell("SELECT directory FROM plugin_config WHERE directory='syslog' and status=1")) {
		intropage_debug('Checking Syslog Statistics');

		// Grab row counts from the information schema, it's faster
		$i_rows     = syslog_db_fetch_cell("SELECT TABLE_ROWS FROM information_schema.TABLES WHERE TABLE_NAME = 'syslog_incoming'");
		$total_rows = syslog_db_fetch_cell("SELECT TABLE_ROWS FROM information_schema.TABLES WHERE TABLE_NAME = 'syslog'");

		$alert_rows = syslog_db_fetch_cell('SELECT ifnull(sum(count),0) FROM syslog_logs WHERE
			logtime > date_sub(now(), INTERVAL ' . read_config_option('poller_interval') .' SECOND)');

		db_execute("INSERT INTO plugin_intropage_trends (name,value) VALUES ('syslog_incoming','" . $i_rows . "')");
		db_execute("INSERT INTO plugin_intropage_trends (name,value) VALUES ('syslog_total','" . $total_rows . "')");
		db_execute("INSERT INTO plugin_intropage_trends (name,value) VALUES ('syslog_alert','" . $alert_rows . "')");

		$checks++;
	}

	// check db
	if (read_config_option('intropage_analyse_db_interval') > 0)	{
		intropage_debug('Checking Cacti Database Enabled');

		$last = db_fetch_cell("SELECT UNIX_TIMESTAMP(value)
			FROM plugin_intropage_trends
			WHERE name='db_check_testdate'");

		if (time() > ($last + read_config_option('intropage_analyse_db_interval')) || $force) {
			intropage_debug('Checking Cacti Database');

			include_once($config['base_path'] . '/plugins/intropage/include/functions.php');
			db_check();

			$checks++;
		} else {
			intropage_debug('Not Time to Check Cacti Database');
		}
	}

	// check poller_table is empty?
	intropage_debug('Checking For Lingering Poller Output');

	$count = db_fetch_cell("SELECT COUNT(local_data_id) FROM poller_output");

	db_execute_prepared('REPLACE INTO plugin_intropage_trends
		(name, value) VALUES (?, ?)',
		array('poller_output', $count));

	$checks++;
	
	
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

