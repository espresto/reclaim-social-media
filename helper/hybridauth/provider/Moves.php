<?php
/*!
* HybridAuth
* http://hybridauth.sourceforge.net | https://github.com/hybridauth/hybridauth
*  (c) 2009-2012 HybridAuth authors | http://hybridauth.sourceforge.net/licenses.html
*/

/**
* Hybrid_Providers_Instagram (By Sebastian Lasse - https://github.com/sebilasse)
*/
class Hybrid_Providers_Moves extends Hybrid_Provider_Model_OAuth2
{ 
	// default permissions   
	public $scope = "activity"; 

	/**
	* IDp wrappers initializer 
	*/
	function initialize()
	{
		parent::initialize();

		// Provider api end-points
		$this->api->api_base_url  = "https://api.moves-app.com/api/v1";
		$this->api->authorize_url = "https://api.moves-app.com/oauth/v1/authorize";
		$this->api->token_url     = "https://api.moves-app.com/oauth/v1/access_token";
	}

	/**
	* load the user profile from the IDp api client
	*/
	function getUserProfile(){ 
		$data = $this->api->api("/user/profile" ); 

		if ( $data->userId == "" ){
			throw new Exception( "User profile request failed! {$this->providerId} returned an invalid response.", 6 );
		}

		$this->user->profile->identifier  = $data->userId; 

		return $this->user->profile;
	}
}
