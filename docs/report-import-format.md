# Disbursement report and Jabot piece specials — 4 November 2026

## Latest correction: final discounted charge, no extra tax

This correction supersedes the earlier tax-addition notes below. Report column
I keeps the heading `Amount (Inc TAX)` but contains the exact saved discounted
line charge. No 10% is appended. The fixed tax-rate field remains template
metadata and does not change this amount. The barrister email's final heading
is `Discounted cost`; its rows and total read the same final charges directly.
For example, $32 remains $32, and the pictured four-item request totals $52
in both the report and the email.

Use a freshly generated export after deploying this correction. Already
downloaded or uploaded workbooks retain their old values.

Changed: `src/Service/WeeklyReportWorkbook.php`,
`resources/automation/DailyLaundry.ts`, report/reader tests and documentation.

The export remains a single worksheet containing the native Excel table
`BarristerRequests`. It starts at A1 with no title, footer or spacer rows.
Each row represents one submitted garment line. Downloading resets exactly
the exported request snapshot; it never deletes the underlying requests.

## Columns

| Column | Header | Value |
| --- | --- | --- |
| A | Description | `Clerk Disbursement` |
| B | Barrister | Saved barrister name |
| C | Email address | Saved email address |
| D | Type ('Expense' or 'Capital') | `Expense` |
| E | Category | `Dry Cleaning` |
| F | Merchant | Blank, matching the supplied template |
| G | Date (DD/MM/YYYY) | Submitted date in Melbourne time |
| H | TAX rate (%) | Numeric `0.1`, displayed as 10% |
| I | Amount (Inc TAX) | Discounted line cost plus 10% tax |
| J | Item type | Submitted garment name |
| K | Quantity | Submitted quantity |

Inserting email at C shifts the template's original C–H columns one place right.
The fixed literals retain their meanings. Merchant is deliberately empty.
The final two columns preserve the item detail needed by Power Automate.
The separate `Discounted cost (AUD)` column was removed at the user's request.

Tax is calculated from integer cents and rounded half up per exported line.
For example, $32 becomes $35.20. Numeric results are stored directly so imports
and automation do not depend on Excel recalculating formulas. Currency and
date cells remain typed values. User-entered text remains literal text.

## Pricing

All garments except bar robes qualify for repeatable two- and three-piece
specials. This includes Jabots and any older catalogue record whose eligibility
flag is false. Bar robes remain full price and never count toward discount
qualification. The eligibility checkbox only controls legacy whole-order
price caps, which continue to exclude jabots.

Blazer/Bar Jacket $17 + Shirt $9 + Jabot $8 total $34, so the three-piece
special reduces them to $32. Adding a $20 Bar Robe gives a $52 request. The
corresponding report lines total $57.20 including tax. Three $8 Jabots remain
$24 because a $32 special would increase their price.

Admin eligibility checkboxes are labelled for legacy price-cap deals and do
not opt garments out of the piece specials. Browser script URLs include
modification timestamps so newly loaded pages receive the matching deployed
calculator.

## Power Automate

The existing Office Script accepts both `Barrister` / `Barrister name` and
`Date (DD/MM/YYYY)` / the former `Submitted` headers. It reads by header name,
not column number, and keeps returning the same `{email, html}` message array.
Email greetings and CC configuration remain intact. The email column is
labelled `Amount (Inc Tax)`. Each email line reads `Amount (Inc TAX)` directly
from the workbook, and the email total adds those tax-inclusive line amounts.
Tax is not added again or removed. An old discounted-cost column is ignored
when present. Reports without `Amount (Inc TAX)` must be re-exported in the
current format rather than guessing a tax-inclusive amount.

The canonical script backup is `resources/automation/DailyLaundry.ts`. If the
cloud script ever needs restoring, edit the existing **Daily Laundry - Report
Table** script in Excel's Automate pane, retaining its identity used by the flow.
The flow's File field remains `Dry cleaning/foleys-weekly-report.xlsx`, relative
to the **IT Operations** library, so replacement uploads can reuse that name.

The header aliases and eleven-column compatibility were saved in the existing
cloud script on 4 November. No email-sending flow test was triggered.

No database migration, rule activation or live request changes are required
for this update. Review, commit and push these source changes when ready, then
wait for Render to deploy. The agent has not committed, pushed or deployed them.

The initial report update passed 66 targeted PHP tests and 160 JavaScript tests. Report tests
cover fixed values, typed dates/prices, tax rounding, empty/600-row tables,
download resets and literal text safety. Pricing tests include the pictured
combination with an old false Jabot flag and keep the Bar Robe at full price.

