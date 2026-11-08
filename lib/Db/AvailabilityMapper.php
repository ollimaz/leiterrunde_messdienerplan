<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\LeiterrundeMessdienerplan\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/** @extends QBMapper<Availability> */
class AvailabilityMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'lr_md_avail', Availability::class);
	}

	public function find(int $id): Availability {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
		return $this->findEntity($qb);
	}

	/** @return Availability[] */
	public function findForServices(array $serviceIds): array {
		if ($serviceIds === []) {
			return [];
		}
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->in('service_id', $qb->createNamedParameter($serviceIds, IQueryBuilder::PARAM_INT_ARRAY)))
			->setMaxResults(10000);
		return $this->findEntities($qb);
	}

	/** @return Availability[] */
	public function findForPerson(int $personId, array $serviceIds): array {
		if ($serviceIds === []) {
			return [];
		}
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->eq('person_id', $qb->createNamedParameter($personId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->in('service_id', $qb->createNamedParameter($serviceIds, IQueryBuilder::PARAM_INT_ARRAY)))
			->setMaxResults(2000);
		return $this->findEntities($qb);
	}

	public function findOne(int $serviceId, int $personId): ?Availability {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->eq('service_id', $qb->createNamedParameter($serviceId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('person_id', $qb->createNamedParameter($personId, IQueryBuilder::PARAM_INT)))
			->setMaxResults(1);
		try {
			return $this->findEntity($qb);
		} catch (DoesNotExistException) {
			return null;
		}
	}

	public function upsert(int $serviceId, int $personId, string $state, ?string $uid): Availability {
		$a = $this->findOne($serviceId, $personId) ?? new Availability();
		$a->setServiceId($serviceId);
		$a->setPersonId($personId);
		$a->setState($state);
		$a->setUpdatedBy($uid);
		$a->setUpdatedAt(time());
		return $a->getId() === null ? $this->insert($a) : $this->update($a);
	}

	public function deleteForService(int $serviceId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('service_id', $qb->createNamedParameter($serviceId, IQueryBuilder::PARAM_INT)));
		$qb->executeStatement();
	}
}
