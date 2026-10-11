<?php
declare(strict_types=1);

/**
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) 2020 Juan Pablo Ramirez and Nicolas Masson
 * @link          https://webrider.de/
 * @since         1.0.0
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
 */namespace CakephpTestSuiteLight\Sniffer\DriverTraits;


use Cake\Database\Connection;
use CakephpTestSuiteLight\Sniffer\BaseTriggerBasedTableSniffer;

/**
 * Trait PostgresSnifferTrait
 * @package CakephpTestSuiteLight\Sniffer\DriverTraits
 */
trait PostgresSnifferTrait
{
    /**
     * @inheritDoc
     */
    public function getTriggers(): array
    {
        $triggerPrefix = BaseTriggerBasedTableSniffer::TRIGGER_PREFIX;
        $triggers = $this->fetchIdentifierQuery("
            SELECT tgname
            FROM pg_trigger
            JOIN pg_class ON pg_class.oid = pg_trigger.tgrelid
            JOIN pg_namespace ON pg_namespace.oid = pg_class.relnamespace
            WHERE pg_namespace.nspname = current_schema() AND tgname LIKE '{$triggerPrefix}%'
        ");

        foreach ($triggers as $k => $trigger) {
            if (strpos($trigger, $triggerPrefix) !== 0) {
                unset($triggers[$k]);
            }
        }

        return (array)$triggers;
    }

    /**
     * @inheritDoc
     */
    public function dropTriggers(): void
    {
        $prefix = BaseTriggerBasedTableSniffer::TRIGGER_PREFIX;
        $statements = $this->fetchIdentifierQuery("
            SELECT format('DROP TRIGGER IF EXISTS %I ON %I.%I', trigger_name, event_object_schema, event_object_table)
            FROM information_schema.triggers
            WHERE left(trigger_name, length('{$prefix}')) = '{$prefix}'
                AND trigger_schema = current_schema()
        ");
        /** @var Connection $connection */
        $connection = $this->getConnection();
        foreach ($statements as $statement) {
            $connection->execute($statement);
        }
    }

    /**
     * @inheritDoc
     */
    public function dropTables(array $tables): void
    {
        if (empty($tables)) {
            return;
        }

        /** @var Connection $connection */
        $connection = $this->getConnection();
        $connection->disableConstraints(function (Connection $connection) use ($tables) {
            $tables[] = BaseTriggerBasedTableSniffer::DIRTY_TABLE_COLLECTOR;
            foreach ($tables as $table) {
                $connection->execute(
                    'DROP TABLE IF EXISTS "' . $table  . '" CASCADE;'
                );
            }
        });
    }
}
