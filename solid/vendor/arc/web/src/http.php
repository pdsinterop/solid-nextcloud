<?php

/*
 * This file is part of the Ariadne Component Library.
 *
 * (c) Muze <info@muze.nl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace arc;

/**
 * Class http
 * Basic HTTP client.
 * @package arc
 */
final class http
{
    /**
     * Returns a http\ServerRequest object with properties for most
     * information in a server request, like url, method, body, etc.
     * @return http\ServerRequest
     */
    public static function serverRequest()
    {
        $context = \arc\context::$context;
        if (!isset($context->serverRequest)) {
            $context->serverRequest = new http\ServerRequest();
        }
        return $context->serverRequest;
    }

    /**
     * Returns a http\Htpasswd object with all users in the htpasswd file
     * @param string $htpasswd a string in htpasswd format
     * @return http\Htpasswd
     */
    public static function htpasswd($htpasswd) {
        return new http\Htpasswd($htpasswd);
    }

    /**
     * Send a HTTP request with a specific method, GET, POST, etc.
     * @param null  $method The method to use, GET, POST, etc.
     * @param null  $url    The URL to request
     * @param null  $query  The query string
     * @param array $options    Any of the HTTP stream context options, e.g. extra headers.
     * @return string
     */
    public static function request( $method = null, $url = null, $query = null, $options = [] )
    {
        $client = new http\ClientStream();

        return $client->request( $method, $url, $query, $options );
    }

    /**
     * Create a new HTTP client with a default set of stream context options to use in all requests.
     * @param array $options Any of the HTTP stream context options, e.g. extra headers.
     * @return http\ClientStream
     */
    public static function client( $options = [] )
    {
        return new http\ClientStream( $options );
    }

    /**
     * Do a HTTP GET request and return the response.
     * @param       $url    The URL to request
     * @param mixed  $query  The query parameters
     * @param array $options    Any of the HTTP stream context options, e.g. extra headers.
     * @return string
     */
    public static function get( $url, $query = null, $options = [] )
    {
        return self::request( 'GET', $url, $query, $options);
    }

    /**
     * Do a HTTP POST request and return the response.
     * @param       $url    The URL to request
     * @param mixed  $query  The query parameters
     * @param array $options    Any of the HTTP stream context options, e.g. extra headers.
     * @return string
     */
    public static function post( $url, $query = null, $options = [] )
    {
        return self::request( 'POST', $url, $query, $options);
    }

    /**
     * Do a HTTP PUT request and return the response.
     * @param       $url    The URL to request
     * @param mixed  $query  The query parameters
     * @param array $options    Any of the HTTP stream context options, e.g. extra headers.
     */
    public static function put( $url, $query = null, $options = [] )
    {
        return self::request( 'PUT', $url, $query, $options);
    }

    /**
     * Do a HTTP DELETE request and return the response.
     * @param       $url    The URL to request
     * @param mixed  $query  The query parameters
     * @param array $options    Any of the HTTP stream context options, e.g. extra headers.
     * @return string
     */
    public static function delete( $url, $query = null, $options = [] )
    {
        return self::request( 'DELETE', $url, $query, $options);
    }
}
