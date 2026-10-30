<?php
declare(strict_types=1);

namespace App\Service;

/** Calculates prices server-side so browser values can never be trusted. */
final class OrderPricingService
{
    public function __construct(private bool $discountsEnabled = false)
    {
    }

    public static function isExcludedGarment(string $name): bool
    {
        return preg_match('/\b(?:bar[\s-]*robes?|jabots?)\b/i', $name) === 1;
    }

    public static function isPriceCapEligible(array $item): bool
    {
        return !self::isExcludedGarment((string)($item['item_name'] ?? $item['name'] ?? ''))
            && (bool)($item['price_cap_eligible'] ?? true);
    }

    private static function garmentKey(string $name): string
    {
        $name = mb_strtolower(trim($name));
        return $name === 'shirts' ? 'shirt' : $name;
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

        // The application explicitly enables pricing; keep the pause override available.
        if (!$this->discountsEnabled) {
            return compact('lines', 'subtotal', 'itemCount') + [
                'discount' => 0, 'total' => $subtotal, 'appliedDiscounts' => [],
            ];
        }

        $eligibleLines = array_filter($lines, self::isPriceCapEligible(...));
        $eligibleCount = array_sum(array_column($eligibleLines, 'quantity'));
        $eligibleSubtotal = array_sum(array_column($eligibleLines, 'line_total_cents'));
        $highestTier = 0;
        $discount = 0;
        $winningRule = null;
        foreach ($discountRules as $rule) {
            if (array_key_exists('active', $rule) && !$rule['active']) {
                continue;
            }
            if (!in_array($rule['discount_type'], ['percentage', 'fixed', 'price_cap'], true)) {
                continue;
            }
            $qualifyingCount = $itemCount;
            if ($rule['discount_type'] === 'price_cap') {
                $qualifyingCount = $eligibleCount;
            } elseif (!empty($rule['qualifying_item_name'])) {
                $garment = self::garmentKey((string)$rule['qualifying_item_name']);
                $qualifyingCount = array_sum(array_map(
                    static fn (array $item): int => self::garmentKey((string)($item['item_name'] ?? '')) === $garment
                        ? (int)$item['quantity'] : 0,
                    $lines,
                ));
            }
            $tier = (int)$rule['minimum_item_count'];
            if ($qualifyingCount < $tier || $tier < $highestTier) {
                continue;
            }
            // Qualification, not savings, decides the tier: five replaces three even
            // when the five-item offer saves less. Removing items restores the lower tier.
            if ($tier > $highestTier) {
                $highestTier = $tier;
                $discount = 0;
                $winningRule = null;
            }
            $amount = match ($rule['discount_type']) {
                'percentage' => (int)round($subtotal * ((int)$rule['discount_value'] / 100)),
                'fixed' => (int)$rule['discount_value'],
                'price_cap' => $eligibleSubtotal - max(0, (int)$rule['discount_value']),
                default => 0,
            };
            $amount = min($subtotal, max(0, $amount));
            // One best offer at this tier. Equal savings use the oldest ID, not query order.
            $winsTie = $amount > 0 && $amount === $discount && isset($rule['id'], $winningRule['id'])
                && (int)$rule['id'] < (int)$winningRule['id'];
            if ($amount > $discount || $winsTie) {
                $discount = $amount;
                $winningRule = $rule;
            }
        }
        $appliedDiscounts = $winningRule === null ? [] : [
            ['name' => (string)$winningRule['name'], 'amount_cents' => $discount],
        ];

        return compact('lines', 'subtotal', 'discount', 'itemCount', 'appliedDiscounts') + [
            'total' => $subtotal - $discount,
        ];
    }
}
