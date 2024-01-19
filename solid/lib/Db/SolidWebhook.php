<?php

namespace OCA\Solid\Db;

use JsonSerializable;
use OCP\AppFramework\Db\Entity;

class SolidWebhook extends Entity implements JsonSerializable {
	public $id;
	public $topic;
	public $webId;
	public $target;

	public function jsonSerialize(): array {
		return [
			'id' => $this->id,
			'webId' => $this->webId,
			'topic' => $this->topic,
			'target' => $this->target
		];
	}
}
