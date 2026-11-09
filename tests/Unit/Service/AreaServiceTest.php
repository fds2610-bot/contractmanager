<?php

declare(strict_types=1);

namespace OCA\NcVertragswerk\Tests\Unit\Service;

use OCA\NcVertragswerk\Db\Area;
use OCA\NcVertragswerk\Db\AreaMapper;
use OCA\NcVertragswerk\Service\AreaService;
use OCP\IConfig;
use PHPUnit\Framework\TestCase;

class AreaServiceTest extends TestCase {

	private function area(int $id, string $name): Area {
		$area = new Area();
		$area->setId($id);
		$area->setName($name);
		return $area;
	}

	public function testSeedCreatesDefaultsAndAssignsPersonal(): void {
		$mapper = $this->createMock(AreaMapper::class);
		$config = $this->createMock(IConfig::class);
		$config->method('getAppValue')->willReturn('');
		$mapper->method('findAll')->willReturn([]);
		$inserted = [];
		$mapper->expects($this->exactly(4))->method('insert')
			->willReturnCallback(function (Area $a) use (&$inserted) {
				$inserted[] = $a->getName();
				return $a;
			});
		$mapper->method('findByName')->with('persönlich')->willReturn($this->area(7, 'persönlich'));
		$mapper->expects($this->once())->method('assignToUnassigned')->with(7);
		$config->expects($this->once())->method('setAppValue')->with('nc_vertragswerk', 'areas_seeded', $this->anything());

		$this->assertTrue((new AreaService($mapper, $config))->seedDefaults());
		$this->assertSame(['persönlich', 'Wohnen', 'Fahrzeuge', 'Handy'], $inserted);
	}

	public function testSeedRunsOnlyOnce(): void {
		$mapper = $this->createMock(AreaMapper::class);
		$config = $this->createMock(IConfig::class);
		$config->method('getAppValue')->willReturn('2026-11-09T00:00:00+00:00');
		$mapper->expects($this->never())->method('insert');
		$mapper->expects($this->never())->method('assignToUnassigned');

		$this->assertFalse((new AreaService($mapper, $config))->seedDefaults());
	}
}
