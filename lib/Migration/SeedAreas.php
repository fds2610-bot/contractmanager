<?php

declare(strict_types=1);

namespace OCA\NcVertragswerk\Migration;

use OCA\NcVertragswerk\Service\AreaService;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;

/**
 * Default „Kategorien" (persönlich, Wohnen, Fahrzeuge, Handy) and
 * „persönlich" for all existing contracts (fds2610/contractmanager#7).
 *
 * Registered as post-migration (update) and install (fresh install, where
 * migrations are schema-only). Runs once per installation.
 */
class SeedAreas implements IRepairStep {

	public function __construct(
		private readonly AreaService $areaService,
	) {
	}

	public function getName(): string {
		return 'Standard-Kategorien anlegen';
	}

	public function run(IOutput $output): void {
		if ($this->areaService->seedDefaults()) {
			$output->info('Standard-Kategorien angelegt, bestehende Verträge auf „persönlich“ gesetzt');
		}
	}
}
