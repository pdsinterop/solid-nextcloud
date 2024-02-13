<?php
script('solid', 'script');
style('solid', 'style');
?>

<div id="app-navigation" class="app-navigation-personal">
	<?php print_unescaped($this->inc('navigation/index')); ?>
</div>

<div id="app-content">
	<div id="app-content-wrapper">
		<?php print_unescaped($this->inc('profile/index')); ?>
	</div>
</div>
