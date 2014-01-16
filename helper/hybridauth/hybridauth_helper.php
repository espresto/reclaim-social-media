<?php
	if ( ($_REQUEST['callbackUrl']=='') || ($_REQUEST['mod']=='') ) {
		echo 'missing parameter.';
		die();
	}
//	require_once(dirname( __FILE__ ) . "/../../vendor/hybridauth/hybridauth/hybridauth/Hybrid/Auth.php");
if (file_exists( __DIR__ . '/../../vendor/autoload.php')) {
    require __DIR__ . '/../../vendor/autoload.php';
}

	if (!session_id())
    	session_start();

	// include hybridauth lib
//	$config = dirname(__FILE__) . '/hybridauth/config.php';
	$mod 			= $_REQUEST['mod'];
//	$mod 			= $_SESSION['mod'];
	$config 		= $_SESSION[$mod]['config'];
	$callback		= urldecode($_REQUEST['callbackUrl']);
	$reclaim_error ="";

	// start login with $mod?
	if ( isset($mod) ){
		try {
			$hybridauth = new Hybrid_Auth( $config );
			$adapter = $hybridauth->authenticate( $mod );
			$user_profile = $adapter->getUserProfile();
			//?
//			$adapter->disconnect();
		}
		catch( Exception $e ){
			$reclaim_error = "<b>Authentication error:</b> " . $e->getMessage(); 
		}
	}

	// logged in ?
	if( ! isset( $user_profile ) ){
		// return error
		if (!isset($reclaim_error))
			$reclaim_error = 'got no user profile from '.$mod.'.';

		$_SESSION['e'] = $reclaim_error;
//		echo $reclaim_error;
		$url = $callback;
		header( "Location: $url" ) ;
	}
	
	// user signed in with $mod
	else{
		// return AccessToken
		$access_tokens = $adapter->getAccessToken();
		$access_token = $access_tokens->accessToken;
		$user_profile = $adapter->getUserProfile();

		$_SESSION['hybridauth_user_access_tokens'] =  json_encode($access_tokens);
		$_SESSION['hybridauth_user_profile'] = json_encode($user_profile);
		$_SESSION['e'] = $reclaim_error;
		$url = $callback;
		// debugging
//		print_r($access_tokens);
//		print_r($user_profile);
//		print_r($_SESSION['hybridauth_user_profile']);
		header( "Location: $url" ) ;
	}

?>

