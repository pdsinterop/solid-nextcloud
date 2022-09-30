<?php
/*
 * This file is part of the Ariadne Component Library.
 *
 * (c) Muze <info@muze.nl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace arc;

function singleton($f) {
    return function () use ($f) {
        static $result;
        if (null === $result) {
            if ( $f instanceof \Closure && isset($this) ) {
                $f = \Closure::bind($f, $this);
            }
            $result = $f();
        }
        return $result;
    };
}

function partial(callable $callable, $partialArgs, $defaultArgs=[] ) {
    $partialMerge = function($partialArgs, $addedArgs, $defaultArgs = [])
    {
        end( $partialArgs );
        $l = key( $partialArgs );
        for ($i = 0; $i <= $l; $i++) {
            if (!array_key_exists($i, $partialArgs) && count($addedArgs)) {
                $partialArgs[ $i ] = array_shift( $addedArgs );
            }
        }
        if (count($addedArgs)) { // there are $addedArgs left, so there should be no 'holes' in $partialArgs
            $partialArgs =array_merge( $partialArgs, $addedArgs );
        }
        // fill any 'holes' in $partialArgs with entries from $defaultArgs
        $result =  array_replace( $defaultArgs, $partialArgs );
        ksort($result);

        return $result;
    };

    return function() use ($callable, $partialArgs, $defaultArgs, $partialMerge) {
        if ( $callable instanceof \Closure && isset($this) ) {
            $callable = \Closure::bind($callable, $this);
        }
        return call_user_func_array( $callable, $partialMerge( $partialArgs, func_get_args(), $defaultArgs ) );
    };
}

/**
 * Class lambda
 * Experimental functionality, may be removed later, use at own risk.
 * @package arc
 */
class lambda
{
    /**
     * Returns a function with the given arguments already entered or partially applied.
     * @param callable $callable The function to curry
     * @param array $partialArgs unlimited Optional arguments to curry the function with
     * @param array $defaultArgs optional default values
     * @return callable
     */
    public static function partial(callable $callable, $partialArgs, $defaultArgs = [])
    {
        return partial($callable, $partialArgs, $defaultArgs);
    }


    /**
     * Returns a function with named arguments. The peppered function accepts one argument - a named array of values
     * @param callable $callable The function or method to pepper
     * @param array $namedArgs Optional. The named arguments to pepper the function with, the order must be the order
     *        in which the unpeppered function expects them. If not set, pepper will use Reflection to get them.
     *        Format is [ 'argumentName' => 'defaultValue' ]
     * @return callable
     */
    public static function pepper(callable $callable, $namedArgs=null)
    {
        if ( !is_array( $namedArgs ) ) {
            $ref = !is_array($callable) ? new \ReflectionFunction($callable) : new \ReflectionMethod($callable[0], $callable[1]);
            $namedArgs = [];
            foreach ($ref->getParameters() as $parameter) {
                $namedArgs[ $parameter->getName() ] = $parameter->getDefaultValue();
            }
        }

        return function ($otherArgs) use ($callable, $namedArgs) {
            $args = array_values( array_merge( $namedArgs, $otherArgs ) );
            return call_user_func_array( $callable, $args );
        };
    }

    /**
    * Returns a method that will generate and call the given function only once and return its result for every call.
    * The first call generates the result. Each subsequent call simply returns that same result. This allows you
    * to create in-context singletons for any kind of object.
    * <code>
    *   $proto = \arc\lambda::prototype([
    *     'getSingleton' => \arc\lambda::singleton( function () {
    *       return new ComplexObject();
    *     })
    *   ]);
    * </code>
    * @param callable $f The function to generate the singleton.
    * @return mixed The singleton.
    */
    public static function singleton($f)
    {
        return singleton($f);
    }

}
