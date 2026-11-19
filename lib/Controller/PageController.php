<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\LeiterrundeMessdienerplan\Controller;

use OCA\LeiterrundeMessdienerplan\AppInfo\Application;
use OCA\Leiterrunde\Controller\ModulePageController;
use OCP\App\IAppManager;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IRequest;

/**
 * Delegates to the core app's shared page renderer. Must not extend
 * ModulePageController: when the core app is disabled its classes cannot
 * be autoloaded, so the guard has to run before that class is touched.
 */
class PageController extends Controller {
	public function __construct(
		IRequest $request,
		private IAppManager $appManager,
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function index(): TemplateResponse {
		if (!$this->appManager->isEnabledForAnyone(Application::CORE_APP_ID)) {
			return new TemplateResponse(Application::APP_ID, 'core-required', [], 'blank');
		}
		return (new ModulePageController(Application::APP_ID, $this->request, $this->appManager))->index();
	}
}
