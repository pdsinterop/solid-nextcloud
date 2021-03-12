<?php
script('solid', 'script');
style('solid', 'style');
?>
<div id="app">
	<div id="app-navigation">
		<?php print_unescaped($this->inc('navigation/index')); ?>
	</div>
	<div id="app-content">
		<div id="app-content-wrapper">
			<main class="solid-container">
				<form action="" method="POST">
					<h1><?php p($_['clientName']); ?> is requesting your consent to use <?php p($_['serverName']); ?> as an identity provider</h1>
					<input type="hidden" name="returnUrl" value="<?php p($_['returnUrl']); ?>">
					<button name="approval" value="allow">Allow</button>
					<button name="approval" value="deny">Deny</button>
				</form>
			</main>
		</div>
	</div>
</div>

