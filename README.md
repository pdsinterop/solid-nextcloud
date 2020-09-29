# solid-nextcloud
A plugin to make Nextcloud compatible with Solid

To programmatically build a Nextcloud server that has this plugin working,
you can look at the [nextcloud-server image in the Solid test-suite](https://github.com/solid/test-suite/blob/master/servers/nextcloud-server/Dockerfile),
combined with [how it's initialized](https://github.com/solid/test-suite/blob/665824a/runTests.sh#L52-L53) in the runTests.sh script.

If you enable this app in your Nextcloud instance, you should
[edit your .htaccess file](https://github.com/solid/test-suite/blob/665824af763ddd5dd7242cbc8b18faad4ac304e3/servers/nextcloud-server/init.sh#L5)
and then test whether https://your-server.com/.well-known/openid-configuration redirects to https://your-server.com/apps/solid/openid.