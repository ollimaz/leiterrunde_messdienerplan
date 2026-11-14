<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\LeiterrundeMessdienerplan\Service;

use OCA\Leiterrunde\Module\IPersonFieldsProvider;
use OCA\LeiterrundeMessdienerplan\Db\LocationMapper;

class PersonFieldsProvider implements IPersonFieldsProvider {
	public function __construct(private LocationMapper $locations) {
	}

	public function personFields(): array {
		return [[
			'key' => 'messdiener_location', 'label' => 'Default Kirchort', 'type' => 'select', 'plugin' => true,
			'options' => array_map(static fn ($l) => ['value' => (string)$l->getId(), 'label' => $l->getName()], $this->locations->findAll(true)),
		]];
	}
}
