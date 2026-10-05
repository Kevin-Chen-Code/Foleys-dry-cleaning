<?php $this->assign('title', 'View prices'); $this->Html->script('order-builder', ['block' => true]); $this->Html->css('item-dialog', ['block' => true]); ?>
<section class="page-heading">
    <h1>View our prices</h1>
</section>

<table>
    <thead>
        <tr><th>Item</th><th>Price</th></tr>
    </thead>
    <tbody>
    <?php foreach ($items as $item): ?>
        <tr>
            <td><?= h($item->name) ?></td>
            <td>$<?= number_format($item->unit_price_cents / 100, 2) ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
