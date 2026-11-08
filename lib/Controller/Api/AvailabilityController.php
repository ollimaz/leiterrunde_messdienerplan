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
use OCA\Leiterrunde\Exception\ForbiddenException;
use OCA\Leiterrunde\Service\AuditService;
use OCA\Leiterrunde\Service\PermissionService;
use OCA\LeiterrundeMessdienerplan\Db\Availability;
use OCA\LeiterrundeMessdienerplan\Db\AvailabilityMapper;
use OCA\LeiterrundeMessdienerplan\Db\AssignmentMapper;
use OCA\LeiterrundeMessdienerplan\Db\InviteMapper;
use OCA\LeiterrundeMessdienerplan\Db\LocationMapper;
use OCA\LeiterrundeMessdienerplan\Db\Period;
use OCA\LeiterrundeMessdienerplan\Db\PeriodMapper;
use OCA\LeiterrundeMessdienerplan\Db\Service;
use OCA\LeiterrundeMessdienerplan\Db\ServiceMapper;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

class AvailabilityController extends ApiController {
	public function __construct(
		IRequest $request,
		LoggerInterface $logger,
		private PermissionService $perm,
		private PeriodMapper $periodMapper,
		private ServiceMapper $serviceMapper,
		private InviteMapper $inviteMapper,
		private AvailabilityMapper $availMapper,
		private AssignmentMapper $assignMapper,
		private LocationMapper $locationMapper,
		private PersonMapper $personMapper,
		private AuditService $audit,
	) {
		parent::__construct(Application::APP_ID, $request, $logger);
	}

	/** Full services × invited-persons grid for a period (managers only). */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function matrix(int $period_id): JSONResponse {
		return $this->wrap(function () use ($period_id) {
			$this->perm->require('messdiener.manage');
			$period = $this->periodMapper->find($period_id);
			$services = $this->serviceMapper->findForPeriod($period_id);
			$serviceIds = array_map(static fn (Service $s) => $s->getId(), $services);
			$cells = [];
			foreach ($this->availMapper->findForServices($serviceIds) as $a) {
				$cells[$a->getServiceId()][$a->getPersonId()] = $a->getState();
			}
			$persons = [];
			foreach ($this->inviteMapper->findForPeriod($period_id) as $i) {
				$persons[] = ['id' => $i->getPersonId(), 'name' => $this->personName($i->getPersonId())];
			}
			$locations = [];
			foreach ($this->locationMapper->findAll() as $l) {
				$locations[$l->getId()] = $l->getName();
			}
			return [
				'period' => $period->jsonSerialize(),
				'services' => array_map(static fn (Service $s) => $s->jsonSerialize() + ['location_name' => $locations[$s->getLocationId()] ?? null], $services),
				'persons' => $persons,
				'cells' => $cells,
			];
		});
	}

	/** Own availability + duty roster: services of periods the caller is invited to. */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function mine(): JSONResponse {
		return $this->wrap(function () {
			$this->perm->requireLoggedIn();
			$person = $this->perm->currentPerson();
			if ($person === null) {
				return ['periods' => []];
			}
			$personId = $person->getId();
			$locations = [];
			foreach ($this->locationMapper->findAll() as $l) {
				$locations[$l->getId()] = $l->getName();
			}
			$out = [];
			foreach ($this->periodMapper->findAll() as $period) {
				if ($this->inviteMapper->findForPerson($period->getId(), $personId) === null) {
					continue;
				}
				$services = $this->serviceMapper->findForPeriod($period->getId());
				$serviceIds = array_map(static fn (Service $s) => $s->getId(), $services);
				$myAvail = [];
				foreach ($this->availMapper->findForPerson($personId, $serviceIds) as $a) {
					$myAvail[$a->getServiceId()] = $a->getState();
				}
				$myAssign = [];
				foreach ($this->assignMapper->findForServices($serviceIds) as $a) {
					if ($a->getPersonId() === $personId) {
						$myAssign[$a->getServiceId()] = true;
					}
				}
				$rows = [];
				foreach ($services as $s) {
					$rows[] = $s->jsonSerialize() + [
						'location_name' => $locations[$s->getLocationId()] ?? null,
						'state' => $myAvail[$s->getId()] ?? null,
						'assigned' => isset($myAssign[$s->getId()]),
					];
				}
				$out[] = ['period' => $period->jsonSerialize(), 'services' => $rows];
			}
			return ['periods' => $out, 'person' => $person->jsonSerialize()];
		});
	}

	/** Set own availability for one service. state: yes|no|null (null clears). */
	#[NoAdminRequired]
	public function setMine(int $service_id, ?string $state = null): JSONResponse {
		return $this->wrap(function () use ($service_id, $state) {
			$user = $this->perm->requireLoggedIn();
			$person = $this->perm->currentPerson();
			if ($person === null) {
				throw new ForbiddenException('No person record');
			}
			$service = $this->serviceMapper->find($service_id);
			$this->assertSelfService($service, $person->getId());
			return $this->applyState($service_id, $person->getId(), $state, $user->getUID());
		});
	}

	/** Manager: set availability for any invited person. state: yes|no|null. */
	#[NoAdminRequired]
	public function setFor(int $service_id, int $person_id, ?string $state = null): JSONResponse {
		return $this->wrap(function () use ($service_id, $person_id, $state) {
			$this->perm->require('messdiener.manage');
			$service = $this->serviceMapper->find($service_id);
			if ($this->inviteMapper->findForPerson($service->getPeriodId(), $person_id) === null) {
				throw new ApiException('Person is not invited to this period', 422);
			}
			return $this->applyState($service_id, $person_id, $state, $this->perm->requireLoggedIn()->getUID());
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

	private function assertSelfService(Service $service, int $personId): void {
		if ($this->inviteMapper->findForPerson($service->getPeriodId(), $personId) === null) {
			throw new ForbiddenException('No permission');
		}
		$period = $this->periodMapper->find($service->getPeriodId());
		if ($period->getStatus() !== Period::STATUS_OPEN) {
			throw new ApiException('Period is closed', 422);
		}
	}

	private function applyState(int $serviceId, int $personId, ?string $state, string $uid): array {
		if ($state === null || $state === '') {
			$existing = $this->availMapper->findOne($serviceId, $personId);
			if ($existing !== null) {
				$this->availMapper->delete($existing);
			}
			$this->audit->diff('md_availability', $serviceId, null, ['_action' => 'cleared', 'person_id' => $personId]);
			return ['ok' => true, 'state' => null];
		}
		if (!in_array($state, [Availability::STATE_YES, Availability::STATE_NO], true)) {
			throw new ApiException('Invalid state', 422);
		}
		$a = $this->availMapper->upsert($serviceId, $personId, $state, $uid);
		$this->audit->diff('md_availability', $a->getId(), null, ['_action' => 'set', 'state' => $state]);
		return ['ok' => true, 'state' => $a->getState()];
	}
}
