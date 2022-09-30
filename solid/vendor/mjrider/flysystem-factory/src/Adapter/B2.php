<?php

namespace MJRider\FlysystemFactory\Adapter;

use Mhetreramesh\Flysystem\BackblazeAdapter;
use ChrisWhite\B2\Client;

/**
 * Static factory class for creating an backblaze Adapter
 */
class B2 implements AdapterFactoryInterface
{
    /**
     * @inheritDoc
     */
    public static function create($url)
    {
        $client = new Client($url->user, $url->pass);

        $adapter = new BackblazeAdapter($client, $url->host);

        return $adapter;
    }
}
