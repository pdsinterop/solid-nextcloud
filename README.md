# solid-nextcloud

[![Project stage: Production Ready][project-stage-badge: Production Ready]][project-stage-page]
[![License][license-shield]][license-link]
[![Latest Version][version-shield]][version-link]
![Maintained][maintained-shield]

[![PDS Interop][pdsinterop-shield]][pdsinterop-site]
[![standard-readme compliant][standard-readme-shield]][standard-readme-link]
[![keep-a-changelog compliant][keep-a-changelog-shield]][keep-a-changelog-link]

A plugin to make Nextcloud compatible with Solid.

<!-- toc -->

## Installation

You can download it via the Nextcloud app store: https://apps.nextcloud.com/apps/solid

### Manual install

Please follow the [install instructions](https://github.com/pdsinterop/solid-nextcloud/blob/main/INSTALL.md).

<!-- @TODO: ## Usage --> 

## Contributing

Questions or feedback can be given by [opening an issue on GitHub][issues-link].

All PDS Interop projects are open source and community-friendly.
Any contribution is welcome!
For more details read the [contribution guidelines][contributing-link].

All PDS Interop projects adhere to [the Code Manifesto](http://codemanifesto.com)
as its [code-of-conduct][code-of-conduct]. Contributors are expected to abide by its terms.

There is [a list of all contributors on GitHub][contributors-page].

For a list of changes see the [CHANGELOG][changelog] or [the GitHub releases page][releases-page].

### Development

#### Development install

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

### Testing 

#### Unattended testing

There is a [GitHub Action](https://github.com/pdsinterop/solid-nextcloud/actions/workflows/ci.yml) that runs a [Docker-based test script](https://github.com/pdsinterop/solid-nextcloud/blob/585b968/.github/workflows/ci.yml#L29).

#### Manual testing

You can try out the various Solid apps that show up in the Solid App GUI inside the Nextcloud GUI on first use.

### Build / Deploy

For publishing to the Nextcloud app store see [the deploy instructions](docs/deploy.md).

## License

All code created by PDS Interop is licensed under the [MIT License][license-link].

[changelog]: CHANGELOG.md
[code-of-conduct]: https://pdsinterop.org/code-of-conduct/
[contributing-link]: https://pdsinterop.org/contributing/
[contributors-page]: https://github.com/pdsinterop/solid-nextcloud/contributors
[issues-link]: https://github.com/pdsinterop/solid-nextcloud/issues
[releases-page]: https://github.com/pdsinterop/solid-nextcloud/releases
[keep-a-changelog-link]: https://keepachangelog.com/
[keep-a-changelog-shield]: https://img.shields.io/badge/Keep%20a%20Changelog-f15d30.svg?logo=data%3Aimage%2Fsvg%2Bxml%3Bbase64%2CPHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIGZpbGw9IiNmZmYiIHZpZXdCb3g9IjAgMCAxODcgMTg1Ij48cGF0aCBkPSJNNjIgN2MtMTUgMy0yOCAxMC0zNyAyMmExMjIgMTIyIDAgMDAtMTggOTEgNzQgNzQgMCAwMDE2IDM4YzYgOSAxNCAxNSAyNCAxOGE4OSA4OSAwIDAwMjQgNCA0NSA0NSAwIDAwNiAwbDMtMSAxMy0xYTE1OCAxNTggMCAwMDU1LTE3IDYzIDYzIDAgMDAzNS01MiAzNCAzNCAwIDAwLTEtNWMtMy0xOC05LTMzLTE5LTQ3LTEyLTE3LTI0LTI4LTM4LTM3QTg1IDg1IDAgMDA2MiA3em0zMCA4YzIwIDQgMzggMTQgNTMgMzEgMTcgMTggMjYgMzcgMjkgNTh2MTJjLTMgMTctMTMgMzAtMjggMzhhMTU1IDE1NSAwIDAxLTUzIDE2bC0xMyAyaC0xYTUxIDUxIDAgMDEtMTItMWwtMTctMmMtMTMtNC0yMy0xMi0yOS0yNy01LTEyLTgtMjQtOC0zOWExMzMgMTMzIDAgMDE4LTUwYzUtMTMgMTEtMjYgMjYtMzMgMTQtNyAyOS05IDQ1LTV6TTQwIDQ1YTk0IDk0IDAgMDAtMTcgNTQgNzUgNzUgMCAwMDYgMzJjOCAxOSAyMiAzMSA0MiAzMiAyMSAyIDQxLTIgNjAtMTRhNjAgNjAgMCAwMDIxLTE5IDUzIDUzIDAgMDA5LTI5YzAtMTYtOC0zMy0yMy01MWE0NyA0NyAwIDAwLTUtNWMtMjMtMjAtNDUtMjYtNjctMTgtMTIgNC0yMCA5LTI2IDE4em0xMDggNzZhNTAgNTAgMCAwMS0yMSAyMmMtMTcgOS0zMiAxMy00OCAxMy0xMSAwLTIxLTMtMzAtOS01LTMtOS05LTEzLTE2YTgxIDgxIDAgMDEtNi0zMiA5NCA5NCAwIDAxOC0zNSA5MCA5MCAwIDAxNi0xMmwxLTJjNS05IDEzLTEzIDIzLTE2IDE2LTUgMzItMyA1MCA5IDEzIDggMjMgMjAgMzAgMzYgNyAxNSA3IDI5IDAgNDJ6bS00My03M2MtMTctOC0zMy02LTQ2IDUtMTAgOC0xNiAyMC0xOSAzN2E1NCA1NCAwIDAwNSAzNGM3IDE1IDIwIDIzIDM3IDIyIDIyLTEgMzgtOSA0OC0yNGE0MSA0MSAwIDAwOC0yNCA0MyA0MyAwIDAwLTEtMTJjLTYtMTgtMTYtMzEtMzItMzh6bS0yMyA5MWgtMWMtNyAwLTE0LTItMjEtN2EyNyAyNyAwIDAxLTEwLTEzIDU3IDU3IDAgMDEtNC0yMCA2MyA2MyAwIDAxNi0yNWM1LTEyIDEyLTE5IDI0LTIxIDktMyAxOC0yIDI3IDIgMTQgNiAyMyAxOCAyNyAzM3MtMiAzMS0xNiA0MGMtMTEgOC0yMSAxMS0zMiAxMXptMS0zNHYxNGgtOFY2OGg4djI4bDEwLTEwaDExbC0xNCAxNSAxNyAxOEg5NnoiLz48L3N2Zz4K
[license-link]: ./LICENSE
[license-shield]: https://img.shields.io/github/license/pdsinterop/solid-nextcloud.svg
[maintained-shield]: https://img.shields.io/maintenance/yes/2023.svg
[pdsinterop-shield]: https://img.shields.io/badge/-PDS%20Interop-7C4DFF.svg?logo=data%3Aimage%2Fsvg%2Bxml%3Bbase64%2CPHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9Ii01IC01IDExMCAxMTAiIGZpbGw9IiNGRkYiIHN0cm9rZS13aWR0aD0iMCI+CiAgICA8cGF0aCBkPSJNLTEgNTJoMTdhMzcuNSAzNC41IDAgMDAyNS41IDMxLjE1di0xMy43NWEyMC43NSAyMSAwIDAxOC41LTQwLjI1IDIwLjc1IDIxIDAgMDE4LjUgNDAuMjV2MTMuNzVhMzcgMzQuNSAwIDAwMjUuNS0zMS4xNWgxN2EyMiAyMS4xNSAwIDAxLTEwMiAweiIvPgogICAgPHBhdGggZD0iTSAxMDEgNDhhMi43NyAyLjY3IDAgMDAtMTAyIDBoIDE3YTIuOTcgMi44IDAgMDE2OCAweiIvPgo8L3N2Zz4K
[pdsinterop-site]: https://pdsinterop.org/
[project-stage-badge: Production Ready]: https://img.shields.io/badge/Project%20Stage-Production%20Ready-brightgreen.svg
[project-stage-page]: https://blog.pother.ca/project-stages/
[standard-readme-link]: https://github.com/RichardLitt/standard-readme
[standard-readme-shield]: https://img.shields.io/badge/-Standard%20Readme-brightgreen.svg
[version-link]: https://packagist.org/packages/pdsinterop/solid-nextcloud
[version-shield]: https://img.shields.io/github/v/release/pdsinterop/solid-nextcloud.svg?sort=semver
