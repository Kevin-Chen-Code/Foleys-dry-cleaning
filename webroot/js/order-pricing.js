// Preview only: saved prices are independently validated and calculated on the server.
window.FoleysPricing = {
  MAX_ITEM_COUNT: 10000,
  calculate(selectedItems, rules, discountsEnabled = false) {
    const garmentKey = (name) => {
      const key = String(name ?? '').trim().toLowerCase();
      return key === 'shirts' ? 'shirt' : key;
    };
    const isBarRobe = (name) => /\bbar[\s-]*robes?\b/i.test(String(name ?? ''));
    const isPriceCapEligible = (item) => item.price_cap_eligible !== false && item.price_cap_eligible !== 0
      && !/\b(?:bar[\s-]*robes?|jabots?)\b/i.test(String(item.name ?? ''));
    // Piece specials include every garment except bar robes, regardless of legacy cap flags.
    const isBundleEligible = (item) => !isBarRobe(item.name);
    const active = rules.map((rule, index) => ({...rule, index}))
      .filter((rule) => !Object.hasOwn(rule, 'active') || Boolean(rule.active));
    const lowerId = (candidate, current) => current !== null && candidate.id != null && current.id != null
      && Number(candidate.id) < Number(current.id);
    // Integer cents, including large-but-valid totals, must not lose precision in allocation.
    const share = (weight, cost, remainingWeight) => remainingWeight === weight
      ? cost : Number(BigInt(weight) * BigInt(cost) / BigInt(remainingWeight));
    const lines = [];
    const originalPrices = Array(selectedItems.length).fill(0);
    const unitPrices = Array(selectedItems.length).fill(0);
    const lineTotals = Array(selectedItems.length).fill(0);
    let subtotal = 0;
    let itemCount = 0;
    for (let index = 0; index < selectedItems.length; index++) {
      const selected = selectedItems[index];
      const quantity = Math.trunc(Number(selected.quantity) || 0);
      if (quantity < 1) continue;
      itemCount += quantity;
      if (!Number.isSafeInteger(itemCount) || itemCount > 10000) {
        throw new RangeError('A request can contain at most 10000 items.');
      }
      const price = Math.max(0, Math.trunc(Number(selected.item.price) || 0));
      originalPrices[index] = price;
      unitPrices[index] = price;
      lineTotals[index] = price * quantity;
      subtotal += lineTotals[index];
      lines.push({index, quantity, price, item: selected.item});
    }
    if (discountsEnabled !== true) {
      return {subtotal, discount: 0, total: subtotal, appliedDiscounts: [], lineTotals, unitPrices};
    }

    const unitRules = active.filter((rule) => rule.discount_type === 'unit_price' && rule.qualifying_item_name);
    const chosenUnitRule = Array(selectedItems.length).fill(null);
    for (const line of lines) {
      if (isBarRobe(line.item.name)) continue;
      for (const rule of unitRules) {
        const key = garmentKey(rule.qualifying_item_name);
        if (garmentKey(line.item.name) !== key) continue;
        const count = lines.filter((entry) => !isBarRobe(entry.item.name) && garmentKey(entry.item.name) === key)
          .reduce((sum, entry) => sum + entry.quantity, 0);
        const target = Math.max(0, Math.trunc(Number(rule.discount_value)));
        if (count < Number(rule.minimum_item_count) || !Number.isSafeInteger(target) || target >= line.price) continue;
        if (target < unitPrices[line.index]
          || (target === unitPrices[line.index] && lowerId(rule, chosenUnitRule[line.index]))) {
          unitPrices[line.index] = target;
          chosenUnitRule[line.index] = rule;
        }
      }
      lineTotals[line.index] = unitPrices[line.index] * line.quantity;
    }

    // For any fixed bundle coverage, the most expensive effective units are optimal.
    // A cheaper bundled unit can always be exchanged with a dearer unbundled unit.
    const units = [];
    for (const line of lines) {
      if (!isBundleEligible(line.item)) continue;
      for (let piece = 0; piece < line.quantity; piece++) {
        units.push({line: line.index, original: line.price, effective: unitPrices[line.index], piece});
      }
    }
    units.sort((left, right) => right.effective - left.effective || left.line - right.line || left.piece - right.piece);
    const bundleRules = active.filter((rule) => rule.discount_type === 'bundle'
      && Number.isSafeInteger(Number(rule.minimum_item_count)) && Number(rule.minimum_item_count) >= 2
      && Number.isSafeInteger(Number(rule.discount_value)) && Number(rule.discount_value) >= 0);
    const coverCost = Array(units.length + 1).fill(Infinity);
    const choice = Array(units.length + 1).fill(null);
    const prefix = Array(units.length + 1).fill(0);
    coverCost[0] = 0;
    let bestCovered = 0;
    let bestTotal = lineTotals.reduce((sum, value) => sum + value, 0);
    const singlesTotal = bestTotal;
    for (let count = 1; count <= units.length; count++) {
      prefix[count] = prefix[count - 1] + units[count - 1].effective;
      for (const rule of bundleRules) {
        const size = Number(rule.minimum_item_count);
        if (count < size) continue;
        const cost = coverCost[count - size] + Number(rule.discount_value);
        if (cost < coverCost[count]) {
          coverCost[count] = cost;
          choice[count] = rule;
        }
      }
      const total = singlesTotal - prefix[count] + coverCost[count];
      // Equal costs keep fewer bundled pieces; a deal must really save money.
      if (total < bestTotal) {
        bestTotal = total;
        bestCovered = count;
      }
    }

    const savings = new Map();
    const addSaving = (rule, amount, applications) => {
      if (amount <= 0 || applications < 1) return;
      const existing = savings.get(rule.index) ?? {name: rule.name, amount: 0, applications: 0};
      existing.amount += amount;
      existing.applications += applications;
      savings.set(rule.index, existing);
    };
    const unbundled = Array(selectedItems.length).fill(0);
    for (const line of lines) unbundled[line.index] = line.quantity;
    let end = bestCovered;
    while (end > 0) {
      const rule = choice[end];
      const start = end - Number(rule.minimum_item_count);
      const group = units.slice(start, end);
      let remainingWeight = group.reduce((sum, unit) => sum + unit.effective, 0);
      const originalSum = group.reduce((sum, unit) => sum + unit.original, 0);
      let remainingCost = Number(rule.discount_value);
      // Protect against redundant/non-saving groups even if a future rule introduces ties.
      if (remainingCost < remainingWeight) {
        for (const unit of group) {
          const cost = share(unit.effective, remainingCost, remainingWeight);
          lineTotals[unit.line] += cost - unit.effective;
          unbundled[unit.line]--;
          remainingWeight -= unit.effective;
          remainingCost -= cost;
        }
        addSaving(rule, originalSum - Number(rule.discount_value), 1);
      }
      end = start;
    }
    for (const line of lines) {
      const rule = chosenUnitRule[line.index];
      if (rule !== null) {
        addSaving(rule, (line.price - unitPrices[line.index]) * unbundled[line.index], unbundled[line.index]);
      }
    }
    let total = lineTotals.reduce((sum, value) => sum + value, 0);
    let appliedDiscounts = [...savings.entries()].sort(([left], [right]) => left - right).map(([, value]) => value);

    // Older percentage/fixed/cap offers remain alternatives, never another layer
    // on top of the new disjoint bundle/unit-price plan. Bar robes never qualify.
    const nonRobe = lines.filter((line) => !isBarRobe(line.item.name));
    const capLines = nonRobe.filter((line) => isPriceCapEligible(line.item));
    const normalSubtotal = nonRobe.reduce((sum, line) => sum + line.price * line.quantity, 0);
    const capSubtotal = capLines.reduce((sum, line) => sum + line.price * line.quantity, 0);
    let highestTier = 0;
    let legacySaving = 0;
    let legacyRule = null;
    for (const rule of active) {
      if (!['percentage', 'fixed', 'price_cap'].includes(rule.discount_type)) continue;
      const targetLines = rule.discount_type === 'price_cap' ? capLines : nonRobe;
      const qualifying = rule.discount_type === 'price_cap' || !rule.qualifying_item_name
        ? targetLines
        : targetLines.filter((line) => garmentKey(line.item.name) === garmentKey(rule.qualifying_item_name));
      const count = qualifying.reduce((sum, line) => sum + line.quantity, 0);
      const tier = Number(rule.minimum_item_count);
      if (count < tier) continue;
      const basis = rule.discount_type === 'price_cap' ? capSubtotal : normalSubtotal;
      let amount = rule.discount_type === 'price_cap' ? basis - Number(rule.discount_value)
        : rule.discount_type === 'percentage' ? Math.round(basis * (Number(rule.discount_value) / 100))
          : Number(rule.discount_value);
      amount = Math.max(0, Math.min(basis, amount));
      if (amount <= 0 || tier < highestTier) continue;
      if (tier > highestTier) {
        highestTier = tier;
        legacySaving = 0;
        legacyRule = null;
      }
      if (amount > legacySaving || (amount === legacySaving && lowerId(rule, legacyRule))) {
        legacySaving = amount;
        legacyRule = rule;
      }
    }
    if (legacyRule !== null && subtotal - legacySaving < total) {
      for (const line of lines) {
        lineTotals[line.index] = line.price * line.quantity;
        unitPrices[line.index] = line.price;
      }
      const targetLines = legacyRule.discount_type === 'price_cap' ? capLines : nonRobe;
      let remainingWeight = targetLines.reduce((sum, line) => sum + line.price * line.quantity, 0);
      let remainingCost = remainingWeight - legacySaving;
      for (const line of targetLines) {
        const weight = line.price * line.quantity;
        const cost = remainingWeight > 0 ? share(weight, remainingCost, remainingWeight) : 0;
        lineTotals[line.index] = cost;
        remainingWeight -= weight;
        remainingCost -= cost;
      }
      total = subtotal - legacySaving;
      appliedDiscounts = [{name: legacyRule.name, amount: legacySaving, applications: 1}];
    }
    return {subtotal, discount: subtotal - total, total, appliedDiscounts, lineTotals, unitPrices};
  },
};

