<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

/*
 * Unit coverage for the third-party panel registration API validation and
 * mapping helpers in includes/registration.php. These paths are pure (no
 * database) so they are exercised directly against the unit bootstrap stubs.
 */

beforeAll(function () {
	require_once __DIR__ . '/../../includes/registration.php';
});

function intropage_test_valid_panel(): array {
	return array(
		'panel_id'    => 'myplugin_status',
		'plugin'      => 'myplugin',
		'name'        => 'My Plugin Status',
		'update_func' => 'myplugin_intropage_update',
	);
}

it('accepts a minimal valid registration', function () {
	$errors = intropage_validate_panel_registration(intropage_test_valid_panel(), false);

	expect($errors)->toBe(array());
});

it('requires panel_id, plugin, name and update_func', function () {
	$errors = intropage_validate_panel_registration(array(), false);

	expect($errors)->toContain("The 'panel_id' attribute is required.");
	expect($errors)->toContain("The 'plugin' attribute is required.");
	expect($errors)->toContain("The 'name' attribute is required.");
	expect($errors)->toContain("The 'update_func' attribute is required.");
});

it('rejects an invalid panel_id format', function () {
	$panel             = intropage_test_valid_panel();
	$panel['panel_id'] = 'bad id!';

	$errors = intropage_validate_panel_registration($panel, false);

	expect($errors)->not->toBe(array());
});

it('rejects an out-of-range level, height and alarm', function () {
	$panel           = intropage_test_valid_panel();
	$panel['level']  = 5;
	$panel['height'] = 'huge';
	$panel['alarm']  = 'purple';

	$errors = intropage_validate_panel_registration($panel, false);

	expect($errors)->toContain("The 'level' must be PANEL_SYSTEM (0) or PANEL_USER (1).");
	expect($errors)->toContain("The 'height' must be one of: normal, double, triple.");
	expect($errors)->toContain("The 'alarm' must be one of: red, green, yellow, grey.");
});

it('rejects a negative refresh interval', function () {
	$panel            = intropage_test_valid_panel();
	$panel['refresh'] = -10;

	$errors = intropage_validate_panel_registration($panel, false);

	expect($errors)->toContain("The 'refresh' must be a non-negative integer.");
});

it('allows a partial update without the mandatory attributes', function () {
	$errors = intropage_validate_panel_registration(array('priority' => 10), true);

	expect($errors)->toBe(array());
});

it('validates the request_vars specification', function () {
	$panel                 = intropage_test_valid_panel();
	$panel['request_vars'] = array(
		array('name' => 'device_id', 'type' => 'integer', 'required' => true),
	);

	expect(intropage_validate_panel_registration($panel, false))->toBe(array());
});

it('rejects a request variable with an unknown type', function () {
	$panel                 = intropage_test_valid_panel();
	$panel['request_vars'] = array(
		array('name' => 'device_id', 'type' => 'spaceship'),
	);

	$errors = intropage_validate_panel_registration($panel, false);

	expect($errors)->not->toBe(array());
});

it('rejects a non-array request_vars attribute', function () {
	$errors = intropage_validate_request_var_spec('nope');

	expect($errors)->toContain("The 'request_vars' attribute must be an array of variable definitions.");
});

it('lists the five supported request variable types', function () {
	$types = intropage_registration_request_var_types();

	expect(array_keys($types))->toBe(array('integer', 'numeric', 'boolean', 'alphanumeric', 'string'));
});

it('exposes a default for every documented attribute', function () {
	$defaults = intropage_registration_defaults();

	foreach (array('plugin', 'name', 'level', 'alarm', 'height', 'refresh', 'request_vars') as $key) {
		expect($defaults)->toHaveKey($key);
	}

	expect($defaults['alarm'])->toBe('grey');
	expect($defaults['height'])->toBe('normal');
	expect($defaults['request_vars'])->toBe(array());
});

it('rejects an include_file that escapes the plugins directory', function () {
	expect(intropage_registration_resolve_file('../../etc/passwd'))->toBeFalse();
	expect(intropage_registration_resolve_file(''))->toBeFalse();
});

it('maps a registration row to the library panel shape', function () {
	$row = array(
		'panel_id'     => 'myplugin_status',
		'plugin'       => 'myplugin',
		'name'         => 'My Plugin Status',
		'description'  => 'desc',
		'class'        => '',
		'level'        => 0,
		'priority'     => 60,
		'alarm'        => 'green',
		'width'        => 'quarter-panel',
		'height'       => 'normal',
		'height_fixed' => 1,
		'force'        => 1,
		'refresh'      => 300,
		'trefresh'     => 600,
		'requires'     => 'myplugin',
		'update_func'  => 'myplugin_intropage_update',
		'details_func' => '',
		'trends_func'  => '',
	);

	$def = intropage_registration_to_definition($row);

	expect($def['class'])->toBe('myplugin');
	expect($def['level'])->toBe(0);
	expect($def['height_fixed'])->toBeTrue();
	expect($def['requires'])->toBe('myplugin');
	expect($def['details_func'])->toBeFalse();
	expect($def['trends_func'])->toBeFalse();
	expect($def['update_func'])->toBe('myplugin_intropage_update');
});
