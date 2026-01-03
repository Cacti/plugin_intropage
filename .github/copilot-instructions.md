# Intropage Cacti Plugin Development Guide

## Project Overview

This is a Cacti plugin that provides a customizable dashboard/intropage for displaying system metrics, graphs, and monitoring data. The plugin uses a modular panel system where panels collect, display, and trend various Cacti statistics.

## Architecture

### Core Components

1. **Panel Library System** (`panellib/*.php`) - Modular panel definitions organized by category (system, poller, graphs, alerts, etc.)
2. **Hook System** (`setup.php`) - Registers plugin hooks via `api_plugin_register_hook()` to integrate with Cacti core
3. **Database Layer** (`include/database.php`) - Manages plugin-specific tables for panels, dashboards, trends, and user preferences
4. **Display Engine** (`display.php`) - Renders panels on console or dedicated tab with AJAX updates
5. **Poller Integration** (`poller_intropage.php`) - CLI script for background data collection

### Key Data Flow

```
Panel Registration → Panel Definition (DB) → User Authorization → Data Collection (Poller) → Display/Render
```

1. Panel libraries register panels via `register_<basename>()` functions
2. Panels stored in `plugin_intropage_panel_definition` table
3. User permissions in `plugin_intropage_user_auth` (JSON-encoded)
4. Poller collects data, stores in `plugin_intropage_panel_data`
5. Frontend fetches via AJAX, displays with alarm colors (red/yellow/green/grey)

## Critical Coding Patterns

### Panel Library Registration

Every panel library file (`panellib/*.php`) must have a registration function:

```php
function register_<basename>() {
    global $registry;
    
    $registry['category_name'] = array(
        'name'        => __('Display Name', 'intropage'),
        'description' => __('Description', 'intropage')
    );
    
    $panels = array(
        'panel_id' => array(
            'name'         => __('Panel Name', 'intropage'),
            'description'  => __('Panel description', 'intropage'),
            'class'        => 'category_name',
            'level'        => PANEL_SYSTEM,  // or PANEL_USER
            'refresh'      => 3600,          // seconds between updates
            'trefresh'     => false,         // or seconds for trends
            'force'        => true,          // allow manual reload
            'width'        => 'quarter-panel', // or 'half-panel', 'full-panel'
            'height'       => 'normal',      // or 'double', 'triple'
            'height_fixed' => false,         // prevent user resizing
            'priority'     => 10,            // display order
            'alarm'        => 'grey',        // default: grey/green/yellow/red
            'requires'     => 'plugin_name', // optional dependency (space-separated)
            'update_func'  => 'function_name',  // data collection function
            'details_func' => 'detail_function', // or false
            'trends_func'  => 'trends_function'  // or false
        )
    );
    
    return $panels;
}
```

**Critical**: Function name MUST be `register_` + basename of file (e.g., `system.php` → `register_system()`).

### Panel Data Collection Functions

Update functions receive panel metadata and user_id, return data array:

```php
function panel_update_function($panel, $user_id) {
    $result = array(
        'name'   => $panel['definition']['name'],
        'alarm'  => 'green',  // Set based on thresholds
        'data'   => '',       // HTML content
    );
    
    // Collect data with proper user device filtering
    $allowed_devices = intropage_get_allowed_devices($user_id);
    
    // Query using prepared statements
    $data = db_fetch_assoc_prepared('SELECT ...
        WHERE host_id IN (' . $allowed_devices . ')');
    
    // Build HTML output
    $result['data'] = '<div class="inpa_panel">...</div>';
    
    // Save to database
    save_panel_result($result, $user_id);
    
    return $result;
}
```

### Database Conventions

**Always use prepared statements:**

```php
// Fetch single value
$value = db_fetch_cell_prepared('SELECT column FROM table WHERE id = ?', array($id));

// Fetch single row
$row = db_fetch_row_prepared('SELECT * FROM table WHERE id = ?', array($id));

// Fetch multiple rows
$rows = db_fetch_assoc_prepared('SELECT * FROM table WHERE user_id = ?', array($user_id));

// Execute without return
db_execute_prepared('UPDATE table SET col = ? WHERE id = ?', array($val, $id));
```

**User filtering pattern**: Most queries need `WHERE user_id IN (0, ?)` to include both system-level (user_id=0) and user-specific data.

### Internationalization (i18n)

**All user-facing strings MUST use `__()`:**

```php
__('Text to translate', 'intropage')
__('Format with %s parameter', $value, 'intropage')
__esc('Text needing HTML escaping', 'intropage')
```

The third parameter `'intropage'` is the text domain and is **required** for all translations.

