<?php

namespace MJRider\FlysystemFactory\Adapter;

use OpenCloud\OpenStack;
use League\Flysystem\Rackspace\RackspaceAdapter;
use MJRider\FlysystemFactory\Endpoint;

/**
 * Static factory class for creating an rackspace Adapter
 */
class Rackspace implements AdapterFactoryInterface
{
    use Endpoint;

    /**
     * @inheritDoc
     */
    public static function create($url)
    {
        $auth = null;
        $zone = null;

        if (isset($url->query->authendpoint)) {
            $auth = urldecode($url->query->authendpoint);
            unset($url->query->authendpoint);
        }

        if (isset($url->query->zone)) {
            $zone = urldecode($url->query->zone);
            unset($url->query->zone);
        }

        $auth = self::endpointToURL($auth);

        $args = [
            'username' => urldecode($url->user),
            'password' => urldecode($url->pass),
            'tenantId' => urldecode($url->host),
        ];

        $options = (array) $url->query;

        $path = \arc\path::collapse($url->path);
        $container = trim(\arc\path::head($path), '/');
        $prefix = ltrim(\arc\path::tail($path), '/');

        $client = new OpenStack($auth, $args, $options);
        $store = $client->objectStoreService('swift', $zone);
        $container = $store->getContainer($container);

        $adapter = new RackspaceAdapter($container, $prefix);
        return $adapter;
    }
}
