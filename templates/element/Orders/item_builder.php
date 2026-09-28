<section class="items-card" data-item-builder data-options="
<?= h(json_encode(array_map(fn($item) => ['id'=>(int)$item->id,'name'=>$item->name,'price'=>(int)$item->unit_price_cents], $items))) ?>">
<h2>Items</h2>
<div class="builder-rows"></div>
<button type="button" class="add-row">+ Add another item</button>
<template>
    <div class="builder-row">
        <select name="items[INDEX][service_item_id]"></select>
        <input name="items[INDEX][quantity]" type="number" min="1" value="1">
        <span class="row-price"></span>
        <button type="button" class="remove-row">Remove</button>
    </div>
</template>
</section>
