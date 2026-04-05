<?php
declare(strict_types=1);

namespace OCA\DashLink\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Migration to add user_id column for user-private links
 */
class Version002Date20250210000000 extends SimpleMigrationStep {
	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 * @return null|ISchemaWrapper
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if ($schema->hasTable('dashlink_links')) {
			$table = $schema->getTable('dashlink_links');

			// Add user_id column if it doesn't exist
			// NULL = admin/global link, non-NULL = user's private link
			if (!$table->hasColumn('user_id')) {
				$table->addColumn('user_id', Types::STRING, [
					'notnull' => false,
					'length' => 64,
					'default' => null,
				]);
			}

			// Add index on user_id for filtering user links
			if (!$table->hasIndex('idx_user_id')) {
				$table->addIndex(['user_id'], 'idx_user_id');
			}

			// Add composite index on user_id and position for ordering user links
			if (!$table->hasIndex('idx_user_position')) {
				$table->addIndex(['user_id', 'position'], 'idx_user_position');
			}
		}

		return $schema;
	}
}
