# intropage panel library

## Background

The Panel Library consists of several files that are used to categorize various
panels developed either by the author or other creators.  The way you extend
intropage is by writing a serious of collection, display, and trend functions
and then registering the file that includes these functions using a registration
function that includes the word 'register' and underscore, and the basename of
the file.

So, for example: if you have a plugin called happy, the first function in that
file will be 'register_happy()'.  This file returns an array to the caller that
includes all the relevante information that your extension needs to provide for
the above mentioned functions.  It's that simple.

The format of the array should follow the example below:

```php
$panels = array(
	'mactrack' => array(
		'name'         => __('Mactrack Plugin', 'intropage'),
		'description'  => __('Plugin Mactrack statistics. MAY CONTAIN SENSITIVE INFORMATION FOR ALL USERS!', 'intropage'),
		'level'        => PANEL_USER,
		'interval'     => 900,
		'priority'     => 28,
		'alarm'        => 'green',
		'requires'     => 'mactrack',
		'update_func'  => 'mactrack',
		'details_func' => false,
		'trends_func'  => false
	),
	'mactrack_sites' => array(
		'name'         => __('Mactrack Sites', 'intropage'),
		'description'  => __('Plugin Mactrack sites statistics. MAY CONTAIN SENSITIVE INFORMATION FOR ALL USERS!', 'intropage'),
		'level'        => PANEL_USER,
		'interval'     => 900,
		'priority'     => 27,
		'alarm'        => 'grey',
		'requires'     => 'mactrack',
		'update_func'  => 'mactrack_sites',
		'details_func' => 'mactrack_sites_detail',
		'trends_func'  => false
	)
);
```

The panel level can be either PANEL_SYSTEM, or PANEL_USER.  A system panel will
be the same for every user, and PANEL_USER will be customized for every user of
the panel based upon their permissions.

The interval is the default collection or update interval for the panel.  This
can be overriden in a still to be implemented enhancement.

The 'trends_func' is also yet to be implemented.

## Author
Petr Macek (petr.macek@kostax.cz)
Larry Adams (thewitness@cacti.net)

-----------------------------------------------
Copyright (c) 2004-2023 - The Cacti Group, Inc.
