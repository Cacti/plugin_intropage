<?php

chdir('../../');
include_once("./include/auth.php");
include_once("./plugins/intropage/display.php");

set_default_action();


$lopts = db_fetch_cell('SELECT intropage_opts FROM user_auth WHERE id=' . $_SESSION['sess_user_id']);
if ($lopts == 1)
	top_header();

display_informations();

intropage_console_after();

if ($lopts == 1)
	bottom_footer();

?>
