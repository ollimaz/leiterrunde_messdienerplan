<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\LeiterrundeMessdienerplan\Controller\Api;

use OCA\Leiterrunde\AppInfo\Application;
use OCA\Leiterrunde\Controller\Api\ApiController;
use OCA\Leiterrunde\Exception\ApiException;
use OCA\Leiterrunde\Service\AuditService;
use OCA\Leiterrunde\Service\PermissionService;
use OCA\LeiterrundeMessdienerplan\Db\Location;
use OCA\LeiterrundeMessdienerplan\Db\LocationMapper;
use OCA\LeiterrundeMessdienerplan\Db\ServiceMapper;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

class LocationsController extends ApiController {
	public function __construct(
		IRequest $request,
		LoggerInterface $logger,
		private PermissionService $perm,
		private LocationMapper $mapper,
		private ServiceMapper $serviceMapper,
		private AuditService $audit,
	) {
		parent::__construct(Application::APP_ID, $request, $logger);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function index(): JSONResponse {
		return $this->wrap(function () {
			$this->perm->require('messdiener.read');
			return array_map(static fn ($l) => $l->jsonSerialize(), $this->mapper->findAll());
		});
	}

	#[NoAdminRequired]
	public function create(string $name, ?string $address = null): JSONResponse {
		return $this->wrap(function () use ($name, $address) {
			$this->perm->require('messdiener.manage');
			$l = new Location();
			$this->applyFields($l, ['name' => $name, 'address' => $address]);
			$l->setActive(true);
			$l->setCreatedAt(time());
			$l = $this->mapper->insert($l);
			$this->audit->diff('md_location', $l->getId(), null, ['_action' => 'created']);
			return new JSONResponse($l->jsonSerialize(), Http::STATUS_CREATED);
		});
	}

	#[NoAdminRequired]
	public function update(int $id, ?string $name = null, ?string $address = null, ?bool $active = null): JSONResponse {
		return $this->wrap(function () use ($id, $name, $address, $active) {
			$this->perm->require('messdiener.manage');
			$l = $this->mapper->find($id);
			$before = $l->jsonSerialize();
			$params = $this->request->getParams();
			$data = ['name' => $name, 'address' => $address];
			foreach (array_keys($data) as $k) {
				if (!array_key_exists($k, $params)) {
					unset($data[$k]);
				}
			}
			$this->applyFields($l, $data);
			if ($active !== null && array_key_exists('active', $params)) {
				$l->setActive($active);
			}
			$this->mapper->update($l);
			$this->audit->diff('md_location', $id, $before, $l->jsonSerialize());
			return $l->jsonSerialize();
		});
	}

	#[NoAdminRequired]
	public function destroy(int $id): JSONResponse {
		return $this->wrap(function () use ($id) {
			$this->perm->require('messdiener.manage');
			$this->mapper->find($id);
			if ($this->serviceMapper->countForLocation($id) > 0) {
				throw new ApiException('Location is used by services', 422);
			}
			$this->mapper->delete($this->mapper->find($id));
			$this->audit->diff('md_location', $id, null, ['_action' => 'deleted']);
			return ['ok' => true];
		});
	}

	private function applyFields(Location $l, array $data): void {
		if (isset($data['name'])) {
			if (trim($data['name']) === '' || mb_strlen($data['name']) > 255) {
				throw new ApiException('Name ist erforderlich', 422);
			}
			$l->setName(trim($data['name']));
		}
		if (array_key_exists('address', $data)) {
			$v = $data['address'];
			if ($v !== null && mb_strlen($v) > 500) {
				throw new ApiException('Invalid address', 422);
			}
			$l->setAddress($v === '' ? null : $v);
		}
	}
}
