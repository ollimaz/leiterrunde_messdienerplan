<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\LeiterrundeMessdienerplan\Db;

use OCA\Leiterrunde\Db\BaseEntity;

/**
 * @method string getTitle()
 * @method void setTitle(string $v)
 * @method string getStatus()
 * @method void setStatus(string $v)
 * @method ?int getClosedAt()
 * @method void setClosedAt(?int $v)
 * @method ?string getClosedBy()
 * @method void setClosedBy(?string $v)
 * @method ?string getCreatedBy()
 * @method void setCreatedBy(?string $v)
 * @method int getCreatedAt()
 * @method void setCreatedAt(int $v)
 */
class Period extends BaseEntity {
	public const STATUS_OPEN = 'open';
	public const STATUS_CLOSED = 'closed';

	protected string $title = '';
	protected string $status = self::STATUS_OPEN;
	protected ?int $closedAt = null;
	protected ?string $closedBy = null;
	protected ?string $createdBy = null;
	protected int $createdAt = 0;

	public function __construct() {
		foreach (['closedAt', 'createdAt'] as $p) {
			$this->addType($p, 'integer');
		}
	}
}
