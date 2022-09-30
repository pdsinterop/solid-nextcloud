<?php

namespace OCA\Solid\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\Entity;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class SolidWebhookMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'solid_webhooks', SolidWebhook::class);
	}

	/**
	 * @param string $webId
	 * @param string $topic
	 * @return Entity|SolidWebhook
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 * @throws DoesNotExistException
	 */
	public function find(string $webId, string $topic): SolidWebhook {
		/* @var $qb IQueryBuilder */
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from('solid_webhooks')
			->where($qb->expr()->eq('web_id', $qb->createNamedParameter($webId)))
			->andWhere($qb->expr()->eq('topic', $qb->createNamedParameter($topic)));
		return $this->findEntity($qb);
	}

	/**
	 * @param string $webId
	 * @return array
	 */
	public function findAll(string $webId): array {
		/* @var $qb IQueryBuilder */
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from('solid_webhooks')
			->where($qb->expr()->eq('web_id', $qb->createNamedParameter($webId)));
		return $this->findEntities($qb);
	}

	/**
	 * @param string $topic
	 * @return array
	 */
	public function findByTopic(string $topic): array {
		/* @var $qb IQueryBuilder */
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from('solid_webhooks')
			->where($qb->expr()->eq('topic', $qb->createNamedParameter($topic)));
		return $this->findEntities($qb);
	}
}
