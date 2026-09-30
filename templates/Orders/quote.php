<?php $this->assign('title', 'View prices'); $this->Html->script('order-builder', ['block' => true]); $this->Html->css('item-dialog', ['block' => true]); ?>
<section class="page-heading">
    <h1>View our prices</h1>
    <p>Enter garments and quantities to receive a prospective quote.</p>
</section>
<?= $this->Form->create(null, ['class' => 'builder-form']) ?>
<?= $this->element('Orders/item_builder', ['items' => $items]) ?>

<aside class="pricing-summary">
    <h2>Pricing summary</h2>
    <?php if ($pricing && $pricing['lines']): ?>
        <p>Subtotal <strong>$<?= number_format($pricing['subtotal']/100,2) ?></strong></p>
        <?php foreach ($pricing['appliedDiscounts'] as $discount): ?>
            <p class="discount"><?= h($discount['name']) ?> <strong>-$<?= number_format($discount['amount_cents']/100,2) ?></strong></p>
        <?php endforeach; ?>
        <hr>
        <h3>Total cost <strong>$<?= number_format($pricing['total']/100,2) ?></strong></h3>
    <?php else: ?>
        <p>Subtotal <strong data-live-subtotal>$0.00</strong></p>
        <hr>
        <h3>Total cost <strong data-live-total>$0.00</strong></h3>
        <p>Discounts are applied automatically for qualifying garment bundles.</p>
    <?php endif; ?>
    <?= $this->Form->button('View quote', ['class' => 'button-primary']) ?>
</aside>
<?= $this->Form->end() ?>

<?php if ($pricing && $pricing['lines']): ?>
    <div class="success-overlay">
        <section class="success-dialog">
            <p class="eyebrow">Quote received</p>
            <h1>Thank you.</h1>
            <p>Your quote total is <strong>$<?= number_format($pricing['total']/100, 2) ?></strong>.</p>
            <?= $this->Html->link('Back to home page', '/', ['class' => 'dialog-action']) ?>
        </section>
    </div>
<?php endif; ?>
