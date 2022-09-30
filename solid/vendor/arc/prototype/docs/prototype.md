# arc/prototype

This component allows you to use prototypical inheritance in PHP, e.g:

```php
<?php
    $page = \arc\prototype::create( [
        'title' => 'A page',
        'content' => '<p>Some content</p>',
        'view' => function($args) {
            return '<!doctype html><html><head>' . $this->head($args) 
                 . '</head><body>' . $this->body($args) . '</body></html>';
        },
        'head' => function($args) {
            return '<title>' . $this->title . '</title>';
        },
        'body' => function($args) {
            return '<h1>' . $this->title . '</h1>' . $this->content;
        }
    ] );


    $menuPage = \arc\prototype::extend($page, [
        'body' => function($args) {
            return $this->menu($args) . $this->prototype->body($args);
        },
        'menu' => function($args) {
            $result = '';
            if ( is_array($args['menu']) ) {
                foreach( $args['menu'] as $index => $title ) {
                    $result .= $this->menuItem($index, $title);
                }
            }
            if ( $result ) {
                return \arc\html::ul( ['class' => 'news'], $result );
            }
        },
        'menuItem' => function($index, $title) {
            return \arc\html::li( [], \arc\html::a( [ 'href' => $index ], $title ) );
        }
    ] );
```

Using prototypes as a dependency injection container:

```php
<?php
    $di = \arc\prototype::create([
         'dsn'      => 'mysql:dbname=testdb;host=127.0.0.1';
         'user'     => 'dbuser',
         'password' => 'dbpassword',
         'database' => \arc\prototype::singleton( function() {
             // this generates a single PDO object once and then returns it for each subsequent call
             return new PDO( $this->dsn, $this->user, $this->password );
         } ),
         'session'  => function() {
             // this returns a new mySession object for each call
             return new mySession();
         }
    ] );

    $diCookieSession = \arc\prototype::extend($di, [ 
         'session'  => function() {
             return new myCookieSession();
         }
    ] );
```

# Object creation 

## \arc\prototype::create
    (object) \arc\prototype::create( (array) $properties )

Returns a new \arc\prototype\Prototype object with the given properties. The properties array may contain closures, these will be available as methods on the new Prototype object.

```php
    $view = \arc\prototype::create( [
        'foo' => 'bar',
        'bar' => function () {
            return $this->foo;
        }
    ] );
```

## \arc\prototype::extend
    (object) \arc\prototype::extend( (object) $prototype, (array) $properties)

Returns a new \arc\prototype\Prototype object with the given properties overriding properties in the prototype. All properties on the prototype that aren't overridden are available on the new object.

```php
    $bar = \arc\prototype::extend( $foo, [
        'foo' => 'rab'
    ]);
```

## \arc\prototype::assign
    (object) \arc\prototype::assign( (object) $prototype, (object) ...$objects)

Returns a new \arc\prototype\Prototype object with all the properties of all the assigned objects and the prototype set to $prototype.

```php
    $foo = \arc\prototype::create([
        'foo' => 'Foo'
    ]);
    $bar = \arc\prototype::extend($foo, [
        'bar' => 'Bar'
    ]);
    $baz = \arc\prototype::extend($bar, [
        'bar' => 'Baz'
    ]);
    $zod = \arc\prototype::create([
        'zod' => 'Zod'
    ]);
    $zoom = \arc\prototype::assign($zod, $bar, $baz);
```

# getters and setters

When creating a prototype, you can define a property to use a getter and setter. Or just a getter without a setter, or the other way around.

```php
    $bar = \arc\prototype::create([
        'bar' => 'Bar'
    ]);
    $foo = \arc\prototype::create([
        'bar' => [
            'set' => function($value) use ($bar) {
                $bar->bar = $value.'Bar';
            },
            'get' => function() use ($bar) {
                return $bar->bar;
            }
        ]
    ]);
```


# Protecting objects against changes and immutability

## \arc\prototype::freeze
    (void) \arc\prototype::freeze( (object) $object )

Makes a \arc\prototype\Prototype object immutable. You can no longer change, add or delete properties, nor change its prototype.

```php
    \arc\prototype::freeze($foo);
```

## \arc\prototype::seal
    (void) \arc\prototype::seal( (object) $object )

When you seal an \arc\prototype\Prototype you can no longer add or delete properties, or change its prototype. You can still change the value of properties, but you can reconfigure them. This means that if a property is getter/setter, you can't change that to a simple value, and vice-versa.

