<?php
$this->assign('title', 'Weekly report');
$total = array_sum(array_map(fn($order) => (int)$order->total_cents, $orders));
?>

<section class="page-heading">
    <h1>Weekly report</h1>
    <p> Downloadable in csv format.</p>
</section>

<section class="items-card">
    <div class="report-summary">
        <strong>Total billed: $<?= number_format($total / 100, 2) ?></strong>
        <?= $this->Html->link('Download CSV', '/admin/weekly-report.csv', ['class' => 'csv-download', 'role' => 'button']) ?>
    </div>
    <table>
        <thead>
            <tr><th>Barrister</th><th>Submitted</th><th>Item type</th><th>Quantity</th><th>Cost</th><th>Discounted cost</th></tr>
        </thead>
        <tbody>
        <?php foreach ($orders as $order): ?>
            <?php foreach ($itemsByOrder[(int)$order->id] ?? [] as $index => $item): ?>
                <tr>
                    <td><?= $order->email ? h($order->email) : '&mdash;' ?></td>
                    <td><?= h($order->submitted_at?->format('d M Y')) ?></td>
                    <td><?= h($item->item_name) ?></td>
                    <td><?= (int)$item->quantity ?></td>
                    <td>$<?= number_format($item->line_total_cents / 100, 2) ?></td>
                    <!-- UI: allocated values add up to the request's final discounted total. -->
                    <td>$<?= number_format(($discountedCostsByOrder[(int)$order->id][$index] ?? 0) / 100, 2) ?></td>
                </tr>
            <?php endforeach; ?>
        <?php endforeach; ?>
        </tbody>
    </table>
</section>

<section class="items-card email-settings-card">
    <h2>Email settings</h2>
    <p>New requests are emailed to this mailbox. The weekly CSV report is sent on the selected day when the scheduled report command runs.</p>
    <!-- UI: visibly confirms the values that were last saved to the database. -->
    <?php if ($settings->mailbox): ?>
        <p><strong>Saved mailbox:</strong> <?= h($settings->mailbox) ?> &middot; <strong>Saved schedule:</strong> <?= h(ucfirst($settings->reporting_day)) ?></p>
    <?php endif; ?>
    <?= $this->Form->create($settings) ?>
    <?= $this->Form->hidden('id', ['value' => $settings->id]) ?>
    <?= $this->Form->control('mailbox', ['label' => 'Mailbox', 'type' => 'email', 'placeholder' => 'team@example.com', 'value' => $settings->mailbox]) ?>
    <?= $this->Form->control('reporting_day', ['label' => 'Reporting schedule', 'options' => [
        'monday' => 'Monday', 'tuesday' => 'Tuesday', 'wednesday' => 'Wednesday',
        'thursday' => 'Thursday', 'friday' => 'Friday', 'saturday' => 'Saturday', 'sunday' => 'Sunday',
    ], 'value' => $settings->reporting_day]) ?>
    <?= $this->Form->button('Save email settings', ['class' => 'button-primary']) ?>
    <?= $this->Form->end() ?>
</section>
