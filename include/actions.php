<?php


// favourite graphs
if (isset($_GET['action']) && $_GET['action'] == "favgraph" && is_numeric($_GET['graph']) && is_numeric($_GET['graph_id']))	{
	if (read_user_setting('intropage_favouritegraph_' . $_GET['graph']) != $_GET['graph_id'])
	    set_user_setting('intropage_favouritegraph_' . $_GET['graph'], $_GET['graph_id']);
	else	// unset
	    set_user_setting('intropage_favouritegraph_' . $_GET['graph'], '');

}

 
 
// close panel 
if (isset($_GET['action']) && $_GET['action'] == "disable" && is_numeric($_GET['panel_id']))    {
    db_execute ("delete from plugin_intropage_user_setting where user_id = " . $_SESSION['sess_user_id'] . " and id = " . $_GET['panel_id']);
}

// change priority
if (isset($_GET['xdata']) && is_array($_GET['xdata']))  {
    $error = false;
    $order = array();
    foreach ($_GET['xdata'] as $data)   {
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



if (isset($_POST['intropage_action']) && is_string ($_POST['intropage_action'])) {

    $values = explode ("_", $_POST['intropage_action']);
	
    $action = array_shift($values);
    
    $value = implode ("_",$values);
    
    switch ($action)	{

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