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
use OCA\LeiterrundeMessdienerplan\Db\Assignment;
use OCA\LeiterrundeMessdienerplan\Db\AssignmentMapper;
use OCA\LeiterrundeMessdienerplan\Db\AvailabilityMapper;
use OCA\LeiterrundeMessdienerplan\Db\InviteMapper;
use OCA\LeiterrundeMessdienerplan\Db\LocationMapper;
use OCA\LeiterrundeMessdienerplan\Db\Period;
use OCA\LeiterrundeMessdienerplan\Db\PeriodMapper;
use OCA\LeiterrundeMessdienerplan\Db\Service;
use OCA\LeiterrundeMessdienerplan\Db\ServiceMapper;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

class ServicesController extends ApiController {
	private const REPEATS = ['none', 'weekly', 'biweekly', 'monthly'];
	private const MAX_OCCURRENCES = 60;

	public function __construct(
		IRequest $request,
		LoggerInterface $logger,
		private PermissionService $perm,
		private ServiceMapper $serviceMapper,
		private PeriodMapper $periodMapper,
		private LocationMapper $locationMapper,
		private InviteMapper $inviteMapper,
		private AvailabilityMapper $availMapper,
		private AssignmentMapper $assignMapper,
		private PersonMapper $personMapper,
		private AuditService $audit,
	) {
		parent::__construct(Application::APP_ID, $request, $logger);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function index(?int $period_id = null): JSONResponse {
		return $this->wrap(function () use ($period_id) {
			$this->perm->require('messdiener.read');
			if ($period_id === null) {
				$services = [];
				foreach ($this->periodMapper->findAll(50) as $p) {
					$services = array_merge($services, $this->serviceMapper->findForPeriod($p->getId()));
				}
			} else {
				$services = $this->serviceMapper->findForPeriod($period_id);
			}
			return $this->withDetails($services);
		});
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function show(int $id): JSONResponse {
		return $this->wrap(function () use ($id) {
			$this->perm->require('messdiener.read');
			return $this->withDetails([$this->serviceMapper->find($id)])[0];
		});
	}

	#[NoAdminRequired]
	public function create(
		string $title,
		int $period_id,
		int $location_id,
		string $service_date,
		?string $start_time = null,
		?int $required = null,
		?string $notes = null,
		?string $repeat = null,
		?string $repeat_until = null,
	): JSONResponse {
		return $this->wrap(function () use ($title, $period_id, $location_id, $service_date, $start_time, $required, $notes, $repeat, $repeat_until) {
			$this->perm->require('messdiener.manage');
			$this->periodMapper->find($period_id);
			$this->locationMapper->find($location_id);
			$dates = $this->repeatDates($service_date, $repeat ?? 'none', $repeat_until);

			$created = [];
			foreach ($dates as $d) {
				$s = new Service();
				$this->applyFields($s, [
					'title' => $title,
					'period_id' => $period_id,
					'location_id' => $location_id,
					'service_date' => $d,
					'start_time' => $start_time,
					'required' => $required,
					'notes' => $notes,
				]);
				$s->setCreatedAt(time());
				$s->setUpdatedAt(time());
				$created[] = $this->serviceMapper->insert($s);
			}
			if (count($created) > 1) {
				$seriesId = $created[0]->getId();
				foreach ($created as $s) {
					$s->setSeriesId($seriesId);
					$this->serviceMapper->update($s);
				}
			}
			$this->audit->diff('md_service', $created[0]->getId(), null, ['_action' => 'created', 'occurrences' => count($created)]);
			$out = $this->withDetails($created);
			return new JSONResponse(count($out) === 1 ? $out[0] : $out, Http::STATUS_CREATED);
		});
	}

	#[NoAdminRequired]
	public function update(
		int $id,
		?string $title = null,
		?int $location_id = null,
		?string $service_date = null,
		?string $start_time = null,
		?int $required = null,
		?string $notes = null,
	): JSONResponse {
		return $this->wrap(function () use ($id, $title, $location_id, $service_date, $start_time, $required, $notes) {
			$this->perm->require('messdiener.manage');
			$s = $this->serviceMapper->find($id);
			$before = $s->jsonSerialize();
			if ($location_id !== null) {
				$this->locationMapper->find($location_id);
			}
			$params = $this->request->getParams();
			$data = [
				'title' => $title,
				'location_id' => $location_id,
				'service_date' => $service_date,
				'start_time' => $start_time,
				'required' => $required,
				'notes' => $notes,
			];
			foreach (array_keys($data) as $k) {
				if (!array_key_exists($k, $params)) {
					unset($data[$k]);
				}
			}
			$this->applyFields($s, $data);
			$s->setUpdatedAt(time());
			$this->serviceMapper->update($s);
			$this->audit->diff('md_service', $id, $before, $s->jsonSerialize());
			return $this->withDetails([$s])[0];
		});
	}

	#[NoAdminRequired]
	public function destroy(int $id): JSONResponse {
		return $this->wrap(function () use ($id) {
			$this->perm->require('messdiener.manage');
			$this->deleteService($this->serviceMapper->find($id));
			return ['ok' => true];
		});
	}

	#[NoAdminRequired]
	public function destroySeries(int $series_id): JSONResponse {
		return $this->wrap(function () use ($series_id) {
			$this->perm->require('messdiener.manage');
			$services = $this->serviceMapper->findSeries($series_id);
			foreach ($services as $s) {
				$this->deleteService($s);
			}
			return ['ok' => true, 'deleted' => count($services)];
		});
	}

	/* ---- Assignments ---- */

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function assignments(int $id): JSONResponse {
		return $this->wrap(function () use ($id) {
			$this->perm->require('messdiener.read');
			$this->serviceMapper->find($id);
			return $this->assignmentRows($id);
		});
	}

	#[NoAdminRequired]
	public function assignAdd(int $id, int $person_id): JSONResponse {
		return $this->wrap(function () use ($id, $person_id) {
			$this->perm->require('messdiener.manage');
			$service = $this->serviceMapper->find($id);
			$this->personMapper->find($person_id);
			if ($this->inviteMapper->findForPerson($service->getPeriodId(), $person_id) === null) {
				throw new ApiException('Person is not invited to this period', 422);
			}
			if ($this->assignMapper->findOne($id, $person_id) !== null) {
				throw new ApiException('Person is already assigned', 422);
			}
			$a = new Assignment();
			$a->setServiceId($id);
			$a->setPersonId($person_id);
			$a->setSource(Assignment::SOURCE_MANUAL);
			$a->setCreatedBy($this->perm->requireLoggedIn()->getUID());
			$a->setCreatedAt(time());
			$this->assignMapper->insert($a);
			$this->audit->diff('md_assignment', $a->getId(), null, ['_action' => 'created', 'service_id' => $id]);
			return new JSONResponse($this->assignmentRows($id), Http::STATUS_CREATED);
		});
	}

	#[NoAdminRequired]
	public function assignRemove(int $id, int $assignment_id): JSONResponse {
		return $this->wrap(function () use ($id, $assignment_id) {
			$this->perm->require('messdiener.manage');
			$a = $this->assignMapper->find($assignment_id);
			if ($a->getServiceId() !== $id) {
				throw new ApiException('Not found', 404);
			}
			$this->assignMapper->delete($a);
			$this->audit->diff('md_assignment', $assignment_id, null, ['_action' => 'deleted', 'service_id' => $id]);
			return $this->assignmentRows($id);
		});
	}

	/* ---- helpers ---- */

	private function deleteService(Service $s): void {
		$this->availMapper->deleteForService($s->getId());
		$this->assignMapper->deleteForService($s->getId());
		$this->serviceMapper->delete($s);
		$this->audit->diff('md_service', $s->getId(), null, ['_action' => 'deleted']);
	}

	private function assignmentRows(int $serviceId): array {
		$out = [];
		foreach ($this->assignMapper->findForServices([$serviceId]) as $a) {
			$row = $a->jsonSerialize();
			$row['person_name'] = $this->personName($a->getPersonId());
			$out[] = $row;
		}
		return $out;
	}

	/** @param Service[] $services */
	private function withDetails(array $services): array {
		$locations = [];
		foreach ($this->locationMapper->findAll() as $l) {
			$locations[$l->getId()] = $l->getName();
		}
		$serviceIds = array_map(static fn (Service $s) => $s->getId(), $services);
		$assigned = [];
		foreach ($this->assignMapper->findForServices($serviceIds) as $a) {
			$assigned[$a->getServiceId()][] = $a;
		}
		$names = [];
		$out = [];
		foreach ($services as $s) {
			$row = $s->jsonSerialize();
			$row['location_name'] = $locations[$s->getLocationId()] ?? null;
			$row['assigned'] = [];
			foreach ($assigned[$s->getId()] ?? [] as $a) {
				$pid = $a->getPersonId();
				$names[$pid] ??= $this->personName($pid);
				$row['assigned'][] = [
					'id' => $a->getId(),
					'person_id' => $pid,
					'person_name' => $names[$pid],
					'source' => $a->getSource(),
				];
			}
			$out[] = $row;
		}
		return $out;
	}

	private function personName(int $personId): string {
		try {
			$p = $this->personMapper->find($personId);
			return $p->getLastName() . ', ' . $p->getFirstName();
		} catch (\Throwable) {
			return '#' . $personId;
		}
	}

	/** @return string[] dates to create, first = $serviceDate */
	private function repeatDates(string $serviceDate, string $repeat, ?string $repeatUntil): array {
		if (!in_array($repeat, self::REPEATS, true)) {
			throw new ApiException('Invalid repeat', 422);
		}
		$start = \DateTimeImmutable::createFromFormat('!Y-m-d', $serviceDate);
		if ($start === false) {
			throw new ApiException('Invalid date: %s', 422, ['service_date']);
		}
		if ($repeat === 'none' || $repeatUntil === null || $repeatUntil === '') {
			return [$serviceDate];
		}
		$end = \DateTimeImmutable::createFromFormat('!Y-m-d', $repeatUntil);
		if ($end === false || $end < $start) {
			throw new ApiException('Invalid date: %s', 422, ['repeat_until']);
		}
		if ($end > $start->modify('+2 years')) {
			throw new ApiException('Repeat range too long', 422);
		}
		$step = match ($repeat) {
			'weekly' => '+1 week',
			'biweekly' => '+2 weeks',
			'monthly' => '+1 month',
		};
		$dates = [];
		$d = $start;
		while ($d <= $end && count($dates) < self::MAX_OCCURRENCES) {
			$dates[] = $d->format('Y-m-d');
			$d = $d->modify($step);
		}
		return $dates;
	}

	private function applyFields(Service $s, array $data): void {
		if (isset($data['title'])) {
			if (trim($data['title']) === '' || mb_strlen($data['title']) > 255) {
				throw new ApiException('Titel ist erforderlich', 422);
			}
			$s->setTitle(trim($data['title']));
		}
		if (isset($data['period_id'])) {
			$s->setPeriodId((int)$data['period_id']);
		}
		if (isset($data['location_id'])) {
			$s->setLocationId((int)$data['location_id']);
		}
		if (array_key_exists('service_date', $data)) {
			$v = $data['service_date'];
			if ($v === null || \DateTimeImmutable::createFromFormat('!Y-m-d', $v) === false) {
				throw new ApiException('Invalid date: %s', 422, ['service_date']);
			}
			$s->setServiceDate($v);
		}
		if (array_key_exists('start_time', $data)) {
			$v = $data['start_time'];
			if ($v === null || $v === '') {
				$s->setStartTime(null);
			} elseif (preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $v) !== 1) {
				throw new ApiException('Invalid time: %s', 422, ['start_time']);
			} else {
				$s->setStartTime($v);
			}
		}
		if (array_key_exists('required', $data)) {
			$v = $data['required'];
			$s->setRequired($v === null ? 2 : max(0, min(50, (int)$v)));
		}
		if (array_key_exists('notes', $data)) {
			$s->setNotes($data['notes'] === '' ? null : $data['notes']);
		}
	}
}
