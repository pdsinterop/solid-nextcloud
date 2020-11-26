/*
SPDX-FileCopyrightText: 2020, Michiel de Jong <<michiel@unhosted.org>>
*
SPDX-License-Identifier: MIT
*/




<?php
script('solid', 'script');
style('solid', 'style');
?>

<div id="app">
	<div id="app-navigation">
		<?php print_unescaped($this->inc('navigation/index')); ?>
		<?php print_unescaped($this->inc('settings/index')); ?>
	</div>

	<div id="app-content">
		<div id="app-content-wrapper">
			<?php print_unescaped($this->inc('content/index')); ?>
		</div>
	</div>
</div>

