<?php $this->assign('title', 'Submit a request'); $this->Html->script('order-builder', ['block' => true]); $this->Html->css('item-dialog', ['block' => true]); ?>
<section class="page-heading">
    <h1>Submit your dry cleaning request here</h1>
    <p>Register your chambers garments. Collection takes place daily at 11:00 am.</p>
</section>
<?= $this->Form->create($order ?? null, ['class' => 'builder-form', 'novalidate' => true]) ?>
<section class="items-card">
    <!-- UI: Requests are identified by the required email address, not a name field. -->
    <?= $this->Form->control('email', ['label' => 'Email address *', 'type' => 'email', 'required' => true]) ?>
</section>
<?= $this->element('Orders/item_builder', ['items' => $items]) ?>
<aside class="pricing-summary">
    <h2>Pricing summary</h2>
    <p>Subtotal <strong data-live-subtotal>$0.00</strong></p>
    <hr>
    <h3>Total cost <strong data-live-total>$0.00</strong></h3>
    <p>Discounts are calculated securely when you submit.</p>
    <?= $this->Form->button('Submit request', ['class' => 'button-primary']) ?>
</aside>
<?= $this->Form->end() ?>
