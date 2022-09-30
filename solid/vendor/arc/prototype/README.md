ARC: Ariadne Component Library 
==============================

[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/Ariadne-CMS/arc-prototype/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/Ariadne-CMS/arc-prototype/?branch=master)
[![Code Coverage](https://scrutinizer-ci.com/g/Ariadne-CMS/arc-prototype/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/Ariadne-CMS/arc-prototype/)
[![Latest Stable Version](https://poser.pugx.org/arc/prototype/v/stable.svg)](https://packagist.org/packages/arc/prototype)
[![Total Downloads](https://poser.pugx.org/arc/prototype/downloads.svg)](https://packagist.org/packages/arc/prototype)
[![Latest Unstable Version](https://poser.pugx.org/arc/prototype/v/unstable.svg)](https://packagist.org/packages/arc/prototype)
[![License](https://poser.pugx.org/arc/prototype/license.svg)](https://packagist.org/packages/arc/prototype)

# arc/prototype

This component adds prototypes to PHP, with all the javascript features like Object.extend, Object.assign, Object.freeze and Object.observe. It also has support for setters and getters, defined per property.

## Create a prototype object

```php
    $object = \arc\prototype::create();
```

## Adding properties

```php
    $object->foo = 'Foo';
```

## Adding methods

```php
    $object->bar = function() {
        return $this->foo.'bar';
    }
```

## Extending objects

```php
    $childObject = \arc\prototype::extend($object);
    $childObject->foo = 'Vue';
    echo $childObject->bar();
```

## Quick create

```php
    $object = \arc\prototype::create([
        'foo' => 'Foo',
        'bar' => function() {
            return $this->foo.'bar';
        }
    ]);
```

## Quick extend

```php
    $childObject = \arc\prototype::extend($object, [
        'foo' => 'Vue'
    ]);
```

## Setters and Getters

```php
    $object->guarded = [
        'set' => function($value) {
            if ( $value !== 'Foo' ) {
                $this->unguarded = $value;
            }
        },
        'get' => function() {
            return 'Foo'.$this->unguarded;
        }
    ];
```
If you only have a 'set' function, getting the value will always return 'null'. If you only have a 'get'
function, setting the value will do nothing. 


## Finalizing objects

```php
    \arc\prototype::preventExtension($object);
    $childObject = \arc\prototype::extend($object);
```
This will throw a \BadMethodCallException.

```
    $isExtensible = \arc\prototype::isExtensible($object); // returns false
```


## Sealing objects

```php
    $object->foo = 'Foo';
    \arc\prototype::seal($object);
    $object->foo = 'Bar';
```
This will throw a \LogicException

    $isExtensible = \arc\prototype::isExtensible($object); // returns false
    $isSealed = \arc\prototype::isSealed($object); // returns true
```


## Freezing objects

```php
    $object->foo = 'Foo';
    \arc\prototype::freeze($object);
    $object->foo = 'Bar';
```
This will throw a \LogicException

```
    $isExtensible = \arc\prototype::isExtensible($object); // returns false
    $isSealed = \arc\prototype::isSealed($object); // returns true
    $isFrozen = \arc\prototype::isFrozen($object); // returns true
```

## Observing changes

```php
    $log = [];
    \arc\prototype::observe($object, function($changes) use (&$log) {
        $log[] = $changes;
    });
```

Or limit the observer to specific types of changes:

```php
    $log = [];
    \arc\prototype::observe($object, function($changes) use (&$log) {
        $log[] = $changes;
    }, ['add','delete']);
```

If not set, the full list of change types will be observed: 'add','update','delete','reconfigure'.


## Setters, Getters and Superprivates

```php
    function makeAFoo() {
        $superPrivate = 'Shhh...';
        $object = \arc\prototype::create();
        $object->foo = [
            'get' => function() use (&$superPrivate) {
                return $superPrivate;
            }
        ];
        $object->doFoo = function($value) use (&$superPrivate) {
            $superPrivate = 'Shhh... '.$value;
        };
        return $object;
    }            
```

By using a variable that is not a property of the prototype object, but is in the scope of a number of the objects methods, you 
can create something like a private property. But it is even more private than a normal private property, even other methods in this
object cannot access this variable. This is called a 'SuperPrivate' in javascript.

## Using arc\prototype as a Dependency Injection Container

```php
<?php
    $di = \arc\prototype::create([
         'dsn'      => 'mysql:dbname=testdb;host=127.0.0.1';
         'user'     => 'dbuser',
         'password' => 'dbpassword',
         'database' => \arc\prototype::memoize( function() {
             // this generates a single PDO object once and then returns it for each subsequent call
             return new PDO( $this->dsn, $this->user, $this->password );
         } ),
         'session'  => function() {
             // this returns a new mySession object for each call
             return new mySession();
         }
    ] );

    $diCookieSession = \arc\prototype::extend( $di, [ 
         'session'  => function() {
             return new myCookieSession();
         }
    ] );
```

Note: PHP has a limitation in that you can never bind a static function to an object. This will result in an uncatchable fatal error. To work around this, you must tell the prototype that a Closure is static, by prefixing the name with a ":". In that case the first argument to that method will always be the current object:

```php
<?php
    class foo {
        public static function myFactory() {
            return \arc\prototype::create([
                'foo'  => 'Foo',
                ':bar' => function($self) {
                    return $self->foo;
                },
                'baz' => [
                    ':get' => function($self) {
                        return 'Baz';
                    }
                ]
            ]);
        }
    }

    $f = foo::myFactory();
    echo $f->bar(); // outputs: "Foo";
```
Static closure are all closures defined within a static function, or explicitly defined as static. Closures defined outside of a class scope can be bound and don't need this workaround.


## methods

### \arc\prototype::create
    (object) \arc\prototype::create( (array) $properties )

Returns a new \arc\prototype\Prototype object with the given properties. The properties array may contain closures, these will be available as methods on the new Prototype object.


### \arc\prototype::extend
    (object) \arc\prototype::extend( (object) $prototype, (array) $properties )

This returns a new Prototype object with the given properties, just like \arc\prototype::create(). But in addition the new object has a prototype property linking it to the original object from which it was extended.
Any methods or properties on the original object will also be accessible in the new object through its prototype chain.

You can check an objects prototype by getting the prototype property of a \arc\prototype\Prototype object. You cannot change this property - it is readonly. You can only set the prototype property by using the extend method.


### \arc\prototype::assign
    (object) \arc\prototype::extend( (object) $prototype, (object) ...$objects )

This returns a new Prototype object with the given prototype set. In addition all properties on the extra objects passed to this method, will be copied to the new Prototype object. For any property that is set on multiple objects, the value of the property in the later object overwrites values from other objects.


### \arc\prototype::freeze
    (void) \arc\prototype::freeze( (object) $prototype )

This makes changes to the given Prototype object impossible. The object 
becomes immutable. Any attempt to change the 
object will silently fail. The object is also sealed and no longer
extensible. The only way to unfreeze it is to clone the object. The clone
will be unfrozen, unsealed and open to extension.

### \arc\prototype::isFrozen
    (bool) \arc\prototype::isFrozen( (object) $prototype )

Returns true if this object is frozen and thus immutable.

### \arc\prototype::seal
    (void) \arc\prototype::seal( (object) $prototype )

This makes the object incapable of adding or removing properties, or
reconfiguring them. The object is no longer open to extensions as well.
The only way to unseal it is to clone it. The clone will be unsealed and
open to extension.

### \arc\prototype::isSealed
    (bool) \arc\prototype::isSealed( (object) $prototype )

Returns true if this object is sealed and properties can no longer be
reconfigured, added or deleted. 

### \arc\prototype::preventExtensions
    (void) \arc\prototype::preventExtensions( (object) $prototype )

This makes the object incapable of adding properties or extending it.

### \arc\prototype::isExtensible
    (bool) \arc\prototype::isExtensible( (object) $prototype )

Returns true if this object is open to extensions.

### \arc\prototype::observe
    (void) \arc\prototype::observe( (object) $prototype, (Closure) $f )

This calls the Closure $f each time a property of $prototype is changed. The Closure is called with the prototype object, the name of the property and the new value.
If the closure returns false exactly (no other 'falsy' values will work), the change will be cancelled. 

```php
<?php
    \arc\prototype::observe($object, function($object, $name, $value) {
        if ( $name === 'youcanttouchthis' ) {
            return false;
        }
    });

```

### \arc\prototype::unobserve
    (void) \arc\prototype::unobserve( (object) $prototype, (Closure) $f )

This removes a specific observer function from a Prototype object. You must pass the exact same closure for this to work.

### \arc\prototype::getObservers

### \arc\prototype::hasProperty
    (bool) \arc\prototype::hasProperty( (string) $propertyName )

Returns true if the requested property is available on the current Prototype object itself or any of its prototypes.

### \arc\prototype::keys
    (array) \arc\prototype::keys( (object) $prototype )

### \arc\prototype::values
    (array) \arc\prototype::values( (object) $prototype )

### \arc\prototype::entries
    (array) \arc\prototype::entries( (object) $prototype )

### \arc\prototype::ownKeys
    (array) \arc\prototype::ownKeys( (object) $prototype )

### \arc\prototype::ownValues
    (array) \arc\prototype::ownValues( (object) $prototype )

### \arc\prototype::ownEntries
    (array) \arc\prototype::ownEntries( (object) $prototype )

### \arc\prototype::hasOwnProperty
    (bool) \arc\prototype::hasOwnProperty( (string) $propertyName )

Returns true if the requested property is available on the current Prototype object itself without checking its prototype chain.


### \arc\prototype::hasPrototype
    (bool) \arc\prototype::hasPrototype( (string) $prototypeObject )

Returns true if the given object is part of the prototype chain of the current Prototype object.

### \arc\prototype::getDescendants
    (array) \arc\prototype::getDescendants( (object) $prototype )

### \arc\prototype::getInstances
    (array) \arc\prototype::getInstances( (object) $prototype )

### \arc\prototype::getPrototypes
    (array) \arc\prototype::getPrototypes( (object) $prototype )

### \arc\prototype::memoize
    (Closure) \arc\prototype::memoize( (callable) $f )

Returns a function that will only be run once. After the first run it will then return the value that run returned, unless that value is null. This makes it possible to create lazy loading functions that only run when used. You can also create shared objects in a dependency injection container.

This method doesn't guarantee that the given function is never run more than once - unless you only ever call it indirectly through the resulting closure.
