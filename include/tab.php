<?php

function intropage_show_tab () {
	global $config;
	
	if (api_user_realm_auth('intropage.php') && isset($_SESSION['sess_user_id'])) {
		$lopts = db_fetch_cell_prepared('SELECT intropage_opts FROM user_auth WHERE id=?',array($_SESSION['sess_user_id']));  		
		if ($lopts == 1)	{
			$cp = false;
			if (basename($_SERVER['PHP_SELF']) == 'intropage.php')
				$cp = true;
			
			print('<a href="' . $config['url_path'] . 'plugins/intropage/intropage.php"><img src="' . $config['url_path'] . 'plugins/intropage/images/tab_intropage' . ($cp ? '_down': '') . '.gif" alt="intropage"  align="absmiddle" border="0"></a>');
		}
	}
}

?>
