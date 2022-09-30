# Flysystem Factory

This is a factory package to provide for an easy to use configuration and api for flysystem users.

**Package details** 

[![Latest Release Version](https://img.shields.io/github/release/mjrider/flysystem-factory.svg?style=flat-square)](https://packagist.org/packages/mjrider/flysystem-factory)
[![License](https://img.shields.io/github/license/mjrider/flysystem-factory.svg?style=flat-square)](https://packagist.org/packages/mjrider/flysystem-factory)
![Maintenance](https://img.shields.io/maintenance/yes/2020.svg?style=flat-square)
[![Total Downloads](https://img.shields.io/packagist/dt/mjrider/flysystem-factory.svg?style=flat-square)](https://packagist.org/packages/mjrider/flysystem-factory)

**Code quality**

[![Build Status](https://travis-ci.org/mjrider/flysystem-factory.svg?branch=master)](https://travis-ci.org/mjrider/flysystem-factory)

**Compatibility** 

![PHP_Compatibility 5.4 and up](https://img.shields.io/badge/empty-yes-brightgreen.svg?&label=PHP%20>=%205.4&style=flat-square)
![PHP_Compatibility 7.0 and up](https://img.shields.io/badge/empty-yes-brightgreen.svg?&label=PHP%20>=%207.0&style=flat-square)

## Usage

You can require the bundle:

```
composer require mjrider/flysystem-factory
```

Various backends require additional composer packages. Due to the fact that some are mutual exclusive they are not not a dependency for this package. Please install them conform your own needs

Adapters:

- B2: `mhetreramesh/flysystem-backblaze`
- S3: `league/flysystem-aws-s3-v3`

Caching:

- Memcached: `ext-memcached`
- Predis: `predis/predis`

The syntax for the url follows the following scheme
`adapter://user:pass@[host|region]/sub/folder?extraparam=foo`

For the full list of supported options per adapter see [the examples][examples-page].

### Examples

Examples are listed in de [examples.md][examples-page].


## Upgrading

From time to time there will be breaking change. These will be documented in 
[UPGRADING.md][upgrading-page]. 

It is highly recommended to check what has changed before upgrading to a new 
minor or major release.
 

### Enforcement

The `master` branch is protected so that _at least_ one other developer approves
 by reviewing the change.

For more information about required reviews for pull requests, please check [the 
official GitHub documentation][github-pull-requests].


## Development

The code is accompanied by tests. These can be run using:

    ./vendor/bin/phpunit

## Authors & Contributors

For a full list off all author and/or contributors, please check the [contributors page][contributors-page].

## License

The code in this repository has been licensed under an [MIT License][license-page].

[contributors-page]: https://github.com/mjrider/flysystem-factory/graphs/contributors
[examples-page]: https://github.com/mjrider/flysystem-factory/blob/master/examples.md
[github-pull-requests]: https://help.github.com/articles/about-required-reviews-for-pull-requests/
[license-page]: https://github.com/mjrider/flysystem-factory/blob/master/LICENSE
[upgrading-page]: https://github.com/mjrider/flysystem-factory/blob/master/UPGRADING.md
