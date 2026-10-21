<?php
declare(strict_types=1);

namespace App\Service;

use Cake\Datasource\FactoryLocator;
use Cake\Database\Driver\Postgres;
use Cake\I18n\FrozenTime;
use Closure;
use DateTimeImmutable;
use DateTimeZone;

final class WeeklyReportService
{
    public const TIMEZONE = 'Australia/Melbourne';
    public const EXCEL_MIME = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

    public function __construct(private ?DateTimeImmutable $asOf = null, private ?Closure $workbookBuilder = null)
    {
    }

    public function data(bool $lock = false): array
    {
        $asOf = ($this->asOf ?? DateTimeImmutable::createFromInterface(FrozenTime::now()))
            ->setTimezone(new DateTimeZone(self::TIMEZONE));
        // Calendar yesterday, not "24 hours ago": this also handles DST changes.
        $cutoff = $asOf->modify('-1 day')->setTime(17, 0);
        $utc = new DateTimeZone('UTC');
        $tables = FactoryLocator::get('Table');
        $ordersTable = $tables->get('Orders');
        $query = $ordersTable->find()
            ->where([
                'status' => 'submitted',
                'report_downloaded_at IS' => null,
                'submitted_at >' => $cutoff->setTimezone($utc)->format('Y-m-d H:i:s'),
                'submitted_at <=' => $asOf->setTimezone($utc)->format('Y-m-d H:i:s'),
            ])
            ->orderBy(['submitted_at' => 'DESC', 'id' => 'DESC']);
        // Concurrent Neon downloads wait, then recheck the unexported predicate.
        // This is used only inside downloadAndReset()'s transaction.
        if ($lock && $ordersTable->getConnection()->getDriver() instanceof Postgres) {
            $query->epilog('FOR UPDATE');
        }
        $orders = $query->all()->toList();
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

        return compact('orders', 'itemsByOrder', 'discountedCostsByOrder', 'cutoff', 'asOf');
    }

    public function xlsx(): string
    {
        return $this->buildWorkbook($this->data());
    }

    /** Generate first, then persist the reset for exactly that snapshot. Never delete orders. */
    public function downloadAndReset(): string
    {
        $orders = FactoryLocator::get('Table')->get('Orders');
        return $orders->getConnection()->transactional(function () use ($orders): string {
            $report = $this->data(lock: true);
            $bytes = $this->buildWorkbook($report);
            $ids = array_map(fn (object $order): int => (int)$order->id, $report['orders']);
            if ($ids !== []) {
                $orders->updateAll([
                    'report_downloaded_at' => $report['asOf']->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
                ], ['id IN' => $ids, 'report_downloaded_at IS' => null]);
            }
            return $bytes;
        });
    }

    private function buildWorkbook(array $report): string
    {
        return $this->workbookBuilder !== null
            ? ($this->workbookBuilder)($report)
            : (new WeeklyReportWorkbook())->build($report);
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
