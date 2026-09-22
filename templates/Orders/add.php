<?php
/** @var \App\View\AppView $this */
/** @var array<\Cake\Datasource\EntityInterface> $items */
$order = $order ?? null;
$this->assign('title', 'Collection request');
?>
<section class="portal-hero">
    <p class="eyebrow">Barrister collection service</p>
    <h1>Register dry cleaning for collection</h1>
    <p>Select each item and quantity. Your total is confirmed when you submit.</p>
</section>

<?php if ($items === []): ?>
    <section class="notice-panel">
        <h2>Service catalogue being configured</h2>
        <p>Dry-cleaning items and pricing have not yet been added. Please contact the admin team.</p>
    </section>
<?php else: ?>
    <?= $this->Form->create($order, ['class' => 'request-form']) ?>
    <section class="form-panel">
        <h2>Your details</h2>
        <?= $this->Form->control('barrister_name', ['label' => 'Full name', 'required' => true, 'maxlength' => 150]) ?>
        <?= $this->Form->control('email', ['label' => 'Email address (optional)', 'type' => 'email', 'maxlength' => 255]) ?>
    </section>
    <section class="form-panel">
        <div class="section-heading">
            <div><h2>Items for collection</h2><p>Leave the quantity at zero for items you do not need collected.</p></div>
            <strong id="live-total">$0.00</strong>
        </div>
        <div class="item-list">
            <?php foreach ($items as $item): ?>
                <div class="item-row" data-price="<?= (int)$item->unit_price_cents ?>">
                    <div><strong><?= h($item->name) ?></strong><?php if ($item->description): ?><small><?= h($item->description) ?></small><?php endif; ?></div>
                    <span>$<?= number_format($item->unit_price_cents / 100, 2) ?> each</span>
                    <?= $this->Form->control("items.{$item->id}.quantity", ['label' => 'Quantity', 'type' => 'number', 'min' => 0, 'max' => 50, 'value' => 0, 'class' => 'item-quantity']) ?>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
    <section class="form-panel">
        <?= $this->Form->control('notes', ['label' => 'Collection notes (optional)', 'rows' => 3, 'maxlength' => 2000]) ?>
        <p class="form-note">Any applicable bundle discount is calculated securely when the request is submitted.</p>
        <?= $this->Form->button('Submit collection request', ['class' => 'button-primary']) ?>
    </section>
    <?= $this->Form->end() ?>
    <?php $this->Html->scriptStart(['block' => true]); ?>
    document.querySelectorAll('.item-quantity').forEach((input) => input.addEventListener('input', () => {
        let cents = 0;
        document.querySelectorAll('.item-row').forEach((row) => {
            cents += Number(row.dataset.price) * Math.max(0, Number(row.querySelector('.item-quantity').value || 0));
        });
        document.querySelector('#live-total').textContent = new Intl.NumberFormat('en-AU', {style: 'currency', currency: 'AUD'}).format(cents / 100);
    }));
    <?php $this->Html->scriptEnd(); ?>
<?php endif; ?>
