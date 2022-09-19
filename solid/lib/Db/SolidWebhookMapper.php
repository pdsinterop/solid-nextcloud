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
	 * @param string $path
	 * @return Entity|SolidWebhook
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 * @throws DoesNotExistException
	 */
	public function find(string $webId, string $path): SolidWebhook {
		/* @var $qb IQueryBuilder */
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from('solid_webhooks')
			->where($qb->expr()->eq('web_id', $qb->createNamedParameter($webId)))
			->andWhere($qb->expr()->eq('path', $qb->createNamedParameter($path)));
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
	 * @param string $path
	 * @return array
	 */
	public function findByPath(string $path): array {
		/* @var $qb IQueryBuilder */
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from('solid_webhooks')
			->where($qb->expr()->eq('path', $qb->createNamedParameter($path)));
		return $this->findEntities($qb);
	}
}
