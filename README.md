# solid-nextcloud
A plugin to make Nextcloud compatible with Solid

## CAVEAT: Only identity is working so far!
So far, we only implemented the functionality from [milestone 1](https://github.com/pdsinterop/project-admin/blob/master/milestones.md#1-identity).
Storage and deep integration coming soon!

## Unattended install
To programmatically build a Nextcloud server that has this plugin working,
you can look at the [nextcloud-server image in the Solid test-suite](https://github.com/solid/test-suite/blob/master/servers/nextcloud-server/Dockerfile),
combined with [how it's initialized](https://github.com/solid/test-suite/blob/665824a/runTests.sh#L52-L53) in the runTests.sh script.

## Manual install
If you enable this app in your Nextcloud instance, you should
[edit your .htaccess file](https://github.com/solid/test-suite/blob/665824af763ddd5dd7242cbc8b18faad4ac304e3/servers/nextcloud-server/init.sh#L5)
and then test whether https://your-server.com/.well-known/openid-configuration redirects to https://your-server.com/apps/solid/openid.

## Unattended testing
To test whether your server is install correctly, you can run Solid's [webid-provider-tests](https://github.com/solid/webid-provider-tests#against-production) against it.

## Manual testing
Go to https://solid.community and create a Solid pod.
Go to https://generator.inrupt.com/text-editor and log in with https://solid.community. Tick all 4 boxes in the consent dialog.
Click 'Load', type something, click 'Save'.
Grant access to co-editor https://your-nextcloud-server.com/apps/solid/@your-username/turtle#me
Now in a separate browser window, log in to  https://generator.inrupt.com/text-editor with https://syour-nextcloud-server.com.
You should be able to edit the file as a co-author now, using your Nextcloud account as a webid identity provider.