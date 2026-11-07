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
 * Third-party panel registration API.
 *
 * This file exposes the public functions a foreign Cacti plugin may call to
 * publish, maintain and withdraw its own Intropage dashboard panels without
 * shipping files into Intropage's own panellib/ directory. A plugin should
 * guard every call with function_exists() so it degrades gracefully when
 * Intropage is not installed, e.g.:
 *
 *   if (function_exists('intropage_register_panel')) {
 *       intropage_register_panel($panel);
 *   }
 *
 * Every registration is persisted in plugin_intropage_panel_registration and
 * is merged into the live panel library by initialize_panel_library(). The
 * poller (and every dashboard view) prunes registrations whose owning plugin
 * is no longer installed via intropage_prune_stale_registrations().
 */

/**
 * Returns the whitelist of request-variable types a registered panel may
 * declare, mapped to a human description. These types govern how
 * intropage_validate_request_vars() sanitizes the incoming request variables
 * a panel's details function depends on.
 *
 * @return array Map of type key => description.
 */
function intropage_registration_request_var_types(): array {
	return [
		'integer'      => 'Signed integer, validated with FILTER_VALIDATE_INT.',
		'numeric'      => 'Floating point or integer number, validated with FILTER_VALIDATE_FLOAT.',
		'boolean'      => 'Boolean (1/0, true/false, on/off, yes/no).',
		'alphanumeric' => 'ASCII letters and digits only (^[A-Za-z0-9]+$).',
		'string'       => 'Free text, control characters and HTML tags stripped.',
	];
}

/**
 * Returns the full set of panel attribute keys a registration understands,
 * each mapped to its default value. intropage_register_panel() fills any
 * attribute the caller omits from this list.
 *
 * @return array Map of attribute key => default value.
 */
function intropage_registration_defaults(): array {
	return [
		'plugin'       => '',
		'name'         => '',
		'description'  => '',
		'class'        => '',
		'level'        => defined('PANEL_USER') ? PANEL_USER : 1,
		'priority'     => 50,
		'alarm'        => 'grey',
		'width'        => 'quarter-panel',
		'height'       => 'normal',
		'height_fixed' => false,
		'force'        => true,
		'requires'     => '',
		'include_file' => '',
		'update_func'  => '',
		'details_func' => '',
		'trends_func'  => '',
		'refresh'      => 3600,
		'trefresh'     => 3600,
		'request_vars' => [],
	];
}

/**
 * Validates a panel registration definition supplied by a third-party plugin.
 *
 * @param array $panel   The caller-supplied panel attributes keyed by name.
 * @param bool  $partial When true, only the subset of attributes present in
 *                       $panel is validated (used by the update API); when
 *                       false the mandatory attributes must all be present.
 *
 * @return array A list of human-readable validation error strings. An empty
 *               array means the definition is valid.
 */
