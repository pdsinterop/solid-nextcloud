# solid-nextcloud
A plugin to make Nextcloud compatible with Solid.

You can download it via the Nextcloud app store: https://apps.nextcloud.com/apps/solid


## Unattended install
To programmatically build a Nextcloud server that has this plugin working,
you can look at the [nextcloud-server image in the Solid test-suite](https://github.com/solid/test-suite/blob/main/servers/nextcloud-server/Dockerfile),
combined with [how it's initialized](https://github.com/solid/test-suite/blob/665824a/runTests.sh#L52-L53) in the runTests.sh script.

## Development install
Clone https://github.com/pdsinterop/test-suites, cd into it, and run:
```sh
docker build -t nextcloud-server ./servers/nextcloud-server
docker run -p 443:443 -d --rm --name=server nextcloud-server
echo sleeping
sleep 10
echo slept
docker logs server
docker exec -u www-data -it server sh /init.sh
docker exec -u root -it server service apache2 reload
```
Now visit https://localhost and log in as alice / alice123.

## Manual install
If you enable this app in your Nextcloud instance, you should
[edit your .htaccess file](https://github.com/solid/test-suite/blob/665824af763ddd5dd7242cbc8b18faad4ac304e3/servers/nextcloud-server/init.sh#L5)
and then test whether https://your-nextcloud-server.com/.well-known/openid-configuration redirects to https://your-nextcloud-server.com/apps/solid/openid.

Also, take the CORS instructions from site.conf and add them to your own webserver configuration.

## Unattended testing
To test whether your server is install correctly, you can run Solid's [webid-provider-tests](https://github.com/solid/webid-provider-tests#against-production) against it.

## Manual testing
Go to https://solidcommunity.net and create a Solid pod.
Go to https://generator.inrupt.com/text-editor and log in with https://solidcommunity.net. Tick all 4 boxes in the consent dialog.
Click 'Load', type something, click 'Save'.
Grant access to co-editor https://your-nextcloud-server.com/apps/solid/@your-username/profile/card#me.
For instance if you're using the [development install](#development-install), that would be https://localhost/apps/solid/@alice/profile/card#me
Now in a separate browser window, log in to  https://generator.inrupt.com/text-editor with https://your-nextcloud-server.com.
You should be able to edit the file as a co-author now, using your Nextcloud account as a webid identity provider.
