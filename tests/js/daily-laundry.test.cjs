const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const {stripTypeScriptTypes} = require('node:module');
const source = fs.readFileSync(path.join(__dirname, '..', '..', 'resources', 'automation', 'DailyLaundry.ts'), 'utf8');
const context = vm.createContext({});
vm.runInContext(stripTypeScriptTypes(source), context);
const headers = ['Barrister name', 'Email address', 'Submitted (Melbourne)', 'Item type', 'Quantity', 'Amount (Inc TAX)'];
function run(rows, labels = headers) {
  const table = {getHeaderRowRange: () => ({getTexts: () => [labels]}), getRowCount: () => rows.length,
    getRangeBetweenHeaderAndTotal: () => ({getValues: () => rows, getTexts: () => rows.map(row => row.map(String))})};
  const workbook = {getTable: name => {assert.equal(name, 'BarristerRequests'); return table;}};
  return JSON.parse(JSON.stringify(context.main(workbook)));
}
const row = (name = 'Alex Smith', email = 'alex@example.com', item = 'Shirt', amount = 9.9) => [name, email, '04/11/2026', item, 1, amount];
const importHeaders = ['Description', 'Barrister', 'Email address', "Type ('Expense' or 'Capital')", 'Category', 'Merchant', 'Date (DD/MM/YYYY)', 'TAX rate (%)', 'Amount (Inc TAX)', 'Item type', 'Quantity'];
const importRecord = {
  Description: 'Clerk Disbursement', Barrister: 'Alex Smith', 'Email address': 'alex@example.com',
  "Type ('Expense' or 'Capital')": 'Expense', Category: 'Dry Cleaning', Merchant: '',
  'Date (DD/MM/YYYY)': '04/11/2026', 'TAX rate (%)': 0.1, 'Amount (Inc TAX)': 35.2,
  'Item type': 'Blazer/Bar Jacket', Quantity: 3,
};
const importRow = (labels = importHeaders, overrides = {}) => labels.map(label => ({...importRecord, ...overrides})[label]);

test('Email heading, individual line amounts and total match the reported workbook amounts', () => {
  const result = run([
    importRow(),
    importRow(importHeaders, {'Item type': 'Bar Robe', Quantity: 1, 'Amount (Inc TAX)': 22}),
  ], importHeaders);
  assert.equal(result.length, 1);
  assert.deepEqual(Object.keys(result[0]), ['email', 'html']);
  assert.equal(result[0].email, 'alex@example.com');
  assert.match(result[0].html, /^<p>Dear Alex,/);
  assert.match(result[0].html, /<th>Discounted cost<\/th>/);
  assert.doesNotMatch(result[0].html, /<th>Amount \(Inc Tax\)/);
  assert.match(result[0].html, /<td>Blazer\/Bar Jacket<\/td><td>3<\/td><td>\$35\.20<\/td>/);
  assert.match(result[0].html, /<td>Bar Robe<\/td><td>1<\/td><td>\$22\.00<\/td>/);
  assert.match(result[0].html, /Total: \$57\.20 AUD/);
  assert.doesNotMatch(result[0].html, /\$52\.00/);
});

test('Amount is authoritative in twelve-column reports even when legacy discounted costs disagree', () => {
  const labels = [...importHeaders, 'Discounted cost (AUD)'];
  const result = run([importRow(labels, {'Amount (Inc TAX)': 35.2, 'Discounted cost (AUD)': 1, 'TAX rate (%)': ''})], labels);
  assert.match(result[0].html, /Total: \$35\.20 AUD/);
  assert.doesNotMatch(result[0].html, /<td>\$1\.00<\/td>/);
});

test('Reader does not calculate tax again or require a tax-rate column', () => {
  const labels = importHeaders.filter(label => label !== 'TAX rate (%)');
  const result = run([importRow(labels, {'Amount (Inc TAX)': 35.2})], labels);
  assert.match(result[0].html, /Total: \$35\.20 AUD/);
  assert.doesNotMatch(result[0].html, /\$38\.72/);
});

test('Reordered, case-normalised columns and either email position preserve amounts', () => {
  for (const labels of [importHeaders, [...importHeaders].reverse(), [importHeaders[0], importHeaders[2], importHeaders[1], ...importHeaders.slice(3)]]) {
    const displayedLabels = labels.map(label => '  ' + label.toUpperCase() + '  ');
    const result = run([importRow(labels)], displayedLabels);
    assert.equal(result[0].email, 'alex@example.com');
    assert.match(result[0].html, /Total: \$35\.20 AUD/);
  }
});

