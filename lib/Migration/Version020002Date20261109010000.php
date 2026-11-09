<?php

declare(strict_types=1);

namespace OCA\NcVertragswerk\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * fds2610/contractmanager#7: new field „Kategorie" (internally "area", because
 * the old "category" is shown as „Vertragsart" now) with an admin-managed
 * list, and the payment method („wird abgebucht" / „überweisen").
 *
 * The default areas are created by the repair step SeedAreas, which also
 * runs on a fresh install (migrations there are schema-only).
 */
class Version020002Date20261109010000 extends SimpleMigrationStep {

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('contractmgr_areas')) {
			$table = $schema->createTable('contractmgr_areas');
			$table->addColumn('id', Types::BIGINT, [
				'autoincrement' => true,
				'notnull' => true,
			]);
			$table->addColumn('name', Types::STRING, [
				'notnull' => true,
				'length' => 255,
			]);
			$table->addColumn('sort_order', Types::INTEGER, [
				'notnull' => true,
				'default' => 0,
			]);
			$table->setPrimaryKey(['id']);
		}

		if ($schema->hasTable('contractmgr_contracts')) {
			$table = $schema->getTable('contractmgr_contracts');
			if (!$table->hasColumn('area_id')) {
				$table->addColumn('area_id', Types::BIGINT, [
					'notnull' => false,
					'default' => null,
				]);
			}
			if (!$table->hasColumn('payment_method')) {
				$table->addColumn('payment_method', Types::STRING, [
					'notnull' => false,
					'length' => 20,
					'default' => null,
				]);
			}
		}

		return $schema;
	}
}
