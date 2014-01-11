<?php
	if ($_REQUEST['callbackUrl']=='') {
		echo 'missing parameter. ('.$_REQUEST['callbackUrl-url'].')';
		die();
	// http://reclaim.fm/auth-proxy/index.php?callbackUrl=http://wirres.net
	// http://reclaim.fm/auth-proxy/index.php?callbackUrl=http://wirres.net&login=1
	// http://reclaim.fm/auth-proxy/index.php?callbackUrl=http://wirres.net&login=1&mod=instagram
	// http://reclaim.fm/auth-proxy/index.php?callbackUrl=http://wirres.net&login=0&mod=instagram
	// http://reclaim.fm/auth-proxy/index.php?callbackUrl=http://wirres.net&login=1&mod=facebook
	}

	if (!session_id())
    	session_start();

	// include hybridauth lib
//	$config = dirname(__FILE__) . '/hybridauth/config.php';
	$config = $_SESSION['config'];
	$mod = $_SESSION['mod'];
	require_once(dirname( __FILE__ ) . "/../vendor/hybridauth/hybridauth/src/Hybridauth/Hybridauth.php");
	\Hybridauth\Hybridauth::registerAutoloader();

	// start login with facebook?
	if ( isset($mod) ){
		try {
			$hybridauth = new \Hybridauth\Hybridauth( $config );
			$adapter = $hybridauth->authenticate( $mod );
			// request user profile
			$user_profile = $adapter->getUserProfile();
	
			//?
			$adapter->disconnect();
		}
			catch( Exception $e ){
			$reclaim_error = "<b>got an error!</b> " . $e->getMessage(); 
		}
	}

	// logged in ?
	if( ! isset( $user_profile ) ){
		// return error
		if (!isset($reclaim_error))
			$reclaim_error = 'nothin happend.';
		$url = $_REQUEST['callbackUrl'].'&reclaimError='.urlencode($reclaim_error).'&mod='.$mod;
		header( "Location: $url" ) ;
	}
	
	// user signed in with facebook
	else{
		// return AccessToken
		$access_tokens = $adapter->getTokens();
		$access_token = $access_tokens->accessToken;

		$_SESSION['facebook_user_profile'] = $user_profile;
		$_SESSION['facebook_user_access_token'] = $access_tokens;
		$_SESSION['e'] = $reclaim_error;
	
		$url = $_REQUEST['callbackUrl']
		.'&reclaimError='.urlencode($error).'&mod='.$mod
		.'&reclaimAuthCode='.$access_token;

		$url = $_REQUEST['callbackUrl'].'&link=1&mod='.$mod;
		header( "Location: $url" ) ;
	}
//$user_profile->displayName;
//$user_profile->profileURL;
//print_r( $adapter->getAccessToken() );

?>

sorry.
