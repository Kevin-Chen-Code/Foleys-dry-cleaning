<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service;

use App\Service\OrderPricingService;
use Cake\TestSuite\TestCase;

final class OrderPricingServiceTest extends TestCase
{
    public function testCalculatesLinesAndPercentageDiscountAtThreshold(): void
    {
        $result = (new OrderPricingService())->calculate([
            ['service_item_id' => 1, 'unit_price_cents' => 500, 'quantity' => 3],
            ['service_item_id' => 2, 'unit_price_cents' => 1000, 'quantity' => 3],
        ], [
            'minimum_item_count' => 6,
            'discount_type' => 'percentage',
            'discount_value' => 10,
        ]);

        $this->assertSame(6, $result['itemCount']);
        $this->assertSame(4500, $result['subtotal']);
        $this->assertSame(450, $result['discount']);
        $this->assertSame(4050, $result['total']);
    }

    public function testDoesNotApplyRuleBelowThreshold(): void
    {
        $result = (new OrderPricingService())->calculate([
            ['service_item_id' => 1, 'unit_price_cents' => 500, 'quantity' => 5],
        ], [
            'minimum_item_count' => 6,
            'discount_type' => 'fixed',
            'discount_value' => 1000,
        ]);

        $this->assertSame(2500, $result['subtotal']);
        $this->assertSame(0, $result['discount']);
        $this->assertSame(2500, $result['total']);
    }
}
