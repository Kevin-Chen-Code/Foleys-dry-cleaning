// Preview only: the server independently validates garments and calculates the saved price.
window.FoleysPricing = {
  calculate(selectedItems, rules) {
    const lines = selectedItems.filter((line) => line.quantity > 0);
    const subtotal = lines.reduce((sum, line) => sum + line.item.price * line.quantity, 0);
    const itemCount = lines.reduce((sum, line) => sum + line.quantity, 0);
    const eligible = lines.filter((line) => line.item.price_cap_eligible !== false
      && !/\b(?:bar[\s-]*robes?|jabots?)\b/i.test(line.item.name));
    const eligibleCount = eligible.reduce((sum, line) => sum + line.quantity, 0);
    const eligibleSubtotal = eligible.reduce((sum, line) => sum + line.item.price * line.quantity, 0);
    let capDiscount = 0;
    const appliedDiscounts = [];
    // Keep the exclusive five-shirt policy identical to OrderPricingService.
    const isShirt = (name) => /^shirts?$/i.test(String(name ?? '').trim());
    const shirtCount = lines.filter((line) => isShirt(line.item.name))
      .reduce((sum, line) => sum + line.quantity, 0);
    if (shirtCount >= 5) {
      rules = rules.filter((rule) => rule.discount_type === 'fixed'
        && Number(rule.minimum_item_count) === 5
        && Number(rule.discount_value) === 1500
        && (!rule.qualifying_item_name || isShirt(rule.qualifying_item_name))).slice(0, 1);
    }
    for (const rule of rules) {
      let amount = 0;
      if (rule.discount_type === 'price_cap') {
        if (eligibleCount < Number(rule.minimum_item_count)) continue;
        amount = Math.max(0, eligibleSubtotal - Math.max(0, Number(rule.discount_value)) - capDiscount);
        capDiscount += amount;
      } else {
        const qualifyingCount = shirtCount >= 5 ? shirtCount : rule.qualifying_item_name
          ? lines.filter((line) => line.item.name === rule.qualifying_item_name).reduce((sum, line) => sum + line.quantity, 0)
          : itemCount;
        if (qualifyingCount < Number(rule.minimum_item_count)) continue;
        amount = rule.discount_type === 'percentage'
          ? Math.round(subtotal * (Number(rule.discount_value) / 100))
          : rule.discount_type === 'fixed' ? Number(rule.discount_value) : 0;
      }
      if (amount > 0) appliedDiscounts.push({name: rule.name, amount});
    }
    const discount = Math.min(subtotal, Math.max(0, appliedDiscounts.reduce((sum, rule) => sum + rule.amount, 0)));
    return {subtotal, discount, total: subtotal - discount, appliedDiscounts};
  },
};
