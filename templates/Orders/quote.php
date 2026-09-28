<?php $this->assign('title', 'View prices'); $this->Html->script('order-builder', ['block' => true]); ?>
<section class="page-heading"><h1>View our prices</h1><p>Enter garments and quantities to receive a prospective quote.</p></section>
<?= $this->Form->create(null, ['class' => 'builder-form']) ?>
<?= $this->element('Orders/item_builder', ['items' => $items]) ?>
<aside class="pricing-summary"><h2>Pricing summary</h2><?php if ($pricing && $pricing['lines']): ?><p>Subtotal <strong>$<?= number_format($pricing['subtotal']/100,2) ?></strong></p><?php foreach ($pricing['appliedDiscounts'] as $discount): ?><p class="discount"><?= h($discount['name']) ?> <strong>-$<?= number_format($discount['amount_cents']/100,2) ?></strong></p><?php endforeach; ?><hr><h3>Total cost <strong>$<?= number_format($pricing['total']/100,2) ?></strong></h3><?php else: ?><p>Discounts are applied automatically for qualifying garment bundles.</p><?php endif; ?><?= $this->Form->button('View quote', ['class' => 'button-primary']) ?></aside>
<?= $this->Form->end() ?>
