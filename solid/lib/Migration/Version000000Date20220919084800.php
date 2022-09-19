<?php

declare(strict_types=1);

namespace OCA\Solid\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\SimpleMigrationStep;
use OCP\Migration\IOutput;

class Version000000Date20220919084800 extends SimpleMigrationStep {

	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 * @return null|ISchemaWrapper
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options) {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('solid_notifications_webhooks')) {
			$table = $schema->createTable('solid_notifications_webhooks');
			// id, webid, path, url, expiry
			$table->addColumn('id', 'integer', [
				'autoincrement' => true,
				'notnull' => true,
			]);
			$table->addColumn('webid', 'string', [
				'notnull' => true,
				'length' => 2048
			]);
			$table->addColumn('path', 'string', [
				'notnull' => true,
				'length' => 2048,
			]);
			$table->addColumn('url', 'string', [
				'notnull' => true,
				'length' => 2048,
			]);
			$table->addColumn('expiry', 'string', [
				'notnull' => true,
				'length' => 2048,
			]);

			$table->setPrimaryKey(['id']);
			$table->addIndex(['webid'], 'solid_notifications_webhooks_webid_index');
			$table->addIndex(['path'], 'solid_notifications_webhooks_path_index');
		}
		return $schema;
	}
}
