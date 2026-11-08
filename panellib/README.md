# intropage panel library

## Background

The Panel Library consists of several files that are used to categorize various
panels developed either by the author or other creators.  The way you extend
intropage is by writing a series of collection, display, and trend functions
and then registering the file that includes these functions using a registration
function that includes the word 'register' and underscore, and the basename of
the file.

So, for example: if you have a file called happy.php in this directory, the
registration function in that file is 'register_happy()'.  This function returns
an array to the caller that includes all the information that Intropage needs
about the panels of the file.  Every '.php' file in this directory is loaded;
a plugin cannot register a panel from a file in its own directory, the file has
to be placed here.

The registration function also names the group (the 'class') of its panels in
the global $registry. The name and description are used on the permission pages
of users and groups.

The format of the array follows the example below. This file is tested: it
works on Cacti 1.2.x and on the development branch.

```php
function register_example(): array {
	global $registry;

	$registry['example'] = array(
		'name'        => __('Example Panels', 'intropage'),
		'description' => __('Panels of the example', 'intropage')
	);

	return array(
		'example_panel' => array(
			'name'         => __('Example Panel', 'intropage'),
			'description'  => __('Example', 'intropage'),
			'class'        => 'example',
			'level'        => PANEL_USER,
			'refresh'      => 900,
			'trefresh'     => false,
			'force'        => true,
			'width'        => 'quarter-panel',
			'height'       => 'normal',
			'height_fixed' => true,
			'priority'     => 28,
			'alarm'        => 'green',
			'requires'     => false,
			'update_func'  => 'example_panel',
			'details_func' => false,
			'trends_func'  => false
		)
	);
}

function example_panel($panel, $user_id): void {
	$panel['alarm'] = 'green';
	$panel['data']  = 'hello';

	save_panel_result($panel, $user_id);
}
```

All the keys are used; a panel without 'class', 'refresh' or 'trefresh' is
stored with empty values, PHP warns, and showing it fails.

| Key | Meaning |
| --- | --- |
| name, description | Texts shown in the panel list and on the permission pages |
| class | The key of the group in $registry |
| level | PANEL_SYSTEM or PANEL_USER, see below |
| refresh | Default update interval in seconds |
| trefresh | Default interval of the trend function in seconds, or false |
| force | true when the panel may be reloaded on demand from the dashboard |
| width | 'quarter-panel', or another value (such as 'half-panel') for a panel of double width |
| height | 'normal', 'double' or 'triple', the starting height |
| height_fixed | true when the user cannot change the height |
| priority | Default priority of the panel in the sort order |
| alarm | Starting status colour: 'green', 'yellow', 'red' or 'grey' |
| requires | Directory name of the plugin the panel needs (a comma separated list is allowed), or false |
| update_func | Name of the function that gathers the data |
| details_func | Name of the function for the detail window, or false |
| trends_func | Name of the function that records trend values, or false |

The panel level can be either PANEL_SYSTEM, or PANEL_USER.  A system panel will
be the same for every user (include height and height_fixed parameter),
PANEL_USER will be customized for every user of the panel based upon their permissions.

The update function is called by the poller as update_func($panel, $user_id) for
every stored row of the panel, with 0 as the user for a system panel, and when a
user reloads the panel. It sets $panel['data'] and $panel['alarm'] and calls
save_panel_result($panel, $user_id). A panel with a trend chart can take a
third parameter, the timespan in seconds.

The detail function takes no parameters and returns an array with the keys
'name', 'alarm' and 'detail' (the HTML of the window).

The trend function takes no parameters. The poller calls it once per cycle for
the panel, when the trend interval has passed, and it stores its values in the
table plugin_intropage_trends.

The refresh interval is the default update interval for the panel.  A user can
change it for their own panels, and an administrator for system panels, with
the cog icon of the dashboard.

After adding or changing a file, an administrator runs "Reload Panel
Definitions" in the Actions list of the dashboard so that the panel definitions
in the database are updated.

## Author
Petr Macek (petr.macek@kostax.cz)
Larry Adams (thewitness@cacti.net)

-----------------------------------------------
Copyright (c) 2004-2026 - The Cacti Group, Inc.