The clarified any-three-pieces policy additionally passed 35 targeted PHP tests
and all 164 JavaScript tests. These cover other non-robes with false legacy
flags, the pictured combination, and bar robes not counting toward a bundle.

## Changed files

- [config/app.php](/Users/kevinchen/Foleys-dry-cleaning/config/app.php)
- [resources/reports/weekly-report-template.xlsx](/Users/kevinchen/Foleys-dry-cleaning/resources/reports/weekly-report-template.xlsx)
- [resources/automation/DailyLaundry.ts](/Users/kevinchen/Foleys-dry-cleaning/resources/automation/DailyLaundry.ts)
- [src/Service/WeeklyReportWorkbook.php](/Users/kevinchen/Foleys-dry-cleaning/src/Service/WeeklyReportWorkbook.php)
- [src/Service/OrderPricingService.php](/Users/kevinchen/Foleys-dry-cleaning/src/Service/OrderPricingService.php)
- [src/Model/Table/ServiceItemsTable.php](/Users/kevinchen/Foleys-dry-cleaning/src/Model/Table/ServiceItemsTable.php)
- [templates/Dashboard/manage_pricing.php](/Users/kevinchen/Foleys-dry-cleaning/templates/Dashboard/manage_pricing.php)
- [webroot/js/order-pricing.js](/Users/kevinchen/Foleys-dry-cleaning/webroot/js/order-pricing.js)
- [tests/TestCase/Service/WeeklyReportServiceTest.php](/Users/kevinchen/Foleys-dry-cleaning/tests/TestCase/Service/WeeklyReportServiceTest.php)
- [tests/TestCase/Service/BundlePricingTest.php](/Users/kevinchen/Foleys-dry-cleaning/tests/TestCase/Service/BundlePricingTest.php)
- [tests/TestCase/Controller/PricingPagesTest.php](/Users/kevinchen/Foleys-dry-cleaning/tests/TestCase/Controller/PricingPagesTest.php)
- [tests/fixtures/bundle-pricing-cases.json](/Users/kevinchen/Foleys-dry-cleaning/tests/fixtures/bundle-pricing-cases.json)
- [tests/js/bundle-pricing.test.cjs](/Users/kevinchen/Foleys-dry-cleaning/tests/js/bundle-pricing.test.cjs)
- [tests/js/daily-laundry.test.cjs](/Users/kevinchen/Foleys-dry-cleaning/tests/js/daily-laundry.test.cjs)
- [README.md](/Users/kevinchen/Foleys-dry-cleaning/README.md)
- [docs/pricing-specials.md](/Users/kevinchen/Foleys-dry-cleaning/docs/pricing-specials.md)
- [docs/report-import-format.md](/Users/kevinchen/Foleys-dry-cleaning/docs/report-import-format.md)

Workspace reference copies also updated:

- [DailyLaundry.ts](/Users/kevinchen/Documents/Codex/2026-09-22/attached-within-this-document-are-specifications/power-automate/DailyLaundry.ts)
- [DailyLaundry.test.cjs](/Users/kevinchen/Documents/Codex/2026-09-22/attached-within-this-document-are-specifications/power-automate/DailyLaundry.test.cjs)
- [README.md](/Users/kevinchen/Documents/Codex/2026-09-22/attached-within-this-document-are-specifications/power-automate/README.md)

## Files updated for the any-three-pieces clarification

- [OrderPricingService.php](/Users/kevinchen/Foleys-dry-cleaning/src/Service/OrderPricingService.php)
- [order-pricing.js](/Users/kevinchen/Foleys-dry-cleaning/webroot/js/order-pricing.js)
- [manage_pricing.php](/Users/kevinchen/Foleys-dry-cleaning/templates/Dashboard/manage_pricing.php)
- [BundlePricingTest.php](/Users/kevinchen/Foleys-dry-cleaning/tests/TestCase/Service/BundlePricingTest.php)
- [PricingPagesTest.php](/Users/kevinchen/Foleys-dry-cleaning/tests/TestCase/Controller/PricingPagesTest.php)
- [bundle-pricing.test.cjs](/Users/kevinchen/Foleys-dry-cleaning/tests/js/bundle-pricing.test.cjs)
- [bundle-pricing-cases.json](/Users/kevinchen/Foleys-dry-cleaning/tests/fixtures/bundle-pricing-cases.json)
- [README.md](/Users/kevinchen/Foleys-dry-cleaning/README.md)
- [pricing-specials.md](/Users/kevinchen/Foleys-dry-cleaning/docs/pricing-specials.md)
- [report-import-format.md](/Users/kevinchen/Foleys-dry-cleaning/docs/report-import-format.md)

