<?php

namespace MJRider\FlysystemFactory\Adapter;

use arc\url as url;
use arc\path as path;
use Aws\S3\S3Client;
use League\Flysystem\AwsS3v3\AwsS3Adapter;
use MJRider\FlysystemFactory\Endpoint;

class S3 implements AdapterFactoryInterface
{
    use Endpoint;

    protected static function buildArgs($url)
    {
        $args = [
            'credentials' => [
                'key'    => urldecode($url->user),
                'secret' => urldecode($url->pass)
            ],
            'region' => $url->host,
            'version' => 'latest',
            'use_path_style_endpoint' => false,
        ];

        if (isset($url->query->use_path_style_endpoint)) {
            $args[ 'use_path_style_endpoint' ] = (bool)$url->query->use_path_style_endpoint;
        }

        if (isset($url->query->endpoint)) {
            $args[ 'endpoint' ] = self::endpointToURL(urldecode($url->query->endpoint));
        }

        return $args;
    }

    protected static function buildAdapter($client, $bucket, $subpath)
    {
        return new AwsS3Adapter($client, $bucket, $subpath);
    }

    /**
     * @inheritDoc
     */
    public static function create($url)
    {
        $args = static::buildArgs($url);
        $client = S3Client::factory($args);

        $path = \arc\path::collapse($url->path);
        $bucket  = (string) \arc\path::head($path);
        $subpath = (string) \arc\path::tail($path);
        return static::buildAdapter($client, $bucket, $subpath);
    }
}
