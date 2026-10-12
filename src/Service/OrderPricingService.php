<?php
declare(strict_types=1);

namespace App\Service;

/** Calculates prices server-side so browser values can never be trusted. */
final class OrderPricingService
{
    public static function isExcludedGarment(string $name): bool
    {
        return preg_match('/\b(?:bar[\s-]*robes?|jabots?)\b/i', $name) === 1;
    }

    public static function isPriceCapEligible(array $item): bool
    {
        return !self::isExcludedGarment((string)($item['item_name'] ?? $item['name'] ?? ''))
            && (bool)($item['price_cap_eligible'] ?? true);
    }

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
        $capDiscount = 0;
        $appliedDiscounts = [];
        $eligibleLines = array_filter($lines, self::isPriceCapEligible(...));
        $eligibleCount = array_sum(array_column($eligibleLines, 'quantity'));
        $eligibleSubtotal = array_sum(array_column($eligibleLines, 'line_total_cents'));
        foreach ($discountRules as $rule) {
            if ($rule['discount_type'] === 'price_cap') {
                if ($eligibleCount < (int)$rule['minimum_item_count']) {
                    continue;
                }
                // Multiple caps lower the same eligible subtotal, never discount it twice.
                $amount = max(0, $eligibleSubtotal - max(0, (int)$rule['discount_value']) - $capDiscount);
                if ($amount > 0) {
                    $capDiscount += $amount;
                    $discount += $amount;
                    $appliedDiscounts[] = ['name' => (string)$rule['name'], 'amount_cents' => $amount];
                }
                continue;
            }
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
