<?php
declare(strict_types=1);

namespace App\Test\TestCase\Model\Table;

use App\Model\Table\DiscountRulesTable;
use Cake\TestSuite\TestCase;

final class DiscountRulesTableTest extends TestCase
{
    public function testDollarEntryConvertsExactlyToStoredCents(): void
    {
        foreach (['fixed', 'price_cap'] as $type) {
            foreach (['10' => 1000, '10.25' => 1025, '0.29' => 29, '30.00' => 3000, '0' => 0] as $amount => $expected) {
                $this->assertSame($expected, DiscountRulesTable::formValueToStorage((string)$amount, $type));
            }
        }
        $this->assertSame(15, DiscountRulesTable::formValueToStorage('15', 'percentage'));
    }

    public function testInvalidOrOutOfRangeValuesAreRejected(): void
    {
        foreach (['-10', '10.255', 'text', '1e3', '', ['10'], '99999999999999'] as $amount) {
            $this->assertNull(DiscountRulesTable::formValueToStorage($amount, 'fixed'));
        }
        $this->assertNull(DiscountRulesTable::formValueToStorage('101', 'percentage'));
        $this->assertNull(DiscountRulesTable::formValueToStorage('10.5', 'percentage'));
    }
}
