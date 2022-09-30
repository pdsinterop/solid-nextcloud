# Flysystem Nextcloud

[![Project stage: Development][project-stage-badge: Development]][project-stage-page]
[![License][license-shield]][license-link]
[![Latest Version][version-shield]][version-link]
![Maintained][maintained-shield]

[![PDS Interop][pdsinterop-shield]][pdsinterop-site]
[![standard-readme compliant][standard-readme-shield]][standard-readme-link]
[![keep-a-changelog compliant][keep-a-changelog-shield]][keep-a-changelog-link]

_Flysystem adapter for the Nextcloud filesystem_

## Table of Contents

<!-- toc -->

- [Background](#background)
- [Installation](#installation)
- [Usage](#usage)
- [Develop](#develop)
- [Contribute](#contribute)
- [License](#license)

<!-- tocstop -->

## Background

This project is part of the PHP stack of projects by PDS Interop. It is used by
the Solid-Nextcloud app.

As the functionality seemed useful for other projects, it was implemented as a
separate package.

## Installation

The advised install method is through composer:

```
composer require pdsinterop/flysystem-nextcloud
```

## Usage

This package offers features to interact with the Filesystem provided by
Nextcloud through the Flysystem API.

To use the adapter, instantiate it and add it to a Flysystem filesystem:

```php
<?php
/** @var IRootFolder $rootFolder */
$folder = $rootFolder->getUserFolder('userId')->get('/some/directory');

// Create the Nextcloud Adapter
$adapter = new \Pdsinterop\Flysystem\Adapter\Nextcloud($folder);

// Create Flysystem as usual, adding the Adapter
$filesystem = new \League\Flysystem\Filesystem($adapter);

// Read the contents of a file
$content = $filesystem->read('/some.file');
```

## Develop

- Do not forget to install the required dependencies using `composer`.

- Most of the logic here involves Nextcloud and/or FlySystem. You'll want to familiarise yourself with their workings.

## Contribute

Questions or feedback can be given by [opening an issue on GitHub][issues-link].

All PDS Interop projects are open source and community-friendly.
Any contribution is welcome!
For more details read the [contribution guidelines][contributing-link].

All PDS Interop projects adhere to [the Code Manifesto](http://codemanifesto.com)
as its [code-of-conduct][code-of-conduct]. Contributors are expected to abide by its terms.

There is [a list of all contributors on GitHub][contributors-page].

For a list of changes see the [CHANGELOG][changelog] or [the GitHub releases page][releases-page].

## License

All code created by PDS Interop is licensed under the [MIT License][license-link].

[changelog]: CHANGELOG.md
[code-of-conduct]: CODE_OF_CONDUCT.md
[contributing-link]: CONTRIBUTING.md
[contributors-page]: https://github.com/pdsinterop/flysystem-nextcloud/contributors
[issues-link]: https://github.com/pdsinterop/flysystem-nextcloud/issues
[keep-a-changelog-link]: https://keepachangelog.com/
[keep-a-changelog-shield]: https://img.shields.io/badge/Keep%20a%20Changelog-f15d30.svg?logo=data%3Aimage%2Fsvg%2Bxml%3Bbase64%2CPHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIGZpbGw9IiNmZmYiIHZpZXdCb3g9IjAgMCAxODcgMTg1Ij48cGF0aCBkPSJNNjIgN2MtMTUgMy0yOCAxMC0zNyAyMmExMjIgMTIyIDAgMDAtMTggOTEgNzQgNzQgMCAwMDE2IDM4YzYgOSAxNCAxNSAyNCAxOGE4OSA4OSAwIDAwMjQgNCA0NSA0NSAwIDAwNiAwbDMtMSAxMy0xYTE1OCAxNTggMCAwMDU1LTE3IDYzIDYzIDAgMDAzNS01MiAzNCAzNCAwIDAwLTEtNWMtMy0xOC05LTMzLTE5LTQ3LTEyLTE3LTI0LTI4LTM4LTM3QTg1IDg1IDAgMDA2MiA3em0zMCA4YzIwIDQgMzggMTQgNTMgMzEgMTcgMTggMjYgMzcgMjkgNTh2MTJjLTMgMTctMTMgMzAtMjggMzhhMTU1IDE1NSAwIDAxLTUzIDE2bC0xMyAyaC0xYTUxIDUxIDAgMDEtMTItMWwtMTctMmMtMTMtNC0yMy0xMi0yOS0yNy01LTEyLTgtMjQtOC0zOWExMzMgMTMzIDAgMDE4LTUwYzUtMTMgMTEtMjYgMjYtMzMgMTQtNyAyOS05IDQ1LTV6TTQwIDQ1YTk0IDk0IDAgMDAtMTcgNTQgNzUgNzUgMCAwMDYgMzJjOCAxOSAyMiAzMSA0MiAzMiAyMSAyIDQxLTIgNjAtMTRhNjAgNjAgMCAwMDIxLTE5IDUzIDUzIDAgMDA5LTI5YzAtMTYtOC0zMy0yMy01MWE0NyA0NyAwIDAwLTUtNWMtMjMtMjAtNDUtMjYtNjctMTgtMTIgNC0yMCA5LTI2IDE4em0xMDggNzZhNTAgNTAgMCAwMS0yMSAyMmMtMTcgOS0zMiAxMy00OCAxMy0xMSAwLTIxLTMtMzAtOS01LTMtOS05LTEzLTE2YTgxIDgxIDAgMDEtNi0zMiA5NCA5NCAwIDAxOC0zNSA5MCA5MCAwIDAxNi0xMmwxLTJjNS05IDEzLTEzIDIzLTE2IDE2LTUgMzItMyA1MCA5IDEzIDggMjMgMjAgMzAgMzYgNyAxNSA3IDI5IDAgNDJ6bS00My03M2MtMTctOC0zMy02LTQ2IDUtMTAgOC0xNiAyMC0xOSAzN2E1NCA1NCAwIDAwNSAzNGM3IDE1IDIwIDIzIDM3IDIyIDIyLTEgMzgtOSA0OC0yNGE0MSA0MSAwIDAwOC0yNCA0MyA0MyAwIDAwLTEtMTJjLTYtMTgtMTYtMzEtMzItMzh6bS0yMyA5MWgtMWMtNyAwLTE0LTItMjEtN2EyNyAyNyAwIDAxLTEwLTEzIDU3IDU3IDAgMDEtNC0yMCA2MyA2MyAwIDAxNi0yNWM1LTEyIDEyLTE5IDI0LTIxIDktMyAxOC0yIDI3IDIgMTQgNiAyMyAxOCAyNyAzM3MtMiAzMS0xNiA0MGMtMTEgOC0yMSAxMS0zMiAxMXptMS0zNHYxNGgtOFY2OGg4djI4bDEwLTEwaDExbC0xNCAxNSAxNyAxOEg5NnoiLz48L3N2Zz4K
[license-link]: ./LICENSE
[license-shield]: https://img.shields.io/github/license/pdsinterop/flysystem-nextcloud.svg
[maintained-shield]: https://img.shields.io/maintenance/yes/2022
[pdsinterop-shield]: https://img.shields.io/badge/-PDS%20Interop-7C4DFF.svg?logo=data%3Aimage%2Fsvg%2Bxml%3Bbase64%2CPHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9Ii01IC01IDExMCAxMTAiIGZpbGw9IiNGRkYiIHN0cm9rZS13aWR0aD0iMCI+CiAgICA8cGF0aCBkPSJNLTEgNTJoMTdhMzcuNSAzNC41IDAgMDAyNS41IDMxLjE1di0xMy43NWEyMC43NSAyMSAwIDAxOC41LTQwLjI1IDIwLjc1IDIxIDAgMDE4LjUgNDAuMjV2MTMuNzVhMzcgMzQuNSAwIDAwMjUuNS0zMS4xNWgxN2EyMiAyMS4xNSAwIDAxLTEwMiAweiIvPgogICAgPHBhdGggZD0iTSAxMDEgNDhhMi43NyAyLjY3IDAgMDAtMTAyIDBoIDE3YTIuOTcgMi44IDAgMDE2OCAweiIvPgo8L3N2Zz4K
[pdsinterop-site]: https://pdsinterop.org/
[project-stage-badge: Development]: https://img.shields.io/badge/Project%20Stage-Development-yellowgreen.svg
[project-stage-page]: https://blog.pother.ca/project-stages/
[releases-page]: https://github.com/pdsinterop/flysystem-rdf/releases
[standard-readme-link]: https://github.com/RichardLitt/standard-readme
[standard-readme-shield]: https://img.shields.io/badge/-Standard%20Readme-brightgreen.svg
[version-link]: https://packagist.org/packages/pdsinterop/flysystem-nextcloud
[version-shield]: https://img.shields.io/github/v/release/pdsinterop/flysystem-nextcloud?sort=semver
