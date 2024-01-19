<?php

declare(strict_types=1);

namespace OCA\Solid\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

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

		if (!$schema->hasTable('solid_webhooks')) {
			$table = $schema->createTable('solid_webhooks');
			// id, webid, topic, target
			$table->addColumn('id', 'integer', [
				'autoincrement' => true,
				'notnull' => true,
			]);
			$table->addColumn('web_id', 'string', [
				'notnull' => true,
				'length' => 2048
			]);
			$table->addColumn('topic', 'string', [
				'notnull' => true,
				'length' => 2048,
			]);
			$table->addColumn('target', 'string', [
				'notnull' => true,
				'length' => 2048,
			]);

			$table->setPrimaryKey(['id']);
			$table->addIndex(['web_id'], 'solid_webhooks_web_id_index');
			$table->addIndex(['target'], 'solid_webhooks_target_index');
		}
		return $schema;
	}
}
