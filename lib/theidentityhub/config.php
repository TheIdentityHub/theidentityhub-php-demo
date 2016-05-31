<?php
/**
 * @package		The Identity Hub Server Side PHP SDK
 * @subpackage	Configuration file
 * @author      U2U Consult
 * @version     1.0.0 2015-08-04
 */

 
$this->config->baseUrl = "https://www.theidentityhub.com/[Your URL segment]";
$this->config->clientId = "[Your Application Client Id]";
$this->config->redirectUri = "https://[Your path to SDK]/theidentityhub/callback.php";
$this->config->signOutUri = "https://[Your path to SDK]/theidentityhub/signout.php";
$this->config->signOutRedirectUri = "[Your redirect link]";
 
 
?>