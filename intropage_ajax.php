<?php

chdir('../../');
include_once("./include/auth.php");
include_once("./plugins/intropage/display.php");


if (isset($_GET['xdata']) && is_array($_GET['xdata']))	{
    $error = false;
    $order = array();
    foreach ($_GET['xdata'] as $data)	{
	list($a,$b) = explode ("_",$data);
	if (filter_var($b, FILTER_VALIDATE_INT))    {
	    array_push ($order, $b);
	}
	else	{
	    $error = true;
	}
    
	if (!$error)	{
	    $_SESSION['intropage_order'] = $order;
	}
    
    }

}


display_information();

?>
