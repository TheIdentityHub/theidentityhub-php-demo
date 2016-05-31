<?php
/**
 * @package		The Identity Hub Server Side PHP SDK
 * @subpackage	Sign out helper
 * @author      U2U Consult
 * @version     1.0.0 2015-08-04
 */

require_once 'theidentityhub.php';

session_start();

$ih = new TheIdentityHub;

if ($ih->signOut()) {
	
	// Is there anything else to do on sign out?
	// But without any actual output is sent, either by normal HTML tags, 
	// blank lines in a file, or from PHP - ore header call will fail. 
	// ... 
	session_destroy();
		
	header('Location: ' . $ih->config->signOutRedirectUri, true, 302);
	die;	
}

// if something is wrong try again
echo '<html><body>';
echo 'Invalid Sign out - try again: <a href="' . $ih->config->signOutUri . '">Sign out</a>';
echo '</body></html>';

// and if debug is on show debug data 
if ($ih->debug) {
	$ih->debugEcho();
}


?>