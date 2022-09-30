<?php
/*
 * This file is part of the Ariadne Component Library.
 *
 * (c) Muze <info@muze.nl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace arc\prototype;

/**
 * Implements a class of objects with prototypical inheritance, getters/setters, and observable changes
 * very similar to EcmaScript objects
 * @property \arc\prototype\Prototype $prototype The prototype for this object
 * @property array $properties
 */
final class Prototype implements \JsonSerializable
{
    /**
     * @var array cache for prototype properties
     */
    private static $properties = [];

    /**
     * @var array store for all properties of this instance. Must be private to always trigger __set and observers
     */
    private $_ownProperties = [];

    /**
     * @var array contains a list of local methods that have a static scope, such methods must be prefixed with a ':' when defined.
     */
    private $_staticMethods = [];

    /**
    * @var Prototype prototype Readonly reference to a prototype object. Can only be set in the constructor.
    */
    private $prototype = null;

    /**
     * @param array $properties
     */
    public function __construct($properties = [])
    {
        foreach ($properties as $property => $value) {
            if ( !is_numeric( $property ) && $property!='properties' ) {
                 if ( $property[0] == ':' ) {
                    $property = substr($property, 1);
                    $this->_staticMethods[$property] = true;
                    $this->_ownProperties[$property] = $value;
                } else if ($property == 'prototype') {
                    $this->prototype = $value;
                } else {
                    $this->_ownProperties[$property] = $this->_bind( $value );
                }
            }
        }
    }

    /**
     * @param $name
     * @param $args
     * @return mixed
     * @throws \BadMethodCallException
     */
    public function __call($name, $args)
    {
        if (array_key_exists( $name, $this->_ownProperties ) && is_callable( $this->_ownProperties[$name] )) {
            if ( array_key_exists($name, $this->_staticMethods) ) {
                array_unshift($args, $this);
            }
            return call_user_func_array( $this->_ownProperties[$name], $args );
        } elseif (is_object( $this->prototype)) {
            $method = $this->_getPrototypeProperty( $name );
            if (is_callable( $method )) {
                if ( array_key_exists($name, $this->_staticMethods) ) {
                    array_unshift($args, $this);
                }
                return call_user_func_array( $method, $args );
            }
        }
        throw new \BadMethodCallException( $name.' is not a method on this Object');
    }

    /**
     * @param $name
     * @return array|null|Prototype
     */
    public function __get($name)
    {
        switch ($name) {
            case 'prototype':
                return $this->prototype;
            break;
            case 'properties':
                return $this->_getPublicProperties();
            break;
            default:
                if ( array_key_exists($name, $this->_ownProperties) ) {
                    $property = $this->_ownProperties[$name];
                    if ( is_array($property) ) {
                        if ( isset($property['get']) && is_callable($property['get']) ) {
                            $getter = \Closure::bind( $property['get'], $this, $this );
                            return $getter();
                        } else if ( isset($property[':get']) && is_callable($property[':get']) ) {
                            return $property[':get']($this);
                        } else if ( (isset($property['set']) && is_callable($property['set']) )
                            || ( isset($property[':set']) && is_callable($property[':set']) ) ) {
                            return null;
                        }
                    }
                    return $property;
                }
                return $this->_getPrototypeProperty( $name );
            break;
        }
    }

    private function _isGetterOrSetter($property) {
        return (
            isset($property)
            && is_array($property)
            && (
                 ( isset($property['get']) && is_callable($property['get']) )
                || ( isset($property[':get']) && is_callable($property[':get']) )
                || ( isset($property['set']) && is_callable($property['set']) )
                || ( isset($property[':set']) && is_callable($property[':set']) )
            )
        );
    }

