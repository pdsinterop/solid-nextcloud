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
        // @FIXME: If there is no PUBSUB_URL what should be returned? 404? 500? Yo'mamma? 2023/01/27/BMP
        $pubsub = getenv("PUBSUB_URL") ?: "http://pubsub:8080";

        return new DataResponse([
            "@context" => "https://www.w3.org/ns/solid/notification/v1",
            "type" => "WebSocketSubscription2021",
            "source" => $pubsub . "/?type=WebSocketSubscription2021"
        ]);
    }
}
