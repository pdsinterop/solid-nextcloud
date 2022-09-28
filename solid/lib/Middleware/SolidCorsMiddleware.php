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
            $corsAllowedHeaders="*, allow, accept, authorization, content-type, dpop, slug";
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

            $pubsub = getenv("PUBSUB_URL") ?: "http://pubsub:8080";
            $response->addHeader('updates-via', $pubsub);

            return $response;
        }
    }
