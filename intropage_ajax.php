<?php

chdir('../../');
include_once('./include/auth.php');


// reload jednoho panelu
if (isset_request_var('reload_panel') &&
        get_filter_request_var('reload_panel', FILTER_VALIDATE_REGEXP, array('options' => array('regexp' => '/^[0-9]{1,3}$/')))) {
    
    include_once($config['base_path'] . '/plugins/intropage/include/data.php');
    include_once($config['base_path'] . '/plugins/intropage/include/helpers.php');
    
    $panel = db_fetch_row ('select panel,fav_graph_id from plugin_intropage_user_setting where id = ' . get_request_var('reload_panel'));
    $pokus = $panel['panel'];

    if (isset($panel['fav_graph_id'])) { // fav_graph exception 
	 $data = intropage_favourite_graph($panel['fav_graph_id']);
    } else {        // normal panel
        $data = $pokus();
    }
    intropage_display_panel(get_request_var('reload_panel'),$data['alarm'],$data['name'],$data);

}
else	{	// reload all
    include_once('./plugins/intropage/display.php');
    display_information();
}
