<?php

namespace MJRider\FlysystemFactory\Adapter;

use League\Flysystem\Filesystem;
use League\Flysystem\Adapter\Ftp as Adapter;

/**
 * Static factory class for creating an ftp connection
 */
class Ftp implements AdapterFactoryInterface
{
    /**
     * Builds the arguments for the FTP adapter
     *
     * @param [string] $url
     * @return void
     */
    protected static function buildArgs($url)
    {
        $args = [
            'host' => $url->host,
            'username' => urldecode($url->user),
            'password' => urldecode($url->pass),
            'port' => $url->port,
            'root' => $url->path,
        ];

        if (isset($url->query->passive)) {
            $args[ 'passive' ] = (bool)$url->query->passive;
        }

        if (isset($url->query->ssl)) {
            $args[ 'ssl' ] = (bool)$url->query->ssl;
        }

        if (isset($url->query->timeout)) {
            $args[ 'timeout' ] = (int)$url->query->timeout;
        }

        return $args;
    }

    /**
     * Creates the adapter for FTP
     *
     * @param [string] $url
     * @return void
     */
    public static function create($url)
    {
        $args = static::buildArgs($url);

        $adapter = new Adapter($args);

        return $adapter;
    }
}
