<?php
    namespace OCA\Solid\Notifications;

    use WebSocket\Client;
    use Pdsinterop\Solid\SolidNotifications\SolidNotificationsInterface;

    class SolidPubSub implements SolidNotificationsInterface
    {
        private $pubsub;
        public function __construct($pubsubUrl) {
            $this->pubsub = $pubsubUrl;
        }

        public function send($path, $type) {
            $pubsub = str_replace(["https://", "http://"], "wss://", $this->pubsub);

            $client = new Client($pubsub);
            $client->setContext(['ssl' => [
                'verify_peer'       => false, // if false, accept SSL handshake without client certificate
                'verify_peer_name'  => false,
                'allow_self_signed' => true,
            ]]);
            $client->addHeader("Sec-WebSocket-Protocol", "solid-0.1");
            try {
                $client->text("pub $path\n");
            } catch (\WebSocket\Exception $exception) {
                throw new \Exception('Could not write to pubsub server', 502, $exception);
            }
        }
    }
