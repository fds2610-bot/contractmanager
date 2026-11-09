<?php

declare(strict_types=1);

namespace OCA\NcVertragswerk\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Contract number plus the insurance details (persons and benefits). The two
 * lists are stored as JSON, they only make sense together with the contract.
 */
class Version010016Date20261107000000 extends SimpleMigrationStep {

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if ($schema->hasTable('contractmgr_contracts')) {
			$table = $schema->getTable('contractmgr_contracts');
			if (!$table->hasColumn('contract_number')) {
				$table->addColumn('contract_number', Types::STRING, [
					'notnull' => false,
					'length' => 255,
					'default' => null,
				]);
			}
			if (!$table->hasColumn('insurance_persons')) {
				$table->addColumn('insurance_persons', Types::TEXT, [
					'notnull' => false,
					'default' => null,
				]);
			}
			if (!$table->hasColumn('insurance_benefits')) {
				$table->addColumn('insurance_benefits', Types::TEXT, [
					'notnull' => false,
					'default' => null,
				]);
			}
		}

		return $schema;
	}
}