function intropage_validate_panel_registration(array $panel, bool $partial = false): array {
	$errors  = [];
	$heights = ['normal', 'double', 'triple'];

	if (!$partial) {
		foreach (['panel_id', 'plugin', 'name', 'update_func'] as $required) {
			if (empty($panel[$required])) {
				$errors[] = "The '$required' attribute is required.";
			}
		}
	}

	if (isset($panel['panel_id']) && !preg_match('/^[a-z0-9_]{1,50}$/i', (string) $panel['panel_id'])) {
		$errors[] = "The 'panel_id' must be 1-50 characters of letters, digits or underscores.";
	}

	// get_panel() treats a numeric id as a panel-data row id, so a purely
	// numeric panel_id (including forms like '1e3') would collide with another
	// panel's data when polled or reloaded.
	if (isset($panel['panel_id']) && is_numeric($panel['panel_id'])) {
		$errors[] = "The 'panel_id' must not be purely numeric.";
	}

	if (isset($panel['plugin']) && !preg_match('/^[a-z0-9_]{1,64}$/i', (string) $panel['plugin'])) {
		$errors[] = "The 'plugin' must be the plugin directory name (letters, digits or underscores).";
	}

	// Keep the registration within the live panel-definition column widths so a
	// published registration never overflows or truncates on write.
	$lengths = [
		'name'         => 30,
		'description'  => 200,
		'class'        => 30,
		'update_func'  => 30,
		'details_func' => 30,
		'trends_func'  => 30,
		'requires'     => 128,
		'width'        => 20,
	];

	foreach ($lengths as $attr => $max) {
		if (isset($panel[$attr]) && strlen((string) $panel[$attr]) > $max) {
			$errors[] = "The '$attr' must be $max characters or fewer.";
		}
	}

	if (isset($panel['level']) && (int) $panel['level'] !== 0 && (int) $panel['level'] !== 1) {
		$errors[] = "The 'level' must be PANEL_SYSTEM (0) or PANEL_USER (1).";
	}

	if (isset($panel['height']) && !in_array($panel['height'], $heights, true)) {
		$errors[] = "The 'height' must be one of: " . implode(', ', $heights) . '.';
	}

	if (isset($panel['alarm']) && !in_array($panel['alarm'], ['red', 'green', 'yellow', 'grey'], true)) {
		$errors[] = "The 'alarm' must be one of: red, green, yellow, grey.";
	}

	foreach (['refresh', 'trefresh', 'priority'] as $int_attr) {
		if (isset($panel[$int_attr]) && (!is_numeric($panel[$int_attr]) || (int) $panel[$int_attr] < 0)) {
			$errors[] = "The '$int_attr' must be a non-negative integer.";
		}
	}

	if (isset($panel['include_file']) && $panel['include_file'] !== '') {
		if (intropage_registration_resolve_file((string) $panel['include_file']) === false) {
			$errors[] = "The 'include_file' must be an existing readable PHP file beneath the Cacti plugins directory (e.g. plugins/myplugin/panels.php).";
		}
	}

	if (isset($panel['request_vars'])) {
		$errors = array_merge($errors, intropage_validate_request_var_spec($panel['request_vars']));
	}

	return $errors;
}

/**
 * Validates a request-variable specification array (the 'request_vars'
 * attribute of a registration).
 *
 * @param mixed $spec The request-variable specification to validate.
 *
 * @return array A list of validation error strings (empty when valid).
 */
function intropage_validate_request_var_spec($spec): array {
	$errors = [];

	if (!is_array($spec)) {
		return ["The 'request_vars' attribute must be an array of variable definitions."];
	}

	$types = intropage_registration_request_var_types();

	foreach ($spec as $var) {
		if (!is_array($var) || empty($var['name'])) {
			$errors[] = "Each 'request_vars' entry must be an array with a 'name'.";
			continue;
		}

		if (!preg_match('/^[a-z0-9_]{1,64}$/i', (string) $var['name'])) {
			$errors[] = "The request variable name '" . $var['name'] . "' must be letters, digits or underscores.";
		}

		if (!isset($var['type']) || !isset($types[$var['type']])) {
			$errors[] = "The request variable '" . $var['name'] . "' must declare a 'type' of: " . implode(', ', array_keys($types)) . '.';
		}
	}

	return $errors;
}

/**
 * Resolves a registration include_file path (given relative to the Cacti base
 * path) to an absolute path, enforcing that it stays beneath the Cacti
 * plugins/ directory and points at an existing readable .php file. This guards
 * the include performed by intropage_include_registration_file() against
 * directory traversal and arbitrary file inclusion.
 *
 * @param string $file The include_file path as stored in the registration.
 *
 * @return string|false The validated absolute path, or false when the path is
 *                      empty, escapes the plugins directory, or is missing.
 *
 * @global array $config Cacti global configuration array.
 */
function intropage_registration_resolve_file(string $file) {
	global $config;

	if ($file === '') {
		return false;
	}

	if (strpos($file, "\0") !== false || strpos($file, '..') !== false) {
		return false;
	}

	$plugins_dir = realpath($config['base_path'] . '/plugins');
	$absolute    = realpath($config['base_path'] . '/' . ltrim($file, '/'));

	if ($plugins_dir === false || $absolute === false) {
		return false;
	}

	if (strpos($absolute, $plugins_dir . DIRECTORY_SEPARATOR) !== 0) {
		return false;
	}

	if (substr($absolute, -4) !== '.php' || !is_readable($absolute)) {
		return false;
	}

	return $absolute;
}

