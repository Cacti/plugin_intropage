<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2021 The Cacti Group                                 |
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
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

chdir('../../');
include_once('./include/auth.php');
include_once($config['base_path'] . '/plugins/intropage/include/settings.php');
include_once($config['base_path'] . '/plugins/intropage/display.php');

set_default_action();

if (!function_exists("array_column")) {
	function array_column($array,$column_name) {
        	return array_map(function($element) use($column_name){return $element[$column_name];}, $array);
    	}
}

if (empty($_SESSION['login_opts']))     {   // potrebuju to mit v session, protoze treba mi zmeni z konzole na tab a pak spatne vykresluju
	$login_opts = db_fetch_cell_prepared('SELECT login_opts
		FROM user_auth
		WHERE id = ?',
		array($_SESSION['sess_user_id']));

	$_SESSION['login_opts'] = $login_opts;
}

if ($_SESSION['login_opts'] == 4 || $_SESSION['login_opts'] == 1) {	// separated tab, we need header
	general_header();
}

display_information();

if ($_SESSION['login_opts'] == 4 || $_SESSION['login_opts'] == 1) {	// separated tab, we need footer
	bottom_footer();
}

