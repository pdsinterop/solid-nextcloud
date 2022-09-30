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
 * This class contains static methods to help parse HTTP headers.
 * @package arc\http
 */
final class headers
{

    /**
     * Parse response headers string from a HTTP request into an array of headers. e.g.
     * [ 'Location' => 'http://www.example.com', ... ]
     * When multiple headers with the same name are present, all values will form an array, in the order in which
     * they are present in the source.
     * @param string|string[] $headers The headers string to parse.
     * @return array
     */
    public static function parse( $headers ) {
        if ( !is_array($headers) && !$headers instanceof \ArrayObject ) {
            $headers = array_filter(
                array_map( 'trim', explode( "\n", (string) $headers ) )
            );
        }
        $result = [];
        $currentName = '';
        foreach( $headers as $key => $header ) {
            if ( !is_array($header) ) {
                @list($name, $value) = array_map('trim', explode(':', $header, 2) );
            } else {
                $name = $header;
                $value = null;
            }
            if ( isset( $value ) ) {
                $result = self::addHeader($result, $name, $value);
            } else if (is_numeric($key)) {
                if ( $currentName ) {
                    $result = self::addHeader($result, $currentName, $name);
                } else {
                    $result[] = $name;
                }
                $name = $key;
            } else {
                $result = self::addHeader($result, $key, $name);
                $name = $key;
            }
            $currentName = ( is_numeric($name) ? $currentName : $name );
        }
        return $result;
    }

    /**
     * Return an array with values from a Comma seperated header like Cache-Control or Accept
     * e.g. 'max-age=300,public,no-store'
     * results in
     * [ 0 => [ 'max-age' => '300' ], 1 => [ 'public' => 'public' ], 2 => ['no-store' => 'no-store'] ]
     * @param string $header
     * @return array
     */
    public static function parseHeader($header)
    {
        $header = (strpos($header, ':')!==false) ? explode(':', $header)[1] : $header;
        $parts   = array_map('trim', explode(',', $header));
        $header = [];
        foreach ( $parts as $part ) {
            $elements = array_map('trim', explode(';', $part));
            $result = [];
            foreach ($elements as $element) {
                @list($name, $value)          = array_map('trim', explode( '=', $element));
                if ( !isset($value) ) {
                    $result['value'] = $name;
                } else {
                    $result[$name] = (isset($value) ? $value : $name);
                }
            }
            $header[] = $result;
        }
        return $header;
    }

    /**
     * Merge multiple occurances of a comma seperated header
     * @param array $headers
     * @return array
     */
    public static function mergeHeaders( $headers )
    {
        $result = [];
        if ( is_string($headers) ) {
            $result = self::parseHeader( $headers );
        } else foreach ( $headers as $header ) {
            if (is_string($header)) {
                $header = self::parseHeader($header);
            }
            $result = array_merge( $result, $header );
        }
        return $result;
    }

    /**
     * Parse response headers to determine if and how long you may cache the response. Doesn't understand ETags.
     * @param string|string[] $headers Headers string or array as returned by parse()
     * @param bool $private Whether to store a private cache (true) or public cache image (false). Default is public.
     * @return int The number of seconds you may cache this result starting from now.
     */
    public static function parseCacheTime( $headers, $private=false )
    {
        $result = null;
        if ( is_string($headers) || ( !isset($headers['Cache-Control']) && !isset($headers['Expires']) ) ) {
            $headers = \arc\http\headers::parse( $headers );
        }
        if ( isset( $headers['Cache-Control'] ) ) {
            $header = self::mergeHeaders( $headers['Cache-Control'] );
            $result = self::getCacheControlTime( $header, $private );
        }
        if ( !isset($result) && isset( $headers['Expires'] ) ) {
            $result = strtotime( self::getLastHeader( $headers['Expires'] ) ) - time();
        }
        return (int) $result;
    }

    /**
     * Parses Accept-* header and returns best matching value from the $acceptable list
     * Takes into account the Q value and wildcards. Does not take into account other parameters
     * currently ( e.g. text/html;level=1 )
     * @param array|string $header The Accept-* header (Accept:, Accept-Lang:, Accept-Encoding: etc.)
     * @param array $acceptable List of acceptable values, in order of preference
     * @return string
     */
    public static function accept( $header, $acceptable )
    {
        if ( is_string($header) ) {
            $header = \arc\http\headers::parseHeader( $header );
        }
        $ordered = self::orderByQuality($header);
        foreach( $ordered as $value ) {
            if ( self::isAcceptable($value['value'], $acceptable) ) {
                return $value['value'];
            }
        }
    }

    public static function addHeader($headers, $name, $value)
    {
        if ( !isset($headers[ $name]) ) {
            // first entry for this header
            $headers[ $name ] = $value;
        } else if ( is_string($headers[ $name ]) ) {
            // second header entry with same name
            $headers[ $name ] = [
                $headers[ $name ],
                $value
            ];
        } else { // third or later header entry with same name
            $headers[ $name ][] = $value;
        }
        return $headers;
    }

    private static function getCacheControlTime( $header, $private )
    {
        $result    = null;
        $dontcache = false;
        foreach ( $header as $value ) {
            if ( isset($value['value']) ) {
                switch($value['value']) {
                    case 'private':
                        if ( !$private ) {
                            $dontcache = true;
                        }
                    break;
                    case 'no-cache':
                    case 'no-store':
                    case 'must-revalidate':
                    case 'proxy-revalidate':
                        $dontcache = true;
                    break;
                }
            } else if ( isset($value['max-age']) || isset($value['s-maxage']) ) {
                $maxage = (int) (isset($value['max-age']) ? $value['max-age'] : $value['s-maxage']);
                if ( isset($result) ) {
                    $result = min($result, $maxage);
                } else {
                    $result = $maxage;
                }
            }
        }
        if ( $dontcache ) {
            $result = 0;
        }
        return $result;
    }

    private static function orderByQuality($header)
    {
        $getQ = function($entry) {
            $q = ( isset($entry['q']) ? floatval($entry['q']) : 1);
            $name = $entry['value'];
            if ( $name[ strlen($name)-1 ] == '*' || $name[0] == '*' ) {
                $q -= 0.0001; // exact matches are preferred over wildcards
            }
            return $q;
        };
        usort($header, function($a,$b) use ($getQ) {
            return ($getQ($a)>$getQ($b) ? -1 : 1);
        });
        return $header;
    }

    private static function pregEscape($string) {
        $special = ".\\+'?[^]$(){}=!<>|:";
        // * and - are not included, since they are allowed in the accept mimetypes
        return AddCSlashes($string, $special);
   }

    private static function isAcceptable($name, $acceptable)
    {
        $name = str_replace('*', '.*', $name);
        $result = preg_grep('|'.self::pregEscape($name).'|', $acceptable);
        return current($result);
    }

    /**
     * Return the last value sent for a specific header, uses the output of parse().
     * @param (mixed) $headers An array with multiple header strings or a single string.
     * @return array|mixed
     */
    private static function getLastHeader($headers) {
        if ( is_array($headers) ) {
            return end($headers);
        }
        return $headers;
    }


}