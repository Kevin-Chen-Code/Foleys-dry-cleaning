<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service;

use App\Service\OrderPricingService;
use Cake\TestSuite\TestCase;

final class OrderPricingServiceTest extends TestCase
{
    public function testQuantityTierAndPriceCapScenarios(): void
    {
        $cases = json_decode(file_get_contents(ROOT . '/tests/fixtures/price-cap-cases.json'), true, 512, JSON_THROW_ON_ERROR);
        foreach ($cases as $case) {
            $originalRules = $case['rules'];
            $result = (new OrderPricingService(true))->calculate($case['items'], $case['rules']);
            $this->assertSame($case['subtotal'], $result['subtotal'], $case['name']);
            $this->assertSame($case['total'], $result['total'], $case['name']);
            $this->assertSame($case['subtotal'] - $case['total'], $result['discount'], $case['name']);
            $this->assertSame($case['names'], array_column($result['appliedDiscounts'], 'name'), $case['name']);
            $this->assertLessThanOrEqual(1, count($result['appliedDiscounts']), $case['name']);
            $this->assertSame($result['discount'], array_sum(array_column($result['appliedDiscounts'], 'amount_cents')), $case['name']);
            $this->assertSame($originalRules, $case['rules'], 'Calculating must not change stored rules: ' . $case['name']);
        }
    }

    public function testCalculatesLinesAndPercentageDiscountAtThreshold(): void
    {
        $result = (new OrderPricingService(true))->calculate([
            ['service_item_id' => 1, 'unit_price_cents' => 500, 'quantity' => 3],
            ['service_item_id' => 2, 'unit_price_cents' => 1000, 'quantity' => 3],
        ], [[
            'name' => 'Six item discount', 'minimum_item_count' => 6,
            'discount_type' => 'percentage', 'discount_value' => 10,
        ]]);

        $this->assertSame(6, $result['itemCount']);
        $this->assertSame(4500, $result['subtotal']);
        $this->assertSame(450, $result['discount']);
        $this->assertSame(4050, $result['total']);
    }

    public function testDoesNotApplyRuleBelowThreshold(): void
    {
        $result = (new OrderPricingService(true))->calculate([
            ['service_item_id' => 1, 'unit_price_cents' => 500, 'quantity' => 5],
        ], [[
            'name' => 'Six item discount', 'minimum_item_count' => 6,
            'discount_type' => 'fixed', 'discount_value' => 1000,
        ]]);

        $this->assertSame(2500, $result['subtotal']);
        $this->assertSame(0, $result['discount']);
        $this->assertSame(2500, $result['total']);
    }

    public function testAppliesOnlyTheLargestDiscountAtTheHighestEligibleTier(): void
    {
        $result = (new OrderPricingService(true))->calculate([
            ['service_item_id' => 1, 'unit_price_cents' => 1000, 'quantity' => 5],
        ], [
            ['name' => 'Five-item discount', 'minimum_item_count' => 5, 'discount_type' => 'percentage', 'discount_value' => 10],
            ['name' => 'Collection discount', 'minimum_item_count' => 5, 'discount_type' => 'fixed', 'discount_value' => 200],
        ]);

        $this->assertSame(500, $result['discount']);
        $this->assertSame(4500, $result['total']);
        $this->assertCount(1, $result['appliedDiscounts']);
        $this->assertSame('Five-item discount', $result['appliedDiscounts'][0]['name']);
    }

    public function testItemSpecificRuleOnlyCountsThatGarment(): void
    {
        $result = (new OrderPricingService(true))->calculate([
            ['item_name' => 'Shirt', 'unit_price_cents' => 1000, 'quantity' => 2],
            ['item_name' => 'Coat', 'unit_price_cents' => 1000, 'quantity' => 3],
        ], [[
            'name' => 'Shirt bundle', 'qualifying_item_name' => 'Shirt',
            'minimum_item_count' => 3, 'discount_type' => 'percentage', 'discount_value' => 15,
        ]]);

        $this->assertSame(0, $result['discount']);
    }