Translation files: `locales/po/*.po` → compiled to `locales/LC_MESSAGES/*.mo`

### Hook Registration Pattern

In `setup.php`, all hooks use this pattern:

```php
api_plugin_register_hook('intropage', 'hook_name', 'callback_function', 'file_path.php');
api_plugin_register_realm('intropage', 'file.php', 'Description', 1);
```

Common hooks: `config_settings`, `top_header_tabs`, `console_after`, `page_head`, `graph_buttons`, `poller_bottom`, `user_admin_tab`

### User Permissions

Permissions stored as JSON in `plugin_intropage_user_auth.permissions`:

```php
// Check single panel permission
if (is_panel_allowed($panel_id, $user_id)) {
    // User has access
}

// Get all allowed panels
$panels = get_allowed_panels($user_id);

// User group permissions merge with individual permissions (OR logic)
```

### Panel Display Lifecycle

1. `initialize_panel_library()` - Load all panel definitions from `panellib/*.php`
2. `update_registered_panels()` - Sync panel metadata to database
3. `get_panel($panel_id, $user_id)` - Fetch panel data + metadata
4. `get_panel_data()` - Retrieve cached panel content
5. Frontend calls `/intropage.php?action=reload&panel_id=X` for AJAX updates

### Session and State Management

```php
$_SESSION['sess_user_id']     // Current user
$_SESSION['dashboard_id']     // Active dashboard (user can have multiple)
$login_opts                   // 1=default, 2=console, 3=graphs, 4=intropage tab
```

## Development Workflows

### Adding a New Panel

1. Choose appropriate category file in `panellib/` or create new one
2. Add panel definition to `register_*()` function return array
3. Implement `update_func` to collect data
4. Optionally implement `details_func` for modal view
5. Test by reloading panel library: Console → Settings → Intropage → Force Panel Reload
6. Grant permissions: Console → Users → (edit) → Intropage tab

### Testing Changes

- **Manual reload**: Click refresh icon on panel (if `'force' => true`)
- **Poller testing**: Run `php -q poller_intropage.php --debug --force`
- **Database inspection**: Check `plugin_intropage_panel_data` for cached content
- **Frontend**: Check browser console for AJAX errors

### Common Pitfalls

1. **Forgot `__()` wrapper**: Strings won't translate
2. **Missing user_id filter**: Shows all data instead of user-authorized devices
3. **Wrong function name**: Must match `register_<basename>` pattern
4. **SQL injection**: Always use prepared statements with placeholders
5. **Session conflicts**: Call `session_write_close()` before long operations to unblock other requests
6. **Panel not appearing**: Check `requires` field - dependent plugin may not be installed

## File Organization

```
setup.php              - Plugin hooks, install/uninstall
intropage.php          - Main entry point (standalone tab mode)
display.php            - Rendering logic
poller_intropage.php   - Background data collection (CLI)
include/
  functions.php        - Core utilities, panel management
  database.php         - Schema setup/teardown
  settings.php         - Admin UI configuration
  tab.php             - Tab integration hooks
  variables.php        - Configuration arrays
panellib/             - Panel libraries (modular)
  system.php          - System info panels
  poller.php          - Poller statistics
  graphs.php          - Graph management
  thold.php           - Threshold/alert panels
  mactrack.php        - MacTrack plugin integration
locales/              - Translation files (gettext)
themes/               - CSS for different Cacti themes
```

## External Dependencies

- **Cacti Core**: Uses Cacti's plugin API, database abstraction, user auth system
- **Optional Plugins**: thold (thresholds), mactrack (MAC tracking), syslog (syslog panels)
- **Frontend**: C3.js/D3.js for charts, jQuery for AJAX
- **PHP**: Requires gettext extension for translations

## Key Constants

```php
PANEL_SYSTEM = 0;  // System-wide panel (user_id = 0)
PANEL_USER = 1;    // Per-user panel data
```

## Security Considerations

1. All user input validated with `get_filter_request_var()` or `get_nfilter_request_var()`
2. Output escaped with `html_escape()` or `__esc()`
3. Realm permissions: `api_user_realm_auth('intropage.php')` gates access
4. Panel permissions: `is_panel_allowed()` checks user/group authorization
5. Device filtering: `intropage_get_allowed_devices()` enforces Cacti device permissions

## Debugging

Set Cacti log verbosity: Console → Configuration → Settings → General → Log Level

Check logs:
- Cacti log: `tail -f /path/to/cacti/log/cacti.log | grep -i intropage`
- Poller debug: `php -q poller_intropage.php --debug`

Enable panel force reload for immediate testing (set `'force' => true` in panel definition).
