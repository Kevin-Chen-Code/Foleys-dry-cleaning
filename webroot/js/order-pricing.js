// Preview only: the server independently validates garments and calculates the saved price.
window.FoleysPricing = {
  calculate(selectedItems, rules, discountsEnabled = false) {
    const lines = selectedItems.filter((line) => line.quantity > 0);
    const subtotal = lines.reduce((sum, line) => sum + line.item.price * line.quantity, 0);
    if (discountsEnabled !== true) {
      return {subtotal, discount: 0, total: subtotal, appliedDiscounts: []};
    }
    const itemCount = lines.reduce((sum, line) => sum + line.quantity, 0);
    const eligible = lines.filter((line) => line.item.price_cap_eligible !== false
      && !/\b(?:bar[\s-]*robes?|jabots?)\b/i.test(line.item.name));
    const eligibleCount = eligible.reduce((sum, line) => sum + line.quantity, 0);
    const eligibleSubtotal = eligible.reduce((sum, line) => sum + line.item.price * line.quantity, 0);
    const garmentKey = (name) => {
      const key = String(name ?? '').trim().toLowerCase();
      return key === 'shirts' ? 'shirt' : key;
    };
    let highestTier = 0;
    let discount = 0;
    let winningRule = null;
    for (const rule of rules) {
      if (Object.hasOwn(rule, 'active') && !rule.active) continue;
      if (!['percentage', 'fixed', 'price_cap'].includes(rule.discount_type)) continue;
      const qualifyingCount = rule.discount_type === 'price_cap' ? eligibleCount : rule.qualifying_item_name
        ? lines.filter((line) => garmentKey(line.item.name) === garmentKey(rule.qualifying_item_name))
          .reduce((sum, line) => sum + line.quantity, 0)
        : itemCount;
      const tier = Number(rule.minimum_item_count);
      if (qualifyingCount < tier || tier < highestTier) continue;
      // Mirror the server: highest qualifying tier first, then largest standalone saving.
      if (tier > highestTier) {
        highestTier = tier;
        discount = 0;
        winningRule = null;
      }
      let amount = rule.discount_type === 'price_cap'
        ? eligibleSubtotal - Math.max(0, Number(rule.discount_value))
        : rule.discount_type === 'percentage'
          ? Math.round(subtotal * (Number(rule.discount_value) / 100)) : Number(rule.discount_value);
      amount = Math.min(subtotal, Math.max(0, amount));
      const winsTie = amount > 0 && amount === discount && rule.id != null && winningRule?.id != null
        && Number(rule.id) < Number(winningRule.id);
      if (amount > discount || winsTie) {
        discount = amount;
        winningRule = rule;
      }
    }
    const appliedDiscounts = winningRule === null ? [] : [{name: winningRule.name, amount: discount}];
    return {subtotal, discount, total: subtotal - discount, appliedDiscounts};
  },
};
