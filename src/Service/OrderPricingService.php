<?php
declare(strict_types=1);

namespace App\Service;

/** Calculates prices server-side so browser values can never be trusted. */
final class OrderPricingService
{
    /** @param array<int, array<string, mixed>> $items @param array<string, mixed>|null $discountRule */
    public function calculate(array $items, ?array $discountRule): array
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
        if ($discountRule !== null && $itemCount >= (int)$discountRule['minimum_item_count']) {
            $discount = match ($discountRule['discount_type']) {
                'percentage' => (int)round($subtotal * ((int)$discountRule['discount_value'] / 100)),
                'fixed' => (int)$discountRule['discount_value'],
                default => 0,
            };
        }
        $discount = min($subtotal, max(0, $discount));

        return compact('lines', 'subtotal', 'discount', 'itemCount') + [
            'total' => $subtotal - $discount,
        ];
    }
}
