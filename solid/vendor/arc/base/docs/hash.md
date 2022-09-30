# \arc\hash

This component contains utility methods to ease working with PHP hashes. 

## \arc\hash::get
    (mixed) \arc\hash::get( (string) $path, (array) $hash )

One of the problems with PHP hashes is that when you access an index in a hash, you must first check if it is set, otherwise PHP will generate a warning. You can disable these warnings, but in a default PHP installation they are enabled so any code you share or publish needs to do this right.

This method fixes that for you by always checking the index and only then returning its value. If an index is not set it will return null. 

In addition you can check for a subindex in a nested hash by encoding the nested index as a path ( see \arc\path ). e.g.:

```php
    $hash = [
        'foo' => [
            'bar' => 'This is foobar'
        ]
    ];
    $value = \arc\hash::get( '/foo/bar/', $hash );
```

## \arc\hash::exists
    (bool) \arc\hash::exists( (string) $path, (array) $hash )

This returns true if the given search path matches a key in the hash. The corresponding value may be null. Only if no matching key exists will this return false.

## \arc\hash::parseName
    (string) \arc\hash::parseName( (string) $name )

This method parses a postdata or querystring style hash index and returns its corresponding searchpath. e.g.

```php
    $path = \arc\hash::parseName( 'arguments[foo][bar]' );
    // => '/arguments/foo/bar/'
```

## \arc\hash::compileName
    (string) \arc\hash::compileName( (string) $name, (string) $root = '' )

This method does the reverse of \arc\hash::parseName. Given a searchpath it will return the querystring style name. e.g.

```php
    $name = \arc\hash::compileName( '/arguments/foo/bar/' );
    // => 'arguments[foo][bar]'
```

## \arc\hash::tree
    (object) \arc\hash::tree( (array) $hash )

This method converts a nested hash into a \arc\tree\NamedNode tree. You can then call all the tree utility functions on it or convert it to a collapsed tree array, which is not nested. e.g.

```php
    $hash = [ 'foo' => [ 'bar' => 'a bar' ], 'oof' => 'rab' ];
    $tree = \arc\tree::collapse( \arc\hash::tree( $hash ) );
    // => [ '/foo/bar/' => 'a bar', '/oof/' => 'rab' ];
```