document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('[data-item-builder]').forEach((builder) => {
    const items = JSON.parse(builder.dataset.options); let index = 0;
    const dialog = builder.querySelector('.item-dialog');
    const itemSelect = builder.querySelector('.dialog-item');
    items.forEach((item) => itemSelect.add(new Option(item.name, item.id)));
    const escapeHtml = (text) => String(text).replace(/[&<>'"]/g, (character) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;' }[character]));
    const money = (cents) => new Intl.NumberFormat('en-AU', {style: 'currency', currency: 'AUD'}).format(cents / 100);
    const refresh = () => {
      let cents = 0;
      builder.querySelectorAll('.builder-row').forEach((row) => cents += Number(row.dataset.price) * Number(row.dataset.quantity));
      document.querySelectorAll('[data-live-subtotal],[data-live-total]').forEach((target) => target.textContent = money(cents));
    };
    const addRow = (item, quantity) => {
      const existing = builder.querySelector(`.builder-row[data-service-item-id="${item.id}"]`);
      if (existing) {
        const combinedQuantity = Number(existing.dataset.quantity) + quantity;
        existing.dataset.quantity = combinedQuantity;
        existing.querySelector('input[name$="[quantity]"]').value = combinedQuantity;
        existing.querySelector('.row-quantity').textContent = `${combinedQuantity} × ${money(item.price)}`;
        existing.querySelector('.row-price').textContent = money(item.price * combinedQuantity);
        refresh();
        return;
      }
      const row = document.createElement('div'); row.className = 'builder-row'; row.dataset.price = item.price; row.dataset.quantity = quantity;
      row.dataset.serviceItemId = item.id;
      // UI: render the optional garment description beneath the item name.
      const description = item.description ? `<small>${escapeHtml(item.description)}</small>` : '';
      row.innerHTML = `<input type="hidden" name="items[${index}][service_item_id]" value="${item.id}"><input type="hidden" name="items[${index}][quantity]" value="${quantity}"><span class="builder-item-details"><strong>${escapeHtml(item.name)}</strong>${description}</span><span class="row-quantity">${quantity} × ${money(item.price)}</span><span class="row-price">${money(item.price * quantity)}</span><button type="button" class="remove-row">Remove</button>`;
      row.querySelector('.remove-row').addEventListener('click', () => { row.remove(); refresh(); });
      builder.querySelector('.builder-rows').append(row); index++; refresh();
    };
    builder.querySelector('.add-row').addEventListener('click', () => { dialog.showModal(); });
    builder.querySelector('.cancel-add').addEventListener('click', () => { dialog.close(); });
    builder.querySelector('.confirm-add').addEventListener('click', (event) => {
      event.preventDefault(); const item = items.find((entry) => entry.id === Number(itemSelect.value)); const quantity = Math.max(1, Number(builder.querySelector('.dialog-quantity').value || 1));
      addRow(item, quantity); dialog.close();
    });
    refresh();
  });
});
