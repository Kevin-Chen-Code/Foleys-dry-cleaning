<?php $this->assign('title', 'Submit a request'); $this->Html->script(['order-pricing', 'order-builder', 'barrister-autocomplete'], ['block' => true]); $this->Html->css(['item-dialog', 'barrister-autocomplete'], ['block' => true]); ?>
<section class="page-heading">
    <h1>Submit your dry cleaning request here</h1>
    <p>Register your chambers garments. Collection takes place daily at the schedule outlined <?= $this->Html->link('here', 'https://google.com', ['target' => '_blank', 'rel' => 'noopener noreferrer']) ?>.</p>
</section>
<?= $this->Form->create($order ?? null, ['class' => 'builder-form', 'novalidate' => true]) ?>
<section class="items-card">
    <div class="barrister-name-picker" data-barrister-picker data-suggestions-url="<?= h($this->Url->build('/barristers/suggestions')) ?>">
        <?= $this->Form->control('barrister_name', ['label' => 'Barrister name *', 'required' => true, 'maxlength' => 150, 'autocomplete' => 'off', 'role' => 'combobox', 'aria-autocomplete' => 'list', 'aria-expanded' => 'false', 'aria-controls' => 'barrister-name-suggestions', 'aria-describedby' => 'barrister-name-help', 'data-barrister-name' => true]) ?>
        <ul class="barrister-suggestions" id="barrister-name-suggestions" role="listbox" aria-label="Suggested barristers" hidden></ul>
    </div>
    <p class="barrister-name-help" id="barrister-name-help" data-barrister-status role="status">Start typing your first name or surname, then select your name to fill in your email address.</p>
    <?= $this->Form->control('email', ['label' => 'Email address *', 'type' => 'email', 'required' => true, 'maxlength' => 254, 'autocomplete' => 'email']) ?>
</section>
<?= $this->element('Orders/item_builder', ['items' => $items, 'rules' => $rules]) ?>
<aside class="pricing-summary">
    <h2>Pricing summary</h2>
    <p> Total price: <strong data-live-total>$0.00</strong></p>
    <p>Discounts are calculated securely when you submit.</p>

    <!-- UI: eligible rule names appear here as items are added to the request. -->
    <ul class="applied-discounts" data-live-discounts hidden></ul>
    <?= $this->Form->button('Submit request', ['class' => 'button-primary']) ?>
</aside>
<?= $this->Form->end() ?>
