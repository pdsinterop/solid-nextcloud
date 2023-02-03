# Deploy Process

<!-- toc -->

- [Overview](#overview)
- [Releasing a version](#releasing-a-version)
  - [Opening an MR](#opening-an-mr)
  - [Tagging a release](#tagging-a-release)
- [Building a package](#building-a-package)
  - [Installing dependencies](#installing-dependencies)
  - [Creating a tarball](#creating-a-tarball)
- [Deploying the package](#deploying-the-package)
  - [Uploading the tarball](#uploading-the-tarball)
  - [Creating a release in the Nextcloud App store](#creating-a-release-in-the-nextcloud-app-store)
    - [Creating a signature](#creating-a-signature)
    - [Adding the signature and link to the Nextcloud App store](#adding-the-signature-and-link-to-the-nextcloud-app-store)

<!-- tocstop -->

## Overview

A _"**release**"_ in the Nextcloud App store consists of a URL where an archive lives (zip, tar, gz, etc.) and a SSL digest (or "signature") with which the integrity of the archive can be validated.

To create a release, both the signature and the URL where the archive can be downloaded need to be posted to the App Store.
The archive is created from the source-code in this repository.

It is a common practice to create a _"**tag**"_, to clearly mark the point in code-base's history where the release has been created.
But the source-code from which the archive is created is not enough _by itself_.

The code also relies on other packages (some of which are created by PDS Interop) in order to function properly.
Because of this, an extra _"**build**"_ step is required between tagging the code and creating the archive.

Thus, to publish a release of this project to the Nextcloud app store, the following steps need to be taken:

- Release a version of the code-base
  - Open a release MR
  - Tag a release
- Build a package from the tagged release
  - Install dependencies
  - Create a tarball
- Deploy the built package
  - Upload the tarball to the GitHub Release page
  - Create a release in the Nextcloud App store

These steps are described in more detail below.

## Releasing a version

A release consists of two things:

- A tag in git, annotated and signed
- A release page on GitHub

The tag is needed so a release page can be created. The release page is needed so there is a place where the tarball can live.
Any changes that need to be made to the code-base for a release should live in a separate branch.
These changes can be merged to the main branch by opening a merge-request (MR).
Once the merge request has been merged, a tag can be created, as well as a release.

### Opening an MR

- Create a `release/v0.X.X` branch
- Change anything that needs to be updated for a new release (at the least the version in `solid/appinfo/info.xml`)
- Open an MR
When the MR is merged a release version can be tagged by creating a GitHub Release

### Tagging a release

It is possible to create a tag locally, using the `git tag` command.
However, this is not needed. When a release is created using the GitHub UI, a tag will be created automatically.
Once that has been done, a package can be built from the release.

## Building a package

The "package" consists of an archived version of the `solid/` directory.

Before a tarball can be created to use as package, various dependencies need to be installed.

### Installing dependencies

Currently, the dependencies this project has are satisfied by composer.

They can be installed using:

```sh
# This command should be run in the `solid/` directory
composer install --no-dev --no-interaction --no-plugins --no-scripts --prefer-dist
```

to make sure the provided dependencies are usable in the supported PHP versions, the preferred method is to call composer using a docker image. The verbose, but complete command for this is:

```sh
docker run \
    -it \
    --rm \
    --volume ~/.cache/composer/:/root/composer/ \
    --volume "${sourceDirectory}/solid:/app" \
    --workdir /app \
    php:8.0 \
    bash -c 'curl -s https://getcomposer.org/installer | php \
        && mv composer.phar /usr/local/bin/composer \
        && apt update \
        && apt install -y git zip \
        && COMPOSER_CACHE_DIR=/root/composer/ composer install --no-dev --no-interaction --no-plugins --no-scripts --prefer-dist \

```

### Creating a tarball

Once the dependencies are installed, an archive can be created using TAR:

```sh
# This command should be run from the project root
tar --create --file 'solid.tar.gz' --gzip ./solid
```

## Deploying the package

After the `solid.tar.gz` tarball has been created, it needs to be uploaded to the GitHub release page.

### Uploading the tarball

The tarball can be uploaded by editing the Release created on GitHub

The tarball URL will be similar to `https://github.com/pdsinterop/solid-nextcloud/releases/download/v0.X.X/solid.tar.gz`

### Creating a release in the Nextcloud App store

#### Creating a signature

Now that the tarball is available at a public URL, the "Upload app release" feature in the Nextcloud App Store can be used to create a release. In order to do so, a "Signature" needs to be created.

Make sure `transfer/solid.key` and `transfer/solid.crt` exist, as these are both needed to create a signature.

The command to create the signature is:

```
openssl dgst -sha512 -sign ./transfer/solid.key ./solid.tar.gz | openssl base64
```

#### Adding the signature and link to the Nextcloud App store

The signature and URL should be added using the form at https://apps.nextcloud.com/developer/apps/releases/new

Fill in the URL for the tarball from the GitHub release and the base64 signature from the openssl command.

After clicking "upload" a new release _should_ have been created.

This can be verified by visiting the app page in the store: https://apps.nextcloud.com/apps/solid
