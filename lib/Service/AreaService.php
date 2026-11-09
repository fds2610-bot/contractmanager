<?php

declare(strict_types=1);

namespace OCA\NcVertragswerk\Service;

use OCA\NcVertragswerk\AppInfo\Application;
use OCA\NcVertragswerk\Db\Area;
use OCA\NcVertragswerk\Db\AreaMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\IConfig;

/**
 * „Kategorien" (fds2610/contractmanager#7): global list, managed by admins.
 */
class AreaService {

	public const DEFAULT_AREAS = ['persönlich', 'Wohnen', 'Fahrzeuge', 'Handy'];
	/** Preselected for new contracts and given to existing ones on the update. */
	public const DEFAULT_AREA = 'persönlich';
	private const KEY_SEEDED = 'areas_seeded';

	public function __construct(
		private AreaMapper $mapper,
		private IConfig $config,
	) {
	}

	/**
	 * @return Area[]
	 */
	public function findAll(): array {
		return $this->mapper->findAll();
	}

	/**
	 * Areas with the number of assigned contracts (for the delete question).
	 *
	 * @return list<array{id: int, name: string, sortOrder: int, contractCount: int}>
	 */
	public function findAllWithCounts(): array {
		$counts = $this->mapper->countContracts();
		return array_map(
			fn (Area $area): array => $area->jsonSerialize() + ['contractCount' => $counts[$area->getId()] ?? 0],
			$this->mapper->findAll(),
		);
	}

	/**
	 * @throws NotFoundException
	 */
	public function find(int $id): Area {
		try {
			return $this->mapper->find($id);
		} catch (DoesNotExistException|MultipleObjectsReturnedException $e) {
			throw new NotFoundException($e->getMessage());
		}
	}

	/**
	 * @throws ValidationException
	 */
	public function create(string $name): Area {
		$area = new Area();
		$area->setName($this->validName($name));
		$area->setSortOrder($this->mapper->getMaxSortOrder() + 1);
		return $this->mapper->insert($area);
	}

	/**
	 * Renaming changes the area of all assigned contracts at once — they only
	 * keep its ID.
	 *
	 * @throws NotFoundException
	 * @throws ValidationException
	 */
	public function update(int $id, string $name, ?int $sortOrder = null): Area {
		$area = $this->find($id);
		$area->setName($this->validName($name));
		if ($sortOrder !== null) {
			$area->setSortOrder($sortOrder);
		}
		return $this->mapper->update($area);
	}

	/**
	 * Delete an area; its contracts end up without one.
	 *
	 * @throws NotFoundException
	 */
	public function delete(int $id): Area {
		$area = $this->find($id);
		$this->mapper->unassign($id);
		return $this->mapper->delete($area);
	}

	/**
	 * Create the default areas and assign „persönlich" to all existing
	 * contracts — once per installation, so deleting all areas later does
	 * not bring them back.
	 *
	 * @return bool whether anything was done
	 */
	public function seedDefaults(): bool {
		if ($this->config->getAppValue(Application::APP_ID, self::KEY_SEEDED, '') !== '') {
			return false;
		}
		if ($this->mapper->findAll() === []) {
			foreach (self::DEFAULT_AREAS as $index => $name) {
				$area = new Area();
				$area->setName($name);
				$area->setSortOrder($index + 1);
				$this->mapper->insert($area);
			}
		}
		$default = $this->mapper->findByName(self::DEFAULT_AREA);
		if ($default !== null) {
			$this->mapper->assignToUnassigned($default->getId());
		}
		$this->config->setAppValue(Application::APP_ID, self::KEY_SEEDED, date('c'));
		return true;
	}

	/**
	 * @throws ValidationException
	 */
	private function validName(string $name): string {
		$name = trim($name);
		if ($name === '' || mb_strlen($name) > 255) {
			throw new ValidationException(['name' => 'Ungültiger Name']);
		}
		return $name;
	}
}