## Removing or changing an export column yourself

1. Open [weekly-report-template.xlsx](/Users/kevinchen/Foleys-dry-cleaning/resources/reports/weekly-report-template.xlsx)
   in Excel. Delete the entire unwanted table column, rather than just clearing
   its heading. Keep the table name `BarristerRequests`, header row 1 and the
   styled blank insertion row 2. Save it back to the same project path.
2. Open [WeeklyReportWorkbook.php](/Users/kevinchen/Foleys-dry-cleaning/src/Service/WeeklyReportWorkbook.php:51).
   The `$values` array supplies the cells from left to right. Remove the matching
   value and keep all remaining values in the same order as the template.
   For this removal, the final `$discounted / 100` entry was removed.
3. Update the `$range` in that PHP file to end at the new last column. This
   export changed from `A1:L` to `A1:K`. This resizes both the native table and
   its filter when data is exported.
4. Check [DailyLaundry.ts](/Users/kevinchen/Foleys-dry-cleaning/resources/automation/DailyLaundry.ts)
   if the column is used by emails. The reader now uses the tax-inclusive
   amount directly. Changes to this source backup must also
   be saved in the existing **Daily Laundry - Report Table** cloud Office Script;
   pushing the website alone does not update the cloud script.
5. Run the report and reader checks before committing:

   ```sh
   /opt/homebrew/opt/php@8.4/bin/php vendor/bin/phpunit tests/TestCase/Service/WeeklyReportServiceTest.php
   node --test tests/js/daily-laundry.test.cjs
   ```

The export removal passed 19 PHP report tests. The email display was
subsequently changed to tax-inclusive amounts, as documented below. No emails
were sent during these checks.

Files changed for this removal:

- [weekly-report-template.xlsx](/Users/kevinchen/Foleys-dry-cleaning/resources/reports/weekly-report-template.xlsx)
- [WeeklyReportWorkbook.php](/Users/kevinchen/Foleys-dry-cleaning/src/Service/WeeklyReportWorkbook.php)
- [DailyLaundry.ts](/Users/kevinchen/Foleys-dry-cleaning/resources/automation/DailyLaundry.ts)
- [WeeklyReportServiceTest.php](/Users/kevinchen/Foleys-dry-cleaning/tests/TestCase/Service/WeeklyReportServiceTest.php)
- [daily-laundry.test.cjs](/Users/kevinchen/Foleys-dry-cleaning/tests/js/daily-laundry.test.cjs)
- [README.md](/Users/kevinchen/Foleys-dry-cleaning/README.md)
- [report-import-format.md](/Users/kevinchen/Foleys-dry-cleaning/docs/report-import-format.md)

The three workspace Power Automate reference files linked above were kept in
sync. The existing cloud Office Script was updated and saved as well.

## Tax-inclusive email amounts

The email heading is `Amount (Inc Tax)`, and the amounts exactly follow the
workbook's `Amount (Inc TAX)` column. For the pictured sample, the email shows
$17.60 for Blazer/Bar Jacket, $22.00 for Bar Robe, $9.32 for Shirt and $8.28 for
Jabot, giving a $57.20 total. No tax is added a second time.

The cloud source is the existing **Daily Laundry - Report Table** Office Script.
Its version-controlled backup is
[DailyLaundry.ts](/Users/kevinchen/Foleys-dry-cleaning/resources/automation/DailyLaundry.ts).
The `costColumn` lookup controls the workbook source column, the `<th>` row
controls the visible email heading, and `group.totalCents` sums the line amounts.

Files changed for this email update:

- [DailyLaundry.ts](/Users/kevinchen/Foleys-dry-cleaning/resources/automation/DailyLaundry.ts)
- [daily-laundry.test.cjs](/Users/kevinchen/Foleys-dry-cleaning/tests/js/daily-laundry.test.cjs)
- [README.md](/Users/kevinchen/Foleys-dry-cleaning/README.md)
- [report-import-format.md](/Users/kevinchen/Foleys-dry-cleaning/docs/report-import-format.md)

The three workspace reference files listed above are kept in sync.

Validation: 14 reader tests passed. A generated eleven-column export was also
read successfully, with every email line matching its exported amount and the
four sample items summing to $57.20 including tax. No emails were sent.
