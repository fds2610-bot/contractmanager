<?php

declare(strict_types=1);

namespace OCA\NcVertragswerk\Service;

use OCP\App\IAppManager;
use OCP\Constants;
use OCP\Contacts\IManager;
use OCP\IAddressBook;
use OCP\IURLGenerator;

/**
 * Reads only the viewer's own and shared address books, never the system one with the accounts.
 */
class ContactService {

	private const SEARCH_LIMIT = 20;

	public function __construct(
		private IManager $contactsManager,
		private IAppManager $appManager,
		private IURLGenerator $urlGenerator,
	) {
	}

	public function isAvailable(): bool {
		return $this->personalAddressBooks() !== [];
	}

	/**
	 * @return list<array{uid: string, name: string, organization: string, email: string}>
	 */
	public function search(string $query): array {
		$query = trim($query);
		if ($query === '' || !$this->isAvailable()) {
			return [];
		}

		$results = [];
		$raw = $this->contactsManager->search($query, ['FN', 'ORG', 'EMAIL'], [
			'limit' => self::SEARCH_LIMIT,
			'enumeration' => false,
		]);
		foreach ($raw as $entry) {
			$uid = $this->scalar($entry['UID'] ?? null);
			if ($uid === '' || !empty($entry['isLocalSystemBook']) || isset($results[$uid])) {
				continue;
			}
			$results[$uid] = [
				'uid' => $uid,
				'name' => $this->displayName($entry),
				'organization' => $this->organization($entry),
				'email' => $this->values($entry['EMAIL'] ?? null)[0] ?? '',
			];
			if (count($results) >= self::SEARCH_LIMIT) {
				break;
			}
		}
		return array_values($results);
	}

	/**
	 * @return array{uid: string, name: string, organization: string, emails: list<string>, phones: list<string>, addresses: list<string>, contactsUrl: ?string}|null null when this viewer cannot read it
	 */
	public function resolve(string $uid): ?array {
		$books = $this->personalAddressBooks();
		if ($uid === '' || $books === []) {
			return null;
		}

		$raw = $this->contactsManager->search($uid, ['UID'], [
			'strict_search' => true,
			'enumeration' => false,
		]);
		foreach ($raw as $entry) {
			if ($this->scalar($entry['UID'] ?? null) !== $uid || !empty($entry['isLocalSystemBook'])) {
				continue;
			}
			$book = $books[(string)($entry['addressbook-key'] ?? '')] ?? null;
			if ($book === null) {
				continue;
			}
			return [
				'uid' => $uid,
				'name' => $this->displayName($entry),
				'organization' => $this->organization($entry),
				'emails' => $this->values($entry['EMAIL'] ?? null),
				'phones' => $this->values($entry['TEL'] ?? null),
				'addresses' => array_values(array_filter(array_map(
					fn (string $adr): string => $this->formatAddress($adr),
					$this->values($entry['ADR'] ?? null),
				))),
				'contactsUrl' => $this->contactsUrl($uid, $book),
			];
		}
		return null;
	}

	public const TEL_TYPES = ['CELL', 'HOME', 'WORK', 'FAX', 'VOICE'];
	public const EMAIL_TYPES = ['HOME', 'WORK', 'OTHER'];
	public const ADR_TYPES = ['HOME', 'WORK', 'OTHER'];
	private const MAX_ENTRIES = 10;

	/**
	 * Address books the user can create contacts in (fds2610/contractmanager#3).
	 * `default` marks the preselected one: the book named $preferred (admin
	 * setting, compared by name or URI), otherwise the first.
	 *
	 * @return list<array{key: string, name: string, default: bool}>
	 */
	public function writableAddressBooks(string $preferred = ''): array {
		$books = [];
		foreach ($this->personalAddressBooks() as $key => $book) {
			if (($book->getPermissions() & Constants::PERMISSION_CREATE) === 0) {
				continue;
			}
			$books[] = ['key' => (string)$key, 'name' => (string)$book->getDisplayName(), 'uri' => $book->getUri(), 'default' => false];
		}
		$preferred = mb_strtolower(trim($preferred));
		$index = 0;
		foreach ($books as $i => $book) {
			if ($preferred !== '' && (mb_strtolower($book['name']) === $preferred || mb_strtolower($book['uri']) === $preferred)) {
				$index = $i;
				break;
			}
		}
		if ($books !== []) {
			$books[$index]['default'] = true;
		}
		return array_map(static fn (array $b): array => ['key' => $b['key'], 'name' => $b['name'], 'default' => $b['default']], $books);
	}

