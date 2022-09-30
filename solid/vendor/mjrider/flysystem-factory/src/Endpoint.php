<?php

namespace MJRider\FlysystemFactory;

trait Endpoint
{
    /**
     * @param $endpoint string endpoint formated string
     *
     * @return string;
     * Rewriting endpoint from minimal scheme to full url
     * example.com => https://example.com/
     * example.com/v1  => https://example.com/v1
     * example.com:1443/v1 => https://example.com:1443/v1
     * http://example.com:8080/v1 => http://example.com:8080/v1
     */
    public static function endpointToURL($endpoint)
    {
        if (strpos($endpoint, '//') === 0) {
            $endpoint = 'https:' . $endpoint;
        } elseif (strpos($endpoint, '://') === false) {
            $endpoint = 'https://' . $endpoint;
        }

        $url = \arc\url::url($endpoint);
        return (string) $url;
    }
}
