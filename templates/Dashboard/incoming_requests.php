<?php $this->assign('title', 'Incoming requests'); ?>
<section class="page-heading">
    <h1>Incoming requests</h1>
    <p>Manage submitted dry-cleaning requests.</p>
</section>

<section class="items-card">
    <table>
        <tr>
            <th>Date</th>
            <th>Barrister</th>
            <th>Total</th>
            <th>Status</th>
        </tr>
        <?php foreach ($orders as $order): ?>
            <tr>
                <td><?= h($order->submitted_at?->format('d M Y')) ?></td>
                <td><?= $order->email ? h($order->email) : '&mdash;' ?></td>
                <td>$<?= number_format($order->total_cents/100,2) ?></td>
                <td><?= h($order->status) ?></td>
            </tr>
        <?php endforeach; ?>
    </table>
</section>
