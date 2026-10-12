const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const context = {window: {}};
vm.runInNewContext(fs.readFileSync(path.join(__dirname, '../../webroot/js/order-pricing.js'), 'utf8'), context);
const cases = JSON.parse(fs.readFileSync(path.join(__dirname, '../fixtures/price-cap-cases.json'), 'utf8'));
for (const scenario of cases) {
  test(scenario.name, () => {
    const selectedItems = scenario.items.map((line) => ({
      quantity: line.quantity,
      item: {name: line.item_name, price: line.unit_price_cents, price_cap_eligible: line.price_cap_eligible ?? true},
    }));
    const result = context.window.FoleysPricing.calculate(selectedItems, scenario.rules);
    assert.equal(result.subtotal, scenario.subtotal);
    assert.equal(result.total, scenario.total);
    assert.equal(result.discount, scenario.subtotal - scenario.total);
    assert.equal(JSON.stringify(result.appliedDiscounts.map((rule) => rule.name)), JSON.stringify(scenario.names));
  });
}
