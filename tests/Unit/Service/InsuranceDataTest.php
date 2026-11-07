<?php

declare(strict_types=1);

namespace OCA\ContractManager\Tests\Unit\Service;

use OCA\ContractManager\Db\Contract;
use OCA\ContractManager\Service\InsuranceData;
use PHPUnit\Framework\TestCase;

class InsuranceDataTest extends TestCase {

	public function testPersonsAreNormalized(): void {
		$json = InsuranceData::personsToJson([
			['type' => 'policyholder', 'name' => '  Max Muster ', 'userId' => null],
			['type' => 'beneficiary', 'name' => 'Anna', 'userId' => 'anna'],
			['type' => 'bogus', 'name' => 'Fremd'],
			['type' => 'payer', 'name' => '', 'userId' => 'bob'],
			['type' => 'other', 'name' => '   '],
			'garbage',
		]);

		$this->assertSame([
			['type' => 'policyholder', 'name' => 'Max Muster', 'userId' => null],
			['type' => 'beneficiary', 'name' => 'Anna', 'userId' => 'anna'],
			['type' => 'other', 'name' => 'Fremd', 'userId' => null],
			['type' => 'payer', 'name' => 'bob', 'userId' => 'bob'],
		], json_decode($json, true));
	}

	public function testEmptyListsBecomeNull(): void {
		$this->assertNull(InsuranceData::personsToJson(null));
		$this->assertNull(InsuranceData::personsToJson([]));
		$this->assertNull(InsuranceData::personsToJson([['name' => '']]));
		$this->assertNull(InsuranceData::benefitsToJson(null));
		$this->assertNull(InsuranceData::benefitsToJson(['', '  ']));
		$this->assertNull(InsuranceData::benefitsToJson('kein Array'));
	}

	public function testBenefitsAreTrimmedAndLimited(): void {
		$benefits = array_fill(0, 60, ' Glasbruch ');
		$benefits[] = ['nested'];
		$decoded = json_decode(InsuranceData::benefitsToJson($benefits), true);

		$this->assertCount(InsuranceData::MAX_ENTRIES, $decoded);
		$this->assertSame('Glasbruch', $decoded[0]);
	}

	public function testDecodeIsRobust(): void {
		$this->assertSame([], InsuranceData::decode(null));
		$this->assertSame([], InsuranceData::decode(''));
		$this->assertSame([], InsuranceData::decode('{kaputt'));
		$this->assertSame(['a'], InsuranceData::decode('["a"]'));
	}

	public function testContractNumber(): void {
		$this->assertNull(InsuranceData::normalizeContractNumber('  '));
		$this->assertNull(InsuranceData::normalizeContractNumber(null));
		$this->assertSame('VS-123', InsuranceData::normalizeContractNumber(' VS-123 '));
	}

	public function testContractSerializesDecodedLists(): void {
		$contract = new Contract();
		$contract->setContractNumber('VS-1');
		$contract->setInsurancePersons(InsuranceData::personsToJson([['type' => 'policyholder', 'name' => 'Max']]));

		$data = $contract->jsonSerialize();
		$this->assertSame('VS-1', $data['contractNumber']);
		$this->assertSame([['type' => 'policyholder', 'name' => 'Max', 'userId' => null]], $data['insurancePersons']);
		$this->assertSame([], $data['insuranceBenefits']);
	}
}
