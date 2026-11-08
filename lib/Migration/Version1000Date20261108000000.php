<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Leiterrunde
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\LeiterrundeMessdienerplan\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version1000Date20261108000000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('lr_md_locations')) {
			$table = $schema->createTable('lr_md_locations');
			$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'unsigned' => true]);
			$table->addColumn('name', Types::STRING, ['notnull' => true, 'length' => 255]);
			$table->addColumn('address', Types::STRING, ['notnull' => false, 'length' => 500]);
			$table->addColumn('active', Types::BOOLEAN, ['notnull' => true, 'default' => true]);
			$table->addColumn('created_at', Types::BIGINT, ['notnull' => true]);
			$table->setPrimaryKey(['id']);
		}

		if (!$schema->hasTable('lr_md_periods')) {
			$table = $schema->createTable('lr_md_periods');
			$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'unsigned' => true]);
			$table->addColumn('title', Types::STRING, ['notnull' => true, 'length' => 255]);
			$table->addColumn('status', Types::STRING, ['notnull' => true, 'length' => 20, 'default' => 'open']);
			$table->addColumn('closed_at', Types::BIGINT, ['notnull' => false]);
			$table->addColumn('closed_by', Types::STRING, ['notnull' => false, 'length' => 64]);
			$table->addColumn('created_by', Types::STRING, ['notnull' => false, 'length' => 64]);
			$table->addColumn('created_at', Types::BIGINT, ['notnull' => true]);
			$table->setPrimaryKey(['id']);
		}

		if (!$schema->hasTable('lr_md_services')) {
			$table = $schema->createTable('lr_md_services');
			$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'unsigned' => true]);
			$table->addColumn('period_id', Types::BIGINT, ['notnull' => true]);
			$table->addColumn('location_id', Types::BIGINT, ['notnull' => true]);
			$table->addColumn('series_id', Types::BIGINT, ['notnull' => false]);
			$table->addColumn('title', Types::STRING, ['notnull' => true, 'length' => 255]);
			$table->addColumn('service_date', Types::DATE, ['notnull' => true]);
			$table->addColumn('start_time', Types::STRING, ['notnull' => false, 'length' => 5]);
			$table->addColumn('required', Types::INTEGER, ['notnull' => true, 'default' => 2]);
			$table->addColumn('notes', Types::TEXT, ['notnull' => false]);
			$table->addColumn('created_at', Types::BIGINT, ['notnull' => true]);
			$table->addColumn('updated_at', Types::BIGINT, ['notnull' => true]);
			$table->setPrimaryKey(['id']);
			$table->addIndex(['period_id'], 'lr_md_svc_period_idx');
			$table->addIndex(['service_date'], 'lr_md_svc_date_idx');
			$table->addIndex(['series_id'], 'lr_md_svc_series_idx');
		}

		if (!$schema->hasTable('lr_md_invites')) {
			$table = $schema->createTable('lr_md_invites');
			$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'unsigned' => true]);
			$table->addColumn('period_id', Types::BIGINT, ['notnull' => true]);
			$table->addColumn('person_id', Types::BIGINT, ['notnull' => true]);
			$table->addColumn('created_at', Types::BIGINT, ['notnull' => true]);
			$table->setPrimaryKey(['id']);
			$table->addUniqueIndex(['period_id', 'person_id'], 'lr_md_inv_uniq');
		}

		if (!$schema->hasTable('lr_md_avail')) {
			$table = $schema->createTable('lr_md_avail');
			$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'unsigned' => true]);
			$table->addColumn('service_id', Types::BIGINT, ['notnull' => true]);
			$table->addColumn('person_id', Types::BIGINT, ['notnull' => true]);
			$table->addColumn('state', Types::STRING, ['notnull' => true, 'length' => 10]);
			$table->addColumn('updated_by', Types::STRING, ['notnull' => false, 'length' => 64]);
			$table->addColumn('updated_at', Types::BIGINT, ['notnull' => true]);
			$table->setPrimaryKey(['id']);
			$table->addUniqueIndex(['service_id', 'person_id'], 'lr_md_avail_uniq');
			$table->addIndex(['person_id'], 'lr_md_avail_person_idx');
		}

		if (!$schema->hasTable('lr_md_assign')) {
			$table = $schema->createTable('lr_md_assign');
			$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'unsigned' => true]);
			$table->addColumn('service_id', Types::BIGINT, ['notnull' => true]);
			$table->addColumn('person_id', Types::BIGINT, ['notnull' => true]);
			$table->addColumn('source', Types::STRING, ['notnull' => true, 'length' => 10, 'default' => 'auto']);
			$table->addColumn('created_by', Types::STRING, ['notnull' => false, 'length' => 64]);
			$table->addColumn('created_at', Types::BIGINT, ['notnull' => true]);
			$table->setPrimaryKey(['id']);
			$table->addUniqueIndex(['service_id', 'person_id'], 'lr_md_asn_uniq');
			$table->addIndex(['person_id'], 'lr_md_asn_person_idx');
		}

		return $schema;
	}
}
