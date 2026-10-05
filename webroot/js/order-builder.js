document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('[data-item-builder]').forEach((builder) => {
    const items = JSON.parse(builder.dataset.options);
    const discountRules = JSON.parse(builder.dataset.discountRules || '[]');
    let index = 0;
    const dialog = builder.querySelector('.item-dialog');
    const itemSelect = builder.querySelector('.dialog-item');
    items.forEach((item) => itemSelect.add(new Option(item.name, item.id)));
    const escapeHtml = (text) => String(text).replace(/[&<>'"]/g, (character) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;' }[character]));
    const money = (cents) => new Intl.NumberFormat('en-AU', {style: 'currency', currency: 'AUD'}).format(cents / 100);
    const refresh = () => {
      const selectedItems = [];
      let subtotal = 0;
      builder.querySelectorAll('.builder-row').forEach((row) => {
        const item = items.find((entry) => entry.id === Number(row.dataset.serviceItemId));
        const quantity = Number(row.dataset.quantity);
        if (item && quantity > 0) {
          selectedItems.push({item, quantity});
          subtotal += item.price * quantity;
        }
      });

      const itemCount = selectedItems.reduce((total, line) => total + line.quantity, 0);
      const appliedDiscounts = discountRules.reduce((applied, rule) => {
        const qualifyingCount = rule.qualifying_item_name
          ? selectedItems.filter((line) => line.item.name === rule.qualifying_item_name).reduce((total, line) => total + line.quantity, 0)
          : itemCount;
        if (qualifyingCount < Number(rule.minimum_item_count)) return applied;

        const amount = rule.discount_type === 'percentage'
          ? Math.round(subtotal * (Number(rule.discount_value) / 100))
          : rule.discount_type === 'fixed' ? Number(rule.discount_value) : 0;
        return amount > 0 ? [...applied, {name: rule.name, amount}] : applied;
      }, []);
      const discount = Math.min(subtotal, appliedDiscounts.reduce((total, rule) => total + rule.amount, 0));

      document.querySelectorAll('[data-live-subtotal]').forEach((target) => target.textContent = money(subtotal));
      document.querySelectorAll('[data-live-total]').forEach((target) => target.textContent = money(subtotal - discount));
      document.querySelectorAll('[data-live-discounts]').forEach((list) => {
        list.replaceChildren();
        appliedDiscounts.forEach((rule) => {
          const entry = document.createElement('li');
          entry.textContent = `${rule.name} (-${money(rule.amount)})`;
          list.append(entry);
        });
        list.hidden = appliedDiscounts.length === 0;
      });
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
