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
namespace CakephpTestSuiteLight\Test\TestCase\Sniffer;


use Cake\Database\Driver\Sqlite;
use Cake\Datasource\ConnectionManager;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;
use CakephpTestMigrator\Migrator;
use CakephpTestMigrator\TestSchemaCleaner;
use CakephpTestSuiteLight\FixtureManager;
use CakephpTestSuiteLight\Sniffer\BaseTableSniffer;
use CakephpTestSuiteLight\Sniffer\SnifferRegistry;
use CakephpTestSuiteLight\Test\Traits\InsertTestDataTrait;
use TestApp\Model\Table\CitiesTable;
use TestApp\Model\Table\CountriesTable;
use TestApp\Test\Fixture\CitiesFixture;
use TestApp\Test\Fixture\CountriesFixture;

class TableSnifferDropTablesTest extends TestCase
{
    use InsertTestDataTrait;

    public $fixtures = [
        // The order here is important
        CountriesFixture::class,
        CitiesFixture::class,
    ];

    /**
     * @var BaseTableSniffer
     */
    public $TableSniffer;

    /**
     * @var FixtureManager
     */
    public $FixtureManager;

    /**
     * @var CountriesTable
     */
    public $Countries;

    /**
     * @var CitiesTable
     */
    public $Cities;

    public function setUp()
    {
        $this->FixtureManager = new FixtureManager();
        $this->TableSniffer = SnifferRegistry::get('test');
        $this->Countries = TableRegistry::getTableLocator()->get('Countries');
        $this->Cities = TableRegistry::getTableLocator()->get('Cities');
    }

    public function tearDown()
    {
        Migrator::migrate();

        unset($this->TableSniffer);
        unset($this->FixtureManager);
        unset($this->Countries);
        unset($this->Cities);
        ConnectionManager::drop('test_dummy_connection');

        parent::tearDown();
    }

    /**
     * After dropping all tables, only the dirty table collecting table should remain
     * This should never be dropped
     */
    public function testGetAllTablesAfterDroppingAll()
    {
        $this->assertSame(
            1,
            $this->Countries->find()->count()
        );
        $this->assertSame(
            1,
            $this->Cities->find()->count()
        );

        TestSchemaCleaner::dropSchema('test');

        $this->assertSame([], $this->TableSniffer->fetchAllTables());

        $this->FixtureManager->unload($this);
    }

    public function testDropWithForeignKeyCheckCities()
    {
        $this->activateForeignKeysOnSqlite();
        $this->createCity();
        TestSchemaCleaner::dropSchema(
            $this->TableSniffer->getConnection()->configName()
        );

        $this->expectException(\PDOException::class);
        $this->Cities->find()->first();
    }

    public function testDropWithForeignKeyCheckCountries()
    {
        $this->activateForeignKeysOnSqlite();
        $this->createCity();    // This will create a country too
        TestSchemaCleaner::dropSchema(
            $this->TableSniffer->getConnection()->configName()
        );

        $this->expectException(\PDOException::class);
        $this->Countries->find()->first();
    }

    private function activateForeignKeysOnSqlite() {
        $connection = ConnectionManager::get('test');
        if ($connection->config()['driver'] === Sqlite::class) {
            $connection->execute('PRAGMA foreign_keys = ON;' );
        }
    }
}
