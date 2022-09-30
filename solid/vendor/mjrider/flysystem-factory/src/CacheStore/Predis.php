<?php

namespace MJRider\FlysystemFactory\CacheStore;

use League\Flysystem\Cached\Storage\Predis as CS;

/**
 * Static factory class for for creating an Predis cache storage instance
 */
class Predis implements CacheStoreFactoryInterface
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

        if ($url->scheme == 'predis') {
            $url->scheme = 'tcp';
        }

        if ($url->scheme == 'predis-tcp' || $url->scheme == 'predis-unix') {
            $url->scheme = substr($url->scheme, 7);
        }

        $client = new \Predis\Client((string) $url);

        $adapter = new CS($client, $cachekey, $expire);
        return $adapter;
    }
}
