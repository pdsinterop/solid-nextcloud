<main class="solid-container">
	<section>
		<div class="app-content-details" id="solid-body"
			xmlns:vcard="http://www.w3.org/2006/vcard/ns#"
			xmlns:foaf="http://xmlns.com/foaf/0.1/"
			xmlns:solid="http://www.w3.org/ns/solid/terms#"
			xmlns:acl="http://www.w3.org/ns/auth/acl#"
		>
			<h2>Your Solid profile</h2>
			<p>WebID URI: <span data-simply-field="subject"><?php p($_['profileUri']); ?></span></p>
			<p>Your display name: <span property="foaf:name"><?php p($_['displayName']); ?></span></p>
		</div>
	</section>
</main>