<?php

/*
 * This file is part of the Ariadne Component Library.
 *
 * (c) Muze <info@muze.nl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace arc\http;

/**
 * Class ClientStream
 * Implements a HTTP client using PHP's stream handling.
 * @package arc\http
 */
class ClientStream implements Client
{
    public $whitelist = [
        'http','https'
    ];

    private $options = [
        'headers'          => [],
        'timeout'          => 5,
        'ignore_errors'    => true,
        'protocol_version' => 1.1
    ];

    /**
     */
    public $responseHeaders = null;
    /**
     */
    public $requestHeaders  = null;
    /**
     */
    public $verbs           = [
        'GET'     => true,
        'POST'    => true,
        'PUT'     => true,
        'DELETE'  => true,
        'OPTIONS' => true,
        'HEAD'    => true,
        'TRACE'   => true
    ];
    /**
     * Merges header string and headers array to single string with all headers
     * @return string
     */
    private function mergeHeaders() {
        $args   = func_get_args();
        $result = '';
        foreach ( $args as $headers ) {
            if (is_array($headers) || $headers instanceof \ArrayObject ) {
                $result .= array_reduce( (array) $headers, function($carry, $entry) {
                    return $carry . "\r\n" . $entry;
                }, '');
            } else {
                $result .= (string) $headers;
            }
        }
        if (substr($result, -2)!="\r\n") {
            $result .= "\r\n";
        }
        return $result;
    }

    /**
     * Send a HTTP request and return the response
     * @param string       $type    The method to use, GET, POST, etc.
     * @param string       $url     The URL to request
     * @param array|string $request The query string
     * @param array        $options Any of the HTTP stream context options, e.g. extra headers.
     * @return string
     */
    public function request( $type, $url, $request = null, $options = [] )
    {
        $url = \arc\url::url( (string) $url);
        if (!in_array($url->scheme, $this->whitelist)) {
            throw new \arc\IllegalRequest("Scheme ".$url->scheme." is not allowed", \arc\exceptions::ILLEGAL_ARGUMENT);
        }
        if ($type == 'GET' && $request) {
            $url->query->import( $request);
            $request = null;
        }
        $options = [
            'method'  => $type,
            'content' => $request
        ] + (array) $options;

        $options['header'] = $this->mergeHeaders(
            \arc\hash::get('header', $this->options),
            \arc\hash::get('headers', $this->options),
            \arc\hash::get('header', $options),
            \arc\hash::get('headers', $options)
        );

        $options += (array) $this->options;

        $context = stream_context_create( [ 'http' => $options ] );
        $result  = @file_get_contents( (string) $url, false, $context );
        $this->responseHeaders = isset($http_response_header) ? $http_response_header : null; //magic php variable set by file_get_contents.
        $this->requestHeaders  = isset($options['header']) ? explode("\r\n",$options['header']) : [];

        return $result;
    }

    /**
     * @param array $options Any of the HTTP stream context options, e.g. extra headers.
     */
    public function __construct( $options = [] )
    {
        $this->options = $options;
    }

    public function __call( $name, $args )
    {
        $name = strtoupper($name);
        if ( isset($this->verbs[$name]) && $this->verbs[$name] ) {
            @list($url, $request, $options) = $args;
            return $this->request( $name, $url, $request, $options );
        } else {
            throw new \arc\MethodNotFound("'$name' is not a valid http client method", \arc\exceptions::OBJECT_NOT_FOUND );
        }
    }


    /**
     * Adds headers for subsequent requests
     * @param mixed $headers The headers to add, either as a string or an array of headers.
     * @return $this
     */
    public function headers($headers)
    {
        if (!isset($this->options['headers'])) {
            $this->options['headers'] = [];
        }
        if ( !is_array($headers) ) {
            $headers = explode("\r\n",$headers);
            if (end($headers) == '') {
                array_pop($headers);
            }
        }

        $this->options['headers'] = array_merge($this->options['headers'], $headers);

        return $this;
    }
}
