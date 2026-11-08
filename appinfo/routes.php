<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

return [
	'routes' => [
		['name' => 'page#index', 'url' => '/', 'verb' => 'GET'],
		['name' => 'Api\\Locations#index', 'url' => '/api/locations', 'verb' => 'GET'],
		['name' => 'Api\\Locations#create', 'url' => '/api/locations', 'verb' => 'POST'],
		['name' => 'Api\\Locations#update', 'url' => '/api/locations/{id}', 'verb' => 'PUT'],
		['name' => 'Api\\Locations#destroy', 'url' => '/api/locations/{id}', 'verb' => 'DELETE'],
		['name' => 'Api\\Periods#index', 'url' => '/api/periods', 'verb' => 'GET'],
		['name' => 'Api\\Periods#show', 'url' => '/api/periods/{id}', 'verb' => 'GET'],
		['name' => 'Api\\Periods#create', 'url' => '/api/periods', 'verb' => 'POST'],
		['name' => 'Api\\Periods#update', 'url' => '/api/periods/{id}', 'verb' => 'PUT'],
		['name' => 'Api\\Periods#destroy', 'url' => '/api/periods/{id}', 'verb' => 'DELETE'],
		['name' => 'Api\\Periods#close', 'url' => '/api/periods/{id}/close', 'verb' => 'POST'],
		['name' => 'Api\\Periods#reopen', 'url' => '/api/periods/{id}/reopen', 'verb' => 'POST'],
		['name' => 'Api\\Periods#assign', 'url' => '/api/periods/{id}/assign', 'verb' => 'POST'],
		['name' => 'Api\\Periods#invites', 'url' => '/api/periods/{id}/invites', 'verb' => 'GET'],
		['name' => 'Api\\Periods#inviteAdd', 'url' => '/api/periods/{id}/invites', 'verb' => 'POST'],
		['name' => 'Api\\Periods#inviteRemove', 'url' => '/api/periods/{id}/invites/{invite_id}', 'verb' => 'DELETE'],
		['name' => 'Api\\Services#index', 'url' => '/api/services', 'verb' => 'GET'],
		['name' => 'Api\\Services#show', 'url' => '/api/services/{id}', 'verb' => 'GET'],
		['name' => 'Api\\Services#create', 'url' => '/api/services', 'verb' => 'POST'],
		['name' => 'Api\\Services#update', 'url' => '/api/services/{id}', 'verb' => 'PUT'],
		['name' => 'Api\\Services#destroy', 'url' => '/api/services/{id}', 'verb' => 'DELETE'],
		['name' => 'Api\\Services#destroySeries', 'url' => '/api/services/series/{series_id}', 'verb' => 'DELETE'],
		['name' => 'Api\\Services#assignments', 'url' => '/api/services/{id}/assignments', 'verb' => 'GET'],
		['name' => 'Api\\Services#assignAdd', 'url' => '/api/services/{id}/assignments', 'verb' => 'POST'],
		['name' => 'Api\\Services#assignRemove', 'url' => '/api/services/{id}/assignments/{assignment_id}', 'verb' => 'DELETE'],
		['name' => 'Api\\Availability#matrix', 'url' => '/api/availability', 'verb' => 'GET'],
		['name' => 'Api\\Availability#mine', 'url' => '/api/availability/mine', 'verb' => 'GET'],
		['name' => 'Api\\Availability#setMine', 'url' => '/api/availability/mine', 'verb' => 'PUT'],
		['name' => 'Api\\Availability#setFor', 'url' => '/api/availability', 'verb' => 'PUT'],
		['name' => 'Api\\Stats#index', 'url' => '/api/stats', 'verb' => 'GET'],
	],
];