/**
 * Includes a registration's callback file (if any) so that its update,
 * details and trends functions become callable. Safe to call repeatedly; the
 * file is only ever included once per request.
 *
 * @param array $row A registration row (associative) containing include_file.
 *
 * @return void
 */
function intropage_include_registration_file(array $row): void {
	if (empty($row['include_file'])) {
		return;
	}

	$absolute = intropage_registration_resolve_file((string) $row['include_file']);

	if ($absolute !== false) {
		include_once($absolute);
	}
}

/**
 * Determines whether the given plugin is present (installed) in Cacti,
 * regardless of whether it is currently enabled. A registration whose plugin
 * is not present is considered stale and is pruned.
 *
 * @param string $plugin The plugin directory name.
 *
 * @return bool True when the plugin has a plugin_config row.
 */
function intropage_plugin_is_present(string $plugin): bool {
	if ($plugin === '') {
		return false;
	}

	return db_fetch_cell_prepared('SELECT COUNT(*)
		FROM plugin_config
		WHERE directory = ?',
		[$plugin]) > 0;
}

/**
 * Returns true when a registration already exists for the given panel id.
 *
 * @param string $panel_id The panel id to check.
 *
 * @return bool
 */
function intropage_panel_registration_exists(string $panel_id): bool {
	return db_fetch_cell_prepared('SELECT COUNT(*)
		FROM plugin_intropage_panel_registration
		WHERE panel_id = ?',
		[$panel_id]) > 0;
}

/**
 * Detects an ownership or built-in collision for a panel id before a
 * registration is written. A panel id may only be (re-)registered by the
 * plugin that already owns its registration, and may never shadow a built-in
 * panellib panel (a panel_definition row with no registration behind it).
 *
 * @param string $panel_id The panel id being registered.
 * @param string $plugin   The plugin claiming ownership.
 *
 * @return string An error message describing the conflict, or '' when none.
 */
function intropage_registration_conflict(string $panel_id, string $plugin): string {
	$owner = db_fetch_cell_prepared('SELECT plugin
		FROM plugin_intropage_panel_registration
		WHERE panel_id = ?',
		[$panel_id]);

	if (!empty($owner)) {
		return ($owner === $plugin) ? '' : "Panel id '$panel_id' is already registered by plugin '$owner'.";
	}

	// No registration owns the id yet: reject it if a built-in panel does.
	$builtin = db_fetch_cell_prepared('SELECT COUNT(*)
		FROM plugin_intropage_panel_definition
		WHERE panel_id = ?',
		[$panel_id]);

	if ($builtin > 0) {
		return "Panel id '$panel_id' collides with a built-in Intropage panel.";
	}

	return '';
}

/**
 * Fetches a single registration row, decoding its request_vars JSON into an
 * array.
 *
 * @param string $panel_id The panel id to fetch.
 *
 * @return array|false The registration row, or false when not registered.
 */
function intropage_get_panel_registration(string $panel_id) {
	$row = db_fetch_row_prepared('SELECT *
		FROM plugin_intropage_panel_registration
		WHERE panel_id = ?',
		[$panel_id]);

	if (!is_array($row) || !cacti_sizeof($row)) {
		return false;
	}

	$row['request_vars'] = json_decode((string) $row['request_vars'], true) ?: [];

	return $row;
}

/**
 * Fetches every registration, optionally limited to a single owning plugin.
 *
 * @param string $plugin When non-empty, only registrations owned by this
 *                       plugin are returned.
 *
 * @return array A list of registration rows (request_vars decoded to arrays).
 */
