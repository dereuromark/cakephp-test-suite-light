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
 */
namespace CakephpTestSuiteLight\Sniffer;

use Cake\Core\Exception\CakeException;
use Cake\Datasource\ConnectionInterface;

abstract class BaseTriggerBasedTableSniffer
{
    /**
     * The name of the table collecting dirty tables
     */
    const DIRTY_TABLE_COLLECTOR = 'test_suite_light_dirty_tables';

    const TRIGGER_PREFIX = 'dts_';

    const MODE_KEY = 'dirtyTableCollectorMode';

    /**
     * The dirty table collector is a permanent table
     */
    const PERM_MODE = 'PERM';

    /**
     * The dirty table collector is a temporary table
     */
    const TEMP_MODE = 'TEMP';

    /**
     * @var string
     */
    protected $mode;

    /**
     * @var ConnectionInterface
     */
    protected $connection;

    /**
     * @var array|null
     */
    protected $allTables;

    /**
     * Get triggers relative to the database dirty table collector
     * @return array
     */
    abstract public function getTriggers(): array;

    /**
     * Drop triggers relative to the database dirty table collector
     * @return void
     */
    abstract public function dropTriggers();

    /**
     * Create triggers on all tables listening to inserts
     * @return void
     */
    abstract public function createTriggers(): void;

    /**
     * Mark all tables except phinxlogs and dirty table collector as dirty.
     * @return void
     */
    abstract public function markAllTablesAsDirty(): void;

    /**
     * Create the procedure truncating the dirty tables.
     * @return void
     */
    abstract public function createTruncateDirtyTablesProcedure(): void;

    /**
     * Truncate all the dirty tables
     * @return void
     */
    abstract public function truncateDirtyTables(): void;

    /**
     * Drop tables passed as a parameter
     * @deprecated table dropping is not handled by this package anymore.
     * @param array $tables
     * @return void
     */
    abstract public function dropTables(array $tables): void;

    /**
     * BaseTableTruncator constructor.
     * @param ConnectionInterface $connection
     */
    public function __construct(ConnectionInterface $connection)
    {
        $this->mode = $this->getDefaultMode($connection);
        $this->setConnection($connection);
    }

    /**
     * Check that the dirty table collector exists
     *
     * @return bool
     */
    public function dirtyTableCollectorExists(): bool
    {
        return in_array(self::DIRTY_TABLE_COLLECTOR, $this->getAllTables(true));
    }

    /**
     * Get the sniffer started
     * Typically create the dirty table collector
     * Truncate all tables
     * Create the spying triggers
     * @return void
     */
    public function init(): void
    {
        if (!$this->dirtyTableCollectorExists()) {
            $this->createDirtyTableCollector();
            try {
                $this->createTriggers();
            } catch (\Throwable $e) {
                $message = $e->getMessage();
                $message .= ' ----- Please truncate your test schema manually and run the test suite again.';
                throw new \RuntimeException($message);
            }
            $this->createTruncateDirtyTablesProcedure();
            $this->markAllTablesAsDirty();
        }
    }

    /**
     * Get the name of the dirty table locator.
     *
     * @return string
     */
    public function collectorName(): string
    {
        return BaseTriggerBasedTableSniffer::DIRTY_TABLE_COLLECTOR;
    }

    /**
     * The length of the trigger name is limited to 64 do to MySQL constrain.
     *
     * @param string $tableName Name of the table to create a trigger on.
     * @return string
     */
    protected function getTriggerName(string $tableName): string
    {
        return substr(self::TRIGGER_PREFIX . $tableName, 0, 64);
    }

    /**
     * Find all tables where an insert happened
     * This also includes empty tables, where a delete
     * was performed after an insert
     * @return array
     */
    public function getDirtyTables(): array
    {
        try {
            return $this->fetchQuery("SELECT table_name FROM " . $this->collectorName());
        } catch (\Throwable $e) {
            $this->init();
            return $this->getDirtyTables();
        }
    }

    /**
     * Fetch all tables, excluded from Phinx related and the dirty table collector.
     *
     * @param bool $forceFetch
     * @return array
     */
    public function getAllTablesExceptPhinxlogsAndCollector(bool $forceFetch = false): array
    {
        $allTables = $this->getAllTablesExceptPhinxlogs($forceFetch);

        if (($key = array_search($this->collectorName(), $allTables)) !== false) {
            unset($allTables[$key]);
        }

        return $allTables;
    }

