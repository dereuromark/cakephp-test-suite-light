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


use Cake\Database\Exception;
use Cake\Datasource\ConnectionInterface;

abstract class BaseTriggerBasedTableSniffer extends BaseTableSniffer
{
    /**
     * The name of the table collecting dirty tables
     */
    const DIRTY_TABLE_COLLECTOR = 'test_suite_light_dirty_tables';

    const TRIGGER_PREFIX = 'dirty_table_spy_';

    const MAIN_MODE = 'MAIN_MODE';

    const TEMP_MODE = 'TEMP_MODE';

    /**
     * @var string
     */
    protected $mode;

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
    abstract public function createTriggers();

    /**
     * Mark all tables except phinxlogs as dirty
     * @return void
     */
    abstract public function markAllTablesAsDirty();

    /**
     * BaseTableTruncator constructor.
     * @param ConnectionInterface $connection
     */
    public function __construct(ConnectionInterface $connection)
    {
        $this->mode = self::TEMP_MODE;
        parent::__construct($connection);
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
    public function setConnection(ConnectionInterface $connection)
    {
        $this->connection = $connection;
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
            return $this->fetchQuery("SELECT table_name FROM " . self::DIRTY_TABLE_COLLECTOR);
        } catch (\Exception $e) {
            $this->restart();
            return $this->getAllTablesExceptPhinxlogs(true);
        }
    }

    /**
     * Create the table gathering the dirty tables
     * @return void
     */
    public function createDirtyTableCollector()
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
     * The dirty table collector being temporary,
     * ensure that all tables are clean when starting the suite
     * @return void
     */
    public function cleanAllTables()
    {
        $this->markAllTablesAsDirty();
        $this->truncateDirtyTables();
    }

    /**
     * The dirty table collector is not temporary
     * @return void
     */
    public function activateMainMode()
    {
        $this->setMode(self::MAIN_MODE);
    }

    /**
     * The dirty table collector is temporary
     * @return void
     */
    public function activateTempMode()
    {
        $this->setMode(self::TEMP_MODE);
    }

    /**
     * @param string $mode
     * @return void
     */
    public function setMode(string $mode)
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
     * temporary or not
     * @return string
     */
    public function getMode(): string
    {
        if (!$this->implementsTriggers()) {
            return '';
        }
        return $this->mode;
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
        return ($this->getMode() === self::MAIN_MODE);
    }
}