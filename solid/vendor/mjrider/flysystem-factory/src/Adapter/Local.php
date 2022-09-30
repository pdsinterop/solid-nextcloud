<?php

namespace MJRider\FlysystemFactory\Adapter;

use League\Flysystem\Adapter\Local as FL;

/**
 * Static factory class for creating a local Adapter
 */
class Local implements AdapterFactoryInterface
{
    /**
     * @inheritDoc
     */
    public static function create($url)
    {
        $adapter = new FL($url->path);
        return $adapter;
    }
}
