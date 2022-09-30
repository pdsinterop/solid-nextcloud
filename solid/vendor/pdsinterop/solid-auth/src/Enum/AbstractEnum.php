<?php declare(strict_types=1);

namespace Pdsinterop\Solid\Auth\Enum;

use ReflectionClass;

abstract class AbstractEnum
{
    ////////////////////////////// CLASS PROPERTIES \\\\\\\\\\\\\\\\\\\\\\\\\\\\

    private static $instance = [];

    //////////////////////////// GETTERS AND SETTERS \\\\\\\\\\\\\\\\\\\\\\\\\\\

    private static function getInstance() : AbstractEnum
    {
        $classname = static::class;

        if (array_key_exists($classname, self::$instance) === false) {
            self::$instance[$classname] = new $classname;
        }

        return self::$instance[$classname];
    }

    final public function getValues() : array
    {
        static $values;

        if ($values === null) {
            $reflectionClass = new ReflectionClass(static::class);

            $values = array_values($reflectionClass->getConstants());

            natcasesort($values);
        }

        return $values;
    }

    //////////////////////////////// PUBLIC API \\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\

    final public static function has($value) : bool
    {
        return self::getInstance()->hasValue($value);
    }

    final public function hasValue($value) : bool
    {
        return in_array($value, $this->getValues(), true);
    }
}
