# Report and confirmation email format

## Downloadable report

One worksheet with one native Excel Table, `BarristerRequests`, starts at A1.
There are no titles, footer totals or spacer rows outside the table.
The export keeps one row per garment type. Removing detail columns does not
aggregate requests or change their saved charges.

| Column | Header | Value |
| --- | --- | --- |
| A | Description | Clerk Disbursement |
| B | Barrister | Saved barrister name |
| C | Type ('Expense' or 'Capital') | Expense |
| D | Category | Dry Cleaning |
| E | Merchant | Blank |
| F | Date (DD/MM/YYYY) | Numeric Melbourne submission date |
| G | TAX rate (%) | Template metadata: 10% |
| H | Amount (Inc TAX) | Exact discounted line charge; no extra tax |

Email address, Item type and Quantity are removed from the export only.
The admin request displays retain their detail. For example, a $30 discounted
charge stays $30, even though the template retains its tax metadata.
Existing downloaded workbooks are unchanged; download a new report after deploying.
The previous-day 5 pm filter and persistent reset on download are unchanged.

## Resend request confirmations

The email greets the first word of the saved barrister name, then shows an HTML
table with Submitted (Melbourne date), Item type, Quantity and Discounted cost.
Line amounts use the same saved discounted charges as the report, with legacy
allocation where necessary, and the total is the saved order total. User text
is HTML-escaped. Admin is copied at admin@foleys.com.au. The sender is still
configured with EMAIL_FROM in Render; this change does not alter it.

The old Power Automate script is retained for old workbooks. The new report no
longer supplies the email/item/quantity fields it needs; use Resend confirmations
rather than passing new reports to that script.

## Files changed

- `src/Service/NotificationService.php`: first-name/date/discounted values and HTML delivery.
- `templates/email/html/request_submitted.php`: editable email greeting, table and sign-off.
- `src/Service/WeeklyReportWorkbook.php`: eight-column export data and table bounds.
- `resources/reports/weekly-report-template.xlsx`: matching eight-column styled table.
- `templates/Orders/add.php`: loads submission protection and marks the form/button.
- `webroot/js/request-submit.js`: disables repeat submissions while pending; restores on history return.
- `webroot/css/request-submit.css`: grey disabled button.
- `tests/TestCase/Service/NotificationServiceTest.php`: recipients, HTML escaping, date and discounted amounts.
- `tests/TestCase/Service/WeeklyReportServiceTest.php`: columns, typed amounts, empty/600-row tables and resets.
- `tests/js/request-submit.test.cjs`: pending submission, validation and history behavior.
- `README.md` and this document: current behavior and editing locations.
