<?php

/*
* read more : https://developers.facebook.com/docs/howtos/login/server-side-login/
* use like this:
* /access_token.php?app_id=xxx&app_secret=xxx&reclaim_settings_page=http://root.wirres.net/reclaim/wp-admin/options-general.php?page=reclaim/reclaim.php
* 
*/
session_start();
if (isset($_REQUEST["app_id"])) {
   $app_id = $_REQUEST["app_id"];
   $app_secret = $_REQUEST["app_secret"];
   $reclaim_settings_page = $_REQUEST["reclaim_settings_page"];
   
   $_SESSION['app_id'] = $app_id;
   $_SESSION['app_secret'] = $app_secret;
   $_SESSION['reclaim_settings_page'] = $reclaim_settings_page;
}

   $my_url = "http://".$_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'];
   $code = $_REQUEST["code"];

   if(empty($code)) {
     // Redirect to Login Dialog
     $_SESSION['state'] = md5(uniqid(rand(), TRUE)); // CSRF protection
     $dialog_url = "https://www.facebook.com/dialog/oauth?client_id=" 
       . $app_id . "&redirect_uri=" . urlencode($my_url) . "&state="
       . $_SESSION['state'] . "&scope=publish_stream,user_status,user_activities,read_stream,user_likes,read_friendlists,email";

     echo("<script> top.location.href='" . $dialog_url . "'</script>");
   }

	if($_SESSION['state'] && ($_SESSION['state'] === $_REQUEST['state'])) {
		$token_url = "https://graph.facebook.com/oauth/access_token?"
	       . "client_id=" . $_SESSION['app_id'] . "&redirect_uri=" . urlencode($my_url)
	       . "&client_secret=" . $_SESSION['app_secret'] . "&code=" . $code;

    $timeout = 15;

	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $token_url);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt( $ch, CURLOPT_CONNECTTIMEOUT, $timeout );
   	curl_setopt( $ch, CURLOPT_TIMEOUT, $timeout );
	$output = curl_exec($ch);
	curl_close($ch);
	$response = trim($output);

     $params = null;
     parse_str($response, $params);

     if ($longtoken=$params['access_token']) {
     //echo $longtoken;
     echo("<script>top.location.href='" . $_SESSION['reclaim_settings_page'] . "'</script>");
     }
//save it to database    
}
?>
