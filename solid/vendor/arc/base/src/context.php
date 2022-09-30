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

/**
 * Class context
 * A \arc\prototype\Prototype based dependency injection container
 * with stack functionality.
 *
 * @package arc
 * @requires \arc\prototype
 */
class context
{
    public static $context = null;

    /**
     * Adds/overrides a set of properties/methods in a new 'stack frame'
     * @param $params
     */
    public static function push($params)
    {
        self::$context = \arc\prototype::extend(self::$context, $params );
    }

    /**
     * Removes last addition/overrides from the container.
     */
    public static function pop()
    {
        self::$context = self::$context->prototype;
    }

    /**
     * Take a peek at earlier entries of the container.
     * @param int $level
     * @return null
     */
    public static function peek($level = 0)
    {
        $context = self::$context;
        for ($i = $level; $i >= 0; $i--) {
            $context = $context->prototype;
        }

        return $context;
    }
}

context::$context = \arc\prototype::create([
    'arcPath' => '/'
]);
