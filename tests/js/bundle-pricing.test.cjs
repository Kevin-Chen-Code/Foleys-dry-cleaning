const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const source = process.env.FOLEYS_PRICING_SOURCE || path.join(__dirname, '../../webroot/js/order-pricing.js');
const fixtures = process.env.FOLEYS_BUNDLE_CASES || path.join(__dirname, '../fixtures/bundle-pricing-cases.json');
const context = {window: {}};
vm.runInNewContext(fs.readFileSync(source, 'utf8'), context);
const calculate = (...args) => context.window.FoleysPricing.calculate(...args);
const plain = (value) => JSON.parse(JSON.stringify(value));
const cases = JSON.parse(fs.readFileSync(fixtures, 'utf8'));
const selected = (items) => items.map((line) => ({
  quantity: line.quantity,
  item: {name: line.item_name, price: line.unit_price_cents, price_cap_eligible: line.price_cap_eligible ?? true},
}));
for (const scenario of cases) {
  test('Bundles: ' + scenario.name, () => {
    const input = selected(scenario.items);
    const original = JSON.stringify([input, scenario.rules]);
    const result = calculate(input, scenario.rules, true);
    assert.equal(result.subtotal, scenario.subtotal);
    assert.equal(result.total, scenario.total);
    assert.equal(result.discount, scenario.subtotal - scenario.total);
    assert.deepEqual(plain(result.appliedDiscounts.map((rule) => rule.name)), scenario.names);
    assert.equal(result.lineTotals.reduce((sum, cents) => sum + cents, 0), result.total);
    assert.equal(result.appliedDiscounts.reduce((sum, rule) => sum + rule.amount, 0), result.discount);
    if (scenario.line_totals) assert.deepEqual(plain(result.lineTotals), scenario.line_totals);
    if (scenario.unit_prices) assert.deepEqual(plain(result.unitPrices), scenario.unit_prices);
    if (scenario.applications) assert.deepEqual(plain(result.appliedDiscounts.map((rule) => rule.applications)), scenario.applications);
    for (const [index, cents] of result.lineTotals.entries()) {
      assert.ok(Number.isSafeInteger(cents) && cents >= 0);
      assert.ok(cents <= Math.max(0, scenario.items[index].quantity) * scenario.items[index].unit_price_cents);
      if (/\bbar[\s-]*robes?\b/i.test(scenario.items[index].item_name)) {
        assert.equal(cents, Math.max(0, scenario.items[index].quantity) * scenario.items[index].unit_price_cents);
      }
    }
    for (const rule of result.appliedDiscounts) {
      assert.ok(Number.isSafeInteger(rule.amount) && rule.amount > 0);
      assert.ok(Number.isSafeInteger(rule.applications) && rule.applications > 0);
    }
    assert.equal(JSON.stringify([input, scenario.rules]), original);
  });

  test('Paused bundles: ' + scenario.name, () => {
    const input = selected(scenario.items);
    for (const enabled of [undefined, false]) {
      const result = calculate(input, scenario.rules, enabled);
      assert.equal(result.total, scenario.subtotal);
      assert.equal(result.discount, 0);
      assert.deepEqual(plain(result.appliedDiscounts), []);
      assert.equal(result.lineTotals.reduce((sum, cents) => sum + cents, 0), scenario.subtotal);
    }
  });
}

test('Bundle optimiser matches an independent subset oracle on heterogeneous carts', () => {
  const rules = [
    {name: 'Two pieces', discount_type: 'bundle', minimum_item_count: 2, discount_value: 3000},
    {name: 'Three pieces', discount_type: 'bundle', minimum_item_count: 3, discount_value: 3200},
    {name: 'Five shirts', discount_type: 'unit_price', minimum_item_count: 5, discount_value: 600, qualifying_item_name: 'Shirt'},
  ];
  // Enumerates actual pair/triple subsets; deliberately does not use sorted-prefix DP.
  const oracle = (input) => {
    const shirtCount = input.filter((line) => line.item.name === 'Shirt').length;
    const costs = input.map((line) => line.item.name === 'Shirt' && shirtCount >= 5 ? Math.min(line.item.price, 600) : line.item.price);
    let outside = 0;
    const eligible = [];
    input.forEach((line, index) => {
      if (line.item.name === 'Bar robe' || line.item.name === 'Jabot' || line.item.price_cap_eligible === false) outside += costs[index];
      else eligible.push(costs[index]);
    });
    const memo = new Map([[0, 0]]);
    const solve = (mask) => {
      if (memo.has(mask)) return memo.get(mask);
      let first = 0;
      while (!(mask & (1 << first))) first++;
      const rest = mask & ~(1 << first);
      let answer = eligible[first] + solve(rest);
      for (let second = first + 1; second < eligible.length; second++) {
        if (!(rest & (1 << second))) continue;
        const afterPair = rest & ~(1 << second);
        answer = Math.min(answer, 3000 + solve(afterPair));
        for (let third = second + 1; third < eligible.length; third++) {
          if (afterPair & (1 << third)) answer = Math.min(answer, 3200 + solve(afterPair & ~(1 << third)));
        }
      }
      memo.set(mask, answer);
      return answer;
    };
    return outside + solve((1 << eligible.length) - 1);
  };
  let seed = 713405;
  const random = (limit) => {
    seed = (Math.imul(seed, 1664525) + 1013904223) >>> 0;
    return seed % limit;
  };
  for (let attempt = 0; attempt < 140; attempt++) {
    const count = 1 + random(9);
    const input = Array.from({length: count}, () => ({
      quantity: 1,
      item: {
        name: ['Shirt', 'Shirt', 'Coat', 'Blouse', 'Bar robe', 'Jabot'][random(6)],
        price: [0, 100, 500, 800, 900, 1250, 1800, 2200, 4000, 6000][random(10)],
        price_cap_eligible: random(7) !== 0,
      },
    }));
    const result = calculate(input, rules, true);
    assert.equal(result.total, oracle(input), 'random case ' + attempt + ': ' + JSON.stringify(input));
    assert.equal(result.lineTotals.reduce((sum, value) => sum + value, 0), result.total);
    assert.equal(result.appliedDiscounts.reduce((sum, rule) => sum + rule.amount, 0), result.discount);
    for (const [index, value] of result.lineTotals.entries()) {
      assert.ok(value >= 0 && value <= input[index].item.price);
      if (input[index].item.name === 'Bar robe') assert.equal(value, input[index].item.price);
    }
  }
});

test('Bundle optimiser rejects huge requests before expanding unit arrays', () => {
  for (const enabled of [false, true]) {
    assert.throws(
      () => calculate([{quantity: 10001, item: {name: 'Coat', price: 4000}}], [], enabled),
      /at most 10000 items/,
    );
  }
});

test('Bundle optimiser handles ten thousand pieces with exact cents', () => {
  const result = calculate([{quantity: 10000, item: {name: 'Coat', price: 4000}}], [
    {name: 'Two pieces', discount_type: 'bundle', minimum_item_count: 2, discount_value: 3000},
    {name: 'Three pieces', discount_type: 'bundle', minimum_item_count: 3, discount_value: 3200},
  ], true);
  // 3332 triples plus two pairs is cheaper than 3333 triples plus one full-price single.
  assert.equal(result.total, 3332 * 3200 + 2 * 3000);
  assert.equal(result.lineTotals[0], result.total);
  assert.equal(result.appliedDiscounts.reduce((sum, rule) => sum + rule.amount, 0), result.discount);
});
