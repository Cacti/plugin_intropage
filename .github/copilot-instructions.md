# GitHub Copilot Instructions

## Priority Guidelines

When generating code for this repository:

1. **Version Compatibility**: This is a Cacti plugin (`intropage`, "Intropage/Dashboard", version 4.0.5) targeting Cacti 1.2.17+
2. **Context Files**: Prioritize patterns and standards defined in this file (`.github/copilot-instructions.md`)
3. **Codebase Patterns**: When context files don't provide specific guidance, scan the codebase for established patterns
4. **Architectural Consistency**: Maintain plugin-based architecture extending Cacti core
5. **Code Quality**: Prioritize security, maintainability, and compatibility in all generated code

## Technology Stack

### Core Technologies
- **PHP**: Compatible with Cacti 1.2.x supported versions
- **Platform**: Cacti Plugin Architecture — modular, panel-library-based dashboard/intropage
- **Database**: MySQL/MariaDB
- **Frontend**: C3.js/D3.js for charts, jQuery for AJAX

### Key Dependencies
- Cacti core framework (`api_plugin_*`, `db_*`)
- Optional integration with `thold`, `mactrack`, `syslog` plugins for their respective panels

## Project Structure

```
intropage/                # Repository root (install to plugins/intropage/ in Cacti)
├── include/
│   ├── functions.php        # Core utilities, panel management
│   ├── database.php          # Schema setup/teardown
│   ├── settings.php           # Admin UI configuration
│   └── tab.php                 # Tab integration hooks
├── panellib/                       # Panel libraries (modular; one file per category)
│   ├── system.php / poller.php / graphs.php / thold.php / mactrack.php
├── locales/                          # Translation files (gettext)
├── themes/                             # CSS for different Cacti themes
├── tests/                                # Test suite
├── display.php                             # Renders panels on console or dedicated tab (AJAX updates)
├── intropage.php                             # Main entry point (standalone tab mode)
├── poller_intropage.php                        # Background data collection (CLI)
├── INFO                                          # Plugin metadata (name, version, compat)
├── README.md
└── setup.php                                       # Plugin install/uninstall/upgrade hooks
```

## Naming Conventions

### Function Names
- **Panel library registration functions** MUST be named `register_<basename>()`, matching the panel library file name exactly (e.g. `system.php` → `register_system()`).
- **Hook/lifecycle functions** use the `intropage_` prefix: `intropage_show_tab()`, `intropage_config_arrays()`, `intropage_poller_bottom()`.
- Match the existing prefix used by the function you are editing; do not introduce a new naming scheme.

### Database Tables
Plugin tables are prefixed `plugin_intropage_`: `plugin_intropage_panel_definition`, `plugin_intropage_user_auth`, `plugin_intropage_panel_data`.

## Code Style

### Indentation and Formatting
- **Tabs**: Use tabs (not spaces) for indentation throughout all PHP files.
- **Braces**: Opening brace on the same line for functions and control structures.
- **Spacing**: Space after control structure keywords (`if`, `foreach`, `while`).

### File Headers
ALL PHP files MUST include the standard GPL v2 license header used throughout this repository (see `setup.php`), crediting "The Cacti Group".

## Security Standards

### SQL Query Security
**Always use prepared statements**:

```php
// CORRECT
$value = db_fetch_cell_prepared('SELECT column FROM table WHERE id = ?', array($id));
$rows  = db_fetch_assoc_prepared('SELECT * FROM table WHERE user_id = ?', array($user_id));
db_execute_prepared('UPDATE table SET col = ? WHERE id = ?', array($val, $id));

// WRONG
db_fetch_row("SELECT * FROM table WHERE id = $id");
```

### User/Device Filtering
Most queries need `WHERE user_id IN (0, ?)` to include both system-level (`user_id=0`) and user-specific data. Always filter devices through `intropage_get_allowed_devices($user_id)` before returning device-scoped data.

### Input Validation
All user input MUST be validated with `get_filter_request_var()` or `get_nfilter_request_var()`.

`get_filter_request_var()` (and its `gfrv()` shorthand, where available) called with only the
`$name` argument (no regex/filter as the 2nd/3rd argument) already validates the value as numeric
and returns it as a **string** -- it does not return an int, and it halts execution if the request
value is not numeric. Because of this, do NOT cast its output to `(int)` when the result is only
used for string output (e.g. `print`/`echo`, string concatenation, embedding in HTML/JS); the cast
is redundant. Only cast when the value is genuinely used in an integer/numeric context (e.g.
arithmetic, strict `===` comparisons).

### Output Escaping
Output MUST be escaped with `html_escape()` or `__esc()`.

### Access Control
Realm permissions: `api_user_realm_auth('intropage.php')` gates access. Panel permissions: `is_panel_allowed()` checks user/group authorization before rendering a panel.

## Database Operations

Schema setup/teardown lives in `include/database.php`; keep new tables under the `plugin_intropage_` prefix.

## Internationalization

ALL user-facing strings MUST use `__()`/`__esc()` with the `'intropage'` text domain; for plain strings it is the second argument, and with format arguments it is the final argument:


```php
__('Text to translate', 'intropage');
__esc('Text needing HTML escaping', 'intropage');
```

## Plugin Architecture

### Panel Library System
Every panel library file (`panellib/*.php`) MUST define a `register_<basename>()` function returning a panels array with keys like `name`, `description`, `class`, `level` (`PANEL_SYSTEM`/`PANEL_USER`), `refresh`, `width`, `height`, `priority`, `alarm`, `requires`, `update_func`, `details_func`, `trends_func`.

