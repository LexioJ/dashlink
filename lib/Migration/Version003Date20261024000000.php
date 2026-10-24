<?php
declare(strict_types=1);

namespace OCA\DashLink\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * 1.4.0: link health check columns + admin-suggested links columns
 */
class Version003Date20261024000000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if ($schema->hasTable('dashlink_links')) {
			$table = $schema->getTable('dashlink_links');

			if (!$table->hasColumn('health_status')) {
				$table->addColumn('health_status', Types::STRING, [
					'notnull' => true,
					'length' => 16,
					'default' => 'unknown',
				]);
			}
			if (!$table->hasColumn('health_checked_at')) {
				$table->addColumn('health_checked_at', Types::DATETIME, [
					'notnull' => false,
					'default' => null,
				]);
			}
			if (!$table->hasColumn('health_detail')) {
				$table->addColumn('health_detail', Types::STRING, [
					'notnull' => false,
					'length' => 64,
					'default' => null,
				]);
			}
			if (!$table->hasColumn('is_suggestion')) {
				$table->addColumn('is_suggestion', Types::INTEGER, [
					'notnull' => true,
					'default' => 0,
				]);
			}
			if (!$table->hasColumn('suggested_from')) {
				$table->addColumn('suggested_from', Types::INTEGER, [
					'notnull' => false,
					'default' => null,
				]);
			}
		}

		return $schema;
	}
}
