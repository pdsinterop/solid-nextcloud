arc\route
=========

This component provides very basic URL routing. You could use it like this:

```php
    $config = [
        '/blog/' => function($remainder) {
                if ( preg_match( '/(?<id>\d+)(?<format>\..+)?/', $remainder, $params ) ) {
                    switch ( $params['format'] ) {
                        case '.json':
                            return json_encode( blog::get($params['id']) );
                        break;
                        case '.html':
                            ...
                        break;
                    }
                    return 'Error: unknown format '.$params['format'];
                } else {
                    return 'main';
                }
            },
        '/' => function($remainder) {
                if ( $remainder ) {
                    return 'notfound';
                } else {
                    return 'home';
                }
            }
    ];
    $result = \arc\route::match('/blog/42.html', $config)['result'];
```

URL routing is a good way to implement a REST api, but in this form less usefull for a CMS system. Mostly because routes
are defined in code instead of user editable data. So use it with care.

`arc/route` doesn't implement parameter matching. The example above shows a very simple syntax using regular expressions
which is easy to learn and much more powerful than anything we could build. The most basic use works like this:
 
```php
    if ( preg_match( '|(?<name>[^/]+)/|', $path, $params ) ) {
        echo $params['name'];
    }
```

The syntax `(?<name>` means that the expression following it, untill the next matching `)` will store its matching value
in `$params['name']`. You can use any regular expression inside it. In this case the actual regular expression used is 
`[^/]+` which will match any character except `/`.

The `match()` method always returns an array with the following information:

```php
    [
        'path' => {matched path},
        'remainder' => {the non matched remainder},
        'result' => {the return value of the handler for the matched path}
    ]
```

You can create more complex routers by nesting the routing like this:

```php
    $config = [
        '/site/' => function($remainder) {
            if ( preg_match( '|(?<name>[^/]+)(?<rest>.+)?|', $remainder, $params ) ) {
                $subroute = [
                    '/blog/' => function($remainder) use ($params) {
                        return 'Blog of '.$params['name'];
                    },
                    '/' => function($remainder) use ($params) {
                        return 'Site of '.$params['name'];
                    }
                ];
                return \arc\route::match($params['rest'], $subroute)['result'];
            } else {
                return 'main';
            }
        },
       '/' => function($remainder) {
            if ( $remainder ) {
                return 'notfound';
            } else {
                return 'home';
            }
        }
    ];
    $result = \arc\route::match('/site/mike/blog/', $config)['result'];
```
