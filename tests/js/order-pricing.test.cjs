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
    const originalRules = JSON.stringify(scenario.rules);
    const result = context.window.FoleysPricing.calculate(selectedItems, scenario.rules, true);
    assert.equal(result.subtotal, scenario.subtotal);
    assert.equal(result.total, scenario.total);
    assert.equal(result.discount, scenario.subtotal - scenario.total);
    assert.equal(JSON.stringify(result.appliedDiscounts.map((rule) => rule.name)), JSON.stringify(scenario.names));
    assert.ok(result.appliedDiscounts.length <= 1);
    assert.equal(result.appliedDiscounts.reduce((sum, rule) => sum + rule.amount, 0), result.discount);
    assert.equal(JSON.stringify(scenario.rules), originalRules);
  });

  test(`Paused discounts: ${scenario.name}`, () => {
    const selectedItems = scenario.items.map((line) => ({
      quantity: line.quantity,
      item: {name: line.item_name, price: line.unit_price_cents, price_cap_eligible: line.price_cap_eligible ?? true},
    }));
    const originalRules = JSON.stringify(scenario.rules);
    const defaultResult = context.window.FoleysPricing.calculate(selectedItems, scenario.rules);
    const disabledResult = context.window.FoleysPricing.calculate(selectedItems, scenario.rules, false);
    for (const result of [defaultResult, disabledResult]) {
      assert.equal(result.subtotal, scenario.subtotal);
      assert.equal(result.total, scenario.subtotal);
      assert.equal(result.discount, 0);
      assert.equal(JSON.stringify(result.appliedDiscounts), '[]');
      assert.equal(JSON.stringify(scenario.rules), originalRules);
    }
  });
}

test('Paused discounts handle an empty cart', () => {
  const rules = [{name: 'Fixed discount', minimum_item_count: 1, discount_type: 'fixed', discount_value: 1500}];
  for (const result of [
    context.window.FoleysPricing.calculate([], rules),
    context.window.FoleysPricing.calculate([], rules, false),
  ]) {
    assert.equal(result.subtotal, 0);
    assert.equal(result.discount, 0);
    assert.equal(result.total, 0);
    assert.equal(JSON.stringify(result.appliedDiscounts), '[]');
  }
});

test('Re-enabling discounts uses the preserved five-shirt rule', () => {
  const selectedItems = [{quantity: 5, item: {name: 'Shirt', price: 900}}];
  const rules = [
    {name: 'Percentage', minimum_item_count: 3, discount_type: 'percentage', discount_value: 30},
    {name: 'Five shirt fixed', minimum_item_count: 5, discount_type: 'fixed', discount_value: 1500},
  ];
  const originalRules = JSON.stringify(rules);
  assert.equal(context.window.FoleysPricing.calculate(selectedItems, rules, false).total, 4500);
  const enabled = context.window.FoleysPricing.calculate(selectedItems, rules, true);
  assert.equal(enabled.total, 3000);
  assert.equal(enabled.discount, 1500);
  assert.equal(JSON.stringify(enabled.appliedDiscounts.map((rule) => rule.name)), '["Five shirt fixed"]');
  assert.equal(JSON.stringify(rules), originalRules);
});

test('Increasing and decreasing quantity replaces the winning tier', () => {
  const rules = [
    {name: 'Five-item offer', minimum_item_count: 5, discount_type: 'fixed', discount_value: 1200},
    {name: 'Three-item offer', minimum_item_count: 3, discount_type: 'fixed', discount_value: 500},
  ];
  const originalRules = JSON.stringify(rules);
  for (const [quantity, total, name] of [
    [3, 2500, 'Three-item offer'],
    [5, 3800, 'Five-item offer'],
    [4, 3500, 'Three-item offer'],
  ]) {
    const selectedItems = [{quantity, item: {name: 'Blouse', price: 1000}}];
    const result = context.window.FoleysPricing.calculate(selectedItems, rules, true);
    assert.equal(result.total, total);
    assert.equal(JSON.stringify(result.appliedDiscounts.map((rule) => rule.name)), JSON.stringify([name]));
    assert.equal(JSON.stringify(rules), originalRules);
  }
});
