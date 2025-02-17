<?php
	// phpcs:ignoreFile
	// Let codesniffer ignore this file, as we are heavily mixing php and HTML together.
	script('solid', 'settings-admin');
	style('solid', 'settings-admin');
?>

<div id="solid-admin" class="section">
	<h2 class="inlineblock"><?php p($l->t('Solid OpenID Connect Settings')); ?></h2>
	<p>
		<label>
			<span><?php p($l->t('Private Key')); ?></span>
			<textarea id="solid-private-key" type="text"><?php p($_['privateKey']); ?></textarea>
		</label>

		<label>
			<span><?php p($l->t('Encryption Key')); ?></span>
			<textarea id="solid-encryption-key" type="text"><?php p($_['encryptionKey']); ?></textarea>
		</label>
	</p>
	<h2 class="inlineblock"><?php p($l->t('Solid Client Registrations')); ?></h2>
	<table class="grid">
		<thead>
			<tr>
				<th>Client ID</th>
				<th>Client name</th>
				<th>Block</th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ($_['clients'] as $client => $registration) { ?>
				<tr>
					<td><?php p($registration['clientId']); ?></td>
					<td><?php p($registration['clientName']); ?></td>
					<td><input type="checkbox" class="solid-client-block" data-client="<?php p($registration['clientId']); ?>"<?php if ($registration['clientBlocked']) { echo " checked";} ?> value=1></td>
				</tr>
			<?php } ?>
		</tbody>
	</table>
</div>