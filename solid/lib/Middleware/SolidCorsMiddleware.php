<?php
    namespace OCA\Solid\Middleware;

    use OCP\AppFramework\Middleware;
    use OCP\AppFramework\Http\Response;
    use OCP\IResponse;
    use OCP\IRequest;

    class SolidCorsMiddleware extends Middleware {
        private $request;

        public function __construct(IRequest $request) {
            $this->request = $request;
        }

        public function afterController($controller, $methodName, Response $response) {
            $corsMethods="GET, PUT, POST, OPTIONS, DELETE, PATCH";
            $corsAllowedHeaders="*, allow, accept, authorization, content-type, dpop, slug, link";
            $corsMaxAge="1728000";
            $corsExposeHeaders="Authorization, User, Location, Link, Vary, Last-Modified, ETag, Accept-Patch, Accept-Post, Updates-Via, Allow, WAC-Allow, Content-Length, WWW-Authenticate, MS-Author-Via";
            $corsAllowCredentials="true";

            if (isset($this->request->server['HTTP_ORIGIN'])) {
                $corsAllowOrigin = $this->request->server['HTTP_ORIGIN'];
            } else {
                $corsAllowOrigin = '*';
            }

            $response->addHeader('Access-Control-Allow-Origin', $corsAllowOrigin);
            $response->addHeader('Access-Control-Allow-Methods', $corsMethods);
            $response->addHeader('Access-Control-Allow-Headers', $corsAllowedHeaders);
            $response->addHeader('Access-Control-Max-Age', $corsMaxAge);
            $response->addHeader('Access-Control-Allow-Credentials', $corsAllowCredentials);
            $response->addHeader('Access-Control-Expose-Headers', $corsExposeHeaders);
            $response->addHeader('Accept-Patch', 'text/n3');

            $pubsub = getenv("PUBSUB_URL");
            if ($pubsub) {
                $response->addHeader('updates-via', $pubsub);
            }
            $linkHeaders = '</.well-known/solid>; rel="http://www.w3.org/ns/solid#storageDescription"';
            $existingHeaders = $response->getHeaders();
            if (isset($existingHeaders['Link'])) { // careful - this dictionary key is case sensitive here
                $linkHeaders .= ', ' . $existingHeaders['Link'];
            }
            $response->addHeader('Link', $linkHeaders);

            /**
             * Please note that the Link header with rel='acl' and the WAC-Allow
             * header have already been added by pdsinterop/solid-auth, and Link
             * headers with rel='type' by pdsinterop/php-solid-crud.
             *
             * @see https://github.com/pdsinterop/php-solid-auth/blob/v0.10.1/src/WAC.php#L39-L40
             * @see https://github.com/pdsinterop/php-solid-crud/blob/v0.7.1/src/Server.php#L679-L683
             */
            return $response;
        }
    }
