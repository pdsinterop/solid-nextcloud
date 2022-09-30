arc\base common datatypes for ARC
=================================

[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/Ariadne-CMS/arc-base/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/Ariadne-CMS/arc-base/?branch=master)
[![Code Coverage](https://scrutinizer-ci.com/g/Ariadne-CMS/arc-base/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/Ariadne-CMS/arc-base/)
[![Latest Stable Version](https://poser.pugx.org/arc/base/v/stable.svg)](https://packagist.org/packages/arc/base)
[![Total Downloads](https://poser.pugx.org/arc/base/downloads.svg)](https://packagist.org/packages/arc/base)
[![Latest Unstable Version](https://poser.pugx.org/arc/base/v/unstable.svg)](https://packagist.org/packages/arc/base)
[![License](https://poser.pugx.org/arc/base/license.svg)](https://packagist.org/packages/arc/base)

arc\base is part of [ARC - a component library](http://www.github.com/Ariadne-CMS/arc-arc/). This package provides a 
number of basic datatypes and operations that are used in most other ARC packages. 

ARC is a spinoff from the Ariadne Web Application Platform and Content Management System
[http://www.ariadne-cms.org/](http://www.ariadne-cms.org/).

Installation
------------

You can install the full set of ARC components using composer:

    composer require arc/arc

Or you can start a new project with arc/arc like this:

    composer create-project arc/arc {$path}

Or just use this package:

    composer require arc/base
    
    
arc/base contains
------------------
- [path](docs/path.md): parse paths, including relative paths, get parents, etc.
- [tree](docs/tree.md): methods to parse filesystem-like trees and search and alter them
- [hash](docs/hash.md): methods to ease common tasks with nested hashes
- [template](docs/template.md): simple php based templates, with compile option
- [context](docs/context.md): nested scope or stackable DI container
- [lambda](docs/lambda.md): partial function application

Example code
------------

### \arc\path

    \arc\path::collapse( '../', '/current/directory/' );
    // => '/current/'

    \arc\path::collapse( 'some//../where', '/current/directory' );
    // => '/current/directory/where/'

    \arc\path::collapse( '../../../', '/current/directory/' );
    // => '/'

    \arc\path::diff( '/a/b/', '/a/c/' );
    // => '../c/'

    \arc\path::parent( '/parent/child/' );
    // => '/parent/'

    \arc\path::map( '/some path/with "quotes"/', function( $entry ) {
        return urlencode( $entry, ENT_QUOTES );
    });
    // => '/some+path/with+%22quotes%22/'

    \arc\path::reduce( '/a/b/', function( $result, $entry ) {
        return $result . $entry . '\\';
    }, '\\' );
    // => '\\a\\b\\';

### \arc\tree

    $tree = \arc\tree::expand([
        '/' => 'Root',
        '/foo/bar/' => 'Bar'
    ]);
    // => NamedNode tree with '/' => Root, '/foo/' => null, '/foo/bar/' => 'Bar'

    $hash = \arc\tree::collapse( $tree );
    // [ '/' => 'Root', '/foo/bar/' => 'Bar' ]

    $nodeValueWithAnR = \arc\tree::search( $tree, function($node) {
        if ( strpos( $node->nodeValue, 'R' )!==false ) {
            return $node->nodeValue;
        }
    });
    // => 'Root'

    $Btree = \arc\tree::filter( $tree, function($node) {
        return ( strpos( $node->nodeValue, 'B' ) !== false );
    });
    // => [ '/foo/bar/' => 'Bar' ]

### \arc\hash

    \arc\hash::get( '/foo/bar/', [ 'foo' => [ 'bar' => 'baz' ] ] );
    // => 'baz'

    \arc\hash::get( '/foo/bar/', [ 'foo' => [  ] ], 'zilch' );
    // => 'zilch'

    \arc\hash::parseName( 'input[one]' );
    // => '/input/one/'

    \arc\hash::compileName( '/input/one' );
    // => 'input[one]'

    \arc\tree::collapse(
        \arc\hash::tree(
            [ 'foo' => [ 'bar' => 'A bar', 'baz' => 'Not a bar' ] ]
        )
    );
    // => [ '/foo/bar/' => 'A bar', '/foo/baz/' => 'Not a bar' ]
