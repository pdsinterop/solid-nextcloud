<?php

declare(strict_types=1);

namespace OCA\Solid\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\SimpleMigrationStep;
use OCP\Migration\IOutput;

class Version000000Date20220923134100 extends SimpleMigrationStep {
    /**
     * @param IOutput $output
     * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
     * @param array $options
     *
     * @return null|ISchemaWrapper
     */
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper
    {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        if ($schema->hasTable('solid_jti') === false) {
            $table = $schema->createTable('solid_jti');

            $table->addColumn('id', Types::INTEGER, [
                'autoincrement' => true,
                'notnull' => true,
            ]);

            $table->addColumn('jti', Types::STRING, [
                'length' => 255,
                'notnull' => true,
            ]);

            $table->addColumn('uri', Types::STRING, [
                'length' => 2048,
                'notnull' => true,
            ]);

            $table->addColumn('request_time', Types::DATETIME, [
                'default' => 'CURRENT_TIMESTAMP',
                'notnull' => true,
            ]);

            $table->setPrimaryKey(['id']);
            $table->addIndex(['jti', 'uri']);
        }

        return $schema;
    }
}
