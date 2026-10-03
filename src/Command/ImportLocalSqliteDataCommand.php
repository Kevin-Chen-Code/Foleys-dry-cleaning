<?php
declare(strict_types=1);

namespace App\Command;

use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Datasource\ConnectionManager;
use PDO;

final class ImportLocalSqliteDataCommand extends Command
{
    public function execute(Arguments $args, ConsoleIo $io): int
    {
        if (!$args->getOption('force')) {
            $io->err('Use --force to replace the fresh cloud seed data with the local SQLite data.');
            return static::CODE_ERROR;
        }

        $sqlite = new PDO('sqlite:' . ROOT . DS . 'tmp' . DS . 'foleys.sqlite');
        $sqlite->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $cloud = ConnectionManager::get('default');
        $tables = ['service_items', 'discount_rules', 'orders', 'order_items', 'email_settings'];
        $data = [];
        foreach ($tables as $table) {
            $data[$table] = $sqlite->query("SELECT * FROM {$table} ORDER BY id")->fetchAll();
        }
        foreach (['service_items', 'discount_rules'] as $table) {
            foreach ($data[$table] as &$row) {
                $row['active'] = (int)$row['active'] === 1 ? 'true' : 'false';
            }
            unset($row);
        }

        // A catalogue item may have been deleted locally after it appeared in an older request.
        // Retain such items as inactive records so their historical order lines remain valid.
        $catalogueIds = array_column($data['service_items'], 'id');
        foreach ($data['order_items'] as $orderItem) {
            if (!in_array($orderItem['service_item_id'], $catalogueIds, true)) {
                $data['service_items'][] = [
                    'id' => $orderItem['service_item_id'],
                    'name' => $orderItem['item_name'],
                    'description' => 'Historical item retained for existing requests.',
                    'unit_price_cents' => $orderItem['unit_price_cents'],
                    'active' => 'false',
                    'position' => 0,
                    'created' => $orderItem['created'],
                    'updated' => $orderItem['updated'],
                ];
                $catalogueIds[] = $orderItem['service_item_id'];
            }
        }

        $cloud->transactional(function () use ($cloud, $data, $tables): void {
            // Cloud data is freshly created by the migrations, so replace seed data with the local source of truth.
            foreach (array_reverse($tables) as $table) {
                $cloud->execute("DELETE FROM {$table}");
            }
            foreach ($tables as $table) {
                foreach ($data[$table] as $row) {
                    $cloud->insert($table, $row);
                }
            }
            foreach ($tables as $table) {
                $cloud->execute("SELECT setval(pg_get_serial_sequence('{$table}', 'id'), COALESCE((SELECT MAX(id) FROM {$table}), 1), true)");
            }
        });

        $io->success('Local SQLite data was copied to Neon.');
        return static::CODE_SUCCESS;
    }

    protected function buildOptionParser(\Cake\Console\ConsoleOptionParser $parser): \Cake\Console\ConsoleOptionParser
    {
        $parser->addOption('force', [
            'boolean' => true,
            'help' => 'Replace the newly created cloud seed data with local SQLite data.',
        ]);

        return $parser;
    }
}
