<?php

namespace OCA\Solid\Controller;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;

class SolidWebsocketController extends Controller
{
    /**
     * @PublicPage
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function register(): DataResponse
    {
        $pubsub = getenv("PUBSUB_URL") ?: "http://pubsub:8080";
        return new DataResponse([
            "@context" => "https://www.w3.org/ns/solid/notification/v1",
            "type" => "WebSocketSubscription2021",
            "source" => $pubsub . "/?type=WebSocketSubscription2021"
        ]);
    }
}