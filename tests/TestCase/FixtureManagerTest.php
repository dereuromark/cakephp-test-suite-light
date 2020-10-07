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
namespace CakephpTestSuiteLight\Test\TestCase;


use Cake\Core\Configure;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;
use CakephpTestSuiteLight\FixtureManager;
use CakephpTestSuiteLight\Sniffer\MysqlTableSniffer;
use TestApp\Model\Table\CountriesTable;

class FixtureManagerTest extends TestCase
{
    /**
     * @var FixtureManager
     */
    public $FixtureManager;

    /**
     * @var CountriesTable
     */
    public $Countries;

    public function setUp()
    {
        $this->FixtureManager = new FixtureManager();
        $this->Countries = TableRegistry::getTableLocator()->get('Countries');
    }

    public function tearDown()
    {
        unset($this->FixtureManager);
        unset($this->Countries);
    }

    public function testTablePopulation()
    {
        $country = $this->Countries->newEntity(['name' => 'foo']);
        $this->Countries->saveOrFail($country);

        $this->assertEquals(
            1,
            $this->Countries->find()->count()
        );
        $this->assertEquals(
            1,
            $this->Countries->find()->firstOrFail()->id,
            'The id should be equal to 1. There might be an error in the truncation of the authors table, or of the tables in general'
        );
    }

    public function testTablesEmptyOnStart()
    {
        $tables = ['cities', 'countries'];

        foreach ($tables as $table) {
            $Table = TableRegistry::getTableLocator()->get($table);
            $this->assertEquals(
                0,
                $Table->find()->count(),
                'Make sure that both tables were created by fixture loading by a previous test.'
            );
        }
    }

    public function testConnectionIsTest()
    {
        $this->assertEquals(
            'test',
            $this->Countries->getConnection()->config()['name']
        );
    }

    public function testLoadBaseConfig()
    {
        $expected = MysqlTableSniffer::class;
        $this->FixtureManager->loadConfig();
        $conf = Configure::readOrFail('TestSuiteLightSniffers.' . \Cake\Database\Driver\Mysql::class);
        $this->assertEquals($expected, $conf);
    }

    public function testLoadCustomConfig()
    {
        $expected = '\testTableSniffer';
        $this->FixtureManager->loadConfig();
        $conf = Configure::readOrFail('TestSuiteLightSniffers.\testDriver');
        $this->assertEquals($expected, $conf);
    }
}