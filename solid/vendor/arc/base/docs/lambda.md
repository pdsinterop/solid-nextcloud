# arc/lambda

This component contains a few experimental methods.

## \arc\lambda::singleton
    (function) \arc\lambda::singleton( (callable) $f )

Returns a function that will only be run once. After the first run it will then return the value that run returned, unless that value is null. This makes it possible to create lazy loading functions that only run when used. You can also create shared objects in a dependency injection container.

This method doesn't guarantee that the given function is never run more than once - unless you only ever call it indirectly through the resulting singleton function.

## \arc\lambda::curry
    (function) \arc\lambda::curry( (callable) $f, (array) $curriedArgs )

This method returns a copy of the given function $f from which the arguments supplied as $curriedArgs are removed. When called it will suplly the $curriedArgs in addition to the leftover arguments.

```php
    $myApi = \arc\prototype::prototype( [
       'htmlentities' => \arc\lambda::curry( 'htmlentities', [ 1 => ENT_HTML5|ENT_NOQUOTES, 3 => false ] )
    ] );
```
The above example will result in an object with a htmlentities method that has two arguments, the string to encode and an optional encoding argument. The curried arguments will be mixed in with the given arguments, based on their key in the $curriedArgs.

```php
    echo $myApi->htmlentities( 'Encode < this&tm; >', 'ISO-8859-1' );
```
This is the same as:

```php
    echo htmlentities( 'Encode < this&tm; >', ENT_HTML5|ENT_NOQUOTES, 'ISO-8859-1', false );
```

## \arc\lambda::pepper
    (function) \arc\lambda::pepper( (callable) $callable, (array) $namedArgs=null )

This is an experimental method to convert a normal function or method into a function that accepts an array with named arguments. It uses Reflection to gather information about the given function or method if you don't pass a $namedArgs array.

The format for $namedArgs is [ 'argumentName' => 'defaultValue' ]. The order in $namedArgs is the order in which arguments will be supplied to the original method or function.
    
Given a method that has a large number of arguments, optional or not, pepper allows you to generate a method that is more easily called:

```php
    function complexQuery( $query, $database, $user, $password ) { ... }

    $myApi = \arc\prototype::prototype( [
        'query' => \arc\lambda::pepper( 'complexQuery', [
            'query' => null,
            'database' => $this->database,
            'user'     => $this->dbuser,
            'password' => $this->dbpassword
        ] )
    ] );
```
And now you can call the complexQuery function like this:

```php
    $myApi->query([ 'query' => 'select * from aTable', 'database' => 'alternateDatabase' ]);
```