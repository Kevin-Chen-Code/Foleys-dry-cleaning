<?php
$this->assign('title', 'Weekly report');
$total = array_sum(array_map(fn($order) => (int)$order->total_cents, $orders));
?>

<section class="page-heading">
    <h1>Daily report</h1>
    <p> All daily requests submitted after <?= h($cutoff->format('d M Y, g:i a')) ?>,
        through <?= h($asOf->format('d M Y, g:i a')) ?> (Melbourne time). Downloadable as Excel sheet</p>
</section>

<section class="items-card">
    <div class="report-summary">
        <strong>Total billed: $<?= number_format($total / 100, 2) ?></strong>
        <?= $this->Html->link('Download Excel', '/admin/weekly-report.xlsx', ['class' => 'csv-download', 'role' => 'button']) ?>
    </div>
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
            <tr><td colspan="7">No requests have been submitted in this reporting period.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</section>

<section class="items-card email-settings-card">
    <h2>Email settings</h2>
    <p>New requests are emailed to this mailbox. The Excel report uses the same previous-day 5 pm cutoff and is sent on the selected day when the scheduled report command runs.</p>
    <?php if (!$emailDelivery['ready']): ?>
        <p><strong>Email delivery needs attention:</strong></p>
        <ul><?php foreach ($emailDelivery['issues'] as $issue): ?><li><?= h($issue) ?></li><?php endforeach; ?></ul>
    <?php else: ?>
        <p>Last mailbox saved successfully</p>
    <?php endif; ?>
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
    <?= $this->Form->create(null, ['url' => '/admin/test-email']) ?>
    <?= $this->Form->button('Send test email', ['class' => 'button-primary', 'disabled' => !$emailDelivery['ready'] || !$settings->mailbox]) ?>
    <?= $this->Form->end() ?>
</section>
