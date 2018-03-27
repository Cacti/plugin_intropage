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
					"radio_caption" => "Show the Intropage plugin screen in console screen (you need console access permission!)"),
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

	array_push ($fields_user_user_edit_host['login_opts']['items'],array("radio_value"=>"4","radio_caption"=>"Show Intropage (no matter in console or tab)"));
//        array_push ($fields_user_user_edit_host['login_opts']['items'],array("radio_value"=>"5","radio_caption"=>"Show the Intropage plugin screen in console screen"));


}

function intropage_config_settings()	{
	global $tabs, $settings, $config, $intropage_settings;
	include_once($config['base_path'] . '/plugins/intropage/include/variables.php');
	
	$tabs["intropage"] = "Intropage";
	$settings["intropage"] = $intropage_settings;
}

function intropage_login_options_navigate ()	{
	global $config;
	
	$intropage_lopts = db_fetch_cell('SELECT intropage_opts FROM user_auth WHERE id=' . $_SESSION['sess_user_id']);
	$system_lopts = db_fetch_cell('SELECT login_opts FROM user_auth WHERE id=' . $_SESSION['sess_user_id']);
	$console_access = (db_fetch_assoc("select realm_id from user_auth_realm where user_id='" . $_SESSION["sess_user_id"] . "' and user_auth_realm.realm_id=8"))?true:false;

               $newtheme = false;
                if (user_setting_exists('selected_theme', $_SESSION['sess_user_id']) && read_config_option('selected_theme') != read_user_setting('selected_theme')) {
                        unset($_SESSION['selected_theme']);
                        $newtheme = true;
                }
//echo $system_lopts;	

	if ($console_access)	{
	    if ($system_lopts == 4 && $intropage_lopts == 1)	// intropage as default
                    header("Location: " . $config['url_path'] . "plugins/intropage/intropage.php");



 // ostatni resi asi resi auth login
	    
	
	}
	else	{	// no console access
	    if ($system_lopts == 4 || $system_lopts == 2 )	// intropage as default
                    header("Location: " . $config['url_path'] . "plugins/intropage/intropage.php");
	
	    
	    if ($system_lopts == 3)		
                    header('Location: ' . $config['url_path'] . 'graph_view.php' . ($newtheme ? '?newtheme=1':''));
	}


	
}

function intropage_console_after() {
	global $config;
	$lopts = db_fetch_cell('SELECT intropage_opts FROM user_auth WHERE id=' . $_SESSION['sess_user_id']);

	
	if ($lopts == 1) { // in tab
	} else {  // in console
		include_once($config['base_path'] . "/plugins/intropage/display.php");
		display_information();
	}

	// reload
	$timeout = read_user_setting("intropage_autorefresh");
	if ($timeout > 0)     {
	    $timeout *= 1000;

print <<<EOF

<script type="text/javascript">
var timeout = setInterval(reloadChat, $timeout);
function reloadChat () {
     $('#megaobal').load('$config[url_path]plugins/intropage/intropage_ajax.php');
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


?>