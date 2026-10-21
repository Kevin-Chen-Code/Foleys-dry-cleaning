<?php
$this->assign('title', 'Weekly report');
$total = array_sum(array_map(fn($order) => (int)$order->total_cents, $orders));
?>

<section class="page-heading">
    <h1>Report download</h1>
    <p>Downloading Excel resets this data dump. For request history, see section All requests</p>
</section>

<section class="items-card">
    <div class="report-summary">
        <strong>Total billed: $<?= number_format($total / 100, 2) ?></strong>
        <?= $this->Form->create(null, ['url' => '/admin/weekly-report.xlsx', 'data-report-download' => true]) ?>
        <?= $this->Form->button('Download Excel', ['class' => 'csv-download']) ?>
        <?= $this->Form->end() ?>
    </div>
    <p data-report-download-status role="status" aria-live="polite" hidden></p>
    <table>
        <thead>
            <tr><th>Barrister name</th><th>Email address</th><th>Submitted</th><th>Item type</th><th>Quantity</th><th>Cost</th><th>Discounted cost</th></tr>
        </thead>
        <tbody>
        <?php foreach ($orders as $order): ?>
            <?php foreach ($itemsByOrder[(int)$order->id] ?? [] as $index => $item): ?>
                <tr>
                    <td><?= $order->barrister_name ? h($order->barrister_name) : '&mdash;' ?></td>
                    <td><?= $order->email ? h($order->email) : '&mdash;' ?></td>
                    <td><?= h($order->submitted_at?->setTimezone(new \DateTimeZone('Australia/Melbourne'))->format('d M Y, g:i a')) ?></td>
                    <td><?= h($item->item_name) ?></td>
                    <td><?= (int)$item->quantity ?></td>
                    <td>$<?= number_format($item->line_total_cents / 100, 2) ?></td>
                    <!-- UI: allocated values add up to the request's final discounted total. -->
                    <td>$<?= number_format(($discountedCostsByOrder[(int)$order->id][$index] ?? 0) / 100, 2) ?></td>
                </tr>
            <?php endforeach; ?>
        <?php endforeach; ?>
        <?php if (!$orders): ?>
            <tr><td colspan="7">No undownloaded requests in this reporting period.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</section>
<?= $this->Html->script('report-download', ['defer' => true]) ?>
