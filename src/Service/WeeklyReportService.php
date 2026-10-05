<?php
declare(strict_types=1);

namespace App\Service;

use Cake\Datasource\FactoryLocator;

final class WeeklyReportService
{
    public function data(): array
    {
        $tables = FactoryLocator::get('Table');
        $orders = $tables->get('Orders')->find()
            ->where(['status' => 'submitted'])
            ->orderBy(['submitted_at' => 'DESC'])
            ->all()->toList();
        $orderIds = array_map(fn(object $order): int => (int)$order->id, $orders);
        $items = $orderIds === [] ? [] : $tables->get('OrderItems')->find()
            ->where(['order_id IN' => $orderIds])
            ->all()->toList();
        $itemsByOrder = [];
        foreach ($items as $item) {
            $itemsByOrder[(int)$item->order_id][] = $item;
        }
        $discountedCostsByOrder = [];
        foreach ($orders as $order) {
            $discountedCostsByOrder[(int)$order->id] = $this->discountedLineCosts(
                (int)$order->total_cents,
                $itemsByOrder[(int)$order->id] ?? [],
            );
        }

        return compact('orders', 'itemsByOrder', 'discountedCostsByOrder');
    }

    public function csv(): string
    {
        ['orders' => $orders, 'itemsByOrder' => $itemsByOrder, 'discountedCostsByOrder' => $discountedCostsByOrder] = $this->data();
        $stream = fopen('php://temp', 'r+');
        fputcsv($stream, ['Barrister', 'Submitted', 'Item type', 'Quantity', 'Cost', 'Discounted cost']);
        foreach ($orders as $order) {
            foreach ($itemsByOrder[(int)$order->id] ?? [] as $index => $item) {
                $discountedCents = $discountedCostsByOrder[(int)$order->id][$index] ?? 0;
                fputcsv($stream, [$order->email ?? '', $order->submitted_at?->format('Y-m-d'), $item->item_name, $item->quantity, number_format($item->line_total_cents / 100, 2, '.', ''), number_format($discountedCents / 100, 2, '.', '')]);
            }
        }
        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        return $csv ?: '';
    }

    /**
     * Shares an order-wide discount across its item rows without losing cents.
     * The resulting line totals always add up to the final billed request total.
     *
     * @param array<int, object> $items
     * @return array<int, int>
     */
    public function discountedLineCosts(int $totalCents, array $items): array
    {
        $subtotalCents = array_sum(array_map(fn(object $item): int => (int)$item->line_total_cents, $items));
        if ($subtotalCents <= 0) {
            return array_fill(0, count($items), 0);
        }

        $remainingSubtotal = $subtotalCents;
        $remainingTotal = max(0, min($totalCents, $subtotalCents));
        $discountedCosts = [];
        foreach ($items as $item) {
            $lineSubtotal = (int)$item->line_total_cents;
            $lineTotal = $remainingSubtotal === $lineSubtotal
                ? $remainingTotal
                : intdiv($lineSubtotal * $remainingTotal, $remainingSubtotal);
            $discountedCosts[] = $lineTotal;
            $remainingSubtotal -= $lineSubtotal;
            $remainingTotal -= $lineTotal;
        }

        return $discountedCosts;
    }
}
