<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\LeiterrundeMessdienerplan\Listener;

use OCA\Leiterrunde\Module\RegisterModulesEvent;
use OCA\LeiterrundeMessdienerplan\Module\MessdienerplanModule;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;

class RegisterMessdienerplanModule implements IEventListener {
	public function __construct(private MessdienerplanModule $module) {
	}

	public function handle(Event $event): void {
		if (!$event instanceof RegisterModulesEvent) {
			return;
		}
		$event->registerModule($this->module);
	}
}
