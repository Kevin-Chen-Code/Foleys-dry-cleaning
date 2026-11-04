const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const {stripTypeScriptTypes} = require('node:module');
const source = fs.readFileSync(path.join(__dirname, '..', '..', 'resources', 'automation', 'DailyLaundry.ts'), 'utf8');
const context = vm.createContext({});
vm.runInContext(stripTypeScriptTypes(source), context);
const headers = ['Barrister name', 'Email address', 'Submitted (Melbourne)', 'Item type', 'Quantity', 'Cost (AUD)', 'Discounted cost (AUD)'];
function run(rows, labels = headers) {
  const table = {getHeaderRowRange: () => ({getTexts: () => [labels]}), getRowCount: () => rows.length,
    getRangeBetweenHeaderAndTotal: () => ({getValues: () => rows, getTexts: () => rows.map(row => row.map(String))})};
  const workbook = {getTable: name => {assert.equal(name, 'BarristerRequests'); return table;}};
  return JSON.parse(JSON.stringify(context.main(workbook)));
}
const row = (name = 'Alex Smith', email = 'alex@example.com', item = 'Shirt', cost = 9) => [name, email, '21 Oct 2026 09:00', item, 1, 10, cost];
const importHeaders = ['Description', 'Barrister', 'Email address', "Type ('Expense' or 'Capital')", 'Category', 'Merchant', 'Date (DD/MM/YYYY)', 'TAX rate (%)', 'Amount (Inc TAX)', 'Item type', 'Quantity', 'Discounted cost (AUD)'];
const importRecord = {
  Description: 'Clerk Disbursement', Barrister: 'Alex Smith', 'Email address': 'alex@example.com',
  "Type ('Expense' or 'Capital')": 'Expense', Category: 'Dry Cleaning', Merchant: '',
  'Date (DD/MM/YYYY)': '04/11/2026', 'TAX rate (%)': 0.1, 'Amount (Inc TAX)': 35.2,
  'Item type': 'Blazer/Bar Jacket', Quantity: 3, 'Discounted cost (AUD)': 32,
};
const importRow = (labels = importHeaders, overrides = {}) => labels.map(label => ({...importRecord, ...overrides})[label]);
const currentHeaders = importHeaders.slice(0, -1);

test('Reads the eleven-column report without a discounted-cost column and preserves pre-tax email totals', () => {
  const result = run([
    importRow(currentHeaders),
    importRow(currentHeaders, {'Item type': 'Bar Robe', Quantity: 1, 'Amount (Inc TAX)': 22}),
  ], currentHeaders);
  assert.equal(result.length, 1);
  assert.equal(result[0].email, 'alex@example.com');
  assert.match(result[0].html, /^<p>Dear Alex,/);
  assert.match(result[0].html, /Total: \$52\.00 AUD/);
  assert.doesNotMatch(result[0].html, /\$57\.20/);
});
test('Eleven-column reports support reordered headers and either email position', () => {
  for (const labels of [currentHeaders, [...currentHeaders].reverse(), [currentHeaders[0], currentHeaders[2], currentHeaders[1], ...currentHeaders.slice(3)]]) {
    const displayedLabels = labels.map(label => ' ' + label.toUpperCase() + ' ');
    assert.match(run([importRow(labels)], displayedLabels)[0].html, /Total: \$32\.00 AUD/);
  }
});
test('Reverses line tax rounding at cent boundaries, including zero and a five-cent net cost', () => {
  for (const cents of [0, 1, 4, 5, 15, 753, 847, 999, 3200]) {
    const grossCents = cents + Math.floor(cents / 10) + (cents % 10 >= 5 ? 1 : 0);
    const result = run([importRow(currentHeaders, {'Amount (Inc TAX)': grossCents / 100})], currentHeaders);
    assert.ok(result[0].html.includes('Total: $' + (cents / 100).toFixed(2) + ' AUD'));
  }
  assert.match(run([importRow(currentHeaders, {'Amount (Inc TAX)': 32, 'TAX rate (%)': 0})], currentHeaders)[0].html, /Total: \$32\.00 AUD/);
});
test('Eleven-column reports reject missing amounts, missing tax, and invalid values', () => {
  for (const missing of ['Amount (Inc TAX)', 'TAX rate (%)']) {
    const labels = currentHeaders.filter(label => label !== missing);
    assert.throws(() => run([importRow(labels)], labels), /Missing report column/);
  }
  for (const value of ['', -0.1, Number.NaN, Number.POSITIVE_INFINITY, 'ten percent']) {
    assert.throws(() => run([importRow(currentHeaders, {'TAX rate (%)': value})], currentHeaders), /Invalid name, email/);
  }
  for (const value of ['', -1, Number.NaN, Number.POSITIVE_INFINITY]) {
    assert.throws(() => run([importRow(currentHeaders, {'Amount (Inc TAX)': value})], currentHeaders), /Invalid name, email/);
  }
  assert.throws(() => run([importRow(currentHeaders, {'Amount (Inc TAX)': 0.05})], currentHeaders), /valid cent-rounded cost/);
  assert.deepEqual(run([Array(currentHeaders.length).fill('')], currentHeaders), []);
});
test('Older explicit discounted costs remain authoritative when tax fields are present', () => {
  const result = run([importRow(importHeaders, {'Amount (Inc TAX)': 999, 'TAX rate (%)': ''})], importHeaders);
  assert.match(result[0].html, /Total: \$32\.00 AUD/);
});

