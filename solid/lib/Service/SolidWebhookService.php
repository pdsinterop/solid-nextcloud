<?php

namespace OCA\Solid\Service;

use Exception;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;

use OCA\Solid\Db\SolidWebhook;
use OCA\Solid\Db\SolidWebhookMapper;

class SolidWebhookService {
	/** @var SolidWebhookMapper */
	private $mapper;

	public function __construct(SolidWebhookMapper $mapper) {
		$this->mapper = $mapper;
	}

	public function findAll(string $webId): array {
		return $this->mapper->findAll($webId);
	}

	public function findByPath(string $path): array {
		return $this->mapper->findByPath($path);
	}

	private function handleException(Exception $e): void {
		if (
			$e instanceof DoesNotExistException ||
			$e instanceof MultipleObjectsReturnedException
		) {
			throw new SolidWebhookNotFound($e->getMessage());
		} else {
			throw $e;
		}
	}

	public function find($webId, $path) {
		try {
			return $this->mapper->find($webId, $path);
		} catch (Exception $e) {
			$this->handleException($e);
		}
	}

	public function create($webId, $path, $url, $expiry) {
		$webhook = new SolidWebhook();
		$webhook->setWebId($webId);
		$webhook->setPath($path);
		$webHook->setUrl($url);
		$webHook->setExpiry($expiry);
		return $this->mapper->insert($note);
	}

	public function delete($webId, $path) {
		try {
			$webhook = $this->mapper->find($webId, $path);
			$this->mapper->delete($webhook);
			return $webhook;
		} catch (Exception $e) {
			$this->handleException($e);
		}
	}
}
