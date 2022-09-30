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
 * Class ServerRequest
 * Implements a simple container to retrieve information from the http server request
 * It doesn't load or parse anything, unless you access one of the defined properties
 * and then it only loads/parses what you need
 *
 * Usage:
 *   $request = \arc\http::serverRequest();
 *   echo $request->url;
 *
 * Note: this class doesn't implement Psr7/Psr15. You can easily convert it if you
 * need to. e.g.
 *   $psr7request = \Nyholm\Psr7\ServerRequest(
 *       $request->method,
 *       $request->url,
 *       $request->headers,
 *       $request->body
 *       explode('.',$request->protocol)[1],
 *       $_SERVER
 *   );
 * But you might as well create a new Psr7 request from the Psr15 factory methods
 *
 * @package arc
 * @property string $protocol The HTTP request protocol
 * @property string $method The HTTP request method
 * @property \arc\url\Url $url The requested URL 
 * @property array $headers The Headers sent in the request. Note that Apache's REDIRECT_ prefixes aren't parsed, you have to do that yourself.
 * @property string $body The HTTP request body
 * @property string $params The HTTP post params, if sent
 * @property string $files The files uploaded with the request, if any
 * @property string $user The user name from HTTP Basic authentication, if specified
 * @property string $password The user password from HTTP Basic authentication, if specified
 */
class ServerRequest
{
	
	/**
	 * Lazy load one of the Request properties
	 */
	public function __get($name)
	{
		switch($name) {
			case 'protocol':
				$this->protocol = $this->getProtocol();
				return $this->protocol;
			break;
			case 'method':
				$this->method = $this->getMethod();
				return $this->method;
			break;
			case 'url':
				$this->url = $this->getURL();
				return $this->url;
			break;
			case 'headers':
				$this->headers = $this->getHeaders();
				return $this->headers;
			break;
			case 'params':
				$this->params = $this->getParams();
				return $this->params;
			break;
			case 'body':
				$this->body = $this->getBody();
				return $this->body;
			break;
			case 'user':
				$this->user = $this->getUser();
				return $this->user;
			break;
			case 'password':
				$this->password = $this->getPassword();
				return $this->password;
			break;
			case 'files':
				$this->files = $this->getFiles();
				return $this->files;
			break;
			default:
				throw new \arc\IllegalRequest('Unknown property '.$name, \arc\exceptions::OBJECT_NOT_FOUND);
			break;
		}
	}

	/**
	 * Returns the first header found from a list of headers.
	 * Will try REDIRECT_* headers first, if $followRedirects>0. 
	 * ( Apache adds 'REDIRECT_' to some headers when you use mod_rewrite. )
	 * @param array $list An array of headers to try, in order
	 * @param int $followRedirects The maximum number of REDIRECT_ prefixes to try
	 */
	public function getHeader($list, $followRedirects=0) {
		$redirect = 'REDIRECT_';
		if (!is_array($list)) {
			$list = [ $list => false ];
		}
		foreach ( $list as $header => $extraInfo ) {
			for ($i=$followRedirects; $i>=0; $i--) {
				$check = str_repeat($redirect, $i).$header;
				if ( isset($_SERVER[$check]) ) {
					return [$header, $_SERVER[$check]];
				}
			}
		}
		return [false, ''];
	}

	private function getUser()
	{
		$checks = [ 
			'PHP_AUTH_USER'               => false, 
			'REMOTE_USER'                 => false, 
			'HTTP_AUTHORIZATION'          => function($v) { list($user,$password)=$this->parseAuthUser($v); return $user; },
		];
		list($header, $headerValue) = $this->getHeader($checks, 3);
		if (isset($checks[$header]) && is_callable($checks[$header])) {
			$headerValue = ($checks[$header])($headerValue);
		}
		return $headerValue;
	}

	private function getPassword()
	{
		$checks = [ 
			'PHP_AUTH_PW'                 => false, 
			'HTTP_AUTHORIZATION'          => function($v) { list($user,$password)=$this->parseAuthUser($v); return $password; },
		];
		list($header, $headerValue) = $this->getHeader($checks, 3);
		if (isset($checks[$header]) && is_callable($checks[$header])) {
			$headerValue = ($checks[$header])($headerValue);
		}
		return $headerValue;
	}

	private function parseAuthUser($auth) {
		return explode(':',base64_decode(substr($auth, 6)));
	}

	private function getProtocol()
	{
		list($header, $headerValue) = $this->getHeader('SERVER_PROTOCOL',3);
		return $headerValue ?: 'HTTP/1.1';
	}

	private function getMethod()
	{
		list($header, $headerValue) = $this->getHeader('REQUEST_METHOD',3);
		return $headerValue;
	}

	private function getURL()
	{
		return \arc\url::url($_SERVER['REQUEST_URI']);
	}

	private function getHeaders()
	{
		return getallheaders(); //polyfill via composer require ralouphie/getallheaders
	}

	private function getBody()
	{
		return stream_get_contents(fopen('php://input','r'));
	}

	private function normalizeFiles($files = [])
	{
		$normalized = [];
		if (is_array($files['tmp_name'])) {
			foreach($files['tmp_name'] as $key) {
				$normalized[$key] = [
					'tmp_name' => $files['tmp_name'][$key],
					'size'     => $files['size'][$key],
	                'error'    => $files['error'][$key],
	                'name'     => $files['name'][$key],
	                'type'     => $files['type'][$key]
				];
			}
		} else foreach ($files as $key => $value) {
			if (is_array($value) && isset($value['tmp_name'])) {
				$normalized[$key] = $value;
			} else if (is_array($value)) {
				$normalized[$key] = $this->normalizeFiles($value);
			}
		}
		return $normalized;
    }

	private function getFiles()
	{
		return $this->normalizeFiles($_FILES);
	}

	private function getParams()
	{
		return $_POST;
	}
}