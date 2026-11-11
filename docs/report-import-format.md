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
All undownloaded submitted requests are included regardless of age. Downloading marks only the included snapshot as downloaded. Admin removals retain the request and its items for restoration from Removed requests.

## Resend request confirmations

The email greets the first word of the saved barrister name, then shows an HTML
table with Submitted (Melbourne date), Item type, Quantity and Discounted cost.
Line amounts use the same saved discounted charges as the report, with legacy
allocation where necessary, and the total is the saved order total. Bar robes and jabots are excluded from piece bundles. Garments receiving a unit-price offer are charged separately from bundles. Piece specials group the cheapest eligible garments first and apply only when the selected group costs less under the offer. User text
is HTML-escaped. Admin is copied at admin@foleys.com.au. The sender is still
configured with EMAIL_FROM in Render; this change does not alter it.

The old Power Automate script is retained for old workbooks. The new report no
longer supplies the email/item/quantity fields it needs; use Resend confirmations
rather than passing new reports to that script.

## Request recovery and pricing changes on 11 November 2026

Aaron Lane and Holly Renwick were reconstructed from their delivered Resend
confirmations as requests 64 and 65. Their submitted timestamps have minute
precision because the original rows were unavailable. Source message IDs and
original amounts are recorded in their notes. Aaron's corrected total is $33,
Holly's is $24.50. Sam Stafford request 63 was corrected from $56 to $60, with
original line amounts recorded in notes. Vanessa's request 60 was subsequently corrected from $63 to $65 when the
grouping policy was clarified to use the cheapest eligible garments first.
The $32 triple contains two pants and one blazer, leaving the other blazer
at $17 and the jabots at $16. Discounted line amounts are $16, $28.58 and $20.42.
Original amounts and the reason for correction are retained in the request notes.

The database contains exactly the three canonical offers. Future app removals
set the existing status to deleted and retain item rows. The Removed requests
view supports restoration. Direct deletion through a database console still
bypasses the app's recovery mechanism. The cause of the original missing rows
could not be determined from the available evidence.

Reports include every undownloaded submitted request regardless of age.
The new request confirmation displays the committed request ID. A direct visit
to the thank-you URL without a saved request redirects to the form.

## Files changed for this update

All paths below are relative to `/Users/kevinchen/Foleys-dry-cleaning`.

- `src/Service/WeeklyReportService.php` — remove the previous-day cutoff.
- `src/Service/OrderPricingService.php` — exclude jabots and separately priced unit-offer garments from bundles.
- `webroot/js/order-pricing.js` — matching browser pricing.
- `src/Controller/DashboardController.php` — recoverable removal, restoration, report data and analytics filtering.
- `src/Controller/OrdersController.php` — committed request logging and confirmation guard.
- `config/routes.php` — protected restoration endpoint.
- `templates/Dashboard/incoming_requests.php` — removed-requests view, restore forms and Melbourne dates.
- `templates/Dashboard/weekly_report.php` — explain pending report scope.
- `templates/Dashboard/manage_pricing.php` — explain current bundle exclusions.
- `templates/Orders/received.php` — show the saved request number.
- `tests/TestCase/Controller/OrderNamesTest.php` — submission, rollback, removal/restoration and report regressions.
- `tests/TestCase/Controller/FormSecurityTest.php` — protected restoration tests.
- `tests/TestCase/Controller/PricingPagesTest.php` — updated eligibility wording.
- `tests/TestCase/Service/BundlePricingTest.php` — jabot exclusion.
- `tests/TestCase/Service/WeeklyReportServiceTest.php` — retain older pending requests across dates and daylight saving changes.
- `tests/fixtures/bundle-pricing-cases.json` — clarified real-order scenarios and unit-offer exclusions.
- `tests/js/bundle-pricing.test.cjs` — independent grouping oracle with the current eligibility policy.
- `README.md` — current reporting and pricing behavior.
- `docs/report-import-format.md` — this guide.

## Cheapest-first refinement

The grouping refinement changes these existing files under
`/Users/kevinchen/Foleys-dry-cleaning`:

- `src/Service/OrderPricingService.php`
- `webroot/js/order-pricing.js`
- `templates/Dashboard/manage_pricing.php`
- `tests/fixtures/bundle-pricing-cases.json`
- `tests/js/bundle-pricing.test.cjs`
- `tests/TestCase/Controller/OrderNamesTest.php`
- `README.md`
- `docs/report-import-format.md`

Analytics already selects the same non-deleted requests as All requests,
independently of report download state. The grouping refinement does not modify it.
