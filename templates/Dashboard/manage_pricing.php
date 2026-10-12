<?php
$this->assign('title', 'Manage pricing');
$this->Html->css('item-dialog', ['block' => true]); // UI: styles the pricing edit popups.
$this->Html->script('manage-pricing', ['block' => true]);
$discountTypes = ['percentage' => 'Percentage', 'fixed' => 'Fixed dollar amount', 'price_cap' => 'Price cap (excluding bar robes and jabots)'];
?>

<section class="page-heading">
    <h1>Manage pricing &amp; items</h1>
    <p>Add, edit, or remove garments and discount rules.</p>
</section>

<section class="items-card">
    <h2>Garments</h2>
    <table>
        <thead>
            <tr><th>Item</th><th>Price</th><th>Description</th><th>Actions</th></tr>
        </thead>
        <tbody>
        <?php foreach ($items as $item): ?>
            <tr>
                <td><?= h($item->name) ?></td>
                <td>$<?= number_format($item->unit_price_cents / 100, 2) ?></td>
                <td><?= h($item->description ?: 'N/A') ?></td>
                <td>
                    <button type="button" class="action-button" onclick="this.nextElementSibling.showModal()">Edit</button>
                    <dialog class="item-dialog pricing-editor-dialog">
                        <?= $this->Form->create(null) ?>
                        <?= $this->Form->hidden('action', ['value' => 'save_item']) ?>
                        <?= $this->Form->hidden('id', ['value' => $item->id]) ?>
                        <?= $this->Form->control('name', ['value' => $item->name]) ?>
                        <?= $this->Form->control('price', ['type' => 'number', 'step' => '0.01', 'value' => $item->unit_price_cents / 100]) ?>
                        <?= $this->Form->control('description', ['value' => $item->description]) ?>
                        <div class="add-garment-active-field">
                            <?= $this->Form->checkbox('active', ['id' => 'item-active-' . (int)$item->id, 'checked' => $item->active]) ?>
                            <label for="item-active-<?= (int)$item->id ?>">Active</label>
                        </div>
                        <div class="add-garment-active-field add-price-cap-eligibility-field">
                            <?= $this->Form->checkbox('price_cap_eligible', ['id' => 'item-eligible-' . (int)$item->id, 'checked' => \App\Service\OrderPricingService::isPriceCapEligible($item->toArray()), 'disabled' => \App\Service\OrderPricingService::isExcludedGarment($item->name)]) ?>
                            <label for="item-eligible-<?= (int)$item->id ?>">Eligible for price-cap deals</label>
                        </div>
                        <div class="dialog-actions">
                            <?= $this->Form->button('Save changes', ['class' => 'button-primary']) ?>
                            <button type="button" class="cancel-add" onclick="this.closest('dialog').close()">Cancel</button>
                        </div>
                        <?= $this->Form->end() ?>
                    </dialog>

                    <button type="button" class="delete-button" onclick="this.nextElementSibling.showModal()">Delete</button>
                    <dialog class="item-dialog confirm-dialog">
                        <h2>Confirm delete?</h2>
                        <p>This garment will be removed from the price list.</p>
                        <?= $this->Form->create(null) ?>
                        <?= $this->Form->hidden('action', ['value' => 'delete_item']) ?>
                        <?= $this->Form->hidden('id', ['value' => $item->id]) ?>
                        <button type="button" class="cancel-add" onclick="this.closest('dialog').close()">Cancel</button>
                        <?= $this->Form->button('Confirm delete', ['class' => 'delete-button']) ?>
                        <?= $this->Form->end() ?>
                    </dialog>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <h3>Add garment</h3>
    <?= $this->Form->create() ?>
    <?= $this->Form->hidden('action', ['value' => 'save_item']) ?>
    <?= $this->Form->control('name') ?>
    <?= $this->Form->control('price', ['type' => 'number', 'step' => '0.01']) ?>
    <?= $this->Form->control('description') ?>
    <!-- UI: keeps the Add garment Active label and checkbox aligned like the edit popup. -->
    <div class="add-garment-active-field">
        <?= $this->Form->checkbox('active', ['id' => 'new-item-active', 'checked' => true]) ?>
        <label for="new-item-active">Active</label>
    </div>
    <div class="add-garment-active-field add-price-cap-eligibility-field">
        <?= $this->Form->checkbox('price_cap_eligible', ['id' => 'new-item-eligible', 'checked' => true]) ?>
        <label for="new-item-eligible">Eligible for price-cap deals</label>
    </div>
    <p class="form-note">Bar robes and Jabots are always excluded from price-cap deals.</p>
    <?= $this->Form->button('Save garment', ['class' => 'button-primary']) ?>
    <?= $this->Form->end() ?>
