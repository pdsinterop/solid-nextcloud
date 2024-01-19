<?php

namespace OCA\Solid;

use OcP\AppFramework\Http\TemplateResponse;
use OCP\Settings\ISettings;

class Settings implements ISettings {
	private $config;

	public function __construct(ServerConfig $config) {
		$this->config = $config;
	}

	public function getForm() {
		$response = new TemplateResponse('solid', 'admin');
		$response->setParams([
			'privateKey' => $this->config->getPrivateKey(),
			'encryptionKey' => $this->config->getEncryptionKey()
		]);
		return $response;
	}

	public function getSection() {
		return 'security';
	}

	public function getPriority() {
		return 50;
	}
}
