<?php
    namespace OCA\Solid\Notifications;

    use OCA\Solid\Notifications\SolidPubSub;
    use OCA\Solid\Notifications\SolidWebhook;
    use Pdsinterop\Solid\SolidNotifications\SolidNotificationsInterface;

    class SolidNotifications implements SolidNotificationsInterface
    {
        private $notifications;
        public function __construct() {
            $pubsub = getenv("PUBSUB_URL") ?: "http://pubsub:8080";
            $this->notifications = [
            	new SolidWebhook(),
            	new SolidPubSub($pubsub)
	        ];
        }

        public function send($path, $type) {
            foreach ($this->notifications as $notification) {
                $notification->send($path, $type);
	        }
	    }
    }
