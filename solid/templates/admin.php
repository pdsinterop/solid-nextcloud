<?php
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
</div>