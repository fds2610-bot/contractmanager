<?php

declare(strict_types=1);

namespace OCA\NcVertragswerk\Controller;

use OCA\NcVertragswerk\AppInfo\Application;
use OCA\NcVertragswerk\Service\ContactService;
use OCA\NcVertragswerk\Service\NotFoundException;
use OCA\NcVertragswerk\Service\PermissionService;
use OCA\NcVertragswerk\Service\SettingsService;
use OCA\NcVertragswerk\Service\ValidationException;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IL10N;
use OCP\IRequest;

class ContactController extends Controller {

	public function __construct(
		IRequest $request,
		private ContactService $contactService,
		private PermissionService $permissionService,
		private IL10N $l,
		private ?string $userId,
		private SettingsService $settingsService,
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	#[NoAdminRequired]
	public function status(): JSONResponse {
		if ($this->userId === null || !$this->permissionService->hasAccess($this->userId)) {
			return $this->forbidden();
		}
		return new JSONResponse(['available' => $this->contactService->isAvailable()]);
	}

	#[NoAdminRequired]
	public function search(string $query = ''): JSONResponse {
		if ($this->userId === null || !$this->permissionService->canEdit($this->userId)) {
			return $this->forbidden();
		}
		return new JSONResponse($this->contactService->search($query));
	}

	/**
	 * Reads only the viewer's own address books, so a colleague without access gets null.
	 */
	#[NoAdminRequired]
	public function resolve(string $uid = ''): JSONResponse {
		if ($this->userId === null || !$this->permissionService->hasAccess($this->userId)) {
			return $this->forbidden();
		}
		$uid = trim($uid);
		return new JSONResponse(['contact' => $uid === '' ? null : $this->contactService->resolve($uid)]);
	}

	/**
	 * Writable address books for a new contact, the admin setting decides
	 * which one is preselected (fds2610/contractmanager#3).
	 */
	#[NoAdminRequired]
	public function addressBooks(): JSONResponse {
		if ($this->userId === null || !$this->permissionService->canEdit($this->userId)) {
			return $this->forbidden();
		}
		return new JSONResponse($this->contactService->writableAddressBooks($this->settingsService->getDefaultAddressBook()));
	}

	/**
	 * Create a contact from the contract form (fds2610/contractmanager#3).
	 * Returns the resolved contact, like resolve().
	 *
	 * @param array<string, mixed> $contact
	 */
	#[NoAdminRequired]
	public function create(string $addressBookKey = '', array $contact = []): JSONResponse {
		if ($this->userId === null || !$this->permissionService->canEdit($this->userId)) {
			return $this->forbidden();
		}
		try {
			return new JSONResponse(['contact' => $this->contactService->create($addressBookKey, $contact)], Http::STATUS_CREATED);
		} catch (ValidationException $e) {
			return new JSONResponse(['error' => $this->l->t('Bitte einen Namen oder eine Firma angeben')], Http::STATUS_BAD_REQUEST);
		} catch (NotFoundException $e) {
			return new JSONResponse(['error' => $this->l->t('In diesem Adressbuch können keine Kontakte angelegt werden')], Http::STATUS_BAD_REQUEST);
		}
	}

	private function forbidden(): JSONResponse {
		return new JSONResponse(['error' => $this->l->t('Kein Zugriff')], Http::STATUS_FORBIDDEN);
	}
}
