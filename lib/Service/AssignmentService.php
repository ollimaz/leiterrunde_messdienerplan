<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\LeiterrundeMessdienerplan\Service;

use OCA\Leiterrunde\Exception\ApiException;
use OCA\LeiterrundeMessdienerplan\Db\Assignment;
use OCA\LeiterrundeMessdienerplan\Db\AssignmentMapper;
use OCA\LeiterrundeMessdienerplan\Db\Availability;
use OCA\LeiterrundeMessdienerplan\Db\AvailabilityMapper;
use OCA\LeiterrundeMessdienerplan\Db\InviteMapper;
use OCA\LeiterrundeMessdienerplan\Db\Period;
use OCA\LeiterrundeMessdienerplan\Db\PeriodMapper;
use OCA\LeiterrundeMessdienerplan\Db\Service;
use OCA\LeiterrundeMessdienerplan\Db\ServiceMapper;

/**
 * Smart assignment: distributes invited persons across a period's services.
 *
 * Rules:
 *  - Only persons who answered "yes" for a service are candidates.
 *  - Existing manual assignments are kept and count towards a person's load.
 *  - A person is never assigned twice on the same date.
 *  - Services are filled in order of scarcest candidates first, then by date;
 *    within a service the least-loaded candidate wins (fair distribution).
 *  - Re-running replaces previous auto assignments only.
 */
class AssignmentService {
	public function __construct(
		private PeriodMapper $periodMapper,
		private ServiceMapper $serviceMapper,
		private InviteMapper $inviteMapper,
		private AvailabilityMapper $availMapper,
		private AssignmentMapper $assignMapper,
	) {
	}

	/**
	 * @return array{assigned:int, unfilled:array<int,int>} stats: assignments created, serviceId => missing slots
	 */
	public function run(int $periodId, ?string $uid): array {
		$period = $this->periodMapper->find($periodId);
		if ($period->getStatus() !== Period::STATUS_CLOSED) {
			throw new ApiException('Period is not closed', 422);
		}

		$services = $this->serviceMapper->findForPeriod($periodId);
		$serviceIds = array_map(static fn (Service $s) => $s->getId(), $services);
		$invited = array_flip($this->inviteMapper->personIdsForPeriod($periodId));

		$this->assignMapper->deleteAutoForServices($serviceIds);
		$assignments = $this->assignMapper->findForServices($serviceIds);
		$availRows = $this->availMapper->findForServices($serviceIds);

		// candidates[serviceId] = [personId => true] of invited persons who said yes
		$candidates = [];
		foreach ($availRows as $a) {
			if ($a->getState() === Availability::STATE_YES && isset($invited[$a->getPersonId()])) {
				$candidates[$a->getServiceId()][$a->getPersonId()] = true;
			}
		}

		// manual assignments are fixed; track load per person and booked dates
		$load = [];
		$busyDates = [];
		$manualPerService = [];
		foreach ($assignments as $a) {
			if ($a->getSource() !== Assignment::SOURCE_MANUAL) {
				continue;
			}
			$manualPerService[$a->getServiceId()][] = $a->getPersonId();
			$load[$a->getPersonId()] = ($load[$a->getPersonId()] ?? 0) + 1;
		}
		$serviceDate = [];
		foreach ($services as $s) {
			$serviceDate[$s->getId()] = $s->getServiceDate();
		}
		foreach ($assignments as $a) {
			if ($a->getSource() === Assignment::SOURCE_MANUAL && isset($serviceDate[$a->getServiceId()])) {
				$busyDates[$a->getPersonId()][$serviceDate[$a->getServiceId()]] = true;
			}
		}

		// hardest services first: fewest candidates, then earliest date
		usort($services, static function (Service $a, Service $b) use ($candidates) {
			$ca = count($candidates[$a->getId()] ?? []);
			$cb = count($candidates[$b->getId()] ?? []);
			if ($ca !== $cb) {
				return $ca <=> $cb;
			}
			return strcmp($a->getServiceDate(), $b->getServiceDate());
		});

		$created = 0;
		$unfilled = [];
		foreach ($services as $service) {
			$sid = $service->getId();
			$date = $service->getServiceDate();
			$need = max(0, $service->getRequired() - count($manualPerService[$sid] ?? []));
			if ($need === 0) {
				continue;
			}
			$pool = array_keys($candidates[$sid] ?? []);
			usort($pool, static function ($pa, $pb) use ($load) {
				return ($load[$pa] ?? 0) <=> ($load[$pb] ?? 0) ?: $pa <=> $pb;
			});
			foreach ($pool as $personId) {
				if ($need === 0) {
					break;
				}
				if (isset($busyDates[$personId][$date])) {
					continue;
				}
				$row = new Assignment();
				$row->setServiceId($sid);
				$row->setPersonId($personId);
				$row->setSource(Assignment::SOURCE_AUTO);
				$row->setCreatedBy($uid);
				$row->setCreatedAt(time());
				$this->assignMapper->insert($row);
				$load[$personId] = ($load[$personId] ?? 0) + 1;
				$busyDates[$personId][$date] = true;
				$need--;
				$created++;
			}
			if ($need > 0) {
				$unfilled[$sid] = $need;
			}
		}

		return ['assigned' => $created, 'unfilled' => $unfilled];
	}
}
