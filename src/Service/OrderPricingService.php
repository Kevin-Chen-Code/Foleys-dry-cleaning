<?php
declare(strict_types=1);

namespace App\Service;

use InvalidArgumentException;

/** Prices are calculated independently of the browser; each piece belongs to at most one bundle. */
final class OrderPricingService
{
    public const MAX_ITEM_COUNT = 10000;

    public function __construct(private bool $discountsEnabled = false) {}

    public static function isBarRobe(string $name): bool
    {
        return preg_match('/\bbar[\s-]*robes?\b/i', $name) === 1;
    }

    public static function isExcludedGarment(string $name): bool
    {
        return self::isBarRobe($name) || preg_match('/\bjabots?\b/i', $name) === 1;
    }

    public static function isPriceCapEligible(array $item): bool
    {
        return !self::isExcludedGarment((string)($item['item_name'] ?? $item['name'] ?? ''))
            && (bool)($item['price_cap_eligible'] ?? true);
    }

    /** Every garment except a bar robe qualifies; legacy cap flags do not restrict piece specials. */
    public static function isBundleEligible(array $item): bool
    {
        $name = (string)($item['item_name'] ?? $item['name'] ?? '');
        return !self::isBarRobe($name);
    }

    private static function garmentKey(string $name): string
    {
        $name = mb_strtolower(trim($name));
        return $name === 'shirts' ? 'shirt' : $name;
    }

