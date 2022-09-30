arc/url
=======

This component allows you to easily create and modify URL's. e.g.

```php
    $url = \arc\url::url( 'http://www.ariadne-cms.org/' );
    $url->path = '/docs/search/';
    $url->query['searchstring'] = 'test';
    echo $url; // => 'http://www.ariadne-cms.org/docs/search/?searchstring=test'
```

\arc\url::url
-------------
    ( \arc\url\Url ) \arc\url::url( (string) $url )

This method returns an object which has parsed the given url and contains properties for all its component parts:

- scheme: e.g. 'http'
- user: The user name included in the url.
- password: The password included in the url, will only be included if a username is also specified.
- host: The host name, e.g. 'www.ariadne-cms.org'.
- port: The port number.
- path: The path.
- query: The query string. 
- fragment: The html fragment after the '#'.

The query string is also automatically parsed to a PHPQuery object which implements ArrayObject. You can access any variable of the query string as an index in this array object or add new array entries. See the example above.
The query string is parsed with php's `parse_str` method, which doesn't understand all valid URL query parts. If you need to create a URL that isn't compatible with PHP, use `\arc\url::safeUrl()` instead. This will use an alternate parsing method that keeps the query string as it is.

The Url object and the PHPQuery object both have a \_\_toString method so you can use the objects in echo/print statements.

\arc\url::safeUrl
-----------------
    ( \arc\url\Url ) \arc\url::safeUrl( (string) $url )

This method is similar to `\arc\url::url()` but it returns a Url object with a 'safe' query parser. The difference is that the query part is not parsed with PHP's `parse_str()`, but with a custom parser that keeps all entries. This means that you can parse and create URL's which do not match PHP's query parsing rules, e.g.:

```php
    http://www.example.com/?some+thing
    http://www.example.com/?a=1&a=2
```

The first entry results in a query array with one entry: array('some thing')
The second entry results in array('a' => array('1','2'))

\arc\url\Url::getvar
--------------------------
    (mixed) \arc\url\ParsedUrl::getvar( (string) $name )

Returns the named argument from the query part of the URL or null.

```php
    $searchstring = $url->getvar('searchstring'); // => 'test' (see example url above)
    $searchstring = $url->query->searchstring; // => 'test'
```

\arc\url\Url::putvar
--------------------------
    (void) \arc\url\ParsedUrl::putvar( (string) $name, (mixed) $value )

Updates or inserts the named argument in the query part of the URL. All native PHP types are automaticall serialized into the correct query syntax.

```php
    $url->putvar('searchstring', 'a new value');
    $url->query->searchstring = 'a new value';
```

\arc\url\Url::import
--------------------------
    (void) \arc\url\ParsedUrl::import( (mixed) $values )

Imports the given values and updates or inserts each value into the query part of the URL. The values argument can be either an array or a URL encoded string.

```php
    $url->import( array( 'searchstring' => 'another value', 'argument2' => true ) );
```