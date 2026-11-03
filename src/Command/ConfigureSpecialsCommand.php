<?php
declare(strict_types=1);

namespace App\Command;

use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\Datasource\FactoryLocator;
use Cake\I18n\FrozenTime;

/** Stage safely first; activate only after all web instances have the new pricing code. */
final class ConfigureSpecialsCommand extends Command
{
    public static function definitions(): array
    {
        return [
            ['name' => '2 piece specials', 'minimum_item_count' => 2, 'discount_type' => 'bundle', 'discount_value' => 3000, 'qualifying_item_name' => null],
            ['name' => '3 piece specials', 'minimum_item_count' => 3, 'discount_type' => 'bundle', 'discount_value' => 3200, 'qualifying_item_name' => null],
            ['name' => '5 shirt discount', 'minimum_item_count' => 5, 'discount_type' => 'unit_price', 'discount_value' => 600, 'qualifying_item_name' => 'Shirt'],
        ];
    }

    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        return $parser->setDescription('Stage or activate the two-piece, three-piece and five-shirt offers.')
            ->addOption('stage', ['boolean' => true, 'help' => 'Create new offers inactive; leave existing live offers unchanged.'])
            ->addOption('apply', ['boolean' => true, 'help' => 'Activate these three offers and delete every other discount rule. Deploy the new PHP and JavaScript first.']);
    }

    public function execute(Arguments $args, ConsoleIo $io): int
    {
        if ($args->getOption('stage') && $args->getOption('apply')) {
            $io->err('Choose --stage or --apply, not both.');
            return static::CODE_ERROR;
        }
        $rules = FactoryLocator::get('Table')->get('DiscountRules');
        $activate = (bool)$args->getOption('apply');
        if (!$args->getOption('stage') && !$activate) {
            $io->out(json_encode(self::definitions(), JSON_PRETTY_PRINT));
            $io->out('No changes made. Use --stage now, or --apply only after deploying the new pricing code.');
            return static::CODE_SUCCESS;
        }
        $ids = $rules->getConnection()->transactional(function () use ($rules, $activate): array {
            $ids = [];
            foreach (self::definitions() as $definition) {
                $matches = $rules->find()->where(['name' => $definition['name']])->all()->toList();
                if (count($matches) > 1) {
                    throw new \RuntimeException('Duplicate offer name: ' . $definition['name'] . '. Resolve duplicates before configuring offers.');
                }
                $entity = $matches[0] ?? $rules->newEmptyEntity();
                if (!$activate && !$entity->isNew() && $entity->active) {
                    foreach ($definition as $field => $value) {
                        if ($entity->get($field) != $value) {
                            throw new \RuntimeException('An active offer already uses the name ' . $definition['name'] . '. Stage refused to change live pricing; deploy first, then use --apply.');
                        }
                    }
                }
                $values = $definition + [
                    'active' => $activate || (!$entity->isNew() && (bool)$entity->active),
                    'created' => $entity->isNew() ? FrozenTime::now() : $entity->created,
                    'updated' => FrozenTime::now(),
                ];
                $rules->patchEntity($entity, $values);
                $rules->saveOrFail($entity);
                $ids[] = (int)$entity->id;
            }
            if ($activate) {
                $rules->deleteAll(['id NOT IN' => $ids]);
            }
            return $ids;
        });
        $io->success(($activate ? 'Activated the three new offers; all other discount rules have been deleted.' : 'Staged the three offers; existing live offers remain unchanged.') . ' IDs: ' . implode(', ', $ids));
        return static::CODE_SUCCESS;
    }
}