function intropage_get_panel_registrations(string $plugin = ''): array {
	if ($plugin !== '') {
		$rows = db_fetch_assoc_prepared('SELECT *
			FROM plugin_intropage_panel_registration
			WHERE plugin = ?
			ORDER BY panel_id',
			[$plugin]);
	} else {
		$rows = db_fetch_assoc('SELECT *
			FROM plugin_intropage_panel_registration
			ORDER BY plugin, panel_id');
	}

	if (!cacti_sizeof($rows)) {
		return [];
	}

	foreach ($rows as $i => $row) {
		$rows[$i]['request_vars'] = json_decode((string) $row['request_vars'], true) ?: [];
	}

	return $rows;
}

/**
 * Registers (or fully replaces) a third-party panel.
 *
 * The caller supplies the full set of panel attributes (see
 * intropage_registration_defaults() and the README for the schema). The
 * definition is validated, persisted to plugin_intropage_panel_registration
 * and, when the registration is enabled and the owning plugin is installed,
 * immediately published into the live panel library (definition, seed data
 * row and admin permission) so it is pollable without an admin "refresh".
 *
 * @param array $panel The panel attributes, including the mandatory
 *                     'panel_id', 'plugin', 'name' and 'update_func'.
 *
 * @return bool|array True on success, or a list of validation error strings.
 */
function intropage_register_panel(array $panel) {
	$errors = intropage_validate_panel_registration($panel, false);

	if (cacti_sizeof($errors)) {
		cacti_log('WARNING: intropage_register_panel() rejected panel "' . ($panel['panel_id'] ?? '') . '": ' . implode(' ', $errors), false, 'INTROPAGE');
		return $errors;
	}

	$panel_id = $panel['panel_id'];
	$values   = array_merge(intropage_registration_defaults(), $panel);

	// A registration always requires the owning plugin, so default the plugin
	// dependency to the registering plugin when the caller omits it. An
	// explicit '' is honored as "no runtime dependency".
	if (!array_key_exists('requires', $panel)) {
		$values['requires'] = $values['plugin'];
	}

	// Never let a registration shadow a built-in panel or hijack a panel id
	// another plugin already owns.
	$conflict = intropage_registration_conflict($panel_id, $values['plugin']);

	if ($conflict !== '') {
		cacti_log('WARNING: intropage_register_panel() rejected panel "' . $panel_id . '": ' . $conflict, false, 'INTROPAGE');
		return [$conflict];
	}

	$enabled = isset($panel['enabled']) ? (filter_var($panel['enabled'], FILTER_VALIDATE_BOOLEAN) ? 1 : 0) : 1;

	db_execute_prepared('INSERT INTO plugin_intropage_panel_registration
		(panel_id, plugin, name, description, class, level, priority, alarm, width, height,
		height_fixed, `force`, requires, include_file, update_func, details_func, trends_func,
		refresh, trefresh, request_vars, enabled, registered, last_updated)
		VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
		ON DUPLICATE KEY UPDATE
			plugin=VALUES(plugin), name=VALUES(name), description=VALUES(description),
			class=VALUES(class), level=VALUES(level), priority=VALUES(priority),
			alarm=VALUES(alarm), width=VALUES(width), height=VALUES(height),
			height_fixed=VALUES(height_fixed), `force`=VALUES(`force`), requires=VALUES(requires),
			include_file=VALUES(include_file), update_func=VALUES(update_func),
			details_func=VALUES(details_func), trends_func=VALUES(trends_func),
			refresh=VALUES(refresh), trefresh=VALUES(trefresh), request_vars=VALUES(request_vars),
			enabled=VALUES(enabled), last_updated=NOW()',
		[
			$panel_id, $values['plugin'], $values['name'], $values['description'], $values['class'],
			(int) $values['level'], (int) $values['priority'], $values['alarm'], $values['width'], $values['height'],
			!empty($values['height_fixed']) ? 1 : 0, !empty($values['force']) ? 1 : 0, $values['requires'],
			$values['include_file'], $values['update_func'], $values['details_func'], $values['trends_func'],
			(int) $values['refresh'], (int) $values['trefresh'], json_encode(array_values($values['request_vars'])), $enabled,
		]);

	if ($enabled && intropage_plugin_is_present($values['plugin'])) {
		intropage_apply_panel_registration($panel_id);
	} else {
		intropage_retract_panel_registration($panel_id);
	}

	intropage_registration_reset_cache();

	return true;
}

/**
 * Updates selected attributes of an existing registration, leaving unspecified
 * attributes untouched. Intended for lightweight metadata changes (priority,
 * alarm, refresh, request_vars, ...) without re-sending the whole definition.
 *
 * @param string $panel_id The registered panel id to update.
 * @param array  $fields   The attributes to change, keyed by attribute name.
 *
 * @return bool|array True on success, or a list of validation error strings
 *                    (including when the panel id is not registered).
 */
function intropage_update_panel_registration(string $panel_id, array $fields) {
	if (!intropage_panel_registration_exists($panel_id)) {
		return ["No registration exists for panel id '$panel_id'."];
	}

	unset($fields['panel_id']);

	$errors = intropage_validate_panel_registration($fields, true);

	if (cacti_sizeof($errors)) {
		return $errors;
	}

	$columns = [
		'plugin', 'name', 'description', 'class', 'level', 'priority', 'alarm', 'width',
		'height', 'height_fixed', 'force', 'requires', 'include_file', 'update_func',
		'details_func', 'trends_func', 'refresh', 'trefresh',
	];

	$set    = [];
	$params = [];

	foreach ($columns as $column) {
		if (array_key_exists($column, $fields)) {
			$set[]    = "`$column` = ?";
			$params[] = in_array($column, ['height_fixed', 'force'], true) ? (!empty($fields[$column]) ? 1 : 0) : $fields[$column];
		}
	}

	if (array_key_exists('request_vars', $fields)) {
		$set[]    = 'request_vars = ?';
		$params[] = json_encode(array_values($fields['request_vars']));
	}

	if (!cacti_sizeof($set)) {
		return true;
	}

	$set[]    = 'last_updated = NOW()';
	$params[] = $panel_id;

	db_execute_prepared('UPDATE plugin_intropage_panel_registration
		SET ' . implode(', ', $set) . '
		WHERE panel_id = ?',
		$params);

	$row = intropage_get_panel_registration($panel_id);

	if (is_array($row) && $row['enabled'] && intropage_plugin_is_present($row['plugin'])) {
		intropage_apply_panel_registration($panel_id);
	}

	intropage_registration_reset_cache();

	return true;
}

/**
 * Enables a previously registered (or disabled) panel and publishes it back
 * into the live panel library.
 *
 * @param string $panel_id The registered panel id to enable.
 *
 * @return bool True when the registration exists and was enabled.
 */
function intropage_enable_panel_registration(string $panel_id): bool {
	if (!intropage_panel_registration_exists($panel_id)) {
		return false;
	}

	db_execute_prepared('UPDATE plugin_intropage_panel_registration
		SET enabled = 1, last_updated = NOW()
		WHERE panel_id = ?',
		[$panel_id]);

	$row = intropage_get_panel_registration($panel_id);

	if (is_array($row) && intropage_plugin_is_present($row['plugin'])) {
		intropage_apply_panel_registration($panel_id);
	}

	intropage_registration_reset_cache();

	return true;
}

/**
 * Disables a registered panel without deleting its registration. The panel is
 * removed from the live library, from every dashboard and from the definition
 * and data tables, but the registration row is retained so the panel can be
 * re-enabled later with intropage_enable_panel_registration().
 *
 * @param string $panel_id The registered panel id to disable.
 *
 * @return bool True when the registration exists and was disabled.
 */
function intropage_disable_panel_registration(string $panel_id): bool {
	if (!intropage_panel_registration_exists($panel_id)) {
		return false;
	}

	db_execute_prepared('UPDATE plugin_intropage_panel_registration
		SET enabled = 0, last_updated = NOW()
		WHERE panel_id = ?',
		[$panel_id]);

	intropage_retract_panel_registration($panel_id);
	intropage_registration_reset_cache();

	return true;
}

/**
 * Permanently removes a registration and all of its footprint (definition,
 * data, dashboard placements and per-user/group permissions). A plugin should
 * call this from its own uninstall hook; Intropage also calls it automatically
 * for plugins that disappear (see intropage_prune_stale_registrations()).
 *
 * @param string $panel_id The registered panel id to remove.
 *
 * @return bool True when a registration existed and was removed.
 */
function intropage_unregister_panel(string $panel_id): bool {
	if (!intropage_panel_registration_exists($panel_id)) {
		return false;
	}

	intropage_retract_panel_registration($panel_id);

	db_execute_prepared('DELETE FROM plugin_intropage_panel_registration
		WHERE panel_id = ?',
		[$panel_id]);

	intropage_registration_remove_permissions($panel_id);
	intropage_registration_reset_cache();

	return true;
}

/**
 * Removes every registration owned by the given plugin. A convenience wrapper
 * a plugin can call from its uninstall hook to withdraw all of its panels in
 * one step.
 *
 * @param string $plugin The owning plugin directory name.
 *
 * @return int The number of registrations removed.
 */
function intropage_unregister_plugin_panels(string $plugin): int {
	$removed = 0;

	foreach (intropage_get_panel_registrations($plugin) as $row) {
		if (intropage_unregister_panel($row['panel_id'])) {
			$removed++;
		}
	}

	return $removed;
}

/**
 * Publishes an enabled registration into the live panel tables: upserts its
 * row in plugin_intropage_panel_definition, seeds a plugin_intropage_panel_data
 * row (system panels under user 0, user panels under the admin user) when one
 * does not already exist, and grants the admin user permission to the panel.
 *
 * @param string $panel_id The registered panel id to publish.
 *
 * @return void
 */
function intropage_apply_panel_registration(string $panel_id): void {
	$row = intropage_get_panel_registration($panel_id);

	if (!is_array($row)) {
		return;
	}

	// This API can be reached from a foreign plugin's early hook, before the
	// rest of the Intropage library is loaded; make sure the persistence helper
	// is available.
	if (!function_exists('update_registered_panels')) {
		include_once(__DIR__ . '/functions.php'); // @codeCoverageIgnore
	}

	$definition = intropage_registration_to_definition($row);

	update_registered_panels([$panel_id => $definition]);

	$owner = ((int) $row['level'] === 0) ? 0 : intropage_registration_admin_user();

	$exists = db_fetch_cell_prepared('SELECT COUNT(*)
		FROM plugin_intropage_panel_data
		WHERE panel_id = ?
		AND user_id = ?',
		[$panel_id, $owner]);

	if ($exists == 0) {
		db_execute_prepared('INSERT INTO plugin_intropage_panel_data
			(panel_id, user_id, priority, alarm, refresh_interval, trend_interval, height)
			VALUES (?, ?, ?, ?, ?, ?, ?)',
			[$panel_id, $owner, $row['priority'], $row['alarm'], $row['refresh'], $row['trefresh'], $row['height']]);
	}

	intropage_registration_grant_admin($panel_id);
}

/**
 * Withdraws a panel from the live tables without touching its registration
 * row: deletes its dashboard placements, data rows and definition row. Shared
 * by the disable, unregister and prune paths.
 *
 * @param string $panel_id The panel id to withdraw.
 *
 * @return void
 */
function intropage_retract_panel_registration(string $panel_id): void {
	$ids = array_column(db_fetch_assoc_prepared('SELECT id
		FROM plugin_intropage_panel_data
		WHERE panel_id = ?',
		[$panel_id]), 'id');

	foreach ($ids as $id) {
		db_execute_prepared('DELETE FROM plugin_intropage_panel_dashboard
			WHERE panel_id = ?',
			[$id]);
	}

	db_execute_prepared('DELETE FROM plugin_intropage_panel_data
		WHERE panel_id = ?',
		[$panel_id]);

	db_execute_prepared('DELETE FROM plugin_intropage_panel_definition
		WHERE panel_id = ?',
		[$panel_id]);
}

/**
 * Maps a registration row to the array shape used throughout the panel library
 * (as produced by the built-in panellib register_*() functions), so that
 * registered panels are indistinguishable from native panels once merged.
 *
 * @param array $row A registration row.
 *
 * @return array The library-shaped panel definition.
 */
function intropage_registration_to_definition(array $row): array {
	return [
		'name'         => $row['name'],
		'description'  => $row['description'],
		'class'        => $row['class'] !== '' ? $row['class'] : $row['plugin'],
		'level'        => (int) $row['level'],
		'priority'     => (int) $row['priority'],
		'alarm'        => $row['alarm'],
		'width'        => $row['width'],
		'height'       => $row['height'],
		'height_fixed' => !empty($row['height_fixed']),
		'force'        => !empty($row['force']),
		'refresh'      => (int) $row['refresh'],
		'trefresh'     => (int) $row['trefresh'],
		'requires'     => $row['requires'] !== '' ? $row['requires'] : false,
		'update_func'  => $row['update_func'],
		'details_func' => $row['details_func'] !== '' ? $row['details_func'] : false,
		'trends_func'  => $row['trends_func'] !== '' ? $row['trends_func'] : false,
	];
}

/**
 * Builds the set of registered panels that should participate in the live
 * panel library: every registration that is enabled and whose owning plugin is
 * currently installed. Each contributing registration's callback file is
 * included so its update/details/trends functions resolve. Called from
 * initialize_panel_library().
 *
 * @return array Library-shaped panel definitions keyed by panel id.
 */
function intropage_load_registered_panels(): array {
	global $registry;

	$panels = [];

	if (!db_table_exists('plugin_intropage_panel_registration')) {
		return [];
	}

	$rows = db_fetch_assoc('SELECT r.*
		FROM plugin_intropage_panel_registration AS r
		INNER JOIN plugin_config AS p
		ON p.directory = r.plugin
		WHERE r.enabled = 1');

	if (!cacti_sizeof($rows)) {
		return [];
	}

	foreach ($rows as $row) {
		$row['request_vars'] = json_decode((string) $row['request_vars'], true) ?: [];

		intropage_include_registration_file($row);

		$definition = intropage_registration_to_definition($row);

		// The permission editors group panels by their category ('class') via
		// the global $registry; register a fallback category for a registered
		// panel so its heading is not blank, without overwriting a built-in one.
		$class = $definition['class'];

		if (!isset($registry[$class])) {
			$registry[$class] = [
				'name'        => $row['plugin'],
				'description' => $row['description'],
			];
		}

		$panels[$row['panel_id']] = $definition;
	}

	return $panels;
}

/**
 * Removes every registration whose owning plugin is no longer installed,
 * cleaning up the panel from all dashboards, data, definitions and
 * permissions. Called on each poller cycle and on each dashboard view so that
 * uninstalled plugins leave no orphaned panels behind.
 *
 * @return int The number of stale registrations pruned.
 */
function intropage_prune_stale_registrations(): int {
	if (!db_table_exists('plugin_intropage_panel_registration')) {
		return 0;
	}

	$stale = db_fetch_assoc('SELECT r.panel_id, r.plugin
		FROM plugin_intropage_panel_registration AS r
		LEFT JOIN plugin_config AS p
		ON p.directory = r.plugin
		WHERE p.id IS NULL');

	if (!cacti_sizeof($stale)) {
		return 0;
	}

	$pruned = 0;

	foreach ($stale as $row) {
		if (intropage_unregister_panel($row['panel_id'])) {
			cacti_log(sprintf('INTROPAGE: Pruned panel registration "%s" because its plugin "%s" is no longer installed.', $row['panel_id'], $row['plugin']), false, 'INTROPAGE');
			$pruned++;
		}
	}

	return $pruned;
}

/**
 * Validates and returns the request variables a registered panel declared,
 * reading them from the current request and sanitizing each by its declared
 * type. A registered details function can call this to obtain safe inputs.
 *
 * @param string $panel_id The registered panel id whose request_vars to read.
 *
 * @return array Map of variable name => sanitized value (or declared default
 *               when absent/invalid).
 */
function intropage_validate_request_vars(string $panel_id): array {
	$row = intropage_get_panel_registration($panel_id);

	if (!is_array($row) || !cacti_sizeof($row['request_vars'])) {
		return [];
	}

	$result = [];

	foreach ($row['request_vars'] as $var) {
		$name    = $var['name'];
		$type    = $var['type'] ?? 'string';
		$default = $var['default'] ?? null;

		if (!isset($_REQUEST[$name])) {
			if (!empty($var['required'])) {
				cacti_log(sprintf('INTROPAGE WARNING: Panel "%s" is missing required request variable "%s".', $panel_id, $name), false, 'INTROPAGE');
			}

			$result[$name] = $default;
			continue;
		}

		$raw = get_nfilter_request_var($name);

		// Declared request variables are scalar; reject array/object input
		// (e.g. name[]=value) rather than coercing it to the string 'Array'.
		if (!is_scalar($raw)) {
			$result[$name] = $default;
			continue;
		}

		switch ($type) {
			case 'integer':
				$value = filter_var($raw, FILTER_VALIDATE_INT);
				$result[$name] = ($value === false) ? $default : $value;
				break;
			case 'numeric':
				$value = filter_var($raw, FILTER_VALIDATE_FLOAT);
				$result[$name] = ($value === false) ? $default : $value;
				break;
			case 'boolean':
				// FILTER_VALIDATE_BOOLEAN returns false for both a valid false
				// and invalid input; use FILTER_NULL_ON_FAILURE so an invalid
				// value falls back to the declared default instead of false.
				$value = filter_var($raw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
				$result[$name] = ($value === null) ? $default : $value;
				break;
			case 'alphanumeric':
				$string        = (string) $raw;
				$result[$name] = preg_match('/^[A-Za-z0-9]+$/', $string) ? $string : $default;
				break;
			case 'string':
			default:
				$clean = preg_replace('/[\x00-\x1F\x7F]/u', '', strip_tags((string) $raw));
				$result[$name] = ($clean === '' && $default !== null) ? $default : $clean;
				break;
		}
	}

	return $result;
}

/**
 * Returns the user id used to own system-level seed data and to receive panel
 * permissions, mirroring intropage_initialize_database(): the configured admin
 * user, falling back to the lowest user id. It deliberately ignores the
 * session user so a plugin registering during an ordinary user's page load
 * cannot grant that user (or itself) panel permissions.
 *
 * @return int The admin user id (0 when no users exist).
 */
function intropage_registration_admin_user(): int {
	$user_id = read_config_option('admin_user');

	if (empty($user_id)) {
		$user_id = db_fetch_cell('SELECT id FROM user_auth ORDER BY id ASC LIMIT 1');
	}

	return (int) $user_id;
}

/**
 * Grants the admin user permission to a newly published panel by merging it
 * into their JSON permission set, so the panel is immediately grantable and
 * visible on the admin's dashboard panel picker.
 *
 * @param string $panel_id The panel id to grant.
 *
 * @return void
 */
function intropage_registration_grant_admin(string $panel_id): void {
	$user_id = intropage_registration_admin_user();

	if ($user_id <= 0) {
		return;
	}

	$permissions = db_fetch_cell_prepared('SELECT permissions
		FROM plugin_intropage_user_auth
		WHERE user_id = ?',
		[$user_id]);

	if ($permissions === false || $permissions === null) {
		return;
	}

	$decoded = json_decode((string) $permissions, true) ?: [];

	if (!isset($decoded[$panel_id])) {
		$decoded[$panel_id] = 'on';

		db_execute_prepared('UPDATE plugin_intropage_user_auth
			SET permissions = ?
			WHERE user_id = ?',
			[json_encode($decoded), $user_id]);
	}
}

/**
 * Strips a removed panel from every user and user-group JSON permission set so
 * no dangling permission entries survive an unregister/prune.
 *
 * @param string $panel_id The panel id to remove from permissions.
 *
 * @return void
 */
function intropage_registration_remove_permissions(string $panel_id): void {
	foreach (['plugin_intropage_user_auth' => 'user_id', 'plugin_intropage_user_group_auth' => 'user_group_id'] as $table => $key) {
		$rows = db_fetch_assoc("SELECT $key, permissions FROM $table");

		foreach ($rows as $row) {
			$decoded = json_decode((string) $row['permissions'], true);

			if (is_array($decoded) && isset($decoded[$panel_id])) {
				unset($decoded[$panel_id]);

				db_execute_prepared("UPDATE $table
					SET permissions = ?
					WHERE $key = ?",
					[json_encode($decoded), $row[$key]]);
			}
		}
	}
}

/**
 * Invalidates the request-lifetime panel-library caches so a registration
 * change made in the same request is reflected by the next
 * initialize_panel_library() call: it both clears the session snapshot and
 * raises a flag that initialize_panel_library() checks to discard its
 * function-local static cache.
 *
 * @return void
 */
function intropage_registration_reset_cache(): void {
	$GLOBALS['intropage_panel_library_stale'] = true;

	if (isset($_SESSION['intropage_panel_library'])) {
		unset($_SESSION['intropage_panel_library']);
	}
}
