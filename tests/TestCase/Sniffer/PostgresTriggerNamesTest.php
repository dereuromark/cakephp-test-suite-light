<?php
declare(strict_types=1);

namespace CakephpTestSuiteLight\Test\TestCase\Sniffer;

use Cake\Database\Driver\Postgres;
use Cake\Datasource\ConnectionManager;
use Cake\TestSuite\TestCase;
use CakephpTestSuiteLight\Fixture\TruncateDirtyTables;
use CakephpTestSuiteLight\Sniffer\SnifferRegistry;

final class PostgresTriggerNamesTest extends TestCase
{
    use TruncateDirtyTables;

    public function testLongAndMixedCaseTableTriggersCanBeRecreated(): void
    {
        $connection = ConnectionManager::get('test');
        if (!$connection->getDriver() instanceof Postgres) {
            $this->markTestSkipped('PostgreSQL identifier rules.');
        }
        $sniffer = SnifferRegistry::get('test');
        $tables = [str_repeat('p', 60), 'MixedCaseAppTable'];
        try {
            foreach ($tables as $table) {
                $quoted = $connection->getDriver()->quoteIdentifier($table);
                $connection->execute("CREATE TABLE {$quoted} (id INTEGER)");
            }
            $sniffer->truncateDirtyTables();
            foreach ($tables as $table) {
                $this->assertContains(substr('dts_' . $table, 0, 63), $sniffer->getTriggers());
                $connection->insert($table, ['id' => 1]);
                $this->assertContains($table, $sniffer->getDirtyTables());
            }
            $sniffer->truncateDirtyTables();
            foreach ($tables as $table) {
                $quoted = $connection->getDriver()->quoteIdentifier($table);
                $this->assertEquals(0, $connection->execute("SELECT COUNT(*) FROM {$quoted}")->fetchColumn(0));
            }
            $sniffer->dropTriggers();
            $this->assertSame([], $sniffer->getTriggers());
            $sniffer->truncateDirtyTables();
            foreach ($tables as $table) {
                $this->assertContains(substr('dts_' . $table, 0, 63), $sniffer->getTriggers());
            }
        } finally {
            foreach ($tables as $table) {
                $quoted = $connection->getDriver()->quoteIdentifier($table);
                $connection->execute("DROP TABLE IF EXISTS {$quoted}");
            }
        }
    }
}
