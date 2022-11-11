<?php
    namespace OCA\Solid\Notifications;
    
    use OCA\Solid\Service\SolidWebhookService;
    use Pdsinterop\Solid\SolidNotifications\SolidNotificationsInterface;
    
    class SolidWebhook implements SolidNotificationsInterface
    {
        public function __construct() {
            $app = new \OCP\AppFramework\App('solid');
            $this->webhookService = $app->getContainer()->get(SolidWebhookService::class);
        }

        public function send($topic, $type) {
            $webhooks = $this->getWebhooks($topic);
            foreach ($webhooks as $webhook) {
                try {
                    $this->postUpdate($webhook->{'target'}, $topic, $type);
                } catch(\Exception $e) {
                    // FIXME: add retry code here?
                }
            }
        }
        private function getWebhooks($topic) {
            $urls = $this->webhookService->findByTopic($topic);
            return $urls;
        }
        private function postUpdate($webhookUrl, $topic, $type) {
            try {
                $postData = $this->createUpdatePayload($topic, $type);
                $opts = array(
                    'http' => array(
                        'method'  => 'POST',
                        'header'  => 'Content-Type: application/ld+json',
                        'content' => $postData
                    ),
                    'ssl' => array(
                        'verify_peer' => false, // FIXME: Do we need to be more strict here?
                        'verify_peer_name' => false
                    )
                );
                $context  = stream_context_create($opts);
                $result = file_get_contents($webhookUrl, false, $context);
            } catch (\Exception $exception) {
                throw new Exception('Could not write to webhook server', 502, $exception);
            }
        }
        private function createUpdatePayload($topic, $type) {
            //$updateId = "urn:uuid:<uuid>";
            //$actor = "<WebID>";
            $object = [
                "id" => $topic,
                "type" => [
                    "http://www.w3.org/ns/ldp#Resource"
                ]
            ];
            $state = "1234-5678-90ab-cdef-12345678";
            $published = date(DATE_ISO8601);
            $payload = [
                "@context" => [
                    "https://www.w3.org/ns/activitystreams",
                    "https://www.w3.org/ns/solid/notification/v1"
                 ],
            //    "id":$updateId,
            //    "actor" : [$actor],
                "type" => [$type],
                "object" => $object,
            //    "state" : $state
                "published" => $published
            ];
            
            return json_encode($payload);
        }
    }
