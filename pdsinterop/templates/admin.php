<?php
	script('pdsinterop', 'settings-admin');
	style('pdsinterop', 'settings-admin');
?>

<div id="pdsinterop-admin" class="section">
	<h2 class="inlineblock"><?php p($l->t('Pdsinterop Settings')); ?></h2>
	<p>
		<label>
			<span><?php p($l->t('Private Key')); ?></span>
			<textarea id="pdsinterop-private-key" type="text" value="<?php p($_['privateKey']); ?>"></textarea>
		</label>

		<label>
			<span><?php p($l->t('Public Key')); ?></span>
			<textarea id="pdsinterop-public-key" type="text" value="<?php p($_['publicKey']); ?>"></textarea>
		</label>
	</div>
</div>
