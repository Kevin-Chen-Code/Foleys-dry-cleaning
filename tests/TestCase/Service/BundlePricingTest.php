<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service;

use App\Command\ConfigureSpecialsCommand;
use App\Service\OrderPricingService;
use Cake\TestSuite\TestCase;

final class BundlePricingTest extends TestCase
{
    public function testSharedBundleExamplesAndExactLineCharges(): void
    {
        $cases = json_decode(file_get_contents(ROOT . '/tests/fixtures/bundle-pricing-cases.json'), true, 512, JSON_THROW_ON_ERROR);
        foreach ($cases as $case) {
            foreach ([true, false] as $enabled) {
                $result = (new OrderPricingService($enabled))->calculate($case['items'], $case['rules']);
                $this->assertSame($case['subtotal'], $result['subtotal'], $case['name']);
                $this->assertSame($enabled ? $case['total'] : $case['subtotal'], $result['total'], $case['name']);
                $this->assertSame($enabled ? $case['names'] : [], array_column($result['appliedDiscounts'], 'name'), $case['name']);
                $this->assertSame($result['total'], array_sum(array_column($result['lines'], 'discounted_total_cents')), $case['name']);
                $this->assertSame($result['discount'], array_sum(array_column($result['appliedDiscounts'], 'amount_cents')), $case['name']);
                if ($enabled && isset($case['line_totals'])) {
                    $expected = array_values(array_filter($case['line_totals'], static fn ($value, $index): bool => $case['items'][$index]['quantity'] > 0, ARRAY_FILTER_USE_BOTH));
                    $this->assertSame($expected, array_column($result['lines'], 'discounted_total_cents'), $case['name']);
                }
                if ($enabled && isset($case['unit_prices'])) {
                    $expected = array_values(array_filter($case['unit_prices'], static fn ($value, $index): bool => $case['items'][$index]['quantity'] > 0, ARRAY_FILTER_USE_BOTH));
                    $this->assertSame($expected, $result['unitPrices'], $case['name']);
                }
                if ($enabled && isset($case['applications'])) {
                    $this->assertSame($case['applications'], array_column($result['appliedDiscounts'], 'applications'), $case['name']);
                }
                foreach ($result['lines'] as $line) {
                    if (OrderPricingService::isBarRobe($line['item_name'])) {
                        $this->assertSame($line['line_total_cents'], $line['discounted_total_cents']);
                    }
                }
            }
        }
    }

    public function testCanonicalRulesAndQuantitySafetyLimit(): void
    {
        $definitions = ConfigureSpecialsCommand::definitions();
        $this->assertSame([3000, 3200, 600], array_column($definitions, 'discount_value'));
        $this->assertSame([2, 3, 5], array_column($definitions, 'minimum_item_count'));
        $result = (new OrderPricingService(true))->calculate([
            ['item_name' => 'Coat', 'unit_price_cents' => 4000, 'quantity' => 10000],
        ], $definitions);
        // 3,332 triples plus two pairs: 9,996 + 4 pieces.
        $this->assertSame(10668400, $result['total']);
        $this->expectException(\InvalidArgumentException::class);
        (new OrderPricingService(true))->calculate([
            ['item_name' => 'Coat', 'unit_price_cents' => 4000, 'quantity' => 10001],
        ], $definitions);
    }

    public function testEveryNonRobeGarmentQualifiesRegardlessOfItsLegacyCapFlag(): void
    {
        foreach (['Jabot', 'JABOTS'] as $name) {
            $oldCatalogueItem = ['name' => $name, 'price_cap_eligible' => false];
            $this->assertTrue(OrderPricingService::isBundleEligible($oldCatalogueItem));
            $this->assertFalse(OrderPricingService::isPriceCapEligible($oldCatalogueItem));
        }
        $this->assertFalse(OrderPricingService::isBundleEligible(['name' => 'Bar Robe', 'price_cap_eligible' => true]));
        foreach (['Coat', 'Blazer/Bar Jacket', 'Shirt', 'New garment type'] as $name) {
            foreach ([false, true] as $legacyFlag) {
                $this->assertTrue(OrderPricingService::isBundleEligible(['name' => $name, 'price_cap_eligible' => $legacyFlag]));
            }
        }
        $this->assertFalse(OrderPricingService::isPriceCapEligible(['name' => 'Coat', 'price_cap_eligible' => false]));
        $this->assertTrue(OrderPricingService::isPriceCapEligible(['name' => 'Coat', 'price_cap_eligible' => true]));
    }
}
