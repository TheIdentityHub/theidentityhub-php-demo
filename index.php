<?php 

require_once 'lib/theidentityhub/theidentityhub.php';

session_start();

$ih = new TheIdentityHub;


?>
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <title>PHP test app</title>
    
    <style>
    	.box {margin: 20px; padding: 0 10px 10px 10px; border: 1px solid #999999;}	
    </style>
</head>
<body>

<div id="menu" class="box" style="width: 25%;">
	<h3>Menu</h3>
	<ul>
		<li style="background-color: #aaaaaa;"><a href="index.php">Home</a></li>
		<li><a href="profile.php">Profile</a></li>
		<li><a href="accounts.php">Accounts</a></li>
		<li><a href="friends.php">Friends</a></li>
	</ul>
</div>

<div id="login" class="box">
	<h3>Login form</h3>
	<?php if ($ih->isAuthenticated) { ?>
		<a href="<?php echo $ih->getSignOutURL(); ?>">Log out</a>
	<?php } else { ?>	
		<p>
			<a href="<?php echo $ih->getSignInURL(); ?>">Log in</a>
		</p>
	<?php } ?>	
</div>	

<div id="page_content" class="box">
	<h3>Page content</h3>
	
	<h4>Home page</h2>
	<p>This is small PHP site for illustration on how to use Identity Hub server side PHP SDK.</p>
	
	<?php if ($ih->isAuthenticated) { ?>

		<p>Welcome, you are logged in as:</p>	
		<ul>
			<li>identityId: <?php echo $ih->identity->identityId; ?></li>
			<li>displayName: <?php echo $ih->identity->displayName; ?></li>
		</ul>
		
		<?php 
			/*
			$ih->getProfileUser(12297);
			$ih->getAccounts();
			$ih->getAccountsUser($ih->identity->identityId);
			$ih->getFriends();
			$ih->getFriendsUser(12297);
			
			echo '<h4>Profile data</h4>';
			echo '<pre>';
			echo var_dump($ih->identity);
			echo '</pre>';
			
			//echo var_dump($ih->identityUser);
 			
			echo '<h4>Acounts data</h4>';
			echo '<pre>';
 			echo var_dump($ih->accounts);
			echo '</pre>';
			
			// //echo var_dump($ih->accountsUser);
			
			echo '<h4>Friends data</h4>';
			echo '<pre>';
			echo var_dump($ih->friends);
			echo '</pre>';
			
			// echo var_dump($ih->friendsUser);
			*/
 		?>
		

	<?php } else { ?>

		<p>Log in to see the data.</p>

	<?php } ?>
	
</div>

<div id="footer" class="box">
	<h3>Footer</h3>
	<p>The Identity Hub server side PHP SDK example site</p>
	<p>&nbsp;</p>	
	<?php if ($ih->debug) { ?>
		<div>
			<h5>Debug data:</h5>	
			<p><?php $ih->debugEcho(); ?></p>
		</div>
	<?php } ?>
</div>

</body>