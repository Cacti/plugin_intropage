<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

/*
 * Unit coverage for intropage_config_arrays() in setup.php - builds the
 * trend-timespan and refresh-interval option arrays and augments the
 * built-in roles with intropage's realms.
 */

beforeAll(function () {
	require_once __DIR__ . '/../../setup.php';
});

beforeEach(function () {
	$GLOBALS['__test_augmented_roles'] = array();
	$GLOBALS['__test_config'] = array();
});

it('augments the Normal User and System Administration roles', function () {
	intropage_config_arrays();

	$roles = array_column($GLOBALS['__test_augmented_roles'], 'role');

	expect($roles)->toContain('Normal User');
	expect($roles)->toContain('System Administration');
});

it('builds the trend timespan options', function () {
	global $trend_timespans;

	intropage_config_arrays();

	expect($trend_timespans)->toHaveKey(3600);
	expect($trend_timespans)->toHaveKey(86400);
});

it('excludes refresh intervals shorter than the poller interval', function () {
	global $intropage_intervals;

	$GLOBALS['__test_config']['poller_interval'] = 300;

	intropage_config_arrays();

	expect($intropage_intervals)->not->toHaveKey('60');
	expect($intropage_intervals)->not->toHaveKey('120');
	expect($intropage_intervals)->toHaveKey('300');
	expect($intropage_intervals)->toHaveKey('3600');
});
