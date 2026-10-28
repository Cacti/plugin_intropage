<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

require_once __DIR__ . '/../../includes/functions.php';

describe('intropage_device_scope', function () {
	afterEach(function () {
		unset($GLOBALS['__test_simple_perms'], $GLOBALS['__test_allowed_devices']);
	});

	it('returns a see-all scope for a simple-permission user', function () {
		$GLOBALS['__test_simple_perms'] = true;

		$scope = intropage_device_scope(1);

		expect($scope['simple'])->toBeTrue();
		expect($scope['allowed'])->toBeFalse();
	});

	it('returns the allowed host ids for a restricted user', function () {
		$GLOBALS['__test_simple_perms']    = false;
		$GLOBALS['__test_allowed_devices'] = [['id' => 3], ['id' => 7], ['id' => 9]];

		$scope = intropage_device_scope(2);

		expect($scope['simple'])->toBeFalse();
		expect($scope['allowed'])->toBe('3,7,9');
	});

	it('returns allowed=false for a restricted user with no visible devices', function () {
		$GLOBALS['__test_simple_perms']    = false;
		$GLOBALS['__test_allowed_devices'] = [];

		$scope = intropage_device_scope(2);

		expect($scope['simple'])->toBeFalse();
		expect($scope['allowed'])->toBeFalse();
	});
});

describe('intropage_get_allowed_devices hide_disabled handling', function () {
	beforeEach(function () {
		$GLOBALS['__test_user_settings'] = [];
		$GLOBALS['__test_user_setting']  = ['hide_disabled' => 'on'];
	});

	afterEach(function () {
		unset($GLOBALS['__test_user_setting'], $GLOBALS['__test_allowed_devices_throw'], $GLOBALS['__test_allowed_devices']);
	});

	it('clears the preference for the lookup and puts it back', function () {
		$GLOBALS['__test_allowed_devices'] = [['id' => 4]];

		expect(intropage_get_allowed_devices(9))->toBe('4');
		expect($GLOBALS['__test_user_settings'])->toBe([['hide_disabled', ''], ['hide_disabled', 'on']]);
	});

	it('puts the preference back when the lookup throws', function () {
		$GLOBALS['__test_allowed_devices_throw'] = true;

		expect(fn () => intropage_get_allowed_devices(9))->toThrow(RuntimeException::class);
		expect($GLOBALS['__test_user_settings'])->toBe([['hide_disabled', ''], ['hide_disabled', 'on']]);
	});

	it('leaves the preference alone when it is not set', function () {
		$GLOBALS['__test_user_setting'] = [];

		intropage_get_allowed_devices(9);

		expect($GLOBALS['__test_user_settings'])->toBe([]);
	});
});