    /**
     * @param $name
     * @param $value
     * @throws \LogicException
     */
    public function __set($name, $value)
    {
        if (in_array( $name, [ 'prototype', 'properties' ] )) {
            throw new \LogicException('Property "'.$name.'" is read only.');
            return;
        }
        if ( !isset($this->_ownProperties[$name]) && !\arc\prototype::isExtensible($this) ) {
            throw new \LogicException('Object is not extensible.');
            return;
        }
        $valueIsSetterOrGetter = $this->_isGetterOrSetter($value);
        $propertyIsSetterOrGetter = (isset($this->_ownProperties[$name])
            ? $this->_isGetterOrSetter($this->_ownProperties[$name])
            : false
        );
        if ( \arc\prototype::isSealed($this) && $valueIsSetterOrGetter!=$propertyIsSetterOrGetter ) {
            throw new \LogicException('Object is sealed.');
            return;
        }
        $changes = [];
        $changes['name'] = $name;
        $changes['object'] = $this;
        if ( array_key_exists($name, $this->_ownProperties) ) {
            $changes['type'] = 'update';
            $changes['oldValue'] = $this->_ownProperties[$name];
        } else {
            $changes['type'] = 'add';
        }

        $clearcache = false;
        // get current value for $name, to check if it has a getter and/or a setter
        if ( array_key_exists($name, $this->_ownProperties) ) {
            $current = $this->_ownProperties[$name];
        } else {
            $current = $this->_getPrototypeProperty($name);
        }
        if ( $valueIsSetterOrGetter ) {
            // reconfigure current property
            $clearcache = true;
            $this->_ownProperties[$name] = $value;
            unset($this->_staticMethods[$name]);
        } else if (
            isset($current)
            && (is_array($current) || $current instanceof \ArrayAccess) 
            && isset($current['set']) 
            && is_callable($current['set'])
        ) {
            // bindable setter found, use it, no need to set anything in _ownProperties
            $setter = \Closure::bind($current['set'], $this, $this);
            $setter($value);
        } else if (
            isset($current) 
            && (is_array($current) || $current instanceof \ArrayAccess) 
            && isset($current[':set']) 
            && is_callable($current[':set'])
        ) {
            // nonbindable setter found
            $current[':set']($this, $value);
        } else if (
            isset($current) 
            && (is_array($current) || $current instanceof \ArrayAccess) 
            && ( 
                (isset($current['get']) && is_callable($current['get']) )
                || (isset($current[':get']) && is_callable($current[':get']) )
            )
        ) {
            // there is only a getter, no setter, so ignore setting this property, its readonly.
            throw new \LogicException('Property "'.$name.'" is readonly.');
            return null;
        } else if (!array_key_exists($name, $this->_staticMethods)) {
            // bindable value, update _ownProperties, so clearcache as well
            $clearcache = true;
            $this->_ownProperties[$name] = $this->_bind( $value );
        } else {
            // non bindable value, update _ownProperties, so clearcache as well
            $clearcache = true;
            $this->_ownProperties[$name] = $value;
        }
        if ( $clearcache ) {
            // purge prototype cache for this property - this will clear too much but cache will be filled again
            // clearing exactly the right entries from the cache will generally cost more performance than this
            unset( self::$properties[ $name ] );
            $observers = \arc\prototype::getObservers($this);
            if ( isset($observers[$changes['type']]) ) {
                foreach($observers[$changes['type']] as $observer) {
                    $observer($changes);
                }
            }
        }
    }

    /**
     * Returns a list of publically accessible properties of this object and its prototypes.
     * @return array
     */
    private function _getPublicProperties()
    {
        // get public properties only, so use closure to escape local scope.
        // the anonymous function / closure is needed to make sure that get_object_vars
        // only returns public properties.
        return ( is_object( $this->prototype )
            ? array_merge( $this->prototype->properties, $this->_getLocalProperties() )
            : $this->_getLocalProperties() );
    }

    /**
     * Returns a list of publically accessible properties of this object only, disregarding its prototypes.
     * @return array
     */
    private function _getLocalProperties()
    {
        return [ 'prototype' => $this->prototype ] + $this->_ownProperties;
    }

