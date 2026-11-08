<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\LeiterrundeMessdienerplan\AppInfo;

use OCA\LeiterrundeMessdienerplan\Listener\RegisterMessdienerplanModule;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;

class Application extends App implements IBootstrap {
	public const APP_ID = 'leiterrunde_messdienerplan';
	public const CORE_APP_ID = 'leiterrunde';

	public function __construct() {
		parent::__construct(self::APP_ID);
	}

	public function register(IRegistrationContext $context): void {
		$context->registerEventListener(
			'OCA\Leiterrunde\Module\RegisterModulesEvent',
			RegisterMessdienerplanModule::class
		);
	}

	public function boot(IBootContext $context): void {
	}
}
