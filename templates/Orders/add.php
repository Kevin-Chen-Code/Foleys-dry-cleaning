<?php $this->assign('title', 'Submit a request'); $this->Html->script('order-builder', ['block' => true]); ?>
<section class="page-heading"><h1>Submit your dry cleaning request here</h1><p>Register your chambers garments. Collection takes place daily at 11:00 am.</p></section>
<?= $this->Form->create($order ?? null, ['class' => 'builder-form']) ?>
<section class="items-card"><label>Barrister name *</label><?= $this->Form->control('barrister_name', ['label' => false, 'required' => true, 'placeholder' => 'Please enter your name as registered with Foley’s List']) ?><?= $this->Form->control('email', ['label' => 'Email address (optional)', 'type' => 'email']) ?></section>
<?= $this->element('Orders/item_builder', ['items' => $items]) ?>
<aside class="pricing-summary"><h2>Pricing summary</h2><p>Discounts are calculated securely when you submit.</p><?= $this->Form->button('Submit request', ['class' => 'button-primary']) ?></aside>
<?= $this->Form->end() ?>
