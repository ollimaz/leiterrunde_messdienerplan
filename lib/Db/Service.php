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
 * @method int getLocationId()
 * @method void setLocationId(int $v)
 * @method ?int getSeriesId()
 * @method void setSeriesId(?int $v)
 * @method string getTitle()
 * @method void setTitle(string $v)
 * @method string getServiceDate()
 * @method void setServiceDate(string $v)
 * @method ?string getStartTime()
 * @method void setStartTime(?string $v)
 * @method int getRequired()
 * @method void setRequired(int $v)
 * @method ?string getNotes()
 * @method void setNotes(?string $v)
 * @method int getCreatedAt()
 * @method void setCreatedAt(int $v)
 * @method int getUpdatedAt()
 * @method void setUpdatedAt(int $v)
 */
class Service extends BaseEntity {
	protected int $periodId = 0;
	protected int $locationId = 0;
	protected ?int $seriesId = null;
	protected string $title = '';
	protected string $serviceDate = '';
	protected ?string $startTime = null;
	protected int $required = 2;
	protected ?string $notes = null;
	protected int $createdAt = 0;
	protected int $updatedAt = 0;

	public function __construct() {
		foreach (['periodId', 'locationId', 'seriesId', 'required', 'createdAt', 'updatedAt'] as $p) {
			$this->addType($p, 'integer');
		}
	}
}