    public function testDiscountsArePausedByDefaultAndWhenExplicitlyDisabled(): void
    {
        $cases = json_decode(file_get_contents(ROOT . '/tests/fixtures/price-cap-cases.json'), true, 512, JSON_THROW_ON_ERROR);
        foreach ($cases as $case) {
            $originalRules = $case['rules'];
            foreach ([new OrderPricingService(), new OrderPricingService(false)] as $pricing) {
                $result = $pricing->calculate($case['items'], $case['rules']);
                $this->assertSame($case['subtotal'], $result['subtotal'], $case['name']);
                $this->assertSame($case['subtotal'], $result['total'], $case['name']);
                $this->assertSame(0, $result['discount'], $case['name']);
                $this->assertSame([], $result['appliedDiscounts'], $case['name']);
                $positiveItems = array_filter($case['items'], static fn (array $item): bool => $item['quantity'] > 0);
                $this->assertSame(array_sum(array_column($positiveItems, 'quantity')), $result['itemCount'], $case['name']);
                $this->assertCount(count($positiveItems), $result['lines'], $case['name']);
                $this->assertSame($originalRules, $case['rules'], 'Pausing must not change the stored rules: ' . $case['name']);
            }
        }
    }

    public function testDiscountPauseHandlesEmptyRequest(): void
    {
        $rules = [['name' => 'Fixed discount', 'minimum_item_count' => 1, 'discount_type' => 'fixed', 'discount_value' => 1500]];
        foreach ([new OrderPricingService(), new OrderPricingService(false)] as $pricing) {
            $result = $pricing->calculate([], $rules);
            $this->assertSame(0, $result['subtotal']);
            $this->assertSame(0, $result['discount']);
            $this->assertSame(0, $result['total']);
            $this->assertSame(0, $result['itemCount']);
            $this->assertSame([], $result['lines']);
            $this->assertSame([], $result['appliedDiscounts']);
        }
    }

    public function testEnablingDiscountsAgainUsesUnchangedRules(): void
    {
        $items = [['item_name' => 'Shirt', 'unit_price_cents' => 900, 'quantity' => 5]];
        $rules = [
            ['name' => 'Percentage', 'minimum_item_count' => 3, 'discount_type' => 'percentage', 'discount_value' => 30],
            ['name' => 'Five shirt fixed', 'minimum_item_count' => 5, 'discount_type' => 'fixed', 'discount_value' => 1500],
        ];
        $originalRules = $rules;
        $this->assertSame(4500, (new OrderPricingService(false))->calculate($items, $rules)['total']);
        $result = (new OrderPricingService(true))->calculate($items, $rules);
        $this->assertSame(3000, $result['total']);
        $this->assertSame(1500, $result['discount']);
        $this->assertSame(['Five shirt fixed'], array_column($result['appliedDiscounts'], 'name'));
        $this->assertSame($originalRules, $rules);
    }
    public function testIncreasingAndDecreasingQuantityReplacesTheWinningTier(): void
    {
        $pricing = new OrderPricingService(true);
        $rules = [
            ['name' => 'Five-item offer', 'minimum_item_count' => 5, 'discount_type' => 'fixed', 'discount_value' => 1200],
            ['name' => 'Three-item offer', 'minimum_item_count' => 3, 'discount_type' => 'fixed', 'discount_value' => 500],
        ];
        $originalRules = $rules;
        foreach ([[3, 2500, 'Three-item offer'], [5, 3800, 'Five-item offer'], [4, 3500, 'Three-item offer']] as [$quantity, $total, $name]) {
            $result = $pricing->calculate([
                ['item_name' => 'Blouse', 'unit_price_cents' => 1000, 'quantity' => $quantity],
            ], $rules);
            $this->assertSame($total, $result['total']);
            $this->assertSame([$name], array_column($result['appliedDiscounts'], 'name'));
            $this->assertSame($originalRules, $rules);
        }
    }
}
