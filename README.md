# solid-nextcloud
A plugin to make Nextcloud compatible with Solid.

You can download it via the Nextcloud app store: https://apps.nextcloud.com/apps/solid
IMPORTANT: Follow the [install instructions!](https://github.com/pdsinterop/solid-nextcloud/blob/main/INSTALL.md).

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
Please follow the [install instructions](https://github.com/pdsinterop/solid-nextcloud/blob/main/INSTALL.md).

## Unattended testing
There is a [GitHub Action](https://github.com/pdsinterop/solid-nextcloud/actions/workflows/ci.yml) that runs a [Docker-based test script](https://github.com/pdsinterop/solid-nextcloud/blob/585b968/.github/workflows/ci.yml#L29).

## Manual testing
You can try out the various Solid apps that show up in the Solid App GUI inside the Nextcloud GUI on first use.

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
