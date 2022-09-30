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
 * Interface Client
 * @package arc\http
 * @method string get(string $url, array|string $request = [], array $options = [] )
 * @method string post(string $url, array|string $request = [], array $options = [] )
 * @method string put(string $url, array|string $request = [], array $options = [] )
 * @method string delete(string $url, array|string $request = [], array $options = [] )
 * @method string options(string $url, array|string $request = [], array $options = [] )
 * @method string head(string $url, array|string $request = [], array $options = [] )
 * @method string trace(string $url, array|string $request = [], array $options = [] )
 * @method string request(string $method, string $url, array|string $request = [], array $options = [] )
 * @method $this headers( string|array $headers ) 
 */
interface Client
{

}
