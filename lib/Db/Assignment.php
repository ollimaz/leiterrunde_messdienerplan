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
 * @method string getSource()
 * @method void setSource(string $v)
 * @method ?string getCreatedBy()
 * @method void setCreatedBy(?string $v)
 * @method int getCreatedAt()
 * @method void setCreatedAt(int $v)
 */
class Assignment extends BaseEntity {
	public const SOURCE_AUTO = 'auto';
	public const SOURCE_MANUAL = 'manual';

	protected int $serviceId = 0;
	protected int $personId = 0;
	protected string $source = '';
	protected ?string $createdBy = null;
	protected int $createdAt = 0;

	public function __construct() {
		foreach (['serviceId', 'personId', 'createdAt'] as $p) {
			$this->addType($p, 'integer');
		}
	}
}
