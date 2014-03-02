<?php
/*  Copyright 2013-2014 diplix

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

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
    //$config = dirname(__FILE__) . '/hybridauth/config.php';
	$mod 			= $_REQUEST['mod'];
	$config 		= $_SESSION[$mod]['config'];
	$callback		= urldecode($_REQUEST['callbackUrl']);
    //logout? login?
	$login 			= $_REQUEST['login'];
	if (!isset($login)) { $login = 1; }
	$reclaim_error = "";

	// start login with $mod?
	if ( isset($mod) && $login ){
		try {
            $hybridauth = new Hybrid_Auth( $config );
            $adapter = $hybridauth->authenticate( $mod );
            $user_profile = $adapter->getUserProfile();
			//?
//			$adapter->disconnect();
            if ($mod == "tumblr") {
                $full_user_profile = $adapter->api()->api( 'user/info' ); 
            }
		}
		catch( Exception $e ){
			$reclaim_error = "" . $e->getMessage();
		}
	}
	elseif ( isset($mod) && !$login ){
		try {
            $hybridauth = new Hybrid_Auth( $config );
            $adapter = $hybridauth->logoutAllProviders( $mod );
            $user_profile = '';
            $_SESSION['login'] = 0;
            $reclaim_error = 'logged out user from '.$mod.'.';
		}
		catch( Exception $e ){
			$reclaim_error = "" . $e->getMessage();
		}
	}
     

	// logged in ?
	if( ! isset($user_profile) || empty($user_profile) ){
		// return error
		if (!isset($reclaim_error))
			$reclaim_error = 'got no user profile from '.$mod.'.';

		$_SESSION['e'] = $reclaim_error;
		//echo "".$reclaim_error;
		//print_r($config);
		//print_r($_SESSION);
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
		$_SESSION['hybridauth_full_user_profile'] = json_encode($full_user_profile);
		$_SESSION['e'] = $reclaim_error;
        $_SESSION['login'] = 1;
		$url = $callback;
		// debugging
//		print_r($access_tokens);
//		print_r($user_profile);
//		print_r($_SESSION['hybridauth_user_profile']);
		header( "Location: $url" ) ;
	}

?>