    public function calculate(array $items, array $discountRules = []): array
    {
        $lines = [];
        $subtotal = $itemCount = 0;
        foreach ($items as $item) {
            $quantity = (int)$item['quantity'];
            if ($quantity < 1) { continue; }
            if ($quantity > self::MAX_ITEM_COUNT - $itemCount) {
                throw new InvalidArgumentException('A request may contain at most 10,000 garments.');
            }
            $lineTotal = max(0, (int)$item['unit_price_cents']) * $quantity;
            $lines[] = array_replace($item, ['line_total_cents' => $lineTotal]);
            $subtotal += $lineTotal;
            $itemCount += $quantity;
        }
        $lineTotals = array_column($lines, 'line_total_cents');
        $unitPrices = array_map(static fn (array $line): int => max(0, (int)$line['unit_price_cents']), $lines);
        $rules = array_values(array_filter($discountRules, static fn (array $r): bool => !array_key_exists('active', $r) || (bool)$r['active']));
        $unitRules = [];
        if ($this->discountsEnabled) {
            foreach ($rules as $r => $rule) {
                if ($rule['discount_type'] !== 'unit_price' || empty($rule['qualifying_item_name'])) { continue; }
                $key = self::garmentKey((string)$rule['qualifying_item_name']);
                $count = 0;
                foreach ($lines as $line) {
                    if (!self::isBarRobe((string)($line['item_name'] ?? '')) && self::garmentKey((string)($line['item_name'] ?? '')) === $key) {
                        $count += (int)$line['quantity'];
                    }
                }
                if ($count < (int)$rule['minimum_item_count']) { continue; }
                foreach ($lines as $i => $line) {
                    $target = max(0, (int)$rule['discount_value']);
                    if (self::isBarRobe((string)($line['item_name'] ?? '')) || self::garmentKey((string)($line['item_name'] ?? '')) !== $key) { continue; }
                    $winsTie = isset($unitRules[$i], $rule['id'], $rules[$unitRules[$i]]['id'])
                        && $target === $unitPrices[$i] && (int)$rule['id'] < (int)$rules[$unitRules[$i]]['id'];
                    if ($target < $unitPrices[$i] || $winsTie) {
                        $unitPrices[$i] = $target;
                        $unitRules[$i] = $r;
                    }
                }
            }
        }
        $units = [];
        foreach ($lines as $i => $line) {
            $lineTotals[$i] = $unitPrices[$i] * (int)$line['quantity'];
            if ($this->discountsEnabled && self::isBundleEligible($line)) {
                for ($q = 0; $q < (int)$line['quantity']; $q++) {
                    $units[] = ['line' => $i, 'price' => $unitPrices[$i]];
                }
            }
        }
        usort($units, static fn (array $a, array $b): int => ($b['price'] <=> $a['price']) ?: ($a['line'] <=> $b['line']));
        $bundleRules = $this->discountsEnabled ? array_filter($rules, static fn (array $r): bool => $r['discount_type'] === 'bundle'
            && (int)$r['minimum_item_count'] >= 2 && (int)$r['minimum_item_count'] <= self::MAX_ITEM_COUNT) : [];
        $n = count($units);
        $cost = array_fill(0, $n + 1, PHP_INT_MAX);
        $choice = [];
        $cost[0] = 0;
        $baseline = $total = array_sum($lineTotals);
        $bestCovered = $prefix = 0;
        for ($k = 1; $k <= $n; $k++) {
            $prefix += $units[$k - 1]['price'];
            foreach ($bundleRules as $r => $rule) {
                $size = (int)$rule['minimum_item_count'];
                if ($k >= $size && $cost[$k - $size] !== PHP_INT_MAX) {
                    $candidate = $cost[$k - $size] + max(0, (int)$rule['discount_value']);
                    if ($candidate < $cost[$k]) { $cost[$k] = $candidate; $choice[$k] = $r; }
                }
            }
            $candidate = $cost[$k] === PHP_INT_MAX ? PHP_INT_MAX : $baseline - $prefix + $cost[$k];
            if ($candidate < $total) { $total = $candidate; $bestCovered = $k; }
        }
        $applied = [];
        $bundledPerLine = [];
        for ($k = $bestCovered; $k > 0;) {
            $r = $choice[$k];
            $rule = $rules[$r];
            $size = (int)$rule['minimum_item_count'];
            $group = array_slice($units, $k - $size, $size);
            $remainingSubtotal = array_sum(array_column($group, 'price'));
            $remainingTotal = max(0, (int)$rule['discount_value']);
            $saving = 0;
            foreach ($group as $unit) {
                $i = $unit['line'];
                $charge = $remainingSubtotal === $unit['price'] ? $remainingTotal : self::allocate($unit['price'], $remainingTotal, $remainingSubtotal);
                $lineTotals[$i] += $charge - $unit['price'];
                $saving += max(0, (int)$lines[$i]['unit_price_cents']) - $charge;
                $bundledPerLine[$i] = ($bundledPerLine[$i] ?? 0) + 1;
                $remainingSubtotal -= $unit['price'];
                $remainingTotal -= $charge;
            }
            self::addApplied($applied, $r, $rule, $saving, 1);
            $k -= $size;
        }
        foreach ($unitRules as $i => $r) {
            $count = (int)$lines[$i]['quantity'] - ($bundledPerLine[$i] ?? 0);
            self::addApplied($applied, $r, $rules[$r], (max(0, (int)$lines[$i]['unit_price_cents']) - $unitPrices[$i]) * $count, $count);
        }
        // Legacy offers are alternatives, not additional discounts on top of bundles.
        $legacyTier = $legacySaving = 0;
        $legacyRule = null;
        $legacyIndices = [];
        if ($this->discountsEnabled) {
            foreach ($rules as $rule) {
                if (!in_array($rule['discount_type'], ['percentage', 'fixed', 'price_cap'], true)) { continue; }
                $indices = [];
                $count = 0;
                $qualifier = empty($rule['qualifying_item_name']) ? null : self::garmentKey((string)$rule['qualifying_item_name']);
                foreach ($lines as $i => $line) {
                    if (self::isBarRobe((string)($line['item_name'] ?? '')) || ($rule['discount_type'] === 'price_cap' && !self::isPriceCapEligible($line))) { continue; }
                    $indices[] = $i;
                    if ($rule['discount_type'] === 'price_cap' || $qualifier === null || self::garmentKey((string)($line['item_name'] ?? '')) === $qualifier) {
                        $count += (int)$line['quantity'];
                    }
                }
                $basis = array_sum(array_map(static fn (int $i): int => (int)$lines[$i]['line_total_cents'], $indices));
                $tier = (int)$rule['minimum_item_count'];
                if ($count < $tier) { continue; }
                $saving = match ($rule['discount_type']) {
                    'percentage' => (int)round($basis * ((int)$rule['discount_value'] / 100)),
                    'fixed' => (int)$rule['discount_value'],
                    'price_cap' => $basis - max(0, (int)$rule['discount_value']),
                };
                $saving = min($basis, max(0, $saving));
                if ($saving <= 0 || $tier < $legacyTier) { continue; }
                $winsTie = $tier === $legacyTier && $saving === $legacySaving && isset($rule['id'], $legacyRule['id'])
                    && (int)$rule['id'] < (int)$legacyRule['id'];
                if ($tier > $legacyTier || $saving > $legacySaving || $winsTie) {
                    $legacyTier = $tier; $legacySaving = $saving; $legacyRule = $rule; $legacyIndices = $indices;
                }
            }
        }
        if ($legacyRule !== null && $subtotal - $legacySaving < $total) {
            $lineTotals = array_column($lines, 'line_total_cents');
            $unitPrices = array_map(static fn (array $line): int => max(0, (int)$line['unit_price_cents']), $lines);
            $remainingSubtotal = array_sum(array_map(static fn (int $i): int => $lineTotals[$i], $legacyIndices));
            $remainingTotal = $remainingSubtotal - $legacySaving;
            foreach ($legacyIndices as $i) {
                $price = $lineTotals[$i];
                $lineTotals[$i] = $remainingSubtotal === $price ? $remainingTotal : self::allocate($price, $remainingTotal, $remainingSubtotal);
                $remainingSubtotal -= $price;
                $remainingTotal -= $lineTotals[$i];
            }
            $total = $subtotal - $legacySaving;
            $applied = [['name' => (string)$legacyRule['name'], 'amount_cents' => $legacySaving, 'applications' => 1]];
        }
        foreach ($lines as $i => &$line) { $line['discounted_total_cents'] = $lineTotals[$i]; }
        unset($line);
        $discount = $subtotal - $total;
        ksort($applied);
        $appliedDiscounts = array_values($applied);
        return compact('lines', 'subtotal', 'discount', 'total', 'itemCount', 'appliedDiscounts', 'unitPrices');
    }

