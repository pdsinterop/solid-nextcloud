# \arc\template

The template component is a very basic variable substitution template system. 

## \arc\template::substitute
	(string) \arc\template::substitute( (string) $template, (object|array) $arguments )

This method replaces {$key} entries in a string with the value of that key in an arguments list. If the key isn't in the arguments array, it will remain in the returned string as-is. The arguments list may be an object or an array and the values may be basic types or callable.
In the latter case, the key will be substituted with the return value of the callable. The callable is called with the key matched.

Simple variable substitution:

```php
	$template = 'Hello {$someone}';
    $args = [ 'someone' => 'World!' ];
    $parsed = \arc\template::substitute( $template, $args );
    echo $parsed; // => 'Hello World! from {$somewhere}'
```

Variable substitution using a function:

```php
    $template = 'Hello {$someone}';
    $args = [ 'someone' => function () { return 'World!'; } ];
    $parsed = \arc\template::substitute( $template, $args );
```

## \arc\template::substituteAll
	(string) \arc\template::substituteAll( (string) $template, (object|array) $arguments )

This method is identical to \arc\template::substitute but it removes any keys that are left over after all available variables are substituted.

```php
    $template = 'Hello {$someone} from {$somewhere}';
    $args = [ 'someone' => 'World!' ];
    $parsed = \arc\template::substituteAll( $template, $args );
    echo $parsed; // => 'Hello World! from '
```

## \arc\template::compile
	(callable) \arc\template::compile( (string) $template )
