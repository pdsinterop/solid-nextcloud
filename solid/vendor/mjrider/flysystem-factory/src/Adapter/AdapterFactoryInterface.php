<?php

namespace MJRider\FlysystemFactory\Adapter;

/**
 * Interface defining the interface for AdapterFactory's
 */
interface AdapterFactoryInterface
{
    /**
     * Create Flysystem Adapter
     *
     * @param \arc\url\Url $url url needed to configure the adapter
     *
     * @return \League\Flysystem\AdapterInterface;
     */
    public static function create($url);
}