</section>

<section class="items-card">
    <h2>Discount rules</h2>
    <table>
        <thead>
            <tr><th>Rule</th><th>Value</th><th>Actions</th></tr>
        </thead>
        <tbody>
        <?php foreach ($rules as $rule): ?>
            <tr>
                <td><?= h($rule->name) ?></td>
                <td><?= $rule->discount_type === 'percentage' ? (int)$rule->discount_value . '%' : '$' . number_format($rule->discount_value / 100, 2) ?><?= $rule->discount_type === 'price_cap' ? ' cap' : '' ?></td>
                <td>
                    <button type="button" class="action-button" onclick="this.nextElementSibling.showModal()">Edit</button>
                    <dialog class="item-dialog pricing-editor-dialog discount-editor-dialog">
                        <?= $this->Form->create(null, ['data-discount-editor' => true]) ?>
                        <?= $this->Form->hidden('action', ['value' => 'save_rule']) ?>
                        <?= $this->Form->hidden('id', ['value' => $rule->id]) ?>
                        <?= $this->Form->control('name', ['value' => $rule->name]) ?>
                        <?= $this->Form->control('minimum_item_count', ['label' => 'Minimum item count', 'type' => 'number', 'min' => 1, 'step' => 1, 'required' => true, 'value' => $rule->minimum_item_count]) ?>
                        <?= $this->Form->control('discount_type', ['options' => $discountTypes, 'value' => $rule->discount_type]) ?>
                        <?= $this->Form->control('discount_value', ['label' => $rule->discount_type === 'percentage' ? 'Discount (%)' : ($rule->discount_type === 'price_cap' ? 'Maximum eligible total ($)' : 'Discount amount ($)'), 'type' => 'number', 'min' => 0, 'required' => true, 'step' => $rule->discount_type === 'percentage' ? '1' : '0.01', 'value' => $rule->discount_type === 'percentage' ? $rule->discount_value : number_format($rule->discount_value / 100, 2, '.', '')]) ?>
                        <p data-price-cap-note<?= $rule->discount_type === 'price_cap' ? '' : ' hidden' ?>>Once the minimum eligible count is reached, all eligible garments together are capped at this amount. Bar robes and jabots are charged separately and do not count toward the threshold. Other eligible discounts also apply.</p>
                        <div class="dialog-actions">
                            <?= $this->Form->button('Save changes', ['class' => 'button-primary']) ?>
                            <button type="button" class="cancel-add" onclick="this.closest('dialog').close()">Cancel</button>
                        </div>
                        <?= $this->Form->end() ?>
                    </dialog>

                    <button type="button" class="delete-button" onclick="this.nextElementSibling.showModal()">Delete</button>
                    <dialog class="item-dialog confirm-dialog">
                        <h2>Confirm delete?</h2>
                        <p>This discount rule will be removed.</p>
                        <?= $this->Form->create(null) ?>
                        <?= $this->Form->hidden('action', ['value' => 'delete_rule']) ?>
                        <?= $this->Form->hidden('id', ['value' => $rule->id]) ?>
                        <button type="button" class="cancel-add" onclick="this.closest('dialog').close()">Cancel</button>
                        <?= $this->Form->button('Confirm delete', ['class' => 'delete-button']) ?>
                        <?= $this->Form->end() ?>
                    </dialog>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <h3>Add discount rule</h3>
    <?= $this->Form->create(null, ['data-discount-editor' => true]) ?>
    <?= $this->Form->hidden('action', ['value' => 'save_rule']) ?>
    <?= $this->Form->control('name') ?>
    <?= $this->Form->control('minimum_item_count', ['label' => 'Minimum item count', 'type' => 'number', 'min' => 1, 'step' => 1, 'required' => true]) ?>
    <?= $this->Form->control('discount_type', ['options' => $discountTypes]) ?>
    <?= $this->Form->control('discount_value', ['label' => 'Discount (%)', 'type' => 'number', 'min' => 0, 'step' => 1, 'required' => true]) ?>
    <p data-price-cap-note hidden>Once the minimum eligible count is reached, all eligible garments together are capped at this amount. Bar robes and jabots are charged separately and do not count toward the threshold. Other eligible discounts also apply.</p>
    <?= $this->Form->button('Save discount rule', ['class' => 'button-primary']) ?>
    <?= $this->Form->end() ?>
</section>
