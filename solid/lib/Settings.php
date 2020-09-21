<?php

namespace OCA\Solid;

use OCA\Solid\ServerConfig;
use OcP\AppFramework\Http\TemplateResponse;
use OCP\Settings\ISettings;

class Settings implements ISettings {
	
	private $config;

	public function __construct(Serverconfig $config) {
		$this->config = $config;
	}

	public function getForm() {
		$response = new TemplateResponse('solid', 'admin');
		$response->setParams([
			'privateKey' => $this->config->getPrivateKey(),
			'publicKey' => $this->config->getPublicKey()
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