test('Reads the import report by header and keeps discounted costs separate from tax-inclusive amounts', () => {
  const result = run([
    importRow(),
    importRow(importHeaders, {'Item type': 'Bar Robe', Quantity: 1, 'Discounted cost (AUD)': 20, 'Amount (Inc TAX)': 22}),
  ], importHeaders);
  assert.equal(result.length, 1);
  assert.deepEqual(Object.keys(result[0]), ['email', 'html']);
  assert.equal(result[0].email, 'alex@example.com');
  assert.match(result[0].html, /^<p>Dear Alex,/);
  assert.match(result[0].html, /04\/11\/2026/);
  assert.match(result[0].html, /Blazer\/Bar Jacket/);
  assert.match(result[0].html, /Bar Robe/);
  assert.match(result[0].html, /Total: \$52\.00 AUD/);
  assert.doesNotMatch(result[0].html, /\$57\.20/);
});
test('Import report supports reordered columns, either email position, and normalised header casing', () => {
  for (const labels of [
    [importHeaders[0], importHeaders[2], importHeaders[1], ...importHeaders.slice(3)],
    [...importHeaders].reverse(),
  ]) {
    const displayedLabels = labels.map(label => '  ' + label.toUpperCase() + '  ');
    const result = run([importRow(labels)], displayedLabels);
    assert.equal(result[0].email, 'alex@example.com');
    assert.match(result[0].html, /Total: \$32\.00 AUD/);
  }
});
test('Import report handles empty insert rows and rejects invalid contact or discounted cost', () => {
  assert.deepEqual(run([Array(importHeaders.length).fill('')], importHeaders), []);
  assert.throws(() => run([importRow(importHeaders, {'Email address': 'alex@example.com\r\nother@example.com'})], importHeaders), /Invalid name, email/);
  assert.throws(() => run([importRow(importHeaders, {'Discounted cost (AUD)': ''})], importHeaders), /Invalid name, email/);
});
test('Keeps support for the earlier Submitted and Discounted cost header aliases', () => {
  const labels = headers.map(label => ({'Submitted (Melbourne)': 'Submitted', 'Discounted cost (AUD)': 'Discounted cost'})[label] || label);
  assert.match(run([row()], labels)[0].html, /Total: \$9\.00 AUD/);
});

test('Uses first word only, handles whitespace and single-word names', () => {
  for (const [name, first] of [['Alex Smith','Alex'], ['  Renée   O’Connor  ','Renée'], ['Taylor','Taylor']]) {
    assert.ok(run([row(name)])[0].html.startsWith(`<p>Dear ${first},</p>`));
  }
});
test('Combines same-name same-email items, but keeps different recipients separate', () => {
  const result = run([row(),row('Alex Smith','alex@example.com','Suit jacket',18),row('Jamie Doe','jamie@example.com')]);
  assert.equal(result.length, 2);
  assert.match(result[0].html, /Shirt/);
  assert.match(result[0].html, /Suit jacket/);
  assert.match(result[0].html, /Total: \$27\.00 AUD/);
  assert.deepEqual(Object.keys(result[0]), ['email','html']);
});
test('Keeps different names at a shared testing email separate', () => {
  assert.equal(run([row(),row('Jamie Doe','alex@example.com')]).length,2);
});
test('Uses discounted costs, including zero, and handles cents accurately', () => {
  assert.match(run([row(undefined,undefined,undefined,0)])[0].html, /Total: \$0\.00 AUD/);
  assert.match(run([row(undefined,undefined,undefined,0.1), row(undefined,undefined,undefined,0.2)])[0].html, /Total: \$0\.30 AUD/);
});
test('Escapes untrusted HTML in names and item descriptions', () => {
  const html = run([row('<Alex> Smith',undefined,'<img src=x onerror=alert(1)>')])[0].html;
  assert.match(html,/Dear &lt;Alex&gt;/);
  assert.ok(!html.includes('<img'));
});
test('Empty tables and blank insertion rows produce no messages', () => {
  assert.deepEqual(run([]),[]);
  assert.deepEqual(run([Array(7).fill('')]),[]);
});
test('Rejects missing required columns and invalid email/cost before returning any messages', () => {
  assert.throws(() => run([row()], headers.slice(0,-1)), /Missing report column/);
  assert.throws(() => run([row(),row('Alex Smith','alex@example.com;other@example.com')]), /Invalid name, email/);
  assert.throws(() => run([row(undefined,undefined,undefined,-1)]), /Invalid name, email/);
});
