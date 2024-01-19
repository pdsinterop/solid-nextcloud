<?php

namespace OCA\Solid\Notifications;

use Pdsinterop\Solid\SolidNotifications\SolidNotificationsInterface;

class SolidNotifications implements SolidNotificationsInterface {
	private $notifications;
	public function __construct() {

		$notifications = [
			new SolidWebhook(),
		];

		$pubsub = getenv('PUBSUB_URL');

		if ($pubsub) {
			$notifications[] = new SolidPubSub($pubsub);
		}

		$this->notifications = $notifications;
	}

	public function send($path, $type) {
		foreach ($this->notifications as $notification) {
			$notification->send($path, $type);
		}
	}
}
