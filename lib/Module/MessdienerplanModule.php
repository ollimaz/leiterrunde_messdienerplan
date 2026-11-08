<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\LeiterrundeMessdienerplan\Module;

use OCA\Leiterrunde\Module\CapabilityDefinition;
use OCA\Leiterrunde\Module\IModule;
use OCA\Leiterrunde\Module\OverviewTile;
use OCA\Leiterrunde\Service\PermissionService;
use OCA\LeiterrundeMessdienerplan\Db\AssignmentMapper;
use OCA\LeiterrundeMessdienerplan\Db\Period;
use OCA\LeiterrundeMessdienerplan\Db\PeriodMapper;
use OCA\LeiterrundeMessdienerplan\Db\Service;
use OCA\LeiterrundeMessdienerplan\Db\ServiceMapper;
use OCP\IL10N;
use OCP\IUser;

class MessdienerplanModule implements IModule {
	public function __construct(
		private PermissionService $perm,
		private PeriodMapper $periodMapper,
		private ServiceMapper $serviceMapper,
		private AssignmentMapper $assignMapper,
		private ?IL10N $l10n = null,
	) {
	}

	public function getId(): string {
		return 'leiterrunde_messdienerplan';
	}

	public function getLabel(): string {
		return 'Messdienerplan';
	}

	public function getNavUrl(): string {
		return '/apps/leiterrunde_messdienerplan/';
	}

	public function getCapabilities(): array {
		return [
			new CapabilityDefinition('messdiener.read', $this->l10n->t('Read altar server plan'), $this->l10n->t('Altar server plan'), ['any']),
			new CapabilityDefinition('messdiener.manage', $this->l10n->t('Manage altar server plan'), $this->l10n->t('Altar server plan'), ['leiter', 'admin']),
		];
	}

	public function getDocumentTypes(): array {
		return [];
	}

	public function getOverviewTiles(IUser $user): array {
		$tiles = [];
		$uid = $user->getUID();
		if (!$this->perm->has('messdiener.read', $uid)) {
			return $tiles;
		}
		$today = date('Y-m-d');
		$upcoming = [];
		foreach ($this->periodMapper->findAll(50) as $period) {
			$services = array_filter(
				$this->serviceMapper->findForPeriod($period->getId()),
				static fn (Service $s) => $s->getServiceDate() >= $today
			);
			if ($services === []) {
				continue;
			}
			$assignments = $this->assignMapper->findForServices(
				array_map(static fn (Service $s) => $s->getId(), $services)
			);
			$assigned = [];
			foreach ($assignments as $a) {
				$assigned[$a->getServiceId()] = ($assigned[$a->getServiceId()] ?? 0) + 1;
			}
			$open = 0;
			foreach ($services as $s) {
				$missing = $s->getRequired() - ($assigned[$s->getId()] ?? 0);
				if ($missing > 0) {
					$open += $missing;
				}
				$upcoming[] = $s;
			}
			$status = $period->getStatus() === Period::STATUS_OPEN ? 'info' : ($open > 0 ? 'warn' : 'ok');
			$tiles[] = new OverviewTile(
				'messdiener.period.' . $period->getId(),
				$this->l10n->t('Altar servers: %s', [$period->getTitle()]),
				$status,
				$open > 0 ? $open : 0,
				[],
				'/apps/leiterrunde_messdienerplan/#/plan'
			);
		}
		usort($upcoming, static fn (Service $a, Service $b) => strcmp($a->getServiceDate(), $b->getServiceDate()));
		$items = [];
		foreach (array_slice($upcoming, 0, 5) as $s) {
			$items[] = ['label' => $s->getTitle(), 'date' => $s->getServiceDate()];
		}
		if ($items !== []) {
			$tiles[] = new OverviewTile('messdiener.upcoming', $this->l10n->t('Upcoming services'), 'info', count($upcoming), $items, '/apps/leiterrunde_messdienerplan/#/plan');
		}
		return $tiles;
	}
}
