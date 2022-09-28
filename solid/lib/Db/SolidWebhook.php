<?php

namespace OCA\Solid\Db;

use JsonSerializable;

use OCP\AppFramework\Db\Entity;

class SolidWebhook extends Entity implements JsonSerializable {
	public $id;
	public $path;
	public $webId;
	public $url;
	public $expiry;

	public function jsonSerialize(): array {
		return [
			'id' => $this->id,
			'webId' => $this->webId,
			'path' => $this->path,
			'url' => $this->url,
			'expiry' => $this->expiry
		];
	}
}
