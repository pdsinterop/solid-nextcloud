<?php
	script("solid", "vendor/simplyedit/simply-edit");
	script("solid", "vendor/simplyedit/simply.everything");
	script("solid", "vendor/solid/solid-auth-fetcher.bundle");
	script("solid", "launcher");
?>
<script type="application/json" id="apps">
    <?php echo($_['appsListJson']); ?>
</script>
<main class="solid-launcher" data-simply-field="page" data-simply-content="template">
	<template data-simply-template="Launcher">
		<ul data-simply-list="apps" class="solid-apps">
			<template>
				<li class="solid-app">
					<h2><span data-simply-field="name"></span> <span data-simply-field="registered" data-simply-transformer="registered"></span></h2>
					<p data-simply-field="tagline"></p>
					<h3><span data-simply-field="name"></span> needs the following access to your Pod:</h3>
					<div data-simply-list="requirements">
						<template>
							<div class="solid-permissions" data-simply-field="type" data-simply-content="template">
								<template data-simply-template="podWide">
									Permissions on <strong>all data</strong> in your Pod:
									<ul data-simply-list="permissions" data-simply-entry="entry">
										<template>
											<li class="solid-acl"><span data-simply-field="entry" data-simply-transformer="grants"> all data in your Pod</li>
										</template>
									</ul>
								</template>
								<template data-simply-template="container">
									Permissions in the folder <code data-simply-field="container"></code> in your Pod:
									<ul data-simply-list="permissions" data-simply-entry="entry">
										<template>
											<li class="solid-acl"><span data-simply-field="entry" data-simply-transformer="grants"></li>
										</template>
									</ul>
								</template>
								<template data-simply-template="class">
									Permissions on <span data-simply-field="class" data-simply-transformer="schemaClass"></span> in your Pod:
									<ul data-simply-list="permissions" data-simply-entry="entry">
										<template>
											<li class="solid-acl"><span data-simply-field="entry" data-simply-transformer="grants"></li>
										</template>
									</ul>
								</template>
							</div>
						</template>
					</div>
					<div data-simply-field="registered" data-simply-content="template">
						<template data-simply-template="0">
							<button
									data-simply-command="allowAndLaunch"
									data-simply-field="launchUrl" 
									data-simply-content="attributes" 
									data-simply-attributes="data-solid-url"
								>Allow and Launch</button>
						</template>
						<template data-simply-template="1">
							<button
								data-simply-command="launch"
								data-simply-field="launchUrl" 
								data-simply-content="attributes" 
								data-simply-attributes="data-solid-url"
							>Launch</button>
						</template>
					</div>
				</li>
			</template>
		</ul>
	</template>
</main>
