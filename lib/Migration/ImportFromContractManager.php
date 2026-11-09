<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 fds2610
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\NcVertragswerk\Migration;

use OCA\NcVertragswerk\AppInfo\Application;
use OCP\App\IAppManager;
use OCP\BackgroundJob\IJobList;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;

/**
 * Umstieg von der App-ID `contractmanager` (cpcMomentum, bis 1.12.x) auf
 * `nc_vertragswerk` (ab 2.0.0).
 *
 * Die Tabellen `oc_contractmgr_*` sind dieselben; die Migrationen pruefen auf
 * vorhandene Tabellen und Spalten, die Vertraege sind also sofort da. An der
 * App-ID haengen aber die App-Einstellungen (Rollen, Erinnerungen, KI, …) und
 * die Benutzereinstellungen (Filter, Sortierung, Talk, Backup, …). Die werden
 * hier einmalig kopiert.
 *
 * Laeuft als `install`-Schritt, also nur bei der Erstinstallation von
 * nc_vertragswerk. Vorhandene Werte von nc_vertragswerk werden nie
 * ueberschrieben; die alten Werte bleiben unangetastet.
 *
 * Beide Apps auf denselben Tabellen wuerden Erinnerungen doppelt verschicken.
 * Deshalb wird `contractmanager` deaktiviert, falls sie noch laeuft, und ihre
 * Hintergrundjobs werden aus der Job-Liste genommen.
 */
class ImportFromContractManager implements IRepairStep {

	public const OLD_APP_ID = 'contractmanager';
	public const KEY_DONE = 'imported_from_contractmanager';

	private const OLD_JOBS = [
		'OCA\\ContractManager\\BackgroundJob\\ReminderJob',
		'OCA\\ContractManager\\BackgroundJob\\TrashCleanupJob',
		'OCA\\ContractManager\\BackgroundJob\\StatusUpdateJob',
		'OCA\\ContractManager\\BackgroundJob\\AutoBackupJob',
	];

	/** Verwaltet Nextcloud selbst je App. */
	private const SKIP_APP_KEYS = ['enabled', 'installed_version', 'types'];

	public function __construct(
		private readonly IConfig $config,
		private readonly IDBConnection $db,
		private readonly IAppManager $appManager,
		private readonly IJobList $jobList,
	) {
	}

	public function getName(): string {
		return 'Einstellungen von „contractmanager“ übernehmen';
	}

	public function run(IOutput $output): void {
		$this->retireOldApp($output);

		if ($this->config->getAppValue(Application::APP_ID, self::KEY_DONE, '') !== '') {
			return;
		}

		$appKeys = 0;
		foreach ($this->config->getAppKeys(self::OLD_APP_ID) as $key) {
			if (in_array($key, self::SKIP_APP_KEYS, true)) {
				continue;
			}
			if ($this->config->getAppValue(Application::APP_ID, $key, '') !== '') {
				continue;
			}
			$this->config->setAppValue(Application::APP_ID, $key, $this->config->getAppValue(self::OLD_APP_ID, $key, ''));
			$appKeys++;
		}

		$userKeys = 0;
		$qb = $this->db->getQueryBuilder();
		$qb->select('userid', 'configkey', 'configvalue')
			->from('preferences')
			->where($qb->expr()->eq('appid', $qb->createNamedParameter(self::OLD_APP_ID)));
		$result = $qb->executeQuery();
		while ($row = $result->fetch()) {
			$uid = (string)$row['userid'];
			$key = (string)$row['configkey'];
			if ($this->config->getUserValue($uid, Application::APP_ID, $key, '') !== '') {
				continue;
			}
			$this->config->setUserValue($uid, Application::APP_ID, $key, (string)$row['configvalue']);
			$userKeys++;
		}
		$result->closeCursor();

		$this->config->setAppValue(Application::APP_ID, self::KEY_DONE, date('c'));
		if ($appKeys > 0 || $userKeys > 0) {
			$output->info(sprintf('%d App- und %d Benutzereinstellungen von contractmanager übernommen', $appKeys, $userKeys));
		}
	}

	private function retireOldApp(IOutput $output): void {
		if ($this->appManager->isEnabledForAnyone(self::OLD_APP_ID)) {
			$this->appManager->disableApp(self::OLD_APP_ID);
			$output->info('App contractmanager deaktiviert (Daten bleiben erhalten)');
		}
		foreach (self::OLD_JOBS as $job) {
			$this->jobList->remove($job);
		}
	}
}
