<?php

chdir('../../');
include_once("./include/auth.php");
include_once("./plugins/intropage/display.php");

    $fp = fopen('./plugins/intropage/pokus.txt', 'a');

if ($_GET)	{
//echo "aaaaaaaaa";
    fwrite($fp,"post zacatek\n");

    fwrite($fp, print_r($_GET, TRUE));
    fwrite($fp,"post konec\n");


}
else
    fwrite($fp,"pripisuju bez postu\n");
    
fclose($fp);



display_information();

?>