	/**
	 * Create a contact in one of the user's writable address books.
	 *
	 * @param array<string, mixed> $data firstName, lastName, organization,
	 *   department, title, phones/emails [{type, value}], addresses [{type,
	 *   street, postalCode, city, region, country}], url, birthday (Y-m-d), note
	 * @return array{uid: string, name: string, organization: string, emails: list<string>, phones: list<string>, addresses: list<string>, contactsUrl: ?string}
	 * @throws ValidationException
	 * @throws NotFoundException when the address book is unknown or read-only
	 */
	public function create(string $addressBookKey, array $data): array {
		$book = $this->personalAddressBooks()[$addressBookKey] ?? null;
		if ($book === null || ($book->getPermissions() & Constants::PERMISSION_CREATE) === 0) {
			throw new NotFoundException('Address book not writable');
		}

		$first = $this->field($data, 'firstName');
		$last = $this->field($data, 'lastName');
		$org = $this->field($data, 'organization');
		$name = trim($first . ' ' . $last);
		if ($name === '' && $org === '') {
			throw new ValidationException(['name' => 'Name oder Firma fehlt']);
		}

		$properties = [
			'FN' => $name !== '' ? $name : $org,
			'N' => [['value' => [$last, $first, '', '', '']]],
		];
		$department = $this->field($data, 'department');
		if ($org !== '' || $department !== '') {
			$properties['ORG'] = [['value' => $department !== '' ? [$org, $department] : [$org]]];
		}
		foreach (['title' => 'TITLE', 'url' => 'URL'] as $field => $property) {
			$value = $this->field($data, $field);
			if ($value !== '') {
				$properties[$property] = $value;
			}
		}
		$note = $this->field($data, 'note', 5000);
		if ($note !== '') {
			$properties['NOTE'] = $note;
		}
		$birthday = $this->field($data, 'birthday');
		if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $birthday) === 1 && checkdate((int)substr($birthday, 5, 2), (int)substr($birthday, 8, 2), (int)substr($birthday, 0, 4))) {
			$properties['BDAY'] = str_replace('-', '', $birthday);
		}
		$tel = $this->typedEntries($data['phones'] ?? null, self::TEL_TYPES);
		if ($tel !== []) {
			$properties['TEL'] = $tel;
		}
		$email = $this->typedEntries($data['emails'] ?? null, self::EMAIL_TYPES);
		if ($email !== []) {
			$properties['EMAIL'] = $email;
		}
		$adr = $this->addressEntries($data['addresses'] ?? null);
		if ($adr !== []) {
			$properties['ADR'] = $adr;
		}

		$created = $book->createOrUpdate($properties);
		$uid = $this->scalar($created['UID'] ?? null);
		$contact = $uid !== '' ? $this->resolve($uid) : null;
		if ($contact === null) {
			throw new NotFoundException('Contact was not created');
		}
		return $contact;
	}

	private function field(array $data, string $key, int $max = 255): string {
		$value = $data[$key] ?? '';
		return is_scalar($value) ? mb_substr(trim((string)$value), 0, $max) : '';
	}

	/**
	 * @return list<array{value: string, type: string}>
	 */
	private function typedEntries(mixed $entries, array $types): array {
		$result = [];
		foreach (is_array($entries) ? $entries : [] as $entry) {
			if (!is_array($entry)) {
				continue;
			}
			$value = $this->field($entry, 'value');
			if ($value === '') {
				continue;
			}
			$type = strtoupper($this->field($entry, 'type'));
			$result[] = ['value' => $value, 'type' => in_array($type, $types, true) ? $type : $types[0]];
			if (count($result) >= self::MAX_ENTRIES) {
				break;
			}
		}
		return $result;
	}

	/**
	 * vCard ADR: post office box; extended; street; locality; region; postal code; country
	 *
	 * @return list<array{value: list<string>, type: string}>
	 */
	private function addressEntries(mixed $entries): array {
		$result = [];
		foreach (is_array($entries) ? $entries : [] as $entry) {
			if (!is_array($entry)) {
				continue;
			}
			$parts = ['', '', $this->field($entry, 'street'), $this->field($entry, 'city'), $this->field($entry, 'region'), $this->field($entry, 'postalCode'), $this->field($entry, 'country')];
			if (implode('', $parts) === '') {
				continue;
			}
			$type = strtoupper($this->field($entry, 'type'));
			$result[] = ['value' => $parts, 'type' => in_array($type, self::ADR_TYPES, true) ? $type : self::ADR_TYPES[0]];
			if (count($result) >= self::MAX_ENTRIES) {
				break;
			}
		}
		return $result;
	}

	/**
	 * @return array<string, IAddressBook> keyed by address book key
	 */
	private function personalAddressBooks(): array {
		$books = [];
		foreach ($this->contactsManager->getUserAddressBooks() as $book) {
			if (!$book->isSystemAddressBook()) {
				$books[(string)$book->getKey()] = $book;
			}
		}
		return $books;
	}

	private function contactsUrl(string $uid, IAddressBook $book): ?string {
		if (!$this->appManager->isEnabledForUser('contacts')) {
			return null;
		}
		return $this->urlGenerator->linkToRoute('contacts.contacts.direct', [
			'contact' => $uid . '~' . $book->getUri(),
		]);
	}

	private function displayName(array $entry): string {
		$name = $this->scalar($entry['FN'] ?? null);
		return $name !== '' ? $name : $this->organization($entry);
	}

	private function organization(array $entry): string {
		return trim(explode(';', $this->scalar($entry['ORG'] ?? null))[0]);
	}

	/**
	 * vCard ADR: PO box;extended;street;locality;region;postal code;country
	 */
	private function formatAddress(string $adr): string {
		$parts = array_map('trim', array_pad(explode(';', $adr), 7, ''));
		$lines = [
			trim($parts[0] . ' ' . $parts[1] . ' ' . $parts[2]),
			trim($parts[5] . ' ' . $parts[3]),
			$parts[4],
			$parts[6],
		];
		return implode("\n", array_filter($lines, static fn (string $line): bool => $line !== ''));
	}

	/**
	 * @return list<string>
	 */
	private function values(mixed $value): array {
		$list = is_array($value) ? $value : [$value];
		return array_values(array_filter(
			array_map(fn (mixed $v): string => $this->scalar($v), $list),
			static fn (string $v): bool => $v !== '',
		));
	}

	private function scalar(mixed $value): string {
		return is_scalar($value) ? trim((string)$value) : '';
	}
}
