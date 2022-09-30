<?php

namespace MJRider\FlysystemFactory\CacheStore;

use League\Flysystem\Cached\Storage\Memory as CS;

/**
 * Static factory class for creating an Memory cache storage instance
 */
class Memory implements CacheStoreFactoryInterface
{
    /**
     * {@inheritDoc}
     */
    public static function create($url)
    {
        $adapter = new CS();
        return $adapter;
    }
}
