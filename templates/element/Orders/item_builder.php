<?php $rules = $rules ?? []; ?>
<section class="items-card" data-item-builder data-options="<?= h(json_encode(array_map(fn($item) => ['id' => (int)$item->id, 'name' => $item->name, 'description' => $item->description, 'price' => (int)$item->unit_price_cents], $items))) ?>" data-discount-rules="<?= h(json_encode($rules)) ?>">
    <h2>Items</h2>
    <!-- UI: column labels keep each selected garment's details easy to scan. -->
    <div class="builder-headings" aria-hidden="true">
        <span>Item</span><span>Quantity</span><span>Total</span><span>Option</span>
    </div>
    <div class="builder-rows"></div>
    <button type="button" class="add-row">Add items</button>
    <dialog class="item-dialog add-item-dialog">
        <div class="dialog-content">
            <h2>Add an item</h2>
            <div class="dialog-fields">
                <label>Garment <select class="dialog-item"></select></label>
                <label>Quantity <input class="dialog-quantity" type="number" min="1" value="1"></label>
            </div>
            <div class="dialog-actions">
                <button type="button" class="confirm-add">Add item</button>
                <button type="button" class="cancel-add">Cancel</button>
            </div>
        </div>
    </dialog>
</section>
