# Deploy Process

To publish this project to the Nextcloud app store, the following steps need to be taken:

- Install dependencies
- Create a GitHub release
- Create a package
- Upload the package

A script is provided which automates all of these steps. 
For more detail regarding each step, see below.

## Set up a deploy branch

- `git checkout main`
- `git pull`
- Tag v0.X.X in `solid/appinfo/info.xml`
- `git tag`
- `git tag v0.X.X`
- `git push --follow-tags`
- `git checkout publish`
- `git pull`
- `git merge main`

## Install dependencies

- `cd solid`
- `php ../composer.phar update`
- `php ../composer.phar install --no-dev --prefer-dist`
- `git commit -am"built"` (at least `vendor/composer/installed.php` will have changed)
- `git push`
- `cd ..`

## Create a GitHub release

- `tar -cf solid.tar solid/`
- `gzip solid.tar`
- `git checkout main`
- Edit the release and upload `solid.tar.gz` as a binary attachment

## Upload the package

- make sure transfer/solid.key and transfer/solid.crt exist
  ```
  openssl dgst -sha512 -sign ./transfer/solid.key ./solid.tar.gz | openssl base64
  ```
- visit https://apps.nextcloud.com/developer/apps/releases/new
- In the browser's Developer Tools console, change the `<a>` element around the form to a `<p>` element. This makes it possible to fill in values in this form without being redirected
- fill in `https://github.com/pdsinterop/solid-nextcloud/releases/download/v0.X.X/solid.tar.gz` and the base64 signature from the openssl command
- click 'upload'
