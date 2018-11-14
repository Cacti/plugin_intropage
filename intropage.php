<?php

chdir('../../');
include_once('./include/auth.php');

include_once($config['base_path'] . '/plugins/intropage/include/settings.php');

include_once($config['base_path'] . '/plugins/intropage/display.php');


set_default_action();


$lopts = db_fetch_cell('SELECT intropage_opts FROM user_auth WHERE id=' . $_SESSION['sess_user_id']);
if ($lopts == 1) {	// separated tab, we need header
	general_header();
}

display_information();
intropage_console_after();

if ($lopts == 1) {	// separated tab, we need footer
	bottom_footer();
}
