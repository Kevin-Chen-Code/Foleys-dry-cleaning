<section class="items-card" data-item-builder data-options="<?= h(json_encode(array_map(fn($item) => ['id' => (int)$item->id, 'name' => $item->name, 'price' => (int)$item->unit_price_cents], $items))) ?>">
    <h2>Items</h2>
    <div class="builder-rows"></div>
    <button type="button" class="add-row">Add items</button>
    <dialog class="item-dialog">
        <div>
            <h2>Add an item</h2>
            <label>Garment <select class="dialog-item"></select></label>
            <label>Quantity <input class="dialog-quantity" type="number" min="1" value="1"></label>
            <div>
                <button type="button" class="cancel-add">Cancel</button>
                <button type="button" class="confirm-add">Add item</button>
        </div>
        </div>
    </dialog>
</section>
