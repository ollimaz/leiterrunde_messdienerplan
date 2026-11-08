<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\LeiterrundeMessdienerplan\Db;

use OCA\Leiterrunde\Db\BaseEntity;

/**
 * @method int getPeriodId()
 * @method void setPeriodId(int $v)
 * @method int getPersonId()
 * @method void setPersonId(int $v)
 * @method int getCreatedAt()
 * @method void setCreatedAt(int $v)
 */
class Invite extends BaseEntity {
	protected int $periodId = 0;
	protected int $personId = 0;
	protected int $createdAt = 0;

	public function __construct() {
		foreach (['periodId', 'personId', 'createdAt'] as $p) {
			$this->addType($p, 'integer');
		}
	}
}
