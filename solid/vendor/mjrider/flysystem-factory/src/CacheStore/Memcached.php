<?php

namespace MJRider\FlysystemFactory\CacheStore;

use League\Flysystem\Cached\Storage\Memcached as CS;

/**
 * Static factory class for creating an Memcached cache storage instance
 */
class Memcached implements CacheStoreFactoryInterface
{
    /**
     * {@inheritDoc}
     */
    public static function create($url)
    {
        $expire = null;
        $cachekey = 'flysystem';

        if (isset($url->query->cachekey)) {
            $cachekey = $url->query->cachekey;
            unset($url->query->cachekey);
        }

        if (isset($url->query->expire)) {
            $expire = $url->query->expire;
            unset($url->query->expire);
        }

        $memcache = new \Memcached();
        $memcache->addServer($url->host, $url->port);
        $adapter = new CS($memcache, $cachekey, $expire);
        return $adapter;
    }
}
