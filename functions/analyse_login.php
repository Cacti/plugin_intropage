<?php

function analyse_login() {
	global $config;
	
	$result = array(
		'name' => "Last 10 logins",
		'alarm' => 'green',
		'data' => '',
		'detail' => '',
	);


	// active users in last hour:
//	 select distinct username from user_log  where time > adddate(now(), INTERVAL -1 HOUR) ;

	$flog = 0;
	$sql_result = db_fetch_assoc("SELECT user_log.username, user_auth.full_name, user_log.time, user_log.result, user_log.ip FROM user_auth INNER JOIN user_log ON user_auth.username = user_log.username ORDER  BY user_log.time desc LIMIT 10");
	foreach($sql_result as $row) {
	
		if ($row['result'] == 0) {
			$result['alarm'] = "red";
			$flog++;
		}
		$result['detail'] .= sprintf("%s | %s | %s | %s<br/>",$row['time'],$row['ip'],$row['username'], ($row['result'] == 0)? "failed":"succes");
		
	}
	$result['data'] = "<span class=\"txt_big\">Failed logins: $flog</span><br/><br/>";
	

	// active users in last hour:
	$result['data'] .= "Active users in last hour:<br/>";
	$sql_result = db_fetch_assoc("select distinct username from user_log  where time > adddate(now(), INTERVAL -1 HOUR)");
	foreach($sql_result as $row) {
	    $result['data'] .= $row['username'] . "<br/>";

	}
	
	$loggin_access = (db_fetch_assoc("select realm_id from user_auth_realm where user_id='" . $_SESSION["sess_user_id"] . "' and user_auth_realm.realm_id=19"))?true:false;
	if ($result['detail'] && $loggin_access)	    
		$result['detail'] .= "<br/><br/><a href=\"" . htmlspecialchars($config['url_path']) . "utilities.php?action=view_user_log\">Full log</a><br/>\n";
	
	return $result;
}

?>
