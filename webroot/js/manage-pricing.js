document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('[data-discount-editor]').forEach((form) => {
    const type = form.querySelector('[name="discount_type"]');
    const amount = form.querySelector('[name="discount_value"]');
    const label = amount.closest('.input').querySelector('label');
    const note = form.querySelector('[data-price-cap-note]');
    const update = () => {
      const percentage = type.value === 'percentage';
      const cap = type.value === 'price_cap';
      const bundle = type.value === 'bundle';
      const unit = type.value === 'unit_price';
      label.textContent = percentage ? 'Discount (%)' : cap ? 'Maximum eligible total ($)' : bundle ? 'Price per bundle ($)' : unit ? 'Reduced unit price ($)' : 'Discount amount ($)';
      const qualifier = form.querySelector('[name="qualifying_item_name"]');
      if (qualifier) qualifier.required = unit;
      amount.step = percentage ? '1' : '0.01';
      if (percentage) amount.max = '100';
      else amount.removeAttribute('max');
      note.hidden = !cap;
    };
    type.addEventListener('change', update);
    update();
  });
});
