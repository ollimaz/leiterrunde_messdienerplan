<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\LeiterrundeMessdienerplan\Service;

use OCA\Leiterrunde\Db\PersonMapper;
use OCA\LeiterrundeMessdienerplan\Db\AssignmentMapper;
use OCA\LeiterrundeMessdienerplan\Db\Availability;
use OCA\LeiterrundeMessdienerplan\Db\AvailabilityMapper;
use OCA\LeiterrundeMessdienerplan\Db\InviteMapper;
use OCA\LeiterrundeMessdienerplan\Db\LocationMapper;
use OCA\LeiterrundeMessdienerplan\Db\Service;
use OCA\LeiterrundeMessdienerplan\Db\ServiceMapper;

class StatsService {
	public function __construct(
		private ServiceMapper $serviceMapper,
		private InviteMapper $inviteMapper,
		private AvailabilityMapper $availMapper,
		private AssignmentMapper $assignMapper,
		private LocationMapper $locationMapper,
		private PersonMapper $personMapper,
	) {
	}

	public function forPeriod(int $periodId): array {
		$services = $this->serviceMapper->findForPeriod($periodId);
		$serviceIds = array_map(static fn (Service $s) => $s->getId(), $services);
		$invites = $this->inviteMapper->findForPeriod($periodId);
		$avail = $this->availMapper->findForServices($serviceIds);
		$assignments = $this->assignMapper->findForServices($serviceIds);

		$locations = [];
		foreach ($this->locationMapper->findAll() as $l) {
			$locations[$l->getId()] = $l->getName();
		}
		$names = [];
		foreach ($invites as $i) {
			try {
				$p = $this->personMapper->find($i->getPersonId());
				$names[$i->getPersonId()] = $p->getLastName() . ', ' . $p->getFirstName();
			} catch (\Throwable) {
				$names[$i->getPersonId()] = '#' . $i->getPersonId();
			}
		}

		$perService = [];
		$totalSlots = 0;
		$filled = 0;
		$byLocation = [];
		foreach ($services as $s) {
			$assigned = count(array_filter($assignments, static fn ($a) => $a->getServiceId() === $s->getId()));
			$yes = count(array_filter($avail, static fn ($a) => $a->getServiceId() === $s->getId() && $a->getState() === Availability::STATE_YES));
			$totalSlots += $s->getRequired();
			$filled += $assigned;
			$locName = $locations[$s->getLocationId()] ?? '#' . $s->getLocationId();
			$byLocation[$locName]['services'] = ($byLocation[$locName]['services'] ?? 0) + 1;
			$byLocation[$locName]['filled'] = ($byLocation[$locName]['filled'] ?? 0) + $assigned;
			$byLocation[$locName]['slots'] = ($byLocation[$locName]['slots'] ?? 0) + $s->getRequired();
			$perService[] = [
				'id' => $s->getId(),
				'title' => $s->getTitle(),
				'date' => $s->getServiceDate(),
				'location' => $locName,
				'required' => $s->getRequired(),
				'assigned' => $assigned,
				'yes' => $yes,
			];
		}

		$perPerson = [];
		$answeredPersons = [];
		foreach ($invites as $i) {
			$perPerson[$i->getPersonId()] = [
				'person_id' => $i->getPersonId(),
				'name' => $names[$i->getPersonId()] ?? '#' . $i->getPersonId(),
				'assigned' => 0,
				'yes' => 0,
				'no' => 0,
			];
		}
		foreach ($assignments as $a) {
			if (isset($perPerson[$a->getPersonId()])) {
				$perPerson[$a->getPersonId()]['assigned']++;
			}
		}
		foreach ($avail as $a) {
			if (!isset($perPerson[$a->getPersonId()])) {
				continue;
			}
			$answeredPersons[$a->getPersonId()] = true;
			if ($a->getState() === Availability::STATE_YES) {
				$perPerson[$a->getPersonId()]['yes']++;
			} elseif ($a->getState() === Availability::STATE_NO) {
				$perPerson[$a->getPersonId()]['no']++;
			}
		}
		usort($perPerson, static fn ($a, $b) => $b['assigned'] <=> $a['assigned']);

		return [
			'services' => count($services),
			'invited' => count($invites),
			'responded' => count($answeredPersons),
			'slots' => $totalSlots,
			'filled' => $filled,
			'by_location' => $byLocation,
			'per_person' => array_values($perPerson),
			'per_service' => $perService,
		];
	}
}
