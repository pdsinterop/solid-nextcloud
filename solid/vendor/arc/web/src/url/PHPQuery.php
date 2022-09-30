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
 *	PHPQuery parses a given query string with parse_str and makes all the arguments and
 *	values available as key => value pairs in an array-like object.
 *	It also allows you to import PHP variables from another query string or an array with
 *	key => value pairs.
 *	When cast to string PHPQuery generates a valid query string compatible with PHP.
 *
 *	Usage:
 *		$query = new \arc\url\PHPQuery( 'a[0]=1&a[1]=2&test=foo');
 *		$query['a'][] = 3;
 *		$query['bar']= 'test';
 *		unset( $queyr['foo'] );
 *		echo $query; // => 'a[0]=1&a[1]=2&a[2]=3&bar=test';
 */
class PHPQuery extends Query
{
    /**
     * Create a query string from the given values
     * @param array $values
     * @return string
     */
    protected function compile($values)
    {
        return http_build_query( $values, '', '&', PHP_QUERY_RFC3986 );
    }

    /**
     * Parse a query string and return an array with values.
     * @param string $queryString
     * @return mixed
     */
    protected function parseQueryString($queryString)
    {
        parse_str( (string) $queryString, $values );

        return $values;
    }
}
