<?php
/*!
* HybridAuth
* http://hybridauth.sourceforge.net | http://github.com/hybridauth/hybridauth
* (c) 2009-2012, HybridAuth authors | http://hybridauth.sourceforge.net/licenses.html 
*/

/**
 * Hybrid_Providers_Soundcloud class, wrapper for Vimeo  
 */
class Hybrid_Providers_Soundcloud extends Hybrid_Provider_Model
{ 
	/**
	* IDp wrappers initializer 
	*/
	function initialize() 
	{
		if ( ! $this->config["keys"]["key"] || ! $this->config["keys"]["secret"] )
		{
			throw new Exception( "Your application key and secret are required in order to connect to {$this->providerId}.", 4 );
		}

		//require_once Hybrid_Auth::$config["path_libraries"] . "Soundcloud/Soundcloud.php"; 
		require_once Hybrid_Auth::$config["wrapper"]["base_url"] . "soundcloud_helper/Soundcloud.php";
		//echo "hi!";
		//var_dump(Hybrid_Auth::$config);
		//die();

		$this->api = new Services_Soundcloud( $this->config["keys"]["key"], $this->config["keys"]["secret"], $this->endpoint );


		if( $this->token( "access_token" ) )
		{
			$this->api->setAccessToken( $this->token( "access_token" ) );
		}
	}

	/**
	* begin login step 
	*/
	function loginBegin()
	{ 
 		# redirect to Authorize url
 	    //var_dump($this->api->getAuthorizeUrl());
 	    //die();

		Hybrid_Auth::redirect( $this->api->getAuthorizeUrl() );
	}
 
	/**
	* finish login step 
	*/
	function loginFinish()
	{ 
		$token = @ $_REQUEST['code'];

		if ( ! $token )
		{
			throw new Exception( "Authentication failed! {$this->providerId} returned an invalid Token.", 5 );
		}

		try{
			$response = $this->api->accessToken( $token );
		}
		catch( SoundcloudException $e ){
			throw new Exception( "Authentication failed! {$this->providerId} returned an error while requesting and access token. $e.", 6 );
		}

		if( isset( $response['access_token'] ) && isset( $response['refresh_token'] ) ){
			$this->token( "access_token" , $response['access_token'] );
			
			// let set the user name as access_token_secret ...    @todo: use something else?
			$this->token( "user_name" , $response['refresh_token'] );

			// set user as logged in
			$this->setUserConnected();
		}
		else{
			throw new Exception( "Authentication failed! {$this->providerId} returned an invalid access Token.", 5 );
		}
	}

	/**
	* load the user profile from the IDp api client
	*/
	function getUserProfile()
	{

		try{
			$response = json_decode($this->api->get( "me" ));

		}
		catch( SoundcloudInvalidSessionException $e ){
			throw new Exception( "User profile request failed! {$this->providerId} returned an error while requesting the user profile. Invalid session key - Please re-authenticate. $e.", 6 );
		}
		catch( SoundcloudException $e ){
			throw new Exception( "User profile request failed! {$this->providerId} returned an error while requesting the user profile. $e", 6 );
		}

		// fetch user profile
		$this->user->profile->identifier    = (string) $response->id;
		$this->user->profile->firstName  	= @ (string) $response->full_name;
		$this->user->profile->displayName  	= @ (string) $response->username;
		$this->user->profile->photoURL  	= @ (string) $response->avatar_url;
		$this->user->profile->profileURL    = @ (string) $response->permalink_url;
		
		$this->user->profile->city       = @ (string) $response->city;
		$this->user->profile->country       = @ (string) $response->country;
		/* Note: Some information is not stored by SoundCloud

		$this->user->profile->gender        = @ (string) $response->gender;
		$this->user->profile->age           = @ (int) $response->age;

		if( $this->user->profile->gender == "f" ){
			$this->user->profile->gender = "female";
		}

		if( $this->user->profile->gender == "m" ){
			$this->user->profile->gender = "male";
		}*/

		return $this->user->profile;
	}
}
