<?php

namespace MJRider\FlysystemFactory\Adapter;

use League\Flysystem\Adapter\NullAdapter as FL;

/**
 * Static factory class for creating a null Adapter
 */
class NullAdapter implements AdapterFactoryInterface
{
    /**
     * @inheritDoc
     */
    public static function create($url)
    {
        $adapter = new FL();
        return $adapter;
    }
}
