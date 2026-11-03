<?php
declare(strict_types=1);

namespace App\Test\TestCase\Command;

use App\Command\ConfigureSpecialsCommand;
use App\Model\Table\DiscountRulesTable;
use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Database\Connection;
use Cake\Database\Driver\Sqlite;
use Cake\Datasource\FactoryLocator;
use Cake\Datasource\Locator\LocatorInterface;
use Cake\ORM\Locator\TableLocator;
use Cake\TestSuite\TestCase;
use RuntimeException;

final class ConfigureSpecialsCommandTest extends TestCase
{
    private Connection $connection;
    private DiscountRulesTable $rules;
    private LocatorInterface $originalLocator;
    private array $stdout = [];
    private array $stderr = [];

    public function setUp(): void
    {
        parent::setUp();
        // The command uses only this private database, never app_local.php's connection.
        $this->connection = new Connection(['driver' => Sqlite::class, 'database' => ':memory:']);
        $this->connection->execute('CREATE TABLE discount_rules (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, minimum_item_count INTEGER, discount_type TEXT, discount_value INTEGER, qualifying_item_name TEXT, active BOOLEAN, created DATETIME, updated DATETIME)');
        $this->rules = new DiscountRulesTable(['connection' => $this->connection]);
        $locator = new TableLocator();
        $locator->set('DiscountRules', $this->rules);
        $this->originalLocator = FactoryLocator::get('Table');
        FactoryLocator::add('Table', $locator);
        $this->connection->insert('discount_rules', [
            'name' => 'Old active offer', 'minimum_item_count' => 3,
            'discount_type' => 'percentage', 'discount_value' => 20,
            'active' => 1, 'created' => '2026-10-01 00:00:00', 'updated' => '2026-10-01 00:00:00',
        ]);
        $this->connection->insert('discount_rules', [
            'name' => 'Old inactive offer', 'minimum_item_count' => 4,
            'discount_type' => 'fixed', 'discount_value' => 100,
            'active' => 0, 'created' => '2026-10-02 00:00:00', 'updated' => '2026-10-02 00:00:00',
        ]);
    }

    public function tearDown(): void
    {
        FactoryLocator::add('Table', $this->originalLocator);
        $this->connection->getDriver()->disconnect();
        parent::tearDown();
    }

    public function testStageCreatesThreeInactiveOffersWithoutChangingExistingLiveRules(): void
    {
        $before = $this->snapshot();
        $this->assertSame(Command::CODE_SUCCESS, $this->runCommand(['stage' => true]));

        $this->assertSame(5, $this->rules->find()->count());
        $this->assertSame($before, array_slice($this->snapshot(), 0, 2));
        $this->assertDefinitions(active: false);
        $this->assertStringContainsString('Staged', implode("\n", $this->stdout));
        $this->assertSame([], $this->stderr);
    }

    public function testRepeatedStageUpdatesInPlaceWithoutDuplicatingOffers(): void
    {
        $this->assertSame(Command::CODE_SUCCESS, $this->runCommand(['stage' => true]));
        $first = $this->snapshot();
        $this->assertSame(Command::CODE_SUCCESS, $this->runCommand(['stage' => true]));

        $this->assertSame($first, $this->snapshot());
        $this->assertSame(5, $this->rules->find()->count());
        $this->assertDefinitions(active: false);
    }

    public function testApplyActivatesExactDefinitionsAndDeletesAllOtherRules(): void
    {
        $this->assertSame(Command::CODE_SUCCESS, $this->runCommand(['stage' => true]));
        $beforeIds = array_column($this->snapshot(), 'id', 'name');
        $this->assertSame(Command::CODE_SUCCESS, $this->runCommand(['apply' => true]));

        $this->assertDefinitions(active: true);
        $this->assertSame(3, $this->rules->find()->where(['active' => true])->count());
        $this->assertSame(0, $this->rules->find()->where(['name IN' => ['Old active offer', 'Old inactive offer']])->count());
        $this->assertSame(3, $this->rules->find()->count());
        foreach ($this->snapshot() as $row) {
            $this->assertSame($beforeIds[$row['name']], $row['id']);
        }
        $this->assertStringContainsString('Activated', implode("\n", $this->stdout));
    }

    public function testApplyWithoutPriorStagingCreatesAndActivatesTheThreeOffers(): void
    {
        $this->assertSame(Command::CODE_SUCCESS, $this->runCommand(['apply' => true]));
        $this->assertDefinitions(active: true);
        $this->assertSame(3, $this->rules->find()->where(['active' => true])->count());
        $this->assertSame(3, $this->rules->find()->count());
    }

    public function testStageAfterActivationDoesNotSwitchOffTheNewOffers(): void
    {
        $this->runCommand(['apply' => true]);
        $before = $this->snapshot();
        $this->assertSame(Command::CODE_SUCCESS, $this->runCommand(['stage' => true]));

        $this->assertSame($before, $this->snapshot());
        $this->assertDefinitions(active: true);
    }

