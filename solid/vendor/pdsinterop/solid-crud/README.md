# Solid Resource Server

[![Project stage: Development][project-stage-badge: Development]][project-stage-page]
[![License][license-shield]][license-link]
[![Latest Version][version-shield]][version-link]
![Maintained][maintained-shield]

[![PDS Interop][pdsinterop-shield]][pdsinterop-site]
[![standard-readme compliant][standard-readme-shield]][standard-readme-link]
[![keep-a-changelog compliant][keep-a-changelog-shield]][keep-a-changelog-link]

_Solid HTTPS REST API specification compliant implementation for handling Resource CRUD_

The Solid specification for reading and writing resources in Solid Server (or
"Solid Pod") extends the [Linked Data Platform specification][w3c-spec-ldp]. 

This project provides a single-class API for CRUD operations on resources and
containers, that adheres to the [Solid HTTPS REST API Spec][solid-spec-api-rest].

The used Request and Response objects are compliant with [the PHP Standards Recommendations for HTTP Message Interface (PSR-7)](https://www.php-fig.org/psr/psr-7/) to make integration into existing projects and frameworks easier.

[w3c-spec-ldp]: https://www.w3.org/TR/ldp/
[solid-spec-api-rest]: https://github.com/solid/solid-spec/blob/master/api-rest.md

## Table of Contents

<!-- toc -->

- [Background](#background)
- [Installation](#installation)
- [Usage](#usage)
  - [Creating the filesystem](#creating-the-filesystem)
  - [Creating the server](#creating-the-server)
  - [Handling a request](#handling-a-request)
  - [Changing a request](#changing-a-request)
  - [Full Example](#full-example)
- [Contributing](#contributing)
- [License](#license)

<!-- tocstop -->

## Background

This project is part of the PHP stack of projects by PDS Interop. It is used by
both the Solid-Nextcloud app and the standalone PHP Solid server.

As the functionality seemed useful for other projects, it was implemented as a
separate package.

## Installation

The advised install method is through composer:

```sh
composer require pdsinterop/solid-crud
```

PHP version 7.3 and higher is supported. The [`mbstring`](https://www.php.net/manual/en/book.mbstring.php)
extension needs to be enabled in order for this package to work.

## Usage

This package provides a `Pdsinterop\Solid\Resources\Server` class which, when provided with a PSR-7 compliant Request object, will return a PSR-7 compliant Response object.

To work, the server needs an object that implements `League\Flysystem\FilesystemInterface`
and an object that implements `Psr\Http\Message\ResponseInterface`

The concrete Filesystem object _must_ be provided with the Rdf adapter and the AsMime plugin provided by [`pdsinterop/flysystem-rdf`](https://github.com/pdsinterop/flysystem-rdf). Depending on your own use-cases, it can also be provided with the ReadRdf plugin (provided by the same package).

### Creating the filesystem

This is an example of how to create a filesystem object, using the [local adapter](https://flysystem.thephpleague.com/v1/docs/adapter/local/)

```php
$formats = new \Pdsinterop\Rdf\Formats();

$rdfAdapter = new \Pdsinterop\Rdf\Flysystem\Adapter\Rdf(
    new \League\Flysystem\Adapter\Local('/path/to/data'),
    new \EasyRdf_Graph(),
    $formats,
    'https://example.com/'
);

$filesystem = new \League\Flysystem\Filesystem($rdfAdapter);

$filesystem->addPlugin(new \Pdsinterop\Rdf\Flysystem\Plugin\AsMime($formats));

```

for more details on using the filesystem, please see the documentation at `pdsinterop/flysystem-rdf`.

### Creating the server

with the filesystem, the server can be created, together with a response object. For this example we are using the [Laminas Diactoros](https://github.com/laminas/laminas-diactoros/) `Response` object is used, but any PSR-7 compliant response object will do:

```php
$server = new \Pdsinterop\Solid\Resources\Server($filesystem, new \Laminas\Diactoros\Response());
```

### Handling a request

Once the server is created it can be provided a request to handle. The Request object in this example is created by a PSR-17 compliant ServerRequest Factory (also provided by the Laminas Diactoros package) but any PSR-7 compliant request object will work:

```php
$request = \Laminas\Diactoros\ServerRequestFactory::fromGlobals(
    $_SERVER, $_GET, $_POST, $_COOKIE, $_FILES
);

$response = $server->respondToRequest($request);
```

This will populate and return the Response object the server was provided with for further handling by your application or framework.

### Changing a request

If there is a difference between the request you receive and that which the server object is expected to handle, the request object should be altered before being passed to the server.

For instance to change the requested path:

```php
    $request = $request->withUri($request->getUri()->withPath($changedPath));
```

or the request method:

```php
    $request = $request->withMethod('PUT');
```

### Full Example

Putting all of this together would give us something like this:

```php
<?php

/*/ Create the filesystem /*/
$formats = new \Pdsinterop\Rdf\Formats();

$rdfAdapter = new \Pdsinterop\Rdf\Flysystem\Adapter\Rdf(
    new \League\Flysystem\Adapter\Local('/path/to/data'),
    new \EasyRdf_Graph(),
    $formats,
    'https://example.com/'
);

$filesystem = new \League\Flysystem\Filesystem($rdfAdapter);

$filesystem->addPlugin(new \Pdsinterop\Rdf\Flysystem\Plugin\AsMime($formats));

/*/ Create the server /*/
$server = new \Pdsinterop\Solid\Resources\Server($filesystem, new \Laminas\Diactoros\Response());

/*/ Create a PSR-7 Request object /*/
$request = \Laminas\Diactoros\ServerRequestFactory::fromGlobals(
    $_SERVER, $_GET, $_POST, $_COOKIE, $_FILES
);

/*/ Remove the `/data` prefix from the path /*/
$changedPath = substr($request->getUri()->getPath(), 5);
$request = $request->withUri($request->getUri()->withPath($changedPath));

/*/ Handle the request /*/
$response = $server->respondToRequest($request);
```

A fully working example has been provided at [`src/example.php`](src/example.php).

To try out this dummy server, run: 

```sh
php -S localhost:${PORT:-8080} -t ./src/ ./src/example.php
```

or have composer run it for you by calling: `composer run dev:example`

The server is expected to run on HTTPS, but it can be forced to accept

## Contributing

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
[contributors-page]: https://github.com/pdsinterop/php-solid-crud/contributors
[issues-link]: https://github.com/pdsinterop/php-solid-crud/issues
[releases-page]: https://github.com/pdsinterop/php-solid-crud/releases
[keep-a-changelog-link]: https://keepachangelog.com/
[keep-a-changelog-shield]: https://img.shields.io/badge/Keep%20a%20Changelog-f15d30.svg?logo=data%3Aimage%2Fsvg%2Bxml%3Bbase64%2CPHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIGZpbGw9IiNmZmYiIHZpZXdCb3g9IjAgMCAxODcgMTg1Ij48cGF0aCBkPSJNNjIgN2MtMTUgMy0yOCAxMC0zNyAyMmExMjIgMTIyIDAgMDAtMTggOTEgNzQgNzQgMCAwMDE2IDM4YzYgOSAxNCAxNSAyNCAxOGE4OSA4OSAwIDAwMjQgNCA0NSA0NSAwIDAwNiAwbDMtMSAxMy0xYTE1OCAxNTggMCAwMDU1LTE3IDYzIDYzIDAgMDAzNS01MiAzNCAzNCAwIDAwLTEtNWMtMy0xOC05LTMzLTE5LTQ3LTEyLTE3LTI0LTI4LTM4LTM3QTg1IDg1IDAgMDA2MiA3em0zMCA4YzIwIDQgMzggMTQgNTMgMzEgMTcgMTggMjYgMzcgMjkgNTh2MTJjLTMgMTctMTMgMzAtMjggMzhhMTU1IDE1NSAwIDAxLTUzIDE2bC0xMyAyaC0xYTUxIDUxIDAgMDEtMTItMWwtMTctMmMtMTMtNC0yMy0xMi0yOS0yNy01LTEyLTgtMjQtOC0zOWExMzMgMTMzIDAgMDE4LTUwYzUtMTMgMTEtMjYgMjYtMzMgMTQtNyAyOS05IDQ1LTV6TTQwIDQ1YTk0IDk0IDAgMDAtMTcgNTQgNzUgNzUgMCAwMDYgMzJjOCAxOSAyMiAzMSA0MiAzMiAyMSAyIDQxLTIgNjAtMTRhNjAgNjAgMCAwMDIxLTE5IDUzIDUzIDAgMDA5LTI5YzAtMTYtOC0zMy0yMy01MWE0NyA0NyAwIDAwLTUtNWMtMjMtMjAtNDUtMjYtNjctMTgtMTIgNC0yMCA5LTI2IDE4em0xMDggNzZhNTAgNTAgMCAwMS0yMSAyMmMtMTcgOS0zMiAxMy00OCAxMy0xMSAwLTIxLTMtMzAtOS01LTMtOS05LTEzLTE2YTgxIDgxIDAgMDEtNi0zMiA5NCA5NCAwIDAxOC0zNSA5MCA5MCAwIDAxNi0xMmwxLTJjNS05IDEzLTEzIDIzLTE2IDE2LTUgMzItMyA1MCA5IDEzIDggMjMgMjAgMzAgMzYgNyAxNSA3IDI5IDAgNDJ6bS00My03M2MtMTctOC0zMy02LTQ2IDUtMTAgOC0xNiAyMC0xOSAzN2E1NCA1NCAwIDAwNSAzNGM3IDE1IDIwIDIzIDM3IDIyIDIyLTEgMzgtOSA0OC0yNGE0MSA0MSAwIDAwOC0yNCA0MyA0MyAwIDAwLTEtMTJjLTYtMTgtMTYtMzEtMzItMzh6bS0yMyA5MWgtMWMtNyAwLTE0LTItMjEtN2EyNyAyNyAwIDAxLTEwLTEzIDU3IDU3IDAgMDEtNC0yMCA2MyA2MyAwIDAxNi0yNWM1LTEyIDEyLTE5IDI0LTIxIDktMyAxOC0yIDI3IDIgMTQgNiAyMyAxOCAyNyAzM3MtMiAzMS0xNiA0MGMtMTEgOC0yMSAxMS0zMiAxMXptMS0zNHYxNGgtOFY2OGg4djI4bDEwLTEwaDExbC0xNCAxNSAxNyAxOEg5NnoiLz48L3N2Zz4K
[license-link]: ./LICENSE
[license-shield]: https://img.shields.io/github/license/pdsinterop/php-solid-crud.svg
[maintained-shield]: https://img.shields.io/maintenance/yes/2022.svg
[pdsinterop-shield]: https://img.shields.io/badge/-PDS%20Interop-7C4DFF.svg?logo=data%3Aimage%2Fsvg%2Bxml%3Bbase64%2CPHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9Ii01IC01IDExMCAxMTAiIGZpbGw9IiNGRkYiIHN0cm9rZS13aWR0aD0iMCI+CiAgICA8cGF0aCBkPSJNLTEgNTJoMTdhMzcuNSAzNC41IDAgMDAyNS41IDMxLjE1di0xMy43NWEyMC43NSAyMSAwIDAxOC41LTQwLjI1IDIwLjc1IDIxIDAgMDE4LjUgNDAuMjV2MTMuNzVhMzcgMzQuNSAwIDAwMjUuNS0zMS4xNWgxN2EyMiAyMS4xNSAwIDAxLTEwMiAweiIvPgogICAgPHBhdGggZD0iTSAxMDEgNDhhMi43NyAyLjY3IDAgMDAtMTAyIDBoIDE3YTIuOTcgMi44IDAgMDE2OCAweiIvPgo8L3N2Zz4K
[pdsinterop-site]: https://pdsinterop.org/
[project-stage-badge: Development]: https://img.shields.io/badge/Project%20Stage-Development-yellowgreen.svg
[project-stage-page]: https://blog.pother.ca/project-stages/
[standard-readme-link]: https://github.com/RichardLitt/standard-readme
[standard-readme-shield]: https://img.shields.io/badge/-Standard%20Readme-brightgreen.svg
[version-link]: https://packagist.org/packages/pdsinterop/solid-crud
[version-shield]: https://img.shields.io/github/v/release/pdsinterop/php-solid-crud.svg?sort=semver