    /**
     * Get a property from the prototype chain and caches it.
     * @param $name
     * @return null
     */
    private function _getPrototypeProperty($name)
    {
        if (is_object( $this->prototype )) {
            // cache prototype access per property - allows fast but partial cache purging
            if (!array_key_exists( $name, self::$properties )) {
                self::$properties[ $name ] = new \SplObjectStorage();
            }
            if (!self::$properties[$name]->contains( $this->prototype )) {
                $property = $this->prototype->{$name};
                if ( $property instanceof \Closure ) {
                    if ( !array_key_exists($name, $this->prototype->_staticMethods)) {
                        $property = $this->_bind( $property );
                    } else {
                        $this->_staticMethods[$name] = true;
                    }
                }
                self::$properties[$name][ $this->prototype ] = $property;
            }
            return self::$properties[$name][ $this->prototype ];
        } else {
            return null;
        }
    }


    /**
     * @param $name
     * @return bool
     */
    public function __isset($name)
    {
        if ( array_key_exists($name, $this->_ownProperties) ) {
            return isset($this->_ownProperties[$name]);
        } else {
            $val = $this->_getPrototypeProperty( $name );
            return isset( $val );
        }
    }

    /**
     * @param $name
     * @throws \LogicException
     */
    public function __unset($name) {
        if (!in_array( $name, [ 'prototype', 'properties' ] )) {
            if ( !\arc\prototype::isSealed($this) ) {
                $oldValue = $this->_ownProperties[$name];
                if (array_key_exists($name, $this->_staticMethods)) {
                    unset($this->_staticMethods[$name]);
                }
                unset($this->_ownProperties[$name]);
                // purge prototype cache for this property - this will clear too much but cache will be filled again
                // clearing exactly the right entries from the cache will generally cost more performance than this
                unset( self::$properties[ $name ] );
                $observers = \arc\prototype::getObservers($this);
                $changes = [
                    'type' => 'delete',
                    'name' => $name,
                    'object' => $this,
                    'oldValue' => $oldValue
                ];
                foreach ($observers['delete'] as $observer) {
                    $observer($changes);
                }
            } else {
                throw new \LogicException('Object is sealed.');
            }
        } else {
            throw new \LogicException('Property "'.$name.'" is protected.');
        }
    }

    /**
     *
     */
    public function __destruct()
    {
    	\arc\prototype::_destroy($this);
        return $this->_tryToCall( '__destruct' );
    }

    /**
     * @return mixed
     */
    public function __toString()
    {
        return (string) $this->_tryToCall( '__toString' );
    }

    /**
     * @return mixed
     * @throws \BadMethodCallException
     */
    public function __invoke()
    {
        if (is_callable( $this->__invoke )) {
            return call_user_func_array( $this->__invoke, func_get_args() );
        } else {
            throw new \BadMethodCallException( 'No __invoke method found in this Object' );
        }
    }

    /**
     *
     */
    public function __clone()
    {
        // make sure all methods are bound to $this - the new clone.
        foreach ($this->_ownProperties as $name => $property) {
            if ( $property instanceof \Closure && !$this->_staticMethods[$name] ) {
                $this->{$name} = $this->_bind( $property );
            }
        }
        $this->_tryToCall( '__clone' );
    }

    public function jsonSerialize() {
        $result = $this->_tryToCall( '__toJSON' );
        if (!$result) {
            return \arc\prototype::entries($this);
        } else {
            return $result;
        }
    }

    /**
     * Binds the property to this object
     * @param $property
     * @return mixed
     */
    private function _bind($property)
    {
        if ($property instanceof \Closure ) {
            // make sure any internal $this references point to this object and not the prototype or undefined
            return \Closure::bind( $property, $this );
        }

        return $property;
    }

    /**
     * Only call $f if it is a callable.
     * @param $f
     * @param array $args
     * @return mixed
     */
    private function _tryToCall($name, $args = [])
    {
        if ( isset($this->{$name}) && is_callable( $this->{$name} )) {
            if ( array_key_exists($name, $this->_staticMethods) ) {
                array_unshift($args, $this);
            }
            return call_user_func_array( $this->{$name}, $args );
        }
    }
}

/**
 * Class dummy
 * This class is needed because in PHP7 you can no longer bind to \stdClass
 * And anonymous classes are syntax errors in PHP5.6, so there.
 * @package arc\lambda
 */
class dummy {
}
