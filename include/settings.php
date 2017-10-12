<?php

function intropage_config_form ()	{
	global $fields_user_user_edit_host;
	
	$temp = array(
		"intropage_opts" => array(
			"friendly_name" => "Intro Page Options",
			"method" => "radio",
			"default" => "0",
			"description" => "How we should display the intropage.",
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
	
	if ($lopts == 1) {
		header("Location: " . $config['url_path'] . "plugins/intropage/intropage.php");
	}
}

function intropage_console_after() {
	global $config;
	$lopts = db_fetch_cell('SELECT intropage_opts FROM user_auth WHERE id=' . $_SESSION['sess_user_id']);
	
	if ($lopts == 1) {
	} else {
		include_once($config['base_path'] . "/plugins/intropage/display.php");
		display_informations();

		//$x['data'] = "You can choose view between separated tab and console, you can set it up in Console -> User Management -> User -> Intropage Options ";
//		intropage_display_panel(100,"green","Hint",$x);
//	          intropage_display_panel($size,$value['alarm'],$value['name'],$value);
	    print "<br/>HINT: You can choose view between separated tab and console, you can set it up in Console -> User Management -> User -> Intropage Options";
	}
}

function intropage_user_admin_setup_sql_save($save) {
	global $settings_user;

	$save['intropage_opts'] = form_input_validate(get_nfilter_request_var('intropage_opts'), 'intropage_opts', '^[01]$', true, 3);

	return $save;
}

?>
