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

        return compact('orders', 'itemsByOrder');
    }

    public function csv(): string
    {
        ['orders' => $orders, 'itemsByOrder' => $itemsByOrder] = $this->data();
        $stream = fopen('php://temp', 'r+');
        fputcsv($stream, ['Barrister', 'Submitted', 'Item type', 'Quantity', 'Cost']);
        foreach ($orders as $order) {
            foreach ($itemsByOrder[(int)$order->id] ?? [] as $item) {
                fputcsv($stream, [$order->barrister_name, $order->submitted_at?->format('Y-m-d'), $item->item_name, $item->quantity, number_format($item->line_total_cents / 100, 2, '.', '')]);
            }
        }
        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        return $csv ?: '';
    }
}
