<?php

namespace MJRider\FlysystemFactory;

use League\Flysystem\Filesystem;
use arc\url as url;
use MJRider\FlysystemFactory\Adapter;
use League\Flysystem\Cached\CachedAdapter as FCA;

/**
 * Create a flysystem instance configured from a uri endpoint
 *
 * @param string $endpoint url formated string describing the flysystem configuration
 *
 * @return \League\Flysystem\Filesystem instance
 */
function create($endpoint)
{
    $url = url::url($endpoint);
    $filesystem = null;
    $adapter = null;

    switch ($url->scheme) {
        case 's3':
            $adapter = Adapter\S3::create($url);
            break;
        case 'b2':
            $adapter = Adapter\B2::create($url);
            break;
        case 'ftp':
            $adapter = Adapter\Ftp::create($url);
            break;
        case 'file':
        case 'local':
            $adapter = Adapter\Local::create($url);
            break;
        case 'null':
            $adapter = Adapter\NullAdapter::create($url);
            break;
        case 'rackspace':
            $adapter = Adapter\Rackspace::create($url);
            break;
        case 'openstack':
            $adapter = Adapter\OpenStack::create($url);
            break;
        default:
            throw new \InvalidArgumentException(sprintf('Unknown scheme [%s]', $url->scheme));
    }

    if (!is_null($adapter)) {
        $filesystem = new Filesystem($adapter);
    }

    return $filesystem;
}

/**
 * Create a caching flysystem instance configured from a uri endpoint
 * if no cache config is provided, it returns the flysystem
 * @param string $endpoint url formated string describing the cache configuration
 * @param mixed $store flysystem
 *
 * @return \League\Flysystem\Filesystem instance
 */
function cache($cache, Filesystem $flysystem)
{
    $url = url::url($cache);

    $cachestore = null;
    switch ($url->scheme) {
        case 'phpredis':
            break;
        case 'predis':
        case 'predis-unix':
        case 'predis-tcp':
            $cachestore = CacheStore\Predis::create($url);
            break;
        case 'memory':
            $cachestore = CacheStore\Memory::create($url);
            break;
        case 'stash':
            break;
        case 'memcached':
            $cachestore = CacheStore\Memcached::create($url);
            break;
        default:
    }

    if (isset($cachestore)) {
        // Decorate the filesystem with a cacheadapter
        $adapter = $flysystem->getAdapter();
        $cachedAdapter = new FCA($adapter, $cachestore);
        $flysystem = new Filesystem($cachedAdapter);
    }

    return $flysystem;
}