    /** Exact proportional allocation without a potentially overflowing price * total product. */
    private static function allocate(int $weight, int $total, int $basis): int
    {
        if ($weight === 0 || $total === 0) { return 0; }
        // All allocations here reduce a bill, so total <= basis. Quotient/remainder
        // long multiplication avoids large intermediate products using integer arithmetic.
        $quotient = $remainder = 0;
        $addQuotient = intdiv($total, $basis);
        $addRemainder = $total % $basis;
        for ($n = $weight; $n > 0; $n = intdiv($n, 2)) {
            if ($n % 2) {
                $quotient += $addQuotient;
                $remainder += $addRemainder;
                if ($remainder >= $basis) { $quotient++; $remainder -= $basis; }
            }
            $addQuotient *= 2;
            $addRemainder *= 2;
            if ($addRemainder >= $basis) { $addQuotient++; $addRemainder -= $basis; }
        }
        return $quotient;
    }

    private static function addApplied(array &$applied, int $index, array $rule, int $saving, int $count): void
    {
        if ($saving <= 0 || $count < 1) { return; }
        $applied[$index] ??= ['name' => (string)$rule['name'], 'amount_cents' => 0, 'applications' => 0];
        $applied[$index]['amount_cents'] += $saving;
        $applied[$index]['applications'] += $count;
    }
}