    /**
     * Create the table gathering the dirty tables
     * @return void
     */
    public function createDirtyTableCollector(): void
    {
        $temporary = $this->isInTempMode() ? 'TEMPORARY' : '';
        $dirtyTable = self::DIRTY_TABLE_COLLECTOR;

        $this->getConnection()->execute("
            CREATE {$temporary} TABLE IF NOT EXISTS {$dirtyTable} (
                table_name VARCHAR(128) PRIMARY KEY
            );
        ");
    }

    /**
     * Drop the table gathering the dirty tables
     * @return void
     */
    public function dropDirtyTableCollector()
    {
        $dirtyTable = self::DIRTY_TABLE_COLLECTOR;
        $this->getConnection()->execute("DROP TABLE IF EXISTS {$dirtyTable}");
    }

    /**
     * The dirty table collector is not temporary
     * @return void
     */
    public function activateMainMode(): void
    {
        $this->setMode(self::PERM_MODE);
    }

    /**
     * The dirty table collector is temporary
     * @return void
     */
    public function activateTempMode(): void
    {
        $this->setMode(self::TEMP_MODE);
    }

    /**
     * @param string $mode
     * @return void
     */
    public function setMode(string $mode): void
    {
        if ($this->mode === $mode) {
            return;
        }
        $this->mode = $mode;
        $this->restart();
    }

    /**
     * Get the mode on which the sniffer is running
     * This defines if the collector table is
     * temporary or not.
     *
     * @return string
     */
    public function getMode(): string
    {
        return $this->mode;
    }

    /**
     * Defines the default mode for the dirty table collector.
     *
     * @param ConnectionInterface $connection
     * @return string
     * @throws \Exception
     */
    public function getDefaultMode(ConnectionInterface $connection): string
    {
        $mode = $connection->config()[self::MODE_KEY] ?? self::PERM_MODE;
        if (!in_array($mode, [self::TEMP_MODE, self::PERM_MODE])) {
            $msg = self::MODE_KEY . ' can only be equal to ' . self::PERM_MODE . ' or ' . self::TEMP_MODE;
            throw new \Exception($msg);
        }

        return $mode;
    }

    /**
     * @return bool
     */
    public function isInTempMode(): bool
    {
        return ($this->getMode() === self::TEMP_MODE);
    }

    /**
     * @return bool
     */
    public function isInMainMode(): bool
    {
        return ($this->getMode() === self::PERM_MODE);
    }

    /**
     * @return ConnectionInterface
     */
    public function getConnection(): ConnectionInterface
    {
        return $this->connection;
    }

    /**
     * @param ConnectionInterface $connection
     */
    public function setConnection(ConnectionInterface $connection): void
    {
        $this->connection = $connection;
    }

    /**
     * Stop spying
     * @return void
     */
    public function shutdown(): void
    {
        $this->dropTriggers();
        $this->dropDirtyTableCollector();
    }

    /**
     * Stop spying and restart
     * Useful if the schema or the
     * dirty table collector changed
     * @return void
     */
    public function restart(): void
    {
        $this->shutdown();
        $this->init();
    }

    /**
     * Execute a query returning a list of table
     * In case where the query fails because the database queried does
     * not exist, an exception is thrown.
     *
     * @param string $query
     *
     * @return array
     */
    public function fetchQuery(string $query): array
    {
        try {
            $tables = $this->getConnection()->execute($query)->fetchAll();
            if ($tables === false) {
                throw new \Exception("Failing query: $query");
            }
        } catch (\Exception $e) {
            $name = $this->getConnection()->configName();
            $db = $this->getConnection()->config()['database'];
            throw new CakeException("Error in the connection '$name'. Is the database '$db' created and accessible?");
        }

        foreach ($tables as $i => $val) {
            $tables[$i] = $val[0] ?? $val['name'];
        }

        return $tables;
    }

    /**
     * @param string $glueBefore
     * @param array  $array
     * @param string $glueAfter
     *
     * @return string
     */
    public function implodeSpecial(string $glueBefore, array $array, string $glueAfter): string
    {
        return $glueBefore . implode($glueAfter.$glueBefore, $array) . $glueAfter;
    }

    /**
     * Get all tables except the phinx tables
     * * @param bool $forceFetch
     * @return array
     */
    public function getAllTablesExceptPhinxlogs(bool $forceFetch = false): array
    {
        $allTables = $this->getAllTables($forceFetch);
        foreach ($allTables as $i => $table) {
            if (strpos($table, 'phinxlog') !== false) {
                unset($allTables[$i]);
            }
        }
        return $allTables;
    }

    /**
     * @param bool $forceFetch
     * @return array
     */
    public function getAllTables(bool $forceFetch = false): array
    {
        if (is_null($this->allTables) || $forceFetch) {
            $this->allTables = $this->fetchAllTables();
        }
        return $this->allTables;
    }

    /**
     * List all tables
     * @return string[]
     */
    public function fetchAllTables(): array
    {
        return $this->getConnection()->getSchemaCollection()->listTables();
    }
}
