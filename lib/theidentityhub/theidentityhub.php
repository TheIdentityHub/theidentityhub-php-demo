<?php
/**
 * @package		The Identity Hub Server Side PHP SDK
 * @subpackage	Main class
 * @author      U2U Consult
 * @version     1.0.0 2015-08-04
 */
 
 class TheIdentityHub {
	
	public $config;

	public $debug = false;
	protected $queue = array();
	protected $errors = array();
	protected $errors_show = false;

	public $isAuthenticated = false; 
	public $isVerified = false;  
	
	public $identity = null;
	
	public $roles = array(); // TODO
	public $isInRole = array(); // TODO 
	
	public $identityUser = null;
	public $accounts = array();
	public $accountsUser = array();
	public $friends = array();
	public $friendsUser = array();	
	
	protected $token = null;
	
	
	function __construct() {
		$this->config = new \stdClass;
		require_once __DIR__ . DIRECTORY_SEPARATOR . 'config.php';
		
		if ($this->debug) { $this->enqueueMsg("Entering in " . __FUNCTION__); }
		$this->getToken(); // is there token set or it is in session?
		
		if ($this->token != null) { // there is but it should be checked is it still valid on theindentityhub server
			if ($this->verifyToken() == false ) { // try to refresh
				if ($this->refreshToken() == false) {
					// not logged in, stay like that - we want to have Login form shown
					$this->resetTokenAndSessionData(); // clean up 
				}				
			}
			// if it is ok by verify or refresh is seccesfull then it still will be
			// (if verify is false and refresh unsuccesfull the refresh will destroy token)
			if ($this->token != null) { // token is ok let load main profile - we will need it anyway for some data on the page
				$this->getProfile();	
			} 			
		}
		
	}


	function getSignInURL() {
		$url = $this->config->baseUrl . "/oauth2/v1/auth?response_type=code"
            . "&client_id=" . urlencode($this->config->clientId)  
            . "&redirect_uri=" . urlencode($this->config->redirectUri);
		if ($this->config->scopes != '') {
			$url .= "&scope=" . urlencode($this->config->scopes);
		}
		
		// get current address
		if (isset($_SERVER['HTTPS']) && !empty($_SERVER['HTTPS']) && (strtolower($_SERVER['HTTPS']) != 'off')) {
            $https = 's://';
        } else {
            $https = '://';
		}

		if (!empty ($_SERVER['HTTP_HOST']) && !empty ($_SERVER['REQUEST_URI'])) {
			$theURI = 'http' . $https . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
			if (strlen($_SERVER['QUERY_STRING']) && strpos($_SERVER['REQUEST_URI'], $_SERVER['QUERY_STRING']) === false) {
				$theURI .= '?'.$_SERVER['QUERY_STRING'];
    		}
		} else {
			$theURI = 'http' . $https . $_SERVER['HTTP_HOST'] . $_SERVER['SCRIPT_NAME'];
			if (isset($_SERVER['QUERY_STRING']) && !empty($_SERVER['QUERY_STRING'])) {
				$theURI .= '?' . $_SERVER['QUERY_STRING'];
    		}
		}
		
		$url .= "&state=" . urlencode($theURI);
		
		if ($this->debug) { $this->enqueueMsg('SignInURL: ' . $url); }
		if ($this->debug) { $this->enqueueMsg('SignInURL decodeded: ' . urldecode($url) ); }
		
		return $url;
		
	}
	function getSignInURLhtmlentities() {
		$url = $this->config->baseUrl;
		$url .= "/oauth2/v1/auth?response_type=token"
            . "&client_id=" . urlencode(htmlentities($this->config->clientId))  
            . "&redirect_uri=" . urlencode(htmlentities($this->config->redirectUri));
		if ($this->config->scopes != '') {
			$url .= "&scope=" . urlencode(htmlentities($this->config->scopes));
		}
		
		// get current address
		if (isset($_SERVER['HTTPS']) && !empty($_SERVER['HTTPS']) && (strtolower($_SERVER['HTTPS']) != 'off')) {
            $https = 's://';
        } else {
            $https = '://';
		}

		if (!empty ($_SERVER['HTTP_HOST']) && !empty ($_SERVER['REQUEST_URI'])) {
			$theURI = 'http' . $https . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
			if (strlen($_SERVER['QUERY_STRING']) && strpos($_SERVER['REQUEST_URI'], $_SERVER['QUERY_STRING']) === false) {
				$theURI .= '?'.$_SERVER['QUERY_STRING'];
    		}
		} else {
			$theURI = 'http' . $https . $_SERVER['HTTP_HOST'] . $_SERVER['SCRIPT_NAME'];
			if (isset($_SERVER['QUERY_STRING']) && !empty($_SERVER['QUERY_STRING'])) {
				$theURI .= '?' . $_SERVER['QUERY_STRING'];
    		}
		}
		
		$url .= "&state=" . urlencode(htmlentities($theURI));
		
		return $url;
		
	}

	function getSignOutURL() {
		return $this->config->signOutUri;		
	}

	

	function parseResponseCode() {
		
		// user has logged in with login page of the hub and returns via callback.php
				
		$params = $this->getQueryParameters($_SERVER['QUERY_STRING']);
		
		// query says that return erorr is from # and not ? 
		// (while successful request is from ?)  
		// so if it is # I can not read the error with PHP
		// TODO needs to be check if the error will go via PHP or JS resubmit
		// so far we consider both cases we have from ?

		// is it an error?
		$error = false;
		foreach ($params as $key => $value) {
			if ($key == 'error') {
				$this->errors[] = $value;
				$error = true;
			}
		}
			
		if ($error == false) { // error free but do we have necesary params?
			if (isset($params['code']) && isset($params['state'])) { // let's try to get token
				return $params;				
			} else {
				$this->errors[] = 'Wrong parameters';
			}
		}
		
		return false;
		
	}

    function getQueryParameters($query ='') {
    	if ($query == '') {	
    		$query = $_SERVER['QUERY_STRING'];
		}
		$result = array();
		if ($query != '' ) {		
			$pairs = explode('&', $query);
			foreach ($pairs as $pair) {
				$pair_values = explode('=', $pair);
				if (count($pair_values) == 2) {
					$result[$pair_values[0]] = $pair_values[1];
				}			
			}
		}

		if ($this->debug) {
			$msg = 'getQueryParameters: ';
			foreach ($result as $key => $value) { $msg .= $key.'='.$value.'; '; }
			$this->enqueueMsg($msg);
		}

        return $result;
    }

	function exchangeCodeAndSetToken($params) {
		if ($this->debug) { $this->enqueueMsg("Entering in " . __FUNCTION__ ); }
		
		$postfields = array();
		$postfields['grant_type'] = 'authorization_code';
		$postfields['code'] = $params['code'];
		$postfields['client_id'] = $this->config->clientId;
		//$postfields['client_secret'] = N/A;
		$postfields['redirect_uri'] = $this->config->redirectUri;
		
		$url = $this->config->baseUrl . '/oauth2/v1/token';
		
		if ($this->cURLdownload($url, array(), $postfields, 'POST')) {
			
			if ($this->debug) { $this->enqueueMsg("json in " . __FUNCTION__ . ': ' . $this->cURLdata); }
			
			$data = json_decode($this->cURLdata);
			if ($data !== null) { // unable to decode JSON
				if ( isset($data->error) ) {
					$this->errors[] = $data->error;
				} else { // we have valid answer if all vars are set
					//if ( isset($data->access_token) && isset($data->token_type) && isset($data->expires_in) && isset($data->scope) && isset($data->refresh_token) ) {
					if ( isset($data->access_token) && isset($data->token_type) && isset($data->expires_in) && isset($data->refresh_token) ) {
						if ($this->setToken($data)) {
							
							return true;
						}
					}
				}
			}

		} else {
			$this->errors[] = 'Unable to get token';
		}
		return false;
	}


	function verifyToken() { // Token is already loaded in class
		if ($this->debug) { $this->enqueueMsg("Entering in " . __FUNCTION__ . ', current token: ' . $this->token->access_token); }
		
		$url = $this->config->baseUrl . "/oauth2/v1/verify/?access_token=" . urlencode($this->token->access_token);
		if ( $result = $this->cURLdownload($url, array(), array() ) ) {

			if ($this->debug) { $this->enqueueMsg("json in " . __FUNCTION__ . ': ' . $this->cURLdata); }

			$check = json_decode($this->cURLdata);
			if (!isset($check->error)) {
				if (isset($check->audience) && $check->audience == $this->config->clientId) { // ok but check scope
					if (isset($check->scope) || isset($this->token->scope) ) {
						if (isset($check->scope) && isset($this->token->scope) && $check->scope == $this->token->scope) {
							if ($this->debug) { $this->enqueueMsg("Token is ok (scope set) by " . __FUNCTION__); }
							if ($check->resource_owner_identity_verified == true) { $this->isVerified = true; } else { $this->isVerified = false; }
							return true; // Token is ok, scope match
						}
					} else { // neither is set - they are equal
						if ($this->debug) { $this->enqueueMsg("Token is ok (scope not set) by " . __FUNCTION__); }
						if ($check->resource_owner_identity_verified == true) { $this->isVerified = true; } else { $this->isVerified = false; }
						return true; // Token is ok, scope match
					}
				} 
			}
			
		} else {
			$this->errors[] = "Error: Unable to get data with cURL in " . __FUNCTION__;
		}
		return false;
	}

	function refreshToken(){
		if ($this->debug) { $this->enqueueMsg("Entering in " . __FUNCTION__ . ', current token: ' . $this->token->access_token); }
		
		$postfields = array();
		$postfields['grant_type'] = 'refresh_token';
		$postfields['refresh_token'] = $this->token->refresh_token;
		$postfields['client_id'] = $this->config->clientId;
		//$postfields['client_secret'] = N/A;
		$url = $this->config->baseUrl . '/oauth2/v1/token';
		
		if ($this->cURLdownload($url, array(), $postfields, 'POST')) {

			if ($this->debug) { $this->enqueueMsg("json in " . __FUNCTION__ . ': ' . $this->cURLdata); }
				
			$data = json_decode($this->cURLdata);
			if ($data !== null) { // unable to decode JSON
				if ( isset($data->error) ) {
					$this->errors[] = $data->error;
				} else { // we have valid answer if all vars are set
					//if ( isset($data->access_token) && isset($data->token_type) && isset($data->expires_in) && isset($data->scope) && isset($data->refresh_token) ) {
					if ( isset($data->access_token) && isset($data->token_type) && isset($data->expires_in) && isset($data->refresh_token) ) {
						if ($this->setToken($data)) {
							if ( (int) $data->resource_owner_identity_verified == 0) { $this->isVerified = false; } else { $this->isVerified = true; }
							if ($this->debug) { $this->enqueueMsg("Token is refreshed by " . __FUNCTION__); }
							return true;
						}
					}
				}
			}

		} else {
			$this->errors[] = 'Unable to get with cURL for refresh token';
		}
		
		$this->resetTokenAndSessionData(); //if it is not possible to refresh destroy it

		return false;
		
	}

	function resetTokenAndSessionData() {
		$this->token = null;
		$this->isAuthenticated = false;
		$this->isVerified = false;
		$_SESSION['token'] = null;
		$_SESSION['isAuthenticated'] = false;
		$_SESSION['isVerified'] = false;
		session_destroy();
	}

	function signOut() {
		if ($this->debug) { $this->enqueueMsg("Entering in " . __FUNCTION__ . ', current token: ' . $this->token->access_token); }
		
		$this->getToken();
		if ($this->isAuthenticated == false ) {
			// there is no valid token nothing to revoke
		} else {
			$postfields = array();
			$postfields['token'] = $this->token->access_token;
			$postfields['token_type_hint'] = 'access_token';
			$postfields['client_id'] = $this->config->clientId;
			$url = $this->config->baseUrl . '/oauth2/v1/revoke';
			
			if ($this->cURLdownload($url, array("Authorization" => "Bearer " . $this->token->access_token), $postfields, 'POST', 1)) {
				
				if (strpos($this->cURLdata, 'OK') !== false) { // 
					if ($this->debug) { $this->enqueueMsg("Token revoke is ok by " . __FUNCTION__); }
					return true;
				}
	
			} else {
				$this->errors[] = 'Unable to get curl data in '.__FUNCTION__;
			}
			return false;
		}

		$this->resetTokenAndSessionData(); // reset all
		
	}



	function setToken($data) {
		$now = time();
					
		$this->token = new \stdClass;
		$this->token->access_token = $data->access_token;
		$this->token->token_type = $data->token_type;
		$this->token->expiry = $now + (int) $data->expires_in;
		$this->token->refresh_token = $data->refresh_token;
		if (isset($data->scope)) { $this->token->scope = $data->scope; }
		$this->isAuthenticated = true;
		
		$_SESSION['token'] = $this->token;
		$_SESSION['isAuthenticated'] = true;
		
		return true;
		
	}
	
	function getToken() {
		$now = time();
		// if set in object, return from object
		if ($this->token !== null) {
			if (isset($this->token->access_token) && isset($this->token->expiry) && $this->token->expiry > $now) {
				$this->isAuthenticated = true;
				return $this->token;
			} 
		} else { // if not set in obj try to get it from session
			if (isset($_SESSION['token'])) {
				$this->token = $_SESSION['token'];
				if (isset($this->token->access_token) && isset($this->token->expiry) && $this->token->expiry > $now) {
					$this->isAuthenticated = true;
					return $this->token;
				} 
			}	
		}
		
		// if not in session reset  
		//$this->resetTokenAndSessionData();
	}

	function debugEcho() {


		// add errors to msgs			
		if ($this->errors_show) {
			foreach ($this->errors as $value) {
				$this->enqueueMsg('Error: '.$value);
			}
		}
			
			
		echo '<p>Messages and errors:</p>';
		if (count($this->queue)) {
			echo '<ul>';
			foreach ($this->queue as $value) {
				echo '<li>'.htmlentities($value).'</li>';
			}
			echo '</ul>';
		}
		echo '<p>&nbsp;</p>';
		
		
		echo '<p>$_REQUEST:</p>';
		echo '<pre>';
		echo var_dump($_REQUEST);
		echo '</pre>';
		
		echo '<p>$_SESSION:</p>';
		echo '<pre>';
		echo var_dump($_SESSION);
		echo '</pre>';
		
		echo '<p>$_SERVER:</p>';
		echo '<pre>';
		echo var_dump($_SERVER);
		echo '</pre>';

		echo '<p>$_GET:</p>';
		echo '<pre>';
		echo var_dump($_GET);
		echo '</pre>';

		echo '<p>$_COOKIE:</p>';
		echo '<pre>';
		echo var_dump($_COOKIE);
		echo '</pre>';

		echo '<p>config:</p>';
		echo '<pre>';
		echo var_dump($this->config);
		echo '</pre>';
	}

	function enqueueMsg($msg) {
		$this->queue[] = $msg;
	}




    function cURLdownload($url, $headers, $postfields, $method = 'GET', $CURLOPT_HEADER = 0, $timeout = 5, $referer='', $ua = 'TheIdentityHub PHP 1.0.0') {
    	
        $this->cURLdata = null; // clear object before request
		
		// default headers        
        $allheaders[] = "ACCEPT: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8";
        $allheaders[] = "ACCEPT_ENCODING: gzip, deflate";
        $allheaders[] = "CONNECTION: keep-alive";
        $allheaders[] = "Cache-Control: no-cache";
        //optional headders
        foreach ($headers as $key => $value) {
            $allheaders[] = $key . ': ' . $value;
        }
         
        if( !$this->cURLcheckBasicFunctions() ) { $this->errors[] = "UNAVAILABLE: cURL Basic Functions"; }
		        
        $ch = curl_init();
        if($ch) {
            if( !curl_setopt($ch, CURLOPT_URL, $url) )		 		{ curl_close($ch); $this->errors[] = "FAIL: curl_setopt(CURLOPT_URL)"; }
            if( !curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1) )      { $this->errors[] = "FAIL: curl_setopt(CURLOPT_RETURNTRANSFER)"; }
            if( !curl_setopt($ch, CURLOPT_HEADER, $CURLOPT_HEADER) )              { $this->errors[] = "FAIL: curl_setopt(CURLOPT_HEADER)"; }
            if( !curl_setopt($ch, CURLOPT_USERAGENT, $ua) )         { $this->errors[] = "FAIL: curl_setopt(CURLOPT_USERAGENT)"; }
            if ($referer != '') {
            	if( !curl_setopt($ch, CURLOPT_REFERER, $referer) )      { $this->errors[] = "FAIL: curl_setopt(CURLOPT_REFERER)"; }
			}
            if( !curl_setopt($ch, CURLOPT_HTTPHEADER, $allheaders) )    { $this->errors[] = "FAIL: curl_setopt(CURLOPT_HTTPHEADER)"; }
            if( !curl_setopt($ch, CURLOPT_TIMEOUT, $timeout ) )           { $this->errors[] = "FAIL: curl_setopt(CURLOPT_TIMEOUT)"; }
            
            if( !curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1 ) )     { $this->errors[] = "FAIL: curl_setopt(CURLOPT_FOLLOWLOCATION)"; }
            if( !curl_setopt($ch, CURLOPT_MAXREDIRS, 3 ) )          { $this->errors[] = "FAIL: curl_setopt(CURLOPT_MAXREDIRS)"; }

            // if( !curl_setopt ($ch, CURLOPT_COOKIE, $cookie))        { $this->errors[] = "FAIL: curl_setopt(CURLOPT_COOKIE)";
         	// if( !curl_setopt ($ch, CURLOPT_COOKIEJAR, $cookies))    { $this->errors[] = "FAIL: curl_setopt(CURLOPT_COOKIEJAR)"; }
         	// if( !curl_setopt ($ch, CURLOPT_COOKIEFILE, $cookies))   { $this->errors[] = "FAIL: curl_setopt(CURLOPT_COOKIEFILE)"; }
            
 			if( !curl_setopt($ch, CURLOPT_NOBODY, true))   			{ $this->errors[] = "FAIL: curl_setopt(CURLOPT_NOBODY)"; }   
	
			if ( is_array($postfields) ) {
				if( !curl_setopt ($ch, CURLOPT_POSTFIELDS, http_build_query($postfields))) { $this->errors[] = "FAIL: curl_setopt(CURLOPT_POSTFIELDS)"; }
			}
            
			if ($method == 'GET') {
				if( !curl_setopt ($ch, CURLOPT_POST, 0))                { $this->errors[] = "FAIL: curl_setopt(CURLOPT_POST)"; }
		        if( !curl_setopt ($ch, CURLOPT_HTTPGET, 1)) 			{ $this->errors[] = "FAIL: curl_setopt(CURLOPT_HTTPGET)"; }
			} else {
				if( !curl_setopt ($ch, CURLOPT_HTTPGET, 0)) 			{ $this->errors[] = "FAIL: curl_setopt(CURLOPT_HTTPGET)"; }
         		if( !curl_setopt ($ch, CURLOPT_POST, 1))                { $this->errors[] = "FAIL: curl_setopt(CURLOPT_POST)"; }
			}
            
            if (count($this->errors == 0)) { $result = curl_exec($ch); }

            curl_close($ch);
						
            if( $result !== false ) {
	            $this->cURLdata = $result;
	            return true;
			} else {
				// curl couldn't read data, it is not necessary an error, but it could be that the page doesn't exists 
				$this->errors[] = "FAIL: curl_exec()";
			}
        } else {
        	$this->errors[] = "FAIL: curl_init()";
		}

		return false;
		
    }

    function cURLcheckBasicFunctions() {
        if( !function_exists("curl_init") && !function_exists("curl_setopt") && !function_exists("curl_exec") && !function_exists("curl_close") ) return false;
        else return true;
    }






	// Identitiy API fns
	
	function getProfile() {
		$url = $this->config->baseUrl . "/api/identity/v1/";
		if ( $result = $this->cURLdownload($url, array("Authorization" => "Bearer " . $this->token->access_token), array() ) ) {
			$this->identity = json_decode($this->cURLdata);
		} else {
			$this->errors[] = "Error: Unable to get data with cURL in " . __FUNCTION__;
		}
	}

	function getProfileUser($user_id) {
		$url = $this->config->baseUrl . "/api/identity/v1/".$user_id;
		if ( $result = $this->cURLdownload($url, array("Authorization" => "Bearer " . $this->token->access_token), array() ) ) {
			$this->identityUser = json_decode($this->cURLdata);
		} else {
			$this->errors[] = "Error: Unable to get data with cURL in " . __FUNCTION__;
		}
	}

	function getAccounts() {
		$url = $this->config->baseUrl . "/api/identity/v1/accounts";
		if ( $result = $this->cURLdownload($url, array("Authorization" => "Bearer " . $this->token->access_token), array() ) ) {
			$this->accounts = json_decode($this->cURLdata);
		} else {
			$this->errors[] = "Error: Unable to get data with cURL in " . __FUNCTION__;
		}
	}

	function getAccountsUser($user_id) {
		$url = $this->config->baseUrl . "/api/identity/v1/".$user_id.'/accounts';
		if ( $result = $this->cURLdownload($url, array("Authorization" => "Bearer " . $this->token->access_token), array() ) ) {
			$this->accountsUser = json_decode($this->cURLdata);
		} else {
			$this->errors[] = "Error: Unable to get data with cURL in " . __FUNCTION__;
		}
	}

	function getFriends() {
		$url = $this->config->baseUrl . "/api/identity/v1/friends";
		if ( $result = $this->cURLdownload($url, array("Authorization" => "Bearer " . $this->token->access_token), array() ) ) {
			$this->friends = json_decode($this->cURLdata);
		} else {
			$this->errors[] = "Error: Unable to get data with cURL in " . __FUNCTION__;
		}
	}

	function getFriendsUser($user_id) {
		$url = $this->config->baseUrl . "/api/identity/v1/".$user_id.'/friends';
		if ( $result = $this->cURLdownload($url, array("Authorization" => "Bearer " . $this->token->access_token), array() ) ) {
			$this->friendsUser = json_decode($this->cURLdata);
		} else {
			$this->errors[] = "Error: Unable to get data with cURL in " . __FUNCTION__;
		}
	}









}
?>
