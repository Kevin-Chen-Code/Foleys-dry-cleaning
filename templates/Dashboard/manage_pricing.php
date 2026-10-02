<?php
$this->assign('title', 'Manage pricing');
$this->Html->css('item-dialog', ['block' => true]); // UI: styles the pricing edit popups.
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
                        <?= $this->Form->control('position', ['type' => 'number', 'value' => $item->position]) ?>
                        <div class="active-field">
                            <label for="item-active-<?= (int)$item->id ?>">Active</label>
                            <?= $this->Form->checkbox('active', ['id' => 'item-active-' . (int)$item->id, 'checked' => $item->active]) ?>
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
    <?= $this->Form->control('position', ['type' => 'number', 'value' => 100]) ?>
    <!-- UI: keeps the Add garment Active label and checkbox aligned like the edit popup. -->
    <div class="add-garment-active-field">
        <label for="new-item-active">Active</label>
        <?= $this->Form->checkbox('active', ['id' => 'new-item-active', 'checked' => true]) ?>
    </div>
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
                <td><?= (int)$rule->discount_value ?><?= $rule->discount_type === 'percentage' ? '%' : ' cents' ?></td>
                <td>
                    <button type="button" class="action-button" onclick="this.nextElementSibling.showModal()">Edit</button>
                    <dialog class="item-dialog pricing-editor-dialog">
                        <?= $this->Form->create(null) ?>
                        <?= $this->Form->hidden('action', ['value' => 'save_rule']) ?>
                        <?= $this->Form->hidden('id', ['value' => $rule->id]) ?>
                        <?= $this->Form->control('name', ['value' => $rule->name]) ?>
                        <?= $this->Form->control('minimum_item_count', ['type' => 'number', 'value' => $rule->minimum_item_count]) ?>
                        <?= $this->Form->control('discount_type', ['options' => ['percentage' => 'Percentage', 'fixed' => 'Fixed cents'], 'value' => $rule->discount_type]) ?>
                        <?= $this->Form->control('discount_value', ['type' => 'number', 'value' => $rule->discount_value]) ?>
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
    <?= $this->Form->create() ?>
    <?= $this->Form->hidden('action', ['value' => 'save_rule']) ?>
    <?= $this->Form->control('name') ?>
    <?= $this->Form->control('minimum_item_count', ['type' => 'number']) ?>
    <?= $this->Form->control('discount_type', ['options' => ['percentage' => 'Percentage', 'fixed' => 'Fixed cents']]) ?>
    <?= $this->Form->control('discount_value', ['type' => 'number']) ?>
    <?= $this->Form->button('Save discount rule', ['class' => 'button-primary']) ?>
    <?= $this->Form->end() ?>
</section>
