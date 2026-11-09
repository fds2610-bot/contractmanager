<?php

declare(strict_types=1);

namespace OCA\NcVertragswerk\Controller;

use OCA\NcVertragswerk\AppInfo\Application;
use OCA\NcVertragswerk\Service\AreaService;
use OCA\NcVertragswerk\Service\NotFoundException;
use OCA\NcVertragswerk\Service\PermissionService;
use OCA\NcVertragswerk\Service\ValidationException;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IL10N;
use OCP\IRequest;

/**
 * „Kategorien" (fds2610/contractmanager#7). Reading for everyone with a role,
 * changes only for admins — like the „Vertragsarten" (CategoryController).
 */
class AreaController extends Controller {

	public function __construct(
		IRequest $request,
		private AreaService $service,
		private ?string $userId,
		private IL10N $l,
		private PermissionService $permissionService,
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	#[NoAdminRequired]
	public function index(): JSONResponse {
		if ($this->userId === null || !$this->permissionService->hasAccess($this->userId)) {
			return new JSONResponse(['error' => $this->l->t('Kein Zugriff')], Http::STATUS_FORBIDDEN);
		}
		return new JSONResponse($this->service->findAllWithCounts());
	}

	/**
	 * Admin only
	 */
	public function create(string $name): JSONResponse {
		try {
			return new JSONResponse($this->service->create($name), Http::STATUS_CREATED);
		} catch (ValidationException $e) {
			return new JSONResponse(['error' => $this->l->t('Ungültiger Name')], Http::STATUS_BAD_REQUEST);
		}
	}

	/**
	 * Admin only
	 */
	public function update(int $id, string $name, ?int $sortOrder = null): JSONResponse {
		try {
			return new JSONResponse($this->service->update($id, $name, $sortOrder));
		} catch (NotFoundException $e) {
			return new JSONResponse(['error' => $this->l->t('Kategorie nicht gefunden')], Http::STATUS_NOT_FOUND);
		} catch (ValidationException $e) {
			return new JSONResponse(['error' => $this->l->t('Ungültiger Name')], Http::STATUS_BAD_REQUEST);
		}
	}

	/**
	 * Admin only — assigned contracts end up without a category.
	 */
	public function destroy(int $id): JSONResponse {
		try {
			$this->service->delete($id);
			return new JSONResponse(['success' => true]);
		} catch (NotFoundException $e) {
			return new JSONResponse(['error' => $this->l->t('Kategorie nicht gefunden')], Http::STATUS_NOT_FOUND);
		}
	}
}
