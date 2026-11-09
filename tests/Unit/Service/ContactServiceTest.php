<?php

declare(strict_types=1);

namespace OCA\NcVertragswerk\Tests\Unit\Service;

use OCA\NcVertragswerk\Service\ContactService;
use OCP\App\IAppManager;
use OCP\Contacts\IManager;
use OCP\IAddressBook;
use OCP\IURLGenerator;
use PHPUnit\Framework\TestCase;

class ContactServiceTest extends TestCase {

	private IManager $contacts;
	private IAppManager $appManager;
	private IURLGenerator $urlGenerator;
	/** @var IAddressBook[] */
	private array $books = [];

	protected function setUp(): void {
		parent::setUp();
		$this->contacts = $this->createMock(IManager::class);
		$this->appManager = $this->createMock(IAppManager::class);
		$this->urlGenerator = $this->createMock(IURLGenerator::class);
		$this->contacts->method('getUserAddressBooks')->willReturnCallback(fn () => $this->books);
		$this->urlGenerator->method('linkToRoute')->willReturnCallback(
			fn (string $route, array $params) => '/apps/contacts/direct/contact/' . $params['contact'],
		);
	}

	private function service(): ContactService {
		return new ContactService($this->contacts, $this->appManager, $this->urlGenerator);
	}

	private function book(string $key, string $uri, bool $system = false): IAddressBook {
		$book = $this->createMock(IAddressBook::class);
		$book->method('getKey')->willReturn($key);
		$book->method('getUri')->willReturn($uri);
		$book->method('isSystemAddressBook')->willReturn($system);
		return $book;
	}

	public function testOnlySystemAddressBookMeansNotAvailable(): void {
		$this->books = [$this->book('1', 'system', true)];
		$this->contacts->expects($this->never())->method('search');

		$this->assertFalse($this->service()->isAvailable());
		$this->assertSame([], $this->service()->search('Stadtwerke'));
		$this->assertNull($this->service()->resolve('abc'));
	}

	public function testSearchMapsResultsAndSkipsSystemBookAndDuplicates(): void {
		$this->books = [$this->book('7', 'contacts')];
		$this->contacts->method('search')->with('stadt', ['FN', 'ORG', 'EMAIL'])->willReturn([
			['UID' => 'a', 'FN' => 'Stadtwerke Kiel', 'ORG' => 'Stadtwerke;Vertrieb', 'EMAIL' => ['info@sw.de', 'b@sw.de'], 'addressbook-key' => '7'],
			['UID' => 'a', 'FN' => 'Stadtwerke Kiel', 'addressbook-key' => '8'],
			['UID' => 'u1', 'FN' => 'Kollegin Stadt', 'isLocalSystemBook' => true],
			['UID' => 'b', 'FN' => '', 'ORG' => 'Stadtbad GmbH'],
			['FN' => 'ohne UID'],
		]);

		$this->assertSame([
			['uid' => 'a', 'name' => 'Stadtwerke Kiel', 'organization' => 'Stadtwerke', 'email' => 'info@sw.de'],
			['uid' => 'b', 'name' => 'Stadtbad GmbH', 'organization' => 'Stadtbad GmbH', 'email' => ''],
		], $this->service()->search(' stadt '));
	}

	public function testEmptyQueryDoesNotSearch(): void {
		$this->books = [$this->book('7', 'contacts')];
		$this->contacts->expects($this->never())->method('search');

		$this->assertSame([], $this->service()->search('  '));
	}

	public function testResolveReturnsCommunicationDataAndContactsLink(): void {
		$this->books = [$this->book('7', 'kunden-shared_by_alice')];
		$this->appManager->method('isEnabledForUser')->with('contacts')->willReturn(true);
		$this->contacts->method('search')
			->with('abc', ['UID'], $this->callback(fn (array $o) => $o['strict_search'] === true))
			->willReturn([[
				'UID' => 'abc', 'FN' => 'Stadtwerke Kiel', 'addressbook-key' => '7',
				'EMAIL' => ['info@sw.de'], 'TEL' => ['+49 431 1', '0800 112'],
				'ADR' => [';;Hafenstr. 1;Kiel;SH;24103;Deutschland', ';;;;;;'],
			]]);

		$this->assertSame([
			'uid' => 'abc',
			'name' => 'Stadtwerke Kiel',
			'organization' => '',
			'emails' => ['info@sw.de'],
			'phones' => ['+49 431 1', '0800 112'],
			'addresses' => ["Hafenstr. 1\n24103 Kiel\nSH\nDeutschland"],
			'contactsUrl' => '/apps/contacts/direct/contact/abc~kunden-shared_by_alice',
		], $this->service()->resolve('abc'));
	}

