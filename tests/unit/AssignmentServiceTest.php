<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\LeiterrundeMessdienerplan\Tests;

use OCA\LeiterrundeMessdienerplan\Db\Assignment;
use OCA\LeiterrundeMessdienerplan\Db\AssignmentMapper;
use OCA\LeiterrundeMessdienerplan\Db\Availability;
use OCA\LeiterrundeMessdienerplan\Db\AvailabilityMapper;
use OCA\LeiterrundeMessdienerplan\Db\InviteMapper;
use OCA\LeiterrundeMessdienerplan\Db\Period;
use OCA\LeiterrundeMessdienerplan\Db\PeriodMapper;
use OCA\LeiterrundeMessdienerplan\Db\Service;
use OCA\LeiterrundeMessdienerplan\Db\ServiceMapper;
use OCA\LeiterrundeMessdienerplan\Service\AssignmentService;
use PHPUnit\Framework\TestCase;

class AssignmentServiceTest extends TestCase {
	private function service(int $id, string $date, int $required = 1): Service {
		$s = new Service();
		$s->setId($id);
		$s->setPeriodId(1);
		$s->setLocationId(1);
		$s->setTitle('S' . $id);
		$s->setServiceDate($date);
		$s->setRequired($required);
		return $s;
	}

	private function avail(int $serviceId, int $personId, string $state): Availability {
		$a = new Availability();
		$a->setServiceId($serviceId);
		$a->setPersonId($personId);
		$a->setState($state);
		return $a;
	}

	/**
	 * @param Service[] $services
	 * @param Availability[] $avails
	 * @param Assignment[] $existing
	 * @param int[] $invited
	 * @return array{0:array,1:array<int,array<int,int>>} [result, inserted rows serviceId=>[personIds]]
	 */
	private function runAssign(array $services, array $avails, array $existing, array $invited, string $status = Period::STATUS_CLOSED): array {
		$period = new Period();
		$period->setStatus($status);
		$periods = $this->createMock(PeriodMapper::class);
		$periods->method('find')->willReturn($period);

		$serviceMapper = $this->createMock(ServiceMapper::class);
		$serviceMapper->method('findForPeriod')->willReturn($services);

		$invites = $this->createMock(InviteMapper::class);
		$invites->method('personIdsForPeriod')->willReturn($invited);

		$availMapper = $this->createMock(AvailabilityMapper::class);
		$availMapper->method('findForServices')->willReturn($avails);

		$inserted = [];
		$assignMapper = $this->createMock(AssignmentMapper::class);
		$assignMapper->method('findForServices')->willReturn($existing);
		$assignMapper->method('insert')->willReturnCallback(function (Assignment $a) use (&$inserted) {
			$inserted[$a->getServiceId()][] = $a->getPersonId();
			return $a;
		});

		$svc = new AssignmentService($periods, $serviceMapper, $invites, $availMapper, $assignMapper);
		return [$svc->run(1, 'admin'), $inserted];
	}

	public function testAssignsOnlyYesCandidates(): void {
		[$result, $inserted] = $this->runAssign(
			[$this->service(1, '2027-01-03')],
			[$this->avail(1, 10, 'yes'), $this->avail(1, 11, 'no')],
			[],
			[10, 11]
		);
		$this->assertSame([10], $inserted[1]);
		$this->assertSame(1, $result['assigned']);
		$this->assertSame([], $result['unfilled']);
	}

	public function testFairDistribution(): void {
		$services = [
			$this->service(1, '2027-01-03'),
			$this->service(2, '2027-01-10'),
			$this->service(3, '2027-01-17'),
			$this->service(4, '2027-01-24'),
		];
		$avails = [];
		foreach ($services as $s) {
			$avails[] = $this->avail($s->getId(), 10, 'yes');
			$avails[] = $this->avail($s->getId(), 11, 'yes');
		}
		[$result, $inserted] = $this->runAssign($services, $avails, [], [10, 11]);
		$counts = array_count_values(array_merge(...array_values($inserted)));
		$this->assertSame(2, $counts[10]);
		$this->assertSame(2, $counts[11]);
		$this->assertSame(4, $result['assigned']);
	}

	public function testNoDoubleBookingSameDate(): void {
		$services = [
			$this->service(1, '2027-01-03'),
			$this->service(2, '2027-01-03'), // same date, other location
		];
		$avails = [];
		foreach ($services as $s) {
			$avails[] = $this->avail($s->getId(), 10, 'yes');
			$avails[] = $this->avail($s->getId(), 11, 'yes');
		}
		[, $inserted] = $this->runAssign($services, $avails, [], [10, 11]);
		$this->assertNotSame($inserted[1][0], $inserted[2][0]);
	}

	public function testManualAssignmentsKeptAndCounted(): void {
		$manual = new Assignment();
		$manual->setServiceId(1);
		$manual->setPersonId(10);
		$manual->setSource(Assignment::SOURCE_MANUAL);

		$services = [$this->service(1, '2027-01-03', 2), $this->service(2, '2027-01-10')];
		$avails = [
			$this->avail(1, 10, 'yes'), $this->avail(1, 11, 'yes'),
			$this->avail(2, 10, 'yes'), $this->avail(2, 11, 'yes'),
		];
		[$result, $inserted] = $this->runAssign($services, $avails, [$manual], [10, 11]);
		// service 1: 1 manual + 1 auto needed; person 10 already has load 1 → 11 fills service 1 too? No:
		// service 1 has fewer-or-equal candidates; person 10 is busy on 2027-01-03 via manual anyway
		$this->assertSame([11], $inserted[1]);
		// person 10 now has the manual; service 2 should go to 10 only if fair → loads: 10→1, 11→1 after service1 → tie, lower id wins... both 1 → 10 gets it
		$this->assertCount(1, $inserted[2]);
		$this->assertSame(2, $result['assigned']);
		$this->assertSame([], $result['unfilled']);
	}

	public function testUnfilledReported(): void {
		[$result] = $this->runAssign(
			[$this->service(1, '2027-01-03', 3)],
			[$this->avail(1, 10, 'yes')],
			[],
			[10, 11]
		);
		$this->assertSame(1, $result['assigned']);
		$this->assertSame([1 => 2], $result['unfilled']);
	}

	public function testNonInvitedYesIgnored(): void {
		[, $inserted] = $this->runAssign(
			[$this->service(1, '2027-01-03')],
			[$this->avail(1, 99, 'yes')],
			[],
			[10]
		);
		$this->assertSame([], $inserted);
	}

	public function testOpenPeriodRejected(): void {
		$this->expectException(\OCA\Leiterrunde\Exception\ApiException::class);
		$this->runAssign([], [], [], [], Period::STATUS_OPEN);
	}
}
