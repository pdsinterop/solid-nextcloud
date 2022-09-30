# arc\path

This component provides a few utility methods that ease working with filesystem-like paths. A path is defined as a string of names seperated by the '/' character. The '/' cannot be escaped in the string.

## \arc\path::clean
    (string) \arc\path::clean( $path, $filter = FILTER_SANITIZE_ENCODED, $flags = null )

```php
    $cleanPath = \arc\path::clean( $inputPath );
```
This method will filter each filename part of the input path with the given filter. You can specify any filter that `filter_var` accepts and the same for the flags.

```php
    \arc\path::clean( '/a space/with"quotes/' ); // => '/a%20space/with%34quotes/'
    \arc\path::clean( '/a space/', FILTER_SANITIZE_URL ); // '/aspace/with"quotes/'
```
Or you can specify a callback method in place of $filter. This will do exactly the same as \arc\path::map() but may be a better name for what you are doing - cleaning the path names of illegal characters.

## \arc\path::collapse
    (string) \arc\path::collapse( $path, $cwd = '/' )

```php
    $absolutePath = \arc\path::collapse( $inputPath, '/current/directory/' );
```
This method parses a path string and given a current working directory will generate an absolute path. It will change '..' into the correct path based on the current working directory. It will skip '.' and empty filenames.
It will always return an absolute path with a starting and ending '/'. If the input path has more '..' parts than the current working directory has levels, it will ignore the extra '..' parts. If the input path starts with a '/', the current working directory is ignored.

```php
    \arc\path::collapse( '../', '/current/directory/' ); // => '/current/'
    \arc\path::collapse( 'some//../where', '/current/directory' ); // => '/current/directory/where/'
    \arc\path::collapse( '../../../', '/current/directory/' ); // => '/'
    \arc\path::collapse( '/some/where', '/current/directory/' ); // => '/some/where/'
```
## \arc\path::diff
   (string) \arc\path::diff( (string) $sourcePath, (string) $targetPath )

```php
    \arc\path::diff( '/a/b/', '/a/c/' ); // => '../c/'
```
Returns the difference between sourcePath and targetPath as a relative path in such a way that if you append the relative path to the source path and collapse that, the result is the targetPath.

## \arc\path::head
    (string) \arc\path::head( (string) $path )

```php
    $rootName = \arc\path::head( '/root/of/a/path/' ); // => 'root'
    $rootName = \arc\path::head( '../b/c/' ); // => '..'
```
Returns the root name of the given path.

## \arc\path::isAbsolute
    (bool) \arc\path::isAbsolute( (string) $path )

Returns true if the given path starts with a '/'.

## \arc\path::isChild
    (bool) \arc\path::isChild( $path, $parent )

Returns true if the $path is a child or descendant of $parent.

## \arc\path::map
    (string) \arc\path::map( (string) $path, (Callable) $callback )

```php
    $htmlentitiesPath = \arc\path::map( $inputPath, function( $entry ) {
        return htmlentities( $entry, ENT_QUOTES );
    });
```

This method will call a callback method for each filename in a given path. The result of the callback will replace the original filename. The resulting path is returned.
The example above will use the normal htmlentitied() method instead of the filter_var method used by clean().

## \arc\path::parent
    (string|null) \arc\path::parent( $path, $root = '/' )

```php
    $parent = \arc\path::parent( $inputPath, '/root/' );
```

This method will return the parent path string of the given input path, provided the input path is a child of the given root path. It is similar to the dirname() method except that if the input path has no valid parent, it will return NULL instead of the root. In addition it will always return a path with a closing '/'.

```php
    \arc\path::parent( '/some/where/' ); // => '/some/'
    \arc\path::parent( '/' ); // => NULL
    \arc\path::parent( '/root/', '/root/' ); // => NULL
```
## \arc\path::parents
    (array) \arc\path::parents( $path, $root = '/' )

```php
    $parents = \arc\path::parents( $inputPath, '/root/' );
```

This method will return an array of valid parent paths. It will start at the root and end with the input path. It will not normalize the input path for you. It will always at least return the root path.

```php
    \arc\path::parents( '/some/where/' ); // => array( '/', '/some/', '/some/where/' )
    \arc\path::parents( '/some/where/', '/some/' ); // => array( '/some/', '/some/where/' )
    \arc\path::parents( '/some/where/', '/foo/' ); // => array( '/foo/' )
```

## \arc\path::reduce
    (string) \arc\path::reduce( (string) $path, (Callable) $callback, (mixed) $initial )

```php
    $reducedPath = \arc\path::reduce( $inputPath, function( $result, $entry ) {
        return $result . $entry . '\\';
    }, '\\' ); // => '/a/b/' results in '\\a\\b\\';
```

This method will call a callback method for each filename in a given path. It will also pass on a result variable which contains the result of the previous call to the callback method. You can also optionally pass an initial value for the result variable.

## \arc\path::tail
    (string) \arc\path::tail( (string) $path )

```php
    $remainder = \arc\path::tail( '/root/of/a/path/' ); // => '/of/a/path/'
    $remainder = \arc\path::tail( '../b/c/' ); // => '/b/c/'
```

Returns the remainder of the path, after the root has been chopped of.

## \arc\path::walk
    (mixed) \arc\path::walk( (string) $path, (Callable) $callback, (bool) $startAtRoot = true, (string) $root = '/' )

```php
    $result = \arc\path::walk( '/foo/bar/', function( $parent ) {
        if ( $parent == '/foo/' ) {
            return true;
        }
    }); // => true
```

This method will call a callback method for each parent in a path. It will continue untill the callback method returns something and will then return that result. By default it will start with the root parent and continue up to the given path. You can reverse this order by setting `$startAtRoot` to `false`. You can also specify an alternate root.