	public function testResolveWithoutContactsAppHasNoLink(): void {
		$this->books = [$this->book('7', 'contacts')];
		$this->appManager->method('isEnabledForUser')->willReturn(false);
		$this->contacts->method('search')->willReturn([['UID' => 'abc', 'FN' => 'X', 'addressbook-key' => '7']]);

		$this->assertNull($this->service()->resolve('abc')['contactsUrl']);
	}

	public function testResolveIgnoresOtherUidsAndUnknownBooks(): void {
		$this->books = [$this->book('7', 'contacts')];
		$this->contacts->method('search')->willReturn([
			['UID' => 'abcd', 'FN' => 'Teiltreffer', 'addressbook-key' => '7'],
			['UID' => 'abc', 'FN' => 'Systembuch', 'addressbook-key' => '7', 'isLocalSystemBook' => true],
			['UID' => 'abc', 'FN' => 'Fremdes Buch', 'addressbook-key' => '99'],
		]);

		$this->assertNull($this->service()->resolve('abc'));
	}

	private function writableBook(string $key, string $uri, string $name, int $permissions = 31): IAddressBook {
		$book = $this->book($key, $uri);
		$book->method('getDisplayName')->willReturn($name);
		$book->method('getPermissions')->willReturn($permissions);
		return $book;
	}

	public function testWritableAddressBooksPreselectByNameOrFirst(): void {
		$this->books = [
			$this->writableBook('1', 'contacts', 'Kontakte'),
			$this->writableBook('2', 'readonly-shared_by_x', 'Nur lesen', 1),
			$this->writableBook('3', 'vertraege', 'Verträge'),
			$this->book('4', 'system', true),
		];
		$this->assertSame([
			['key' => '1', 'name' => 'Kontakte', 'default' => false],
			['key' => '3', 'name' => 'Verträge', 'default' => true],
		], $this->service()->writableAddressBooks('verträge'));
		$this->assertTrue($this->service()->writableAddressBooks('gibt es nicht')[0]['default']);
		$this->assertTrue($this->service()->writableAddressBooks('')[0]['default']);
	}

	public function testCreateBuildsVcardPropertiesAndReturnsResolvedContact(): void {
		$book = $this->writableBook('7', 'contacts', 'Kontakte');
		$this->books = [$book];
		$book->expects($this->once())->method('createOrUpdate')
			->with($this->callback(function (array $p): bool {
				$this->assertSame('Anna Agent', $p['FN']);
				$this->assertSame([['value' => ['Agent', 'Anna', '', '', '']]], $p['N']);
				$this->assertSame([['value' => ['Rhion', 'Vertrieb']]], $p['ORG']);
				$this->assertSame([['value' => '0711 1', 'type' => 'WORK'], ['value' => '0170 2', 'type' => 'CELL']], $p['TEL']);
				$this->assertSame([['value' => 'a@rhion.de', 'type' => 'HOME']], $p['EMAIL']);
				$this->assertSame([['value' => ['', '', 'Weg 1', 'Ort', '', '12345', 'DE'], 'type' => 'WORK']], $p['ADR']);
				$this->assertSame('19800102', $p['BDAY']);
				$this->assertArrayNotHasKey('URL', $p);
				return true;
			}))
			->willReturn(['UID' => 'new-1']);
		$this->contacts->method('search')->willReturn([['UID' => 'new-1', 'FN' => 'Anna Agent', 'addressbook-key' => '7']]);

		$contact = $this->service()->create('7', [
			'firstName' => ' Anna ', 'lastName' => 'Agent', 'organization' => 'Rhion', 'department' => 'Vertrieb',
			'phones' => [['type' => 'work', 'value' => '0711 1'], ['type' => 'cell', 'value' => '0170 2'], ['type' => 'HOME', 'value' => ' ']],
			'emails' => [['type' => 'bogus', 'value' => 'a@rhion.de']],
			'addresses' => [['type' => 'WORK', 'street' => 'Weg 1', 'postalCode' => '12345', 'city' => 'Ort', 'country' => 'DE'], ['type' => 'HOME']],
			'birthday' => '1980-01-02', 'url' => '',
		]);
		$this->assertSame('new-1', $contact['uid']);
	}

	public function testCreateNeedsNameOrOrganizationAndWritableBook(): void {
		$this->books = [$this->writableBook('7', 'contacts', 'Kontakte'), $this->writableBook('8', 'ro', 'RO', 1)];
		try {
			$this->service()->create('7', ['firstName' => ' ']);
			$this->fail('expected ValidationException');
		} catch (\OCA\NcVertragswerk\Service\ValidationException) {
		}
		$this->expectException(\OCA\NcVertragswerk\Service\NotFoundException::class);
		$this->service()->create('8', ['firstName' => 'X']);
	}
}
