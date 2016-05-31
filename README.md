# theidentityhub-php-demo
Demo PHP Application for The Identity Hub. The Identity Hub makes it easy to connect your app to all major identity providers like Microsoft, Facebook, Google, Twitter, Linked In and more. For more information see https://www.theidentityhub.com

# Getting Started

Download or Clone the repository. 

Find the config.php file in the lib theidentityhub folder and locate the following fragment:
````js
$this->config->baseUrl = "https://www.theidentityhub.com/[Your URL segment]"; 
$this->config->clientId = "[Your Application Client Id]"; 
$this->config->redirectUri = "https://[Your path to SDK]/theidentityhub/callback.php"; 
$this->config->signOutUri = "https://[Your path to SDK]/theidentityhub/signout.php"; 
$this->config->signOutRedirectUri = "[Your redirect link]"; 
````

Change the configuration as follows

1. Replace [Your Application Client Id] with the client id from your App configured in The Identity Hub.

2. Replace [Your URL segment] with the url of your tenant on The Identity Hub.

3. Replace [Your path to SDK] with the base url of your site. Configure the redirect uri in your OAuth 2.0 App parameters at The Identity Hub.

4. Navigate to the index.php page

If you do not have already created an App see https://www.theidentityhub.com/hub/Documentation/#CreateAnApp.

