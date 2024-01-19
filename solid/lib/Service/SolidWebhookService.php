<?php

namespace OCA\Solid\Service;

use Exception;
use OCA\Solid\Db\SolidWebhook;
use OCA\Solid\Db\SolidWebhookMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;

class SolidWebhookService {
	/** @var SolidWebhookMapper */
	private $mapper;

	public function __construct(SolidWebhookMapper $mapper) {
		$this->mapper = $mapper;
	}

	public function findAll(string $webId): array {
		return $this->mapper->findAll($webId);
	}

	public function findByTopic(string $topic): array {
		return $this->mapper->findByTopic($topic);
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

	public function find($webId, $topic) {
		try {
			return $this->mapper->find($webId, $topic);
		} catch (Exception $e) {
			$this->handleException($e);
		}
	}

	public function create($webId, $topic, $target) {
		$webhook = new SolidWebhook();
		$webhook->setWebId($webId);
		$webhook->setTopic($topic);
		$webhook->setTarget($target);
		return $this->mapper->insert($webhook);
	}

	public function delete($webId, $topic) {
		try {
			$webhook = $this->mapper->find($webId, $topic);
			$this->mapper->delete($webhook);
			return $webhook;
		} catch (Exception $e) {
			$this->handleException($e);
		}
	}
}
