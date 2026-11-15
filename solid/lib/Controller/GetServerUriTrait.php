<?php

namespace OCA\Solid\Controller;

use OCP\IRequest;

/**
 * Builds the absolute URI of the current request, as seen by the client.
 *
 * The URI is used as the base for resolving relative IRIs in RDF documents.
 * It must therefore be the public URI, not the one seen by the web server.
 * Behind a reverse proxy that terminates TLS, `$_SERVER['REQUEST_SCHEME']`
 * and `$_SERVER['SERVER_NAME']` describe the internal hop (for instance
 * `http` and an internal host name). Nextcloud's IRequest honours the
 * `overwriteprotocol`, `overwritehost` and `trusted_proxies` settings, so the
 * scheme and host are taken from there.
 *
 * @see https://github.com/pdsinterop/solid-nextcloud/issues/225
 */
trait GetServerUriTrait
{
	final public function getServerUri(IRequest $request): string
	{
		$scheme = $request->getServerProtocol();
		$host = $request->getServerHost();
		$path = $request->getRequestUri();

		return "{$scheme}://{$host}{$path}";
	}
}
