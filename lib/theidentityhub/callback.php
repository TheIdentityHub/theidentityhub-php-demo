<?php 
/**
 * @package		The Identity Hub Server Side PHP SDK
 * @subpackage	Callback helper
 * @author      U2U Consult
 * @version     1.0.0 2015-08-04
 */

require_once 'theidentityhub.php';

session_start();

$ih = new TheIdentityHub;

if ($params = $ih->parseResponseCode() ) { 	// get authorization code and state
	// check params validity but it probably already done by hub 
	if ( $ih->exchangeCodeAndSetToken($params) ) { // token is exchanged and set
		header('Location: ' . urldecode($params['state']), true, 302); // redirect to $params['state']
		die;
	} 
}; 

// if something is wrong and if debug is on show debug data 
if ($ih->debug) {
	$ih->debugEcho();
}

?>
