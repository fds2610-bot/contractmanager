<?php

declare(strict_types=1);

namespace OCA\NcVertragswerk\Service;

/**
 * Normalizes the insurance details of a contract (persons and benefits) into
 * the JSON stored in the database. Anything malformed is dropped silently,
 * so neither the API nor an import can store garbage.
 */
class InsuranceData {

	public const PERSON_TYPES = [
		'policyholder',
		'insured_person',
		'beneficiary',
		'co_insured',
		'payer',
		'other',
	];

	public const MAX_ENTRIES = 50;
	public const MAX_NAME_LENGTH = 255;
	public const MAX_BENEFIT_LENGTH = 1000;

	/**
	 * @param mixed $persons list of ['type' => string, 'name' => string, 'userId' => ?string]
	 * @return string|null JSON, or null when nothing is left
	 */
	public static function personsToJson(mixed $persons): ?string {
		if (!is_array($persons)) {
			return null;
		}
		$result = [];
		foreach ($persons as $person) {
			if (!is_array($person)) {
				continue;
			}
			$name = is_scalar($person['name'] ?? null) ? trim((string)$person['name']) : '';
			$userId = is_scalar($person['userId'] ?? null) ? trim((string)$person['userId']) : '';
			if ($userId === '' || strlen($userId) > 64) {
				$userId = null;
			}
			if ($name === '') {
				$name = $userId ?? '';
			}
			if ($name === '') {
				continue;
			}
			$type = $person['type'] ?? null;
			$result[] = [
				'type' => in_array($type, self::PERSON_TYPES, true) ? $type : 'other',
				'name' => mb_substr($name, 0, self::MAX_NAME_LENGTH),
				'userId' => $userId,
			];
			if (count($result) >= self::MAX_ENTRIES) {
				break;
			}
		}
		return $result === [] ? null : json_encode($result, JSON_UNESCAPED_UNICODE);
	}

	/**
	 * @param mixed $benefits list of strings
	 * @return string|null JSON, or null when nothing is left
	 */
	public static function benefitsToJson(mixed $benefits): ?string {
		if (!is_array($benefits)) {
			return null;
		}
		$result = [];
		foreach ($benefits as $benefit) {
			$text = is_scalar($benefit) ? trim((string)$benefit) : '';
			if ($text === '') {
				continue;
			}
			$result[] = mb_substr($text, 0, self::MAX_BENEFIT_LENGTH);
			if (count($result) >= self::MAX_ENTRIES) {
				break;
			}
		}
		return $result === [] ? null : json_encode($result, JSON_UNESCAPED_UNICODE);
	}

	/**
	 * Decode a stored JSON list; anything unreadable becomes an empty list.
	 */
	public static function decode(?string $json): array {
		if ($json === null || $json === '') {
			return [];
		}
		$data = json_decode($json, true);
		return is_array($data) ? array_values($data) : [];
	}

	public static function normalizeContractNumber(?string $value): ?string {
		$value = $value === null ? '' : trim($value);
		return $value === '' ? null : mb_substr($value, 0, 255);
	}
}
