<?php
    namespace OCA\Solid\Notifications;

    use WebSocket\Client;
    use Pdsinterop\Solid\SolidNotificationsInterface;

    class SolidPubSub implements SolidNotificationsInterface
    {
        private $pubsub;
        public function __construct($pubsubUrl) {
            $this->pubsub = $pubsubUrl;
        }

        public function send($path, $type) {
            $pubsub = str_replace(["https://", "http://"], "ws://", $this->pubsub);

            $client = new Client($pubsub, array(
                'headers' => array(
                    'Sec-WebSocket-Protocol' => 'solid-0.1'
                )
            ));

            try {
                $client->send("pub $path\n");
            } catch (\WebSocket\Exception $exception) {
                throw new Exception('Could not write to pubsub server', 502, $exception);
            }
        }
    }
