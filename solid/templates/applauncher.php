<?php
script('solid', 'script');
style('solid', 'style');
?>

<div id="app-navigation">
    <?php print_unescaped($this->inc('navigation/index')); ?>
</div>

<div id="app-content">
    <div id="app-content-wrapper" class="viewcontainer">
        <?php print_unescaped($this->inc('applauncher/index')); ?>
    </div>
</div>
