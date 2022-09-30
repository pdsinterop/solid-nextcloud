# \arc\context

This component implements a stackable dependency injection container.

## Basic usage

```php
    // initialization
    \arc\context::push([
        'myService' => function() {
            return new Service();
        },
        'myCredentials' => [
            'user' => 'Me',
            'password' => 'secret'
        ]
    ]);

    // use
    $newService = \arc\context::$context->myService();

    // cleanup
    \arc\context::pop();
```

So when your application initializes, you push a new set of information and closures onto the context stack. You use the
context stack in your application and when the application is done, you pop the stack to remove all information and closures
you added.

You can add information later without pushing a new context onto the stack, e.g.:

```php
    \arc\context::$context->anotherService = function($credentials) {
        return new anotherService($credentials);
    };
```

A method in the context is always run on demand. But you can also create a lazy loading function that only runs on the first
call and for all subsequent calls returns the result of the first run:

```php
    \arc\context::$context->reusableConnection = \arc\lambda::singleton( function($credentials) {
        return new myConnection($credentials);
    } );
```

A method in the context can access other methods and information by simply accessing $this. Each closure is automatically
bound to the $context object when it is assigned. So this will work:

```php
    \arc\context::$context->reusableConnection = \arc\lambda::singleton( function($credentials = null) {
        if ( !$credentials ) {
            $credentials = $this->myCredentials;
        }
        return new myConnection($credentials);
    } );
```

## Creating multiple DI containers

A 'frame' in the context stack is actually just a \arc\lambda\Prototype. This means that you can create a DI container
yourself by doing:

```php
    $myContainer = \arc\lambda::prototype([
        'myService' => function() {
           return new Service();
       },
       'myCredentials' => [
           'user' => 'Me',
           'password' => 'secret'
       ]
    ]);
```

Each push simply extends the current prototype object, so to implement that just do:

```php
    $stackedContainer = $myContainer->extend([
        'myService' => function() {
            return new alternateService();
        }
    ]);
```

To get at the previous DI container, like \arc\context::pop(), use its prototype property:

```php
    $originalContainer = $stackedContainer->prototype;
```

## Usage in ARC

Every ARC component comes with a set of static factory methods. These make the API easy to use. You don't have to know all the ins and outs of each components dependencies. The factory methods hide this from you. But to make this work, there must be a central store of current state. The predefined factory methods all use \arc\context for this.

No ARC component will ever call \arc\context::push() however. This is so you are always able to manage the stack yourself.

Currently the following data is stored by ARC components:

  - (string) arcPath - used to specify the current path in factory methods - if not passed as a parameter
  - (object) arcConfig - used to store configuration data that is dependant on a path
  - (object) arcGrants - used to store authorization data ( grants ) per path

You should not access or change these directly, instead use the corresponding methods like cd() or the specific components.

ARC components use \arc\context just for information storage, generally they don't add closures/methods. This is because they define these methods in their own scope as static factory methods. There is certainly no need to do it this way for your own projects, but the advantage is that the DI container is relatively simple and lightweight while the factory methods all are closely tied to their respective components.