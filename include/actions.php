<?php



if (isset_request_var('intropage_action') && 
    get_filter_request_var('intropage_action', FILTER_VALIDATE_REGEXP, array('options' => array('regexp' => '/^([a-z0-9_-]+)$/')))) {

    $values = explode ("_", get_request_var('intropage_action'));
    // few parameters from input type select has format reset_all, refresh_180, ... first is action	
    $action = array_shift($values);
    $value = implode ("_", $values);
    
    switch ($action)	{

	// close panel 
	case "droppanel":
	    if (get_filter_request_var('panel_id')) 
		db_execute ("delete from plugin_intropage_user_setting where user_id = " . $_SESSION['sess_user_id'] . " and id = " . get_request_var('panel_id'));
	break;


	// favourite graphs
	case "favgraph":
	    if (get_filter_request_var('graph_id'))	{
        
		if (db_fetch_cell ("select count(*) from plugin_intropage_user_setting where user_id=" . $_SESSION['sess_user_id'] . 
				    " and fav_graph_id=" . get_request_var('graph_id')) > 0)	{	// already fav
	
		    db_execute ("delete from plugin_intropage_user_setting where user_id=" . $_SESSION['sess_user_id'] . " and fav_graph_id=" .  get_request_var('graph_id')); 
	
		}
		else	{	// add to fav
		    // priority for new panel:
		    $prio = db_fetch_cell ("select priority from plugin_intropage_panel where panel='intropage_favourite_graph'");
		    
		    db_execute ("insert into plugin_intropage_user_setting (user_id,priority,panel,fav_graph_id) values (" . $_SESSION['sess_user_id'] . 
				",$prio,'intropage_favourite_graph'," . get_request_var('graph_id') . ")"); 
		}
	    }
	break;

	// panel order
	case "order":
	    if (isset_request_var('xdata'))  {
		$error = false;
		$order = array();
		foreach (get_request_var('xdata') as $data)   {
    		    list($a,$b) = explode ("_",$data);
    		    if (filter_var($b, FILTER_VALIDATE_INT))    {
        		array_push ($order, $b);
    		    }
    		    else    {
        		$error = true;
    		    }

    		    if (!$error)    {
        		$_SESSION['intropage_order'] = $order;
        		$_SESSION['intropage_changed_order'] = true;
    		    }
		}
	    }


	break;

	// reset all panels
	case "reset":
	    if ($value == "all")	{
    		unset ($_SESSION['intropage_changed_order'], $_SESSION['intropage_order']);
		db_execute ("delete from plugin_intropage_user_setting where user_id = " . $_SESSION['sess_user_id']);
		// default values
		set_user_setting('intropage_display_important_first', read_config_option("intropage_display_important_first"));
		set_user_setting('intropage_display_level', read_config_option("intropage_display_level"));
		set_user_setting('intropage_autorefresh', read_config_option("intropage_autorefresh"));
	    }
	    elseif ($value == "order")
		unset ($_SESSION['intropage_changed_order'], $_SESSION['intropage_order']);
	break;
    
	case "addpanel":
	    if (preg_match('/^[a-z0-9\-\_]+$/i', $value))	{
		db_execute ("insert into plugin_intropage_user_setting (user_id,panel,priority) select " . $_SESSION['sess_user_id'] . ",panel,priority from plugin_intropage_panel where panel='$value' limit 1");
	    }
	break;

	case "refresh":
	    if ($value == 0 || $value == 60 || $value == 180 || $value == 600)
		    set_user_setting('intropage_autorefresh', $value);
	break;

	case "debug":
	    if ($value == "ena")
		    set_user_setting('intropage_debug', 1);
	    if ($value == "disa")
		    set_user_setting('intropage_debug', 0);

	break;


	case "important":
		if ($value == "first")		{
		    set_user_setting('intropage_display_important_first', 'on');
		    unset ($_SESSION['intropage_changed_order'], $_SESSION['intropage_order']);
		}
		else	{
		    set_user_setting('intropage_display_important_first', 'off');
		    unset ($_SESSION['intropage_changed_order'], $_SESSION['intropage_order']);
		}

	break;

	case "displaylevel":
	    if (preg_match('/^[0-9]{1}$/', $value))
		set_user_setting("intropage_display_level", $value);
	break;
    

	case "loginopt":
	    if ($value == "intropage") // SELECT login_opts FROM user_auth WHERE id
		db_fetch_cell_prepared('update user_auth set login_opts=4 WHERE id=?',array($_SESSION['sess_user_id']));
	    elseif ($value == "graph")
		db_fetch_cell_prepared('update user_auth set login_opts=3 WHERE id=?',array($_SESSION['sess_user_id']));
    
    }

}

?>