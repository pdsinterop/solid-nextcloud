# solid-nextcloud
A plugin to make Nextcloud compatible with Solid.

You can download it via the Nextcloud app store: https://apps.nextcloud.com/apps/solid
IMPORTANT: Follow the [additional install instructions!](https://github.com/pdsinterop/solid-nextcloud/blob/main/INSTALL.md).

## Development install
Clone https://github.com/pdsinterop/test-suites, cd into it, and run:
```sh
docker pull nextcloud
docker build -t nextcloud-server-base ./servers/nextcloud-server/base/
docker build -t nextcloud-server ./servers/nextcloud-server/server/
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

# Publishing to the Nextcloud app store

* `git checkout main`
* `git pull`
* Tag v0.0.X in solid/appinfo/info.xml
* `git tag`
* `git tag v0.0.X`
* `git push --follow-tags`
* `git checkout publish`
* `git pull`
* `git merge main`
* `cd solid`
* `php ../composer.phar update`
* `php ../composer.phar install --no-dev --prefer-dist`
* `git commit -am"built"` (at least `vendor/composer/installed.php` will have changed)
* `git push`
* `cd ..`
* create a release on github from the tag you just pushed
* `tar -cf solid.tar solid/`
* `gzip solid.tar`
* `git checkout main`
* edit the release and upload `solid.tar.gz` as a binary attachment
* make sure transfer/solid.key and transfer/solid.crt exist
* `openssl dgst -sha512 -sign ./transfer/solid.key ./solid.tar.gz | openssl base64`
* visit https://apps.nextcloud.com/developer/apps/releases/new
* go into the developer tools browser console to change the `<a>` element around the form to a `<p>` element. This makes it possible to fill in values in this form without being redirected.
* fill in for instance `https://github.com/pdsinterop/solid-nextcloud/releases/download/v0.0.2/solid.tar.gz` and the base64 signature from the openssl command
* click 'uploaden'
* good luck!