test('Earlier name and submitted-date aliases still work when Amount is present', () => {
  for (const submitted of ['Submitted', 'Submitted (Melbourne)', 'Date (DD/MM/YYYY)']) {
    const labels = headers.map(label => label === 'Submitted (Melbourne)' ? submitted : label);
    assert.match(run([row()], labels)[0].html, /Total: \$9\.90 AUD/);
  }
});

test('Missing Amount is rejected instead of using an old discounted-cost value', () => {
  const labels = headers.map(label => label === 'Amount (Inc TAX)' ? 'Discounted cost (AUD)' : label);
  assert.throws(() => run([row()], labels), /Missing report column: Amount \(Inc TAX\)/);
});

test('Blank, negative, non-finite and unsafe Amount values are rejected even if legacy net cost exists', () => {
  const labels = [...importHeaders, 'Discounted cost (AUD)'];
  for (const amount of ['', -1, Number.NaN, Number.POSITIVE_INFINITY, Number.MAX_SAFE_INTEGER, 'invalid']) {
    assert.throws(() => run([importRow(labels, {'Amount (Inc TAX)': amount, 'Discounted cost (AUD)': 32})], labels), /Invalid/);
  }
});

test('Exact provided cent amounts, including zero and five cents, are retained without reverse-tax rounding', () => {
  for (const amount of [0, 0.01, 0.04, 0.05, 0.06, 0.17, 8.28, 9.32, 35.2]) {
    const result = run([row(undefined, undefined, undefined, amount)]);
    assert.ok(result[0].html.includes('Total: $' + amount.toFixed(2) + ' AUD'));
  }
  assert.match(run([row(undefined, undefined, undefined, 0.1), row(undefined, undefined, undefined, 0.2)])[0].html, /Total: \$0\.30 AUD/);
});

test('Uses first word only and handles whitespace and single-word names', () => {
  for (const [name, first] of [['Alex Smith', 'Alex'], ['  Renée   O’Connor  ', 'Renée'], ['Taylor', 'Taylor']]) {
    assert.ok(run([row(name)])[0].html.startsWith('<p>Dear ' + first + ',</p>'));
  }
});

test('Combines item rows per name and email and totals their reported amounts', () => {
  const result = run([row(), row('Alex Smith', 'alex@example.com', 'Suit jacket', 19.8), row('Jamie Doe', 'jamie@example.com')]);
  assert.equal(result.length, 2);
  assert.match(result[0].html, /Shirt/);
  assert.match(result[0].html, /Suit jacket/);
  assert.match(result[0].html, /Total: \$29\.70 AUD/);
});

test('Keeps different names at a shared testing email separate', () => {
  assert.equal(run([row(), row('Jamie Doe', 'alex@example.com')]).length, 2);
});

test('Escapes untrusted HTML in names and garment descriptions', () => {
  const html = run([row('<Alex> Smith', undefined, '<img src=x onerror=alert(1)>')])[0].html;
  assert.match(html, /Dear &lt;Alex&gt;/);
  assert.ok(!html.includes('<img'));
});

test('Empty tables and blank insertion rows produce no messages', () => {
  assert.deepEqual(run([]), []);
  assert.deepEqual(run([Array(headers.length).fill('')]), []);
  assert.deepEqual(run([Array(importHeaders.length).fill('')], importHeaders), []);
});

test('Invalid email or quantity prevents message generation', () => {
  assert.throws(() => run([row(), row('Alex Smith', 'alex@example.com;other@example.com')]), /Invalid/);
  assert.throws(() => run([importRow(importHeaders, {'Email address': 'alex@example.com\r\nother@example.com'})], importHeaders), /Invalid/);
  for (const quantity of [0, -1, 1.5]) {
    assert.throws(() => run([importRow(importHeaders, {Quantity: quantity})], importHeaders), /Invalid/);
  }
});

test('Discounted charge remains unchanged in report-sourced email amounts', () => {
  const html = run([importRow(importHeaders, {'Amount (Inc TAX)': 32}), importRow(importHeaders, {'Item type': 'Bar Robe', 'Amount (Inc TAX)': 20})], importHeaders)[0].html;
  assert.match(html, /<th>Discounted cost<\/th>/);
  assert.match(html, /Total: \$52\.00 AUD/);
  assert.doesNotMatch(html, /\$57\.20/);
});
