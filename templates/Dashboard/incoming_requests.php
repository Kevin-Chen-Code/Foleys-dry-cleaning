<?php
$this->assign('title', 'Incoming requests');
$this->Html->css('item-dialog', ['block' => true]);
?>
<section class="page-heading">
    <h1>All requests</h1>
    <p> Full history of dry cleaning requests submitted by users </p>
    <p><?= $this->Html->link($showDeleted ? 'Back to All requests' : 'Removed requests', ['action' => 'incomingRequests', '?' => $showDeleted ? [] : ['deleted' => '1']], ['class' => 'button delete-button request-action']) ?></p>
</section>

<section class="items-card">
    <table>
        <tr>
            <th>Date</th>
            <th>Barrister name</th>
            <th>Email address</th>
            <th>Total</th>
            <th>Status</th>
            <th scope="col">Actions</th>
        </tr>
        <?php foreach ($orders as $order): ?>
            <tr>
                <td><?= h($order->submitted_at?->setTimezone(new \DateTimeZone('Australia/Melbourne'))->format('d M Y')) ?></td>
                <td><?= $order->barrister_name ? h($order->barrister_name) : '&mdash;' ?></td>
                <td><?= $order->email ? h($order->email) : '&mdash;' ?></td>
                <td>$<?= number_format($order->total_cents/100,2) ?></td>
                <td><?= h($order->status) ?></td>
                <td>
                    <?php if ($showDeleted): ?>
                        <?= $this->Form->create(null, ['url' => ['action' => 'restoreRequest']]) ?>
                        <?= $this->Form->hidden('id', ['value' => $order->id]) ?>
                        <?= $this->Form->button('Restore request', ['class' => 'button-primary request-action']) ?>
                        <?= $this->Form->end() ?>
                    <?php else: ?>
                    <button type="button" class="delete-button request-action" onclick="this.nextElementSibling.showModal()">Delete</button>
                    <dialog class="item-dialog confirm-dialog" aria-labelledby="delete-request-<?= (int)$order->id ?>">
                        <h2 id="delete-request-<?= (int)$order->id ?>">Delete this request?</h2>
                        <p>Removing request #<?= (int)$order->id ?></p>
                        <?= $this->Form->create(null, ['url' => ['action' => 'deleteRequest']]) ?>
                        <?= $this->Form->hidden('id', ['value' => $order->id]) ?>
                        <button type="button" class="cancel-add" onclick="this.closest('dialog').close()" autofocus>Cancel</button>
                        <?= $this->Form->button('Confirm delete', ['class' => 'delete-button']) ?>
                        <?= $this->Form->end() ?>
                    </dialog>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>
</section>
