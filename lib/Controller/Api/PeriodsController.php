<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\LeiterrundeMessdienerplan\Controller\Api;

use OCA\Leiterrunde\AppInfo\Application;
use OCA\Leiterrunde\Controller\Api\ApiController;
use OCA\Leiterrunde\Db\PersonMapper;
use OCA\Leiterrunde\Exception\ApiException;
use OCA\Leiterrunde\Service\AuditService;
use OCA\Leiterrunde\Service\PermissionService;
use OCA\LeiterrundeMessdienerplan\Db\AssignmentMapper;
use OCA\LeiterrundeMessdienerplan\Db\AvailabilityMapper;
use OCA\LeiterrundeMessdienerplan\Db\Invite;
use OCA\LeiterrundeMessdienerplan\Db\InviteMapper;
use OCA\LeiterrundeMessdienerplan\Db\Period;
use OCA\LeiterrundeMessdienerplan\Db\PeriodMapper;
use OCA\LeiterrundeMessdienerplan\Db\ServiceMapper;
use OCA\LeiterrundeMessdienerplan\Service\AssignmentService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

class PeriodsController extends ApiController {
	public function __construct(
		IRequest $request,
		LoggerInterface $logger,
		private PermissionService $perm,
		private PeriodMapper $mapper,
		private ServiceMapper $serviceMapper,
		private InviteMapper $inviteMapper,
		private AvailabilityMapper $availMapper,
		private AssignmentMapper $assignMapper,
		private PersonMapper $personMapper,
		private AssignmentService $assignment,
		private AuditService $audit,
	) {
		parent::__construct(Application::APP_ID, $request, $logger);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function index(): JSONResponse {
		return $this->wrap(function () {
			$this->perm->require('messdiener.read');
			$out = [];
			foreach ($this->mapper->findAll() as $p) {
				$row = $p->jsonSerialize();
				$row['service_count'] = count($this->serviceMapper->findForPeriod($p->getId()));
				$out[] = $row;
			}
			return $out;
		});
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function show(int $id): JSONResponse {
		return $this->wrap(function () use ($id) {
			$this->perm->require('messdiener.read');
			return $this->mapper->find($id)->jsonSerialize();
		});
	}

	#[NoAdminRequired]
	public function create(string $title): JSONResponse {
		return $this->wrap(function () use ($title) {
			$this->perm->require('messdiener.manage');
			if (trim($title) === '' || mb_strlen($title) > 255) {
				throw new ApiException('Titel ist erforderlich', 422);
			}
			$p = new Period();
			$p->setTitle(trim($title));
			$p->setStatus(Period::STATUS_OPEN);
			$p->setCreatedBy($this->perm->requireLoggedIn()->getUID());
			$p->setCreatedAt(time());
			$p = $this->mapper->insert($p);
			$this->audit->diff('md_period', $p->getId(), null, ['_action' => 'created']);
			return new JSONResponse($p->jsonSerialize(), Http::STATUS_CREATED);
		});
	}

	#[NoAdminRequired]
	public function update(int $id, ?string $title = null): JSONResponse {
		return $this->wrap(function () use ($id, $title) {
			$this->perm->require('messdiener.manage');
			$p = $this->mapper->find($id);
			$before = $p->jsonSerialize();
			if ($title !== null) {
				if (trim($title) === '' || mb_strlen($title) > 255) {
					throw new ApiException('Titel ist erforderlich', 422);
				}
				$p->setTitle(trim($title));
			}
			$this->mapper->update($p);
			$this->audit->diff('md_period', $id, $before, $p->jsonSerialize());
			return $p->jsonSerialize();
		});
	}

	#[NoAdminRequired]
	public function destroy(int $id): JSONResponse {
		return $this->wrap(function () use ($id) {
			$this->perm->require('messdiener.manage');
			$period = $this->mapper->find($id);
			foreach ($this->serviceMapper->findForPeriod($id) as $s) {
				$this->availMapper->deleteForService($s->getId());
				$this->assignMapper->deleteForService($s->getId());
			}
			$this->serviceMapper->deleteForPeriod($id);
			$this->inviteMapper->deleteForPeriod($id);
			$this->mapper->delete($period);
			$this->audit->diff('md_period', $id, null, ['_action' => 'deleted']);
			return ['ok' => true];
		});
	}

	#[NoAdminRequired]
	public function close(int $id): JSONResponse {
		return $this->wrap(function () use ($id) {
			$this->perm->require('messdiener.manage');
			$p = $this->mapper->find($id);
			if ($p->getStatus() === Period::STATUS_CLOSED) {
				throw new ApiException('Period is already closed', 422);
			}
			$before = $p->jsonSerialize();
			$p->setStatus(Period::STATUS_CLOSED);
			$p->setClosedAt(time());
			$p->setClosedBy($this->perm->requireLoggedIn()->getUID());
			$this->mapper->update($p);
			$result = $this->assignment->run($id, $this->perm->requireLoggedIn()->getUID());
			$this->audit->diff('md_period', $id, $before, $p->jsonSerialize());
			return ['period' => $p->jsonSerialize(), 'assignment' => $result];
		});
	}

	#[NoAdminRequired]
	public function reopen(int $id): JSONResponse {
		return $this->wrap(function () use ($id) {
			$this->perm->require('messdiener.manage');
			$p = $this->mapper->find($id);
			$before = $p->jsonSerialize();
			$p->setStatus(Period::STATUS_OPEN);
			$p->setClosedAt(null);
			$p->setClosedBy(null);
			$this->mapper->update($p);
			$this->audit->diff('md_period', $id, $before, $p->jsonSerialize());
			return $p->jsonSerialize();
		});
	}

	#[NoAdminRequired]
	public function assign(int $id): JSONResponse {
		return $this->wrap(function () use ($id) {
			$this->perm->require('messdiener.manage');
			$this->mapper->find($id);
			return $this->assignment->run($id, $this->perm->requireLoggedIn()->getUID());
		});
	}

	/* ---- Invites ---- */

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function invites(int $id): JSONResponse {
		return $this->wrap(function () use ($id) {
			$this->perm->require('messdiener.manage');
			$this->mapper->find($id);
			$out = [];
			foreach ($this->inviteMapper->findForPeriod($id) as $i) {
				$row = $i->jsonSerialize();
				$row['person_name'] = $this->personName($i->getPersonId());
				$out[] = $row;
			}
			return $out;
		});
	}

	#[NoAdminRequired]
	public function inviteAdd(int $id, int $person_id): JSONResponse {
		return $this->wrap(function () use ($id, $person_id) {
			$this->perm->require('messdiener.manage');
			$this->mapper->find($id);
			$this->personMapper->find($person_id);
			if ($this->inviteMapper->findForPerson($id, $person_id) !== null) {
				throw new ApiException('Person is already invited', 422);
			}
			$i = new Invite();
			$i->setPeriodId($id);
			$i->setPersonId($person_id);
			$i->setCreatedAt(time());
			$i = $this->inviteMapper->insert($i);
			$row = $i->jsonSerialize();
			$row['person_name'] = $this->personName($person_id);
			return new JSONResponse($row, Http::STATUS_CREATED);
		});
	}

	#[NoAdminRequired]
	public function inviteRemove(int $id, int $invite_id): JSONResponse {
		return $this->wrap(function () use ($id, $invite_id) {
			$this->perm->require('messdiener.manage');
			$i = $this->inviteMapper->find($invite_id);
			if ($i->getPeriodId() !== $id) {
				throw new ApiException('Not found', 404);
			}
			$this->inviteMapper->delete($i);
			return ['ok' => true];
		});
	}

	private function personName(int $personId): string {
		try {
			$p = $this->personMapper->find($personId);
			return $p->getLastName() . ', ' . $p->getFirstName();
		} catch (\Throwable) {
			return '#' . $personId;
		}
	}
}