### Data Flow
1. Panel libraries register panels via `register_<basename>()`.
2. Panels are stored in `plugin_intropage_panel_definition`.
3. User permissions live in `plugin_intropage_user_auth` (JSON-encoded).
4. The poller collects data and stores it in `plugin_intropage_panel_data`.
5. The frontend fetches results via AJAX and renders with alarm colors (red/yellow/green/grey).

### Plugin Hooks
Register hooks in `setup.php`: `config_settings`, `top_header_tabs`, `console_after`, `page_head`, `graph_buttons`, `poller_bottom`, `user_admin_tab`, plus the user/group admin lifecycle hooks (`user_remove`, `user_group_admin_tab`, `user_group_remove`, `copy_user`).

Data-collection (`update_func`) functions receive `($panel, $user_id)`, update/save the panel via `save_panel_result($panel, $user_id)`, and apply `intropage_get_allowed_devices($user_id)` only to device-scoped queries.

Data-collection (`update_func`) functions receive `($panel, $user_id)` and must return a result array with `name`, `alarm`, and `data` keys, filtering by `intropage_get_allowed_devices($user_id)` before querying.

## Best Practices

1. Keep new panels in `panellib/`, following the `register_<basename>()` naming rule exactly.
2. Always filter device-scoped queries through `intropage_get_allowed_devices()`.
3. Call `session_write_close()` before long operations to avoid blocking other requests.
4. Wrap all user-facing strings with `__()`/`__esc()` and the `intropage` domain.

## Common Pitfalls to Avoid

```php
// WRONG - forgot the __() wrapper, string won't translate
print 'Access denied';

// CORRECT
print __('Access denied', 'intropage');

// WRONG - missing user_id filter shows all data instead of authorized devices
$rows = db_fetch_assoc('SELECT * FROM host');

$scope = intropage_device_scope($user_id);
if ($scope['simple']) {
	$rows = db_fetch_assoc_prepared('SELECT * FROM host');
} elseif ($scope['allowed'] === false) {
	$rows = [];
} else {
	$ids  = explode(',', $scope['allowed']);
	$rows = db_fetch_assoc_prepared(
		'SELECT * FROM host WHERE id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')',
		$ids
	);
}
$rows = db_fetch_assoc("SELECT * FROM host WHERE id IN($allowed)");
```

## Version Control

Document all changes in `CHANGELOG.md`; use descriptive commit messages referencing issue/PR numbers when applicable.

## CI & Dependency Baselines

- Do not commit a `composer.json` or `composer.lock` in this plugin's own repo root — the shared CI workflow installs Pest/dev dependencies into Cacti's own Composer-managed vendor tree (checked out alongside the plugin). Use Cacti's `composer.json`, not a plugin-local one.
- Do not add a plugin-local `.phpstan.neon`/`phpstan.neon` or `.php-cs-fixer.php`/`.php-cs-fixer.dist.php` — lint/static-analysis steps run against Cacti's own config from the Cacti core checkout, targeting this plugin's directory. Use the Cacti version, not a plugin-local config.
- Prefer Cacti's `cacti_count()`/`cacti_sizeof()` wrappers over the raw `count()`/`sizeof()` builtins in new or edited code.

## Internationalization (i18n)

- Translatable strings are managed with GNU gettext via `locales/build_gettext.sh`. `locales/po/cacti.pot` is the source template; Weblate owns syncing the per-language `.po`/`.mo` files from it.
- When a pull request adds or changes a string wrapped in `__()`/`__n()`/`__esc()`/`__x()`/`__xn()`/`__gettext()`, run `locales/build_gettext.sh` before pushing and add the resulting change to `locales/po/cacti.pot` only. Do not commit the regenerated per-language `.po`/`.mo` files in the same PR — Weblate takes care of the rest.

## References

- [Cacti main repo](https://github.com/Cacti/cacti/tree/1.2.x)
- [Cacti Documentation](https://www.github.com/Cacti/documentation)
- `README.md` for feature descriptions
- `CHANGELOG.md` for version history

## Security & Quality Conventions

These conventions apply across the Cacti plugin fleet and should be followed whenever touching
existing code or adding new code, not just in dedicated cleanup passes:

- **No hardcoded third-party hosts.** Never hardcode a third-party IP address, hostname, or URL
  in plugin code (even for tooling/download helpers). Expose it as a plugin setting instead, with
  secure-by-default values (e.g. an SSL-verification setting that defaults to verify-on).
- **Prepared statements over `db_qstr()`.** Build dynamic `WHERE` clauses using the
  `$sql_where`/`$sql_params` prepared-statement pattern, not string concatenation via `db_qstr()`.
- **Use `html_escape_request_var()`.** Prefer it over the `html_escape(get_request_var(...))` call
  chain.
- **Harden `unserialize()`.** Always pass `['allow_classes' => false]` as the second argument.
- **i18n text domain.** Every `__()`/`__esc()` call must include this plugin's text domain as the
  final argument, except when deliberately comparing against a literal, untranslated Cacti-core
  label.
- **Plugin table-creation API.** Use `api_plugin_db_table_create()`/`api_plugin_db_add_column()`
  (from Cacti core's `lib/plugins.php`) instead of raw `CREATE TABLE`/`ALTER TABLE ... ADD COLUMN`.
  Both are idempotent (safe no-ops when already applied), so the same call can run unconditionally
  from both the install AND upgrade paths.
- **PHPDoc shape.** Every function gets a PHPDoc block: a one-line description, a blank comment
  line, `@param` lines, a blank comment line, then `@return`. Infer parameter/return types from
  actual usage; don't change the function's real type-hints in the same pass (let static analysis
  flag mismatches separately). Skip vendored third-party library files.
