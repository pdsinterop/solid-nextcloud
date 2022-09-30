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
 * Class url
 * Simple URL manipulation.
 * @package arc
 */
class url
{
    /**
     *	Returns a new URL object with easy access to the components (scheme, host, port, path, etc) and
     *	the query parameters in the url. It parses these according to PHP's own rules. If the URL is
     *	incompatible with PHP, use \arc\url\safeUrl() instead.
     *	@param string $url
     *	@return \arc\url\Url The parsed url object
    */
    public static function url($url)
    {
        return new url\Url( $url, new url\PHPQuery() );
    }

    /**
     * Returns a new URL object with easy access to the components (scheme, host, port, path, etc)
     * It will parse the query string without PHP centric assumptions. You can have the same parameter
     * present multiple times and it will automatically turn into an array. You can use parameters without
     * a value, these will become array values with a numbered index.
     * @param string $url
     * @return \arc\url\Url The url object
    */
    public static function safeUrl($url)
    {
        return new url\Url( $url, new url\Query() );
    }
}
