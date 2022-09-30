<?php

/*
 * This file is part of the Ariadne Component Library.
 *
 * (c) Muze <info@muze.nl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace arc\url;

/**
 * Url parses a URL string and returns an object with the seperate parts. You can change
 * these and when cast to a string Url will regenerate the URL string and make sure it
 * is valid.
 *
 * Usage:
 *    $url = new \arc\url\Url( 'http://www.ariadne-cms.org/' );
 *    $url->path = '/docs/search/';
 *    $url->query = 'a=1&a=2';
 *    echo $url; // => 'http://www.ariadne-cms.org/docs/search/?a=1&a=2'
 * @property Query $query The query arguments
 */
class Url
{
    /**
     * All parts of the URL format, as returned by parse_url.
     * scheme://user:pass@host:port/path?query#fragment
     */
    public $scheme, $user, $pass, $host, $port, $path, $fragment;
    private $query;

    /**
     * @param string $url The URL to parse, the query part will remain a string.
     * @param QueryInterface queryObject Optional. An object that parses the query string.
     */
    public function __construct($url, $queryObject = null)
    {
        $componentList = [
            'scheme', 'host', 'port', 'user', 'pass', 'path', 'query', 'fragment'
        ];
        $this->importUrlComponents( parse_url( $url ), $componentList );
        if ($this->scheme!='ldap' && strpos($this->host, ':')) {
            // parse_url allows ':' in host when it occurs more than once\
            $this->host = substr($this->host, 0, strpos($this->host, ':'));
        }
        if ( isset( $queryObject ) ) {
            $this->query = $queryObject->import( $this->query );
        }
    }

    /**
     * @return string
     */
    public function __toString()
    {
        switch ($this->scheme) {
            case 'file':
                return $this->getScheme() . '//' . $this->host . $this->getFilePath();
            break;
            case 'mailto':
            case 'news':
                return ( $this->path ? $this->getScheme() . $this->getPath() : '' );
            break;
            case 'ldap':
                return $this->getSchemeAndAuthority() . $this->getPath() . $this->getLdapQuery();
            break;
            default:
                return $this->getSchemeAndAuthority() . $this->getPath() . $this->getQuery() . $this->getFragment();
            break;
        }
    }

    /**
     * @param $name
     * @return mixed
     */
    public function __get($name)
    {
        switch ( (string) $name ) {
            case 'password':
                return $this->pass;
            break;
            case 'query':
                return $this->query;
            break;
        }
    }

    /**
     * @param $name
     * @param $value
     */
    public function __set($name, $value)
    {
        switch ( (string) $name ) {
            case 'password':
                $this->pass = $value;
            break;
            case 'query':
                if ( is_object( $this->query ) ) {
                    $this->query->reset()->import( $value );
                } else {
                    $this->query = $value;
                }
            break;
        }
    }

    /**
     *
     */
    public function __clone()
    {
        if ( is_object( $this->query ) ) {
            $this->query = clone $this->query;
        }
    }

    /**
     * @param $components
     * @param $validComponents
     */
    private function importUrlComponents($components, $validComponents)
    {
        array_walk( $validComponents, function ($componentName) use ($components) {
            $this->{$componentName} = ( isset( $components[$componentName] ) ? $components[$componentName] : '' );
        } );
    }

    /**
     * @return string
     */
    private function getSchemeAndAuthority()
    {
        // note: both '//google.com/' and 'file:///C:/' are valid URL's - so if either a scheme or host is set, add the // part
        return ( ( $this->scheme || $this->host ) ? $this->getScheme() . $this->getAuthority() : '' );
    }

    /**
     * @return string
     */
    private function getScheme()
    {
        return ( $this->scheme ? $this->scheme . ':' : '' );
    }

    /**
     * @return string
     */
    private function getAuthority()
    {
        return ( $this->host ? '//' . $this->getUser() . $this->host . $this->getPort() : '' );
    }

    /**
     * @return string
     */
    private function getUser()
    {
        return ( $this->user ? rawurlencode( $this->user ) . $this->getPassword() . '@' : '' );
    }

    /**
     * @return string
     */
    private function getPassword()
    {
        return ( $this->user && $this->pass ?  ':' . rawurlencode( $this->pass ) : '' );
    }

    /**
     * @return string
     */
    private function getPort()
    {
        return ( $this->port ? ':' . (int) $this->port : '' );
    }

    /**
     * @return mixed|string
     */
    private function getPath()
    {
        if (!$this->path) {
            return '';
        }
        $path = $this->path;
        if ( $this->host && ( !$path || $path[0] !== '/' ) ) {
            // note: if a host is set, the path _must_ be made absolute or the URL will be invalid
            $path = '/' . $path;
        }
        // urlencode encodes too many characters for the path part, so we decode them back to get readable urls.
        return str_replace( [ '%3D', '%2B', '%3A', '%40', '%7E'], [ '=', '+', ':', '@', '~'], $path = join( '/', array_map( 'urlencode', explode( '/', $path ) ) ) );
    }

    /**
     * @return mixed|string
     */
    private function getFilePath()
    {
        // in the file: scheme, a path must start with a '/' even if no host is set. This contradicts with the email: scheme.
        $path = $this->getPath();
        if ($path && $path[0]!=='/') {
            $path = '/' . $path;
        }

        return $path;
    }

    /**
     * @return string
     */
    private function getQuery()
    {
        // queries are assumed to handle themselves, so no encoding here.
        $query = (string) $this->query; // convert explicitly to string first, because the query object may exist but still return an empty string

        return ( $query ? '?' . $query : '' );
    }

    /**
     * @return string
     */
    private function getLdapQuery()
    {
        // ldap queries may contain multiple ? tokens - so these are unencoded here.
        $query = (string) $this->query; // convert explicitly to string first, because the query object may exist but still return an empty string

        return ( $query ? '?' . str_replace( '%3F', '?', $query ) : '' );
    }

    /**
     * @return string
     */
    private function getFragment()
    {
        return ( $this->fragment ? '#' . urlencode($this->fragment) : '' );
    }

}