```php
    \arc\prototype::seal($foo);
```

## \arc\prototype::preventExtensions
    (void) \arc\prototype::preventExtensions( (object) $object )

This method makes an object no longer extensible. You cannot add new properties. You can still change existing properties.

```php
    \arc\prototype::preventExtensions($foo);
```

# observing objects

## \arc\prototype::observe
    (void) \arc\prototype::observe( (object) $object, (callable) $f )

```php
    $foo = \arc\prototype::create([]);
    $log = [];
    $f = function($changes) use (&$log) {
        $log[] = $changes;
    };
    \arc\prototype::observe($foo, $f);
    $foo->bar = 'bar';
    $foo->bar = 'foo';
    print_r($log);
```

This will then print out:

```php
Array
(
    [0] => Array
        (
            [name] => bar
            [object] => (...)
            [type] => add
        )

    [1] => Array
        (
            [name] => bar
            [object] => (...)
            [type] => update
            [oldValue] => bar
        )
)
```
The type can be add, update or delete. The new value is not passed to the observer, since you get access to the entire object and you can read it there if you need it.

## \arc\prototype::unobserve
    (void) \arc\prototype::unobserve( (object) $object, (callable) $f)

Stops the given observer method from observing the given object. Changes to the object will no longer be passed on to the observer method.

# introspection

## \arc\prototype::hasProperty
    (bool) \arc\prototype\Prototype::hasProperty( (object) $object, (string) $propertyName )

Returns true if the requested property is available on the current Prototype object or any object in its prototype chain.

## \arc\prototype::hasOwnProperty
    (bool) \arc\prototype\Prototype::hasOwnProperty( (object) $object, (string) $propertyName )

Returns true if the requested property is available on the current Prototype object itself without checking its prototype chain.

## \arc\prototype::hasPrototype
    (bool) \arc\prototype\Prototype::hasPrototype( (object) $object, (string) $prototypeObject )

Returns true if the given object is part of the prototype chain of the current Prototype object.

## \arc\prototype::keys
## \arc\prototype::entries
## \arc\prototype::values
## \arc\prototype::ownKeys
## \arc\prototype::ownEntries
## \arc\prototype::ownValues
    (array) \arc\prototype::*( (object) $object )

Returns a list of either the keys or the values or both, of the entire prototype chain or just this object. The method names should be self explanatory.

## \arc\prototype::isFrozen
    (bool) \arc\prototype::isFrozen( (object) $object)

Returns true if the given prototype is not frozen by calling freeze().

## \arc\prototype::isSealed
    (bool) \arc\prototype::isSealed( (object) $object)

Returns true if the given prototype is not sealed by calling seal().

## \arc\prototype::isExtensible
    (bool) \arc\prototype::isExtensible( (object) $object)

Returns true if the given prototype is not made un-extensible by calling preventExtensions().

## \arc\prototype::getObservers
    (array) \arc\prototype::getObservers( (object) $object )

Returns a list of observer methods for this object.

## \arc\prototype::getPrototypes
    (array) \arc\prototype::getPrototypes( (object) $object )

Returns the chain of prototypes for this object as an array. The first entry is the direct prototype, the last entry is the root prototype.

## \arc\prototype::getInstances
    (array) \arc\prototype::getInstances( (object) $object )

Returns a list of objects that have this object as a direct prototype.

## \arc\prototype::getDescendants
    (array) \arc\prototype::getDescendants( (object) $object )

Returns a list of objects that have this object as a prototype anywhere in the prototype chain.

# static methods

When adding a method to a \arc\prototype\Prototype object, you may also use static methods. But you need to tell the prototype object that it is static for this to work. Do this by adding a colon as a prefix for the method:

```php
    $foo = \arc\prototype::create([
        'bar' => 'Bar',
        ':foo' => static function($self) {
            return $self->bar;
        }
    ]);
```

You can call these methods just like normal methods, you don't need to know they've been defined as static methods at all:

```php
    $result = $foo->foo();
```

You can also use a static function as a setter or getter, but you must then use ':set' and ':get' as the setter and getter names:

```php
    $foo = \arc\prototype::create([
        'bar' => [
            ':set' => static function($self, $value) use ($bar) {
                $bar->bar = $value.'Bar';
            },
            ':get' => static function($self) use ($bar) {
                return 'Foo'.$bar->bar;
            }
        ]
    ]);
```
