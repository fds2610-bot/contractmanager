<?php

declare(strict_types=1);

namespace OCA\NcVertragswerk\Db;

use JsonSerializable;
use OCP\AppFramework\Db\Entity;

/**
 * „Kategorie" of a contract (fds2610/contractmanager#7), e.g. „Wohnen" or
 * „Fahrzeuge". Not to be confused with Category, which the UI calls
 * „Vertragsart".
 *
 * @method int getId()
 * @method void setId(int $id)
 * @method string getName()
 * @method void setName(string $name)
 * @method int getSortOrder()
 * @method void setSortOrder(int $sortOrder)
 */
class Area extends Entity implements JsonSerializable {

	protected string $name = '';
	protected int $sortOrder = 0;

	public function __construct() {
		$this->addType('id', 'integer');
		$this->addType('sortOrder', 'integer');
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->id,
			'name' => $this->name,
			'sortOrder' => $this->sortOrder,
		];
	}
}
