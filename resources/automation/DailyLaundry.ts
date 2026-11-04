interface LaundryEmail {
  email: string;
  html: string;
}

interface LaundryGroup {
  email: string;
  name: string;
  rows: string[];
  totalCents: number;
}

/** Read-only: builds email messages for Power Automate, without editing cells or sending mail. */
function main(workbook: ExcelScript.Workbook): LaundryEmail[] {
  const table = workbook.getTable('BarristerRequests');
  if (!table) throw new Error('The workbook must contain the BarristerRequests table.');
  const headers = table.getHeaderRowRange().getTexts()[0].map((header) => header.trim().toLowerCase());
  const column = (names: string[]): number => {
    for (const name of names) {
      const index = headers.indexOf(name.toLowerCase());
      if (index >= 0) return index;
    }
    throw new Error('Missing report column: ' + names[0]);
  };
  const nameColumn = column(['Barrister name', 'Barrister']);
  const emailColumn = column(['Email address']);
  const submittedColumn = column(['Submitted (Melbourne)', 'Submitted', 'Date (DD/MM/YYYY)']);
  const itemColumn = column(['Item type']);
  const quantityColumn = column(['Quantity']);
  // Amount contains the final discounted charge. Use it directly without adding or removing tax.
  const costColumn = column(['Amount (Inc TAX)']);
  if (table.getRowCount() === 0) return [];
  const body = table.getRangeBetweenHeaderAndTotal();
  const values = body.getValues();
  const texts = body.getTexts();
  const groups = new Map<string, LaundryGroup>();
  const escapeHtml = (value: string): string => value
    .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
  for (let index = 0; index < values.length; index++) {
    const row = values[index];
    if (row.every((value) => String(value).trim() === '')) continue;
    const name = String(row[nameColumn]).trim().replace(/\s+/g, ' ');
    const email = String(row[emailColumn]).trim();
    const item = String(row[itemColumn]).trim();
    const quantity = Number(row[quantityColumn]);
    const cost = Number(row[costColumn]);
    const cents = Math.round(cost * 100);
    if (!name || !/^[^\s@;,<>]+@[^\s@;,<>]+\.[^\s@;,<>]+$/.test(email)
        || /[\r\n]/.test(email) || !item || !Number.isInteger(quantity) || quantity <= 0
        || String(row[costColumn]).trim() === '' || !Number.isFinite(cost) || cost < 0
        || !Number.isSafeInteger(cents)) {
      throw new Error('Invalid name, email, item, quantity or amount in table data row ' + (index + 1) + '. No emails have been sent by this script.');
    }
    // Keep different names separate even when they share an email address in a test report.
    const key = email.toLowerCase() + '\u0000' + name.toLowerCase();
    let group = groups.get(key);
    if (!group) {
      group = {email, name, rows: [], totalCents: 0};
      groups.set(key, group);
    }
    group.totalCents += cents;
    group.rows.push('<tr><td>' + escapeHtml(texts[index][submittedColumn]) + '</td><td>'
      + escapeHtml(item) + '</td><td>' + quantity + '</td><td>$' + (cents / 100).toFixed(2) + '</td></tr>');
  }
  return Array.from(groups.values()).map((group) => {
    const firstName = group.name.split(/\s+/)[0];
    const html = '<p>Dear ' + escapeHtml(firstName) + ',</p>'
      + '<p>Thank you for submitting your dry-cleaning request. Your submitted items are listed below.</p>'
      + '<table border="1" cellpadding="8" cellspacing="0" style="border-collapse:collapse">'
      + '<thead><tr><th>Submitted (Melbourne)</th><th>Item type</th><th>Quantity</th><th>Discounted cost</th></tr></thead>'
      + '<tbody>' + group.rows.join('') + '</tbody></table>'
      + '<p><strong>Total: $' + (group.totalCents / 100).toFixed(2) + ' AUD</strong></p>'
      + '<p>Kind regards,<br>Foley&#39;s List</p>';
    return {email: group.email, html};
  });
}
