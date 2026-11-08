<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\LeiterrundeMessdienerplan\Db;

use OCA\Leiterrunde\Db\BaseEntity;

/**
 * @method int getServiceId()
 * @method void setServiceId(int $v)
 * @method int getPersonId()
 * @method void setPersonId(int $v)
 * @method string getState()
 * @method void setState(string $v)
 * @method ?string getUpdatedBy()
 * @method void setUpdatedBy(?string $v)
 * @method int getUpdatedAt()
 * @method void setUpdatedAt(int $v)
 */
class Availability extends BaseEntity {
	public const STATE_YES = 'yes';
	public const STATE_NO = 'no';

	protected int $serviceId = 0;
	protected int $personId = 0;
	protected string $state = '';
	protected ?string $updatedBy = null;
	protected int $updatedAt = 0;

	public function __construct() {
		foreach (['serviceId', 'personId', 'updatedAt'] as $p) {
			$this->addType($p, 'integer');
		}
	}
}
