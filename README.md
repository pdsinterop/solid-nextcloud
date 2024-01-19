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
Pleas follow the [install instructions](https://github.com/pdsinterop/solid-nextcloud/blob/main/INSTALL.md).

## Unattended testing
There is a [GitHub Action](https://github.com/pdsinterop/solid-nextcloud/actions/workflows/ci.yml) that runs a [Docker-based test script](https://github.com/pdsinterop/solid-nextcloud/blob/585b968/.github/workflows/ci.yml#L29).

## Manual testing
You can try out the various Solid apps that show up in the Solid App GUI inside the Nextcloud GUI on first use.

### Build / Deploy

For publishing to the Nextcloud app store see [the deploy instructions](docs/deploy.md).
