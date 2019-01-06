<?php

chdir('../../');
include_once('./include/auth.php');



if (isset_request_var('reload_panel') &&
        get_filter_request_var('reload_panel', FILTER_VALIDATE_REGEXP, array('options' => array('regexp' => '/^[0-9]{1,3}$/')))) {
// reload jednoho panelu
    echo "<b>Here will be new panel content - comming soon</b><br/>actual time" . date ("H:m:i");
    echo "panel id " . get_request_var('reload_panel');
}
else	{	// reload all
    include_once('./plugins/intropage/display.php');
    display_information();
}
