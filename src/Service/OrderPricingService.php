<?php
declare(strict_types=1);

namespace App\Service;

/** Calculates prices server-side so browser values can never be trusted. */
final class OrderPricingService
{
    /** @param array<int, array<string, mixed>> $items @param array<int, array<string, mixed>> $discountRules */
    public function calculate(array $items, array $discountRules = []): array
    {
        $lines = [];
        $subtotal = 0;
        $itemCount = 0;

        foreach ($items as $item) {
            $quantity = (int)$item['quantity'];
            if ($quantity < 1) {
                continue;
            }
            $lineTotal = (int)$item['unit_price_cents'] * $quantity;
            $lines[] = $item + ['line_total_cents' => $lineTotal];
            $subtotal += $lineTotal;
            $itemCount += $quantity;
        }

        $discount = 0;
        $appliedDiscounts = [];
        foreach ($discountRules as $rule) {
            $qualifyingCount = $itemCount;
            if (!empty($rule['qualifying_item_name'])) {
                $qualifyingCount = array_sum(array_map(
                    fn (array $item): int => $item['item_name'] === $rule['qualifying_item_name'] ? (int)$item['quantity'] : 0,
                    $lines,
                ));
            }
            if ($qualifyingCount < (int)$rule['minimum_item_count']) {
                continue;
            }
            $amount = match ($rule['discount_type']) {
                'percentage' => (int)round($subtotal * ((int)$rule['discount_value'] / 100)),
                'fixed' => (int)$rule['discount_value'],
                default => 0,
            };
            if ($amount > 0) {
                $appliedDiscounts[] = ['name' => (string)$rule['name'], 'amount_cents' => $amount];
                $discount += $amount;
            }
        }
        $discount = min($subtotal, max(0, $discount));

        return compact('lines', 'subtotal', 'discount', 'itemCount', 'appliedDiscounts') + [
            'total' => $subtotal - $discount,
        ];
    }
}
