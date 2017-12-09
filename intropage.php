<?php

chdir('../../');
include_once("./include/auth.php");
include_once("./plugins/intropage/display.php");


// it works bad with user group, temporary disabled
// set default page
if (isset ($_GET["default"]) && $_GET["default"] == "true")             {

    if (isset ($_GET["how"]))   {
        $_GET["how"] = intval ($_GET["how"]);

        if ($_GET["how"] >= 1 && $_GET["how"] <= 5 )
        db_execute ("update user_auth set login_opts = ". $_GET["how"] . " where id = " . $_SESSION["sess_user_id"]);
    }
}



set_default_action();


$lopts = db_fetch_cell('SELECT intropage_opts FROM user_auth WHERE id=' . $_SESSION['sess_user_id']);
if ($lopts == 1)	// separated tab, we need header
    general_header();

display_information();
intropage_console_after();

if ($lopts == 1)	// separated tab, we need footer
	bottom_footer();

?>
