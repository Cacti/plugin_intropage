<?php
/* vim: ts=4
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2023 The Cacti Group, Inc.                           |
 | Copyright (C) 2004-2023 Petr Macek                                      |
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

function intropage_show_tab() {
	global $config;

	$console_access = api_plugin_user_realm_auth('index.php');
	$login_opts     = db_fetch_cell_prepared('SELECT login_opts FROM user_auth WHERE id = ?', array($_SESSION['sess_user_id']));

	if ($config['poller_id'] == 1 || ($config['poller_id'] > 1 && $config['connection'] == 'online')) {
		if (api_user_realm_auth('intropage.php') && isset($_SESSION['sess_user_id'])) {
			if (($console_access && $login_opts == 4) || !$console_access)	{
				$cp = false;

				if (basename($_SERVER['PHP_SELF']) == 'intropage.php') {
					$cp = true;
				}

				print('<a href="' . $config['url_path'] . 'plugins/intropage/intropage.php"><img src="' . $config['url_path'] . 'plugins/intropage/images/tab_intropage' . ($cp ? '_down' : '') . '.gif" alt="intropage"  align="absmiddle" border="0"></a>');
			}
		}
	}
}
