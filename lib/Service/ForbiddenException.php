<?php

declare(strict_types=1);

namespace OCA\NcVertragswerk\Service;

use Exception;

/**
 * Exception thrown when a user tries to access a resource they don't own
 */
class ForbiddenException extends Exception {

	public function __construct(string $message = 'Access denied') {
		parent::__construct($message);
	}
}
