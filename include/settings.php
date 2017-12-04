<?php

function intropage_config_form ()	{
	global $fields_user_user_edit_host;
	
	$temp = array(
		"intropage_opts" => array(
			"friendly_name" => "Intro Page Options",
			"method" => "radio",
			"default" => "0",
			"description" => "How we should display the intropage. <strong>For users without console access you must choose separated tab</strong>",
			"value" => "|arg1:intropage_opts|",
			"items" => array(
				0 => array(
					"radio_value" => "0",
					"radio_caption" => "Show the Intropage plugin screen in console screen"),
				1 => array(
					"radio_value" => "1",
					"radio_caption" => "Show the Intropage plugin screen in separated tab"),
			),
		),
	);
	
	$new = array();
	foreach($fields_user_user_edit_host as $key => $val) {
		$new = array_merge($new,array($key => $val));
		if ($key == 'login_opts') {
			$new = array_merge($new,$temp);
		}
	}
	
	$fields_user_user_edit_host = $new;
}

function intropage_config_settings()	{
	global $tabs, $settings, $config, $intropage_settings;
	include_once($config['base_path'] . '/plugins/intropage/include/variables.php');
	
	$tabs["intropage"] = "Intropage";
	$settings["intropage"] = $intropage_settings;
}

function intropage_login_options_navigate ()	{
	global $config;
	
	$lopts = db_fetch_cell('SELECT intropage_opts FROM user_auth WHERE id=' . $_SESSION['sess_user_id']);
	
	if ($lopts == 1) { // tab

	    // from auth_login.php - graph view or intropage (for users without console)
	    $lopts = db_fetch_cell('SELECT login_opts FROM user_auth WHERE id=' . $_SESSION['sess_user_id']);
		if ($lopts == 3)
		    header('Location: ' . $config['url_path'] . 'graph_view.php' . ($newtheme ? '?newtheme=1':''));
		else
	    	    header("Location: " . $config['url_path'] . "plugins/intropage/intropage.php");
	}

	
}

function intropage_console_after() {
	global $config;
	$lopts = db_fetch_cell('SELECT intropage_opts FROM user_auth WHERE id=' . $_SESSION['sess_user_id']);

	
	if ($lopts == 1) { // in tab
	} else {  // in console
		include_once($config['base_path'] . "/plugins/intropage/display.php");
		display_information();


		//$x['data'] = "You can choose view between separated tab and console, you can set it up in Console -> User Management -> User -> Intropage Options ";
//		intropage_display_panel(100,"green","Hint",$x);
//	          intropage_display_panel($size,$value['alarm'],$value['name'],$value);
//	    print "<br/>HINT: You can choose view between separated tab and console, you can set it up in Console -> User Management -> User -> Intropage Options";
	}

	    intropage_display_hint();

	// reload
	$timeout = read_config_option("intropage_autorefresh");
	if ($timeout > 0)     {
	    $timeout *= 1000;

print <<<EOF

<script type="text/javascript">
var timeout = setInterval(reloadChat, $timeout);
function reloadChat () {
     $('#obal').load('$config[url_path]plugins/intropage/intropage_ajax.php?header=false');
}
</script>

EOF;

	}
}

function intropage_user_admin_setup_sql_save($save) {
	global $settings_user;

	$save['intropage_opts'] = form_input_validate(get_nfilter_request_var('intropage_opts'), 'intropage_opts', '^[01]$', true, 3);

	return $save;
}

///* it works bad with user group
function intropage_display_hint ()       {
    global $config;

    $lopts = db_fetch_cell('SELECT login_opts FROM user_auth WHERE id=' . $_SESSION['sess_user_id']);
    // login options can change user group! 

    switch ($lopts)     { // after login: 3=graphs, 4=intropage tab, 5=intropage in console
        case "1" :
        case "2" :
            if (db_fetch_assoc("select realm_id from user_auth_realm where user_id='" . $_SESSION["sess_user_id"] . "' and user_auth_realm.realm_id=8"))        {
                echo "<b>Hint: </b>If you want to see Intropage plugin as default page (in console or separated tab), you can set it up in Console -> User Management -> User -> Login Options <br/>";
            }
            else	{ // no console right
                echo "<b>Hint: </b>If you want to see Intropage plugin as default page, <a href=\"" . $config['url_path'] . "plugins/intropage/intropage.php?default=true&how=4\">click here </a><BR/>\n";
            }
        break;

        case "3":
            if (!db_fetch_assoc("select realm_id from user_auth_realm where user_id='" . $_SESSION["sess_user_id"] . "' and user_auth_realm.realm_id=8"))
                echo "<b>Hint: </b>If you want to see Intropage as default page <a href=\"" . $config['url_path'] . "plugins/intropage/intropage.php?default=true&how=4\">click here </a><br/>\n";
        break;

        case "4":
            if (db_fetch_assoc("select realm_id from user_auth_realm where user_id='" . $_SESSION["sess_user_id"] . "' and user_auth_realm.realm_id=8"))
                echo "<b>Hint: </b>If you want to see Intropage plugin as default page (in console or separated tab), you can set it up in Console -> User Management -> User -> Login Options <br/>";
            else
                echo "<b>Hint: </b>If you want to see Graphs as default page <a href=\"" . $config['url_path'] . "plugins/intropage/intropage.php?default=true&how=3\">click here </a><br/>\n";
        break;

        case "5" :
            if (db_fetch_assoc("select realm_id from user_auth_realm where user_id='" . $_SESSION["sess_user_id"] . "' and user_auth_realm.realm_id=8"))
                echo "<b>Hint: </b>You can choose view between separated tab and console, you can set it up in Console -> User Management -> User -> Login Options <br/>";
        break;
    }
}


?>
