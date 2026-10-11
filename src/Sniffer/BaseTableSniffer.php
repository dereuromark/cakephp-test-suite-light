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


use Cake\Database\Connection;
use Cake\Datasource\ConnectionInterface;

abstract class BaseTableSniffer
{
    /**
     * @var ConnectionInterface
     */
    protected $connection;

    /**
     * @var array<string>|null
     */
    protected $allTables;

    /**
     * Truncate all the dirty tables
     * @return void
     */
    abstract public function truncateDirtyTables(): void;

    /**
     * Get all the dirty tables
     * @return array<string>
     */
    abstract public function getDirtyTables(): array;

    /**
     * Drop tables passed as a parameter
     * @deprecated table dropping is not handled by this package anymore.
     * @param array<string> $tables
     * @return void
     */
    abstract public function dropTables(array $tables): void;

    /**
     * BaseTableTruncator constructor.
     * @param ConnectionInterface $connection
     */
    public function __construct(ConnectionInterface $connection)
    {
        $this->setConnection($connection);
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
     * Get the sniffer started
     * Typically create the dirty table collector
     * Truncate all tables
     * Create the spying triggers
     * @return void
     */
    public function init(): void
    {
        $this->getAllTables(true);
    }

    /**
     * Stop spying
     * @return void
     */
    public function shutdown(): void
    {}

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
     * @return array<int, mixed>
     * @throws \Exception
     */
    public function fetchQuery(string $query): array
    {
        /** @var Connection $connection */
        $connection = $this->getConnection();
        try {
            $tables = $connection->execute($query)->fetchAll();
            if ($tables === false) {
                throw new \Exception("Failing query: $query");
            }
        } catch (\Exception $e) {
            $name = $connection->configName();
            $db = $connection->config()['database'];
            throw new \Exception("Error in the connection '$name'. Is the database '$db' created and accessible?");
        }

        $values = [];
        foreach ($tables as $val) {
            if (!is_array($val)) {
                throw new \UnexpectedValueException('Expected a database result row.');
            }
            $values[] = $val[0] ?? $val['name'];
        }

        return $values;
    }

    /**
     * Fetch database identifiers while preserving the scalar query API.
     *
     * @param string $query
     * @return list<string>
     */
    protected function fetchIdentifierQuery(string $query): array
    {
        $identifiers = [];
        foreach ($this->fetchQuery($query) as $identifier) {
            if (!is_string($identifier)) {
                throw new \UnexpectedValueException('Expected a database identifier.');
            }
            $identifiers[] = $identifier;
        }

        return $identifiers;
    }

    /**
     * @param string $glueBefore
     * @param array<string> $array
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
     * @return array<string>
     */
    public function getAllTablesExceptPhinxlogs(bool $forceFetch = false): array
    {
        $allTables = $this->getAllTables($forceFetch);
        foreach ($allTables as $i => $table) {
            if (strpos($table, 'phinxlog') !== false || $table === 'cake_migrations') {
                unset($allTables[$i]);
            }
        }
        return $allTables;
    }

    /**
     * @param bool $forceFetch
     * @return array<string>
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
        /** @var Connection $connection */
        $connection = $this->getConnection();
        return $connection->getSchemaCollection()->listTables();
    }
}
