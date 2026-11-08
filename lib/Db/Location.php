<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\LeiterrundeMessdienerplan\Db;

use OCA\Leiterrunde\Db\BaseEntity;

/**
 * @method string getName()
 * @method void setName(string $v)
 * @method ?string getAddress()
 * @method void setAddress(?string $v)
 * @method bool getActive()
 * @method void setActive(bool $v)
 * @method int getCreatedAt()
 * @method void setCreatedAt(int $v)
 */
class Location extends BaseEntity {
	protected string $name = '';
	protected ?string $address = null;
	protected bool $active = true;
	protected int $createdAt = 0;

	public function __construct() {
		$this->addType('active', 'boolean');
		$this->addType('createdAt', 'integer');
	}
}
