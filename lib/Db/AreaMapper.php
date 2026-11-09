<?php

declare(strict_types=1);

namespace OCA\NcVertragswerk\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @template-extends QBMapper<Area>
 */
class AreaMapper extends QBMapper {

	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'contractmgr_areas', Area::class);
	}

	/**
	 * @return Area[]
	 */
	public function findAll(): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->orderBy('sort_order', 'ASC')
			->addOrderBy('name', 'ASC');
		return $this->findEntities($qb);
	}

	/**
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 */
	public function find(int $id): Area {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
		return $this->findEntity($qb);
	}

	/**
	 * Case-insensitive, so an import finds „Wohnen" also as „wohnen".
	 */
	public function findByName(string $name): ?Area {
		foreach ($this->findAll() as $area) {
			if (mb_strtolower($area->getName()) === mb_strtolower(trim($name))) {
				return $area;
			}
		}
		return null;
	}

	public function getMaxSortOrder(): int {
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->func()->max('sort_order'))
			->from($this->getTableName());
		$result = $qb->executeQuery();
		$max = $result->fetchOne();
		$result->closeCursor();
		return (int)($max ?? 0);
	}

	/**
	 * Number of contracts per area, including trashed and archived ones —
	 * all of them lose the area when it is deleted.
	 *
	 * @return array<int, int> area ID => count
	 */
	public function countContracts(): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('area_id')
			->selectAlias($qb->func()->count('id'), 'cnt')
			->from('contractmgr_contracts')
			->where($qb->expr()->isNotNull('area_id'))
			->groupBy('area_id');
		$result = $qb->executeQuery();
		$counts = [];
		while ($row = $result->fetch()) {
			$counts[(int)$row['area_id']] = (int)$row['cnt'];
		}
		$result->closeCursor();
		return $counts;
	}

	/**
	 * Remove an area from all contracts (before deleting it).
	 */
	public function unassign(int $areaId): int {
		$qb = $this->db->getQueryBuilder();
		$qb->update('contractmgr_contracts')
			->set('area_id', $qb->createNamedParameter(null, IQueryBuilder::PARAM_NULL))
			->where($qb->expr()->eq('area_id', $qb->createNamedParameter($areaId, IQueryBuilder::PARAM_INT)));
		return $qb->executeStatement();
	}

	/**
	 * Give every contract without an area the given one (initial migration).
	 */
	public function assignToUnassigned(int $areaId): int {
		$qb = $this->db->getQueryBuilder();
		$qb->update('contractmgr_contracts')
			->set('area_id', $qb->createNamedParameter($areaId, IQueryBuilder::PARAM_INT))
			->where($qb->expr()->isNull('area_id'));
		return $qb->executeStatement();
	}
}
