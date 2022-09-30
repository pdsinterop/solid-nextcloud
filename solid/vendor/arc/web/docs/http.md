arc\http
========

This component provides a very basic http client using PHP stream handling, and a simple ServerRequest implementation.

\arc\http::serverRequest
------------------------
	(object) \arc\http::serverRequest();

```php
    $request = \arc\http::serverRequest();
```

The request object gives access to the following properties:
- protocol
- method
- url
- headers
- body

In addition the following methods are provided:
- \arc\http\ServerRequest::getHeader($headerList, $followRedirects=0)
- \arc\http\ServerRequest::getUser()
- \arc\http\ServerRequest::getPassword()

\arc\http::htpasswd
--------------------
	(object) \arc\http::htpasswd(string $passwdFileContents)

```php
	$htpasswd = \arc\http::htpasswd(file_get_contents('.htpasswd'));
	if ($htpasswd->check($user, $password)) {
		// user and password are valid
	}
```
This returns a \arc\http\Htpasswd object with the users from the htpasswd file.
It contains one method: 
	(bool) \arc\http\Htpasswd::check(string $user, string $password)


\arc\http::get
--------------------
\arc\http::post
--------------------
\arc\http::put
--------------------
\arc\http::delete
--------------------
    (string) \arc\http::get( $url = null, $query = null, $options = array() )

```php
    $htmlResult = \arc\http::get( 'http://www.ariadne-cms.org/', '?foo=bar' );
```

These methods send a http requests to the given url with the given query arguments. 
The options array is a optional list of [http context options](http://www.php.net/manual/en/context.http.php).


\arc\http::request
--------------------
    (string) \arc\http::request( $method = null, $url = null, $query = null, $options = array() )

```php
    $htmlResult = \arc\http::request( 'GET', 'http://www.ariadne-cms.org/', '?foo=bar' );
```

This method sends a http request with the given method to the given url with the given query arguments.
The options array is a optional list of [http context options](http://www.php.net/manual/en/context.http.php).

\arc\http::client
-------------------
    (object) \arc\http::client( array( 'max_redirects' => 1 ) );

This method returns a new http\ClientStream object.
The options array is a optional list of [http context options](http://www.php.net/manual/en/context.http.php).

\arc\http\ClientStream::get
--------------------
\arc\http\ClientStream::post
--------------------
\arc\http\ClientStream::put
--------------------
\arc\http\ClientStream::delete
--------------------
\arc\http\ClientStream::request
-------------------------------
These methods are identical to their \arc\http:: counterparts.

\arc\http\ClientStream::$responseHeaders
--------------------
\arc\http\ClientStream::$requestHeaders
----------------------------------------
These public properties provide access to the response and request headers of the last request.

\arc\http\ClientStream::headers
-------------------------------
    (object) \arc\http\ClientStream::headers( (array) $headers )

This method adds the given headers to the default set of headers to be sent with each request.

