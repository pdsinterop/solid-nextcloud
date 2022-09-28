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

        public function send($path, $type) {
            $webhooks = $this->getWebhooks($path);
            foreach ($webhooks as $webhook) {
                try {
                    $this->postUpdate($webhook['url'], $path, $type);
                } catch(\Exception $e) {
                    // FIXME: add retry code here?
                }
            }
        }
        private function getWebhooks($path) {
            $urls = $this->webhookService->findByPath($path);
            return $urls;
        }
        private function postUpdate($webhookUrl, $path, $type) {
            try {
                $postData = $this->createUpdatePayload($path, $type);
                $opts = array('http' =>
                    array(
                        'method'  => 'POST',
                        'header'  => 'Content-Type: application/ld+json',
                        'content' => $postData
                    )
                );
                $context  = stream_context_create($opts);
                $result = file_get_contents($webhookUrl, false, $context);
            } catch (\Exception $exception) {
                throw new Exception('Could not write to webhook server', 502, $exception);
            }
        }
        private function createUpdatePayload($path, $type) {
            //$updateId = "urn:uuid:<uuid>";
            //$actor = "<WebID>";
            $object = [
                "id" => $path,
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
