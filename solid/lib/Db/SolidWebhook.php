<?php

namespace OCA\Solid\Db;

use JsonSerializable;

use OCP\AppFramework\Db\Entity;

class SolidWebhook extends Entity implements JsonSerializable {
	public $id;
	protected $path;
	protected $webId;
	protected $url;
	protected $expiry;

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