    public function testRepeatedApplyKeepsOnlyTheSameThreeCanonicalRules(): void
    {
        $this->assertSame(Command::CODE_SUCCESS, $this->runCommand(['apply' => true]));
        $before = $this->snapshot();
        $this->assertSame(Command::CODE_SUCCESS, $this->runCommand(['apply' => true]));
        $this->assertSame($before, $this->snapshot());
        $this->assertSame(3, $this->rules->find()->count());
        $this->assertDefinitions(active: true);
    }

    public function testNoFlagsOnlyPrintsDefinitionsWithoutChangingData(): void
    {
        $before = $this->snapshot();
        $this->assertSame(Command::CODE_SUCCESS, $this->runCommand());

        $this->assertSame($before, $this->snapshot());
        $this->assertSame(ConfigureSpecialsCommand::definitions(), json_decode($this->stdout[0], true, 512, JSON_THROW_ON_ERROR));
        $this->assertStringContainsString('No changes made', implode("\n", $this->stdout));
    }

    public function testStageAndApplyTogetherAreRejectedWithoutChangingData(): void
    {
        $before = $this->snapshot();
        $this->assertSame(Command::CODE_ERROR, $this->runCommand(['stage' => true, 'apply' => true]));

        $this->assertSame($before, $this->snapshot());
        $this->assertStringContainsString('not both', implode("\n", $this->stderr));
    }

    public function testDuplicateOfferNamesRollBackStagingAndActivation(): void
    {
        foreach (['stage', 'apply'] as $mode) {
            $this->connection->execute('DELETE FROM discount_rules WHERE name = ?', ['3 piece specials']);
            foreach ([3100, 3200] as $value) {
                $this->connection->insert('discount_rules', [
                    'name' => '3 piece specials', 'minimum_item_count' => 3,
                    'discount_type' => 'bundle', 'discount_value' => $value,
                    'active' => 0, 'created' => '2026-10-01 00:00:00', 'updated' => '2026-10-01 00:00:00',
                ]);
            }
            $before = $this->snapshot();
            $failure = null;
            try {
                $this->runCommand([$mode => true]);
            } catch (RuntimeException $exception) {
                $failure = $exception;
            }
            $this->assertInstanceOf(RuntimeException::class, $failure, 'Duplicate offer names must prevent configuration.');
            $this->assertStringContainsString('Duplicate offer name: 3 piece specials', $failure->getMessage());
            $this->assertSame($before, $this->snapshot(), 'No partial writes or old-offer deactivation after a failed ' . $mode);
            $this->assertSame(1, $this->rules->find()->where(['name' => 'Old active offer', 'active' => true])->count());
            $this->assertSame(0, $this->rules->find()->where(['name' => '2 piece specials'])->count());
        }
    }

    public function testStageRefusesToRewriteAnActiveOfferWithAConflictingDefinition(): void
    {
        $this->connection->insert('discount_rules', [
            'name' => '2 piece specials', 'minimum_item_count' => 2,
            'discount_type' => 'price_cap', 'discount_value' => 2800,
            'active' => 1, 'created' => '2026-10-01 00:00:00', 'updated' => '2026-10-01 00:00:00',
        ]);
        $before = $this->snapshot();
        $failure = null;
        try {
            $this->runCommand(['stage' => true]);
        } catch (RuntimeException $exception) {
            $failure = $exception;
        }
        $this->assertInstanceOf(RuntimeException::class, $failure, 'Staging must not rewrite an existing active offer before deployment.');
        $this->assertSame($before, $this->snapshot());
    }

    private function assertDefinitions(bool $active): void
    {
        $expected = [
            ['2 piece specials', 2, 'bundle', 3000, null],
            ['3 piece specials', 3, 'bundle', 3200, null],
            ['5 shirt discount', 5, 'unit_price', 600, 'Shirt'],
        ];
        foreach ($expected as [$name, $count, $type, $value, $garment]) {
            $matches = $this->rules->find()->where(['name' => $name])->all()->toList();
            $this->assertCount(1, $matches, $name);
            $rule = $matches[0];
            $this->assertSame($count, $rule->minimum_item_count, $name);
            $this->assertSame($type, $rule->discount_type, $name);
            $this->assertSame($value, $rule->discount_value, $name);
            $this->assertSame($garment, $rule->qualifying_item_name, $name);
            $this->assertSame($active, $rule->active, $name);
            $this->assertNotNull($rule->created, $name);
            $this->assertNotNull($rule->updated, $name);
        }
    }

    private function snapshot(): array
    {
        return $this->connection->execute('SELECT * FROM discount_rules ORDER BY id')->fetchAll('assoc');
    }

    private function runCommand(array $options = []): int
    {
        $io = $this->createStub(ConsoleIo::class);
        $recordOutput = function (array|string $message): int {
            $this->stdout[] = is_array($message) ? implode("\n", $message) : $message;
            return 0;
        };
        $io->method('out')->willReturnCallback($recordOutput);
        $io->method('success')->willReturnCallback($recordOutput);
        $io->method('err')->willReturnCallback(function (array|string $message): int {
            $this->stderr[] = is_array($message) ? implode("\n", $message) : $message;
            return 0;
        });
        return (new ConfigureSpecialsCommand())->execute(new Arguments([], $options + ['stage' => false, 'apply' => false], []), $io);
    }
}

