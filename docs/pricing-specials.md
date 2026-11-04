# Piece specials and safe activation — 2 November 2026

## Current offers

- **2 piece specials:** any eligible pair for $30, only where cheaper than individual prices.
- **3 piece specials:** any eligible triple for $32, replacing a pair on those same pieces.
- **5 shirt discount:** five or more Shirts qualify for a $6 single-piece rate; other garment quantities do not trigger it.

Bundles are repeatable and disjoint. The optimiser compares all feasible combinations
of pairs, triples and individually priced pieces, choosing the lowest final cost.
Six sufficiently expensive eligible pieces cost $64 (two triples); eleven cost
$126 (three triples and one pair). Four expensive pieces can cost $60 (two pairs).
Cheap pieces remain at their lower individual prices. Three $8 jabots cost $24.
Five $9 shirts cost $30; six cost $36. Adding other garments never makes an
otherwise ineligible four-shirt request receive the shirt offer.

Bar robes are excluded from **every** discount, including older percentage/fixed
offers. From 4 November, all other garments qualify for piece specials, including
jabots and older catalogue rows with a false eligibility flag. That flag only
controls legacy whole-order caps, which still exclude jabots.
Blazer/Bar Jacket $17 + Shirt $9 + Jabot $8 qualify for a $32 triple, with a
$20 Bar Robe charged separately: $52 in total before the export's 10% tax.
A garment is not charged for two different
bundles; shirt unit offers and bundles are compared without counting the same
piece's saving twice. Exact final line charges are saved and used by the report
and Excel export, so bar robes stay full price there too. Requests are limited
to 10,000 pieces to bound optimisation work.

## Shared database changes already performed

- Deleted exactly garment ID 10, named **Test garment**, after confirming it had
  no order-item references. No requests or order items were deleted.
- Recovery copy: `tmp/removed-test-garment-20261102.json` (ignored by Git).
- Added only nullable `service_items.archived_at` and
  `order_items.discounted_total_cents`; did not run unrelated pending migrations.
- Cleared the local schema cache. Both corresponding migrations are idempotent,
  so running migrations later will not add either column twice.
- Initially staged exactly the three requested offers as inactive IDs **5, 6, 7**.
- Deleted older discount-rule IDs **1, 3, 4**, including both active and legacy
  whole-order offers. No orders, garments or order prices were changed by this
  discount-rule cleanup. The saved snapshot can recover the deleted rules.
- The user subsequently activated the three retained offers on 2 November.
  All three were verified active on the public request page on 4 November.

The new Delete action refreshes stale metadata. If archival is available it
archives safely; before the field exists it can still delete an unreferenced
garment. It never deletes request history or bypasses foreign keys.

## Your deployment and activation steps

Deployment and activation were left to the user, as explicitly requested.
No commit, push, Render deployment or rule activation was performed.

1. Review, commit and push the changed source files. Preserve the unrelated
   edits in `resources/data/barristers.json` and `templates/Orders/quote.php`;
   those were not changed as part of this work. Never commit `app_local.php`
   or credentials.
2. Wait until Render's deployment of the new PHP **and JavaScript** is Live.
   Staging is intentional: the old deployed PHP cannot calculate the new rule types.
3. From the updated project folder on your laptop, activate the new policy on
   its configured shared Neon database:

   ```sh
   /opt/homebrew/opt/php@8.4/bin/php bin/cake.php configure_specials --apply
   ```

   This transaction activates the three named offers and deletes every other
   discount rule, keeping exactly these three. The command is repeatable. With no flags it
   only displays the intended definitions; `--stage` creates inactive offers
   without changing the existing live rules. Do not edit staged rules to make
   them active before the new application is deployed.
4. Refresh the browser and check six expensive eligible pieces, eleven expensive
   eligible pieces, five shirts, three jabots and a bar robe mixed with a special.
   Do not submit a real request just to test pricing unless you intend to create one.

Manage pricing supports editing both new rule types and dollar amounts. For
unit-price offers, set the matching garment name and inclusive minimum quantity;
for piece bundles, minimum item count means **pieces per bundle**. Rules stay
disabled globally if `Foleys.discountsEnabled` is set to false.

## Files created or modified

- [README.md](/Users/kevinchen/Foleys-dry-cleaning/README.md)
- [config/Migrations/20261030000000_AddServiceItemArchive.php](/Users/kevinchen/Foleys-dry-cleaning/config/Migrations/20261030000000_AddServiceItemArchive.php)
- [config/Migrations/20261102000000_AddDiscountedLineTotal.php](/Users/kevinchen/Foleys-dry-cleaning/config/Migrations/20261102000000_AddDiscountedLineTotal.php)
- [config/app.php](/Users/kevinchen/Foleys-dry-cleaning/config/app.php)
- [docs/pricing-specials.md](/Users/kevinchen/Foleys-dry-cleaning/docs/pricing-specials.md)
- [src/Command/ConfigureSpecialsCommand.php](/Users/kevinchen/Foleys-dry-cleaning/src/Command/ConfigureSpecialsCommand.php)
- [src/Controller/DashboardController.php](/Users/kevinchen/Foleys-dry-cleaning/src/Controller/DashboardController.php)
- [src/Controller/OrdersController.php](/Users/kevinchen/Foleys-dry-cleaning/src/Controller/OrdersController.php)
- [src/Model/Table/DiscountRulesTable.php](/Users/kevinchen/Foleys-dry-cleaning/src/Model/Table/DiscountRulesTable.php)
- [src/Model/Table/ServiceItemsTable.php](/Users/kevinchen/Foleys-dry-cleaning/src/Model/Table/ServiceItemsTable.php)
- [src/Service/OrderPricingService.php](/Users/kevinchen/Foleys-dry-cleaning/src/Service/OrderPricingService.php)
- [src/Service/WeeklyReportService.php](/Users/kevinchen/Foleys-dry-cleaning/src/Service/WeeklyReportService.php)
- [templates/Dashboard/manage_pricing.php](/Users/kevinchen/Foleys-dry-cleaning/templates/Dashboard/manage_pricing.php)
- [templates/element/Orders/item_builder.php](/Users/kevinchen/Foleys-dry-cleaning/templates/element/Orders/item_builder.php)
- [tests/TestCase/Command/ConfigureSpecialsCommandTest.php](/Users/kevinchen/Foleys-dry-cleaning/tests/TestCase/Command/ConfigureSpecialsCommandTest.php)
- [tests/TestCase/Controller/OrderNamesTest.php](/Users/kevinchen/Foleys-dry-cleaning/tests/TestCase/Controller/OrderNamesTest.php)
- [tests/TestCase/Controller/PricingPagesTest.php](/Users/kevinchen/Foleys-dry-cleaning/tests/TestCase/Controller/PricingPagesTest.php)
- [tests/TestCase/Model/Table/DiscountRulesTableTest.php](/Users/kevinchen/Foleys-dry-cleaning/tests/TestCase/Model/Table/DiscountRulesTableTest.php)
- [tests/TestCase/Service/BundlePricingTest.php](/Users/kevinchen/Foleys-dry-cleaning/tests/TestCase/Service/BundlePricingTest.php)
- [tests/TestCase/Service/WeeklyReportServiceTest.php](/Users/kevinchen/Foleys-dry-cleaning/tests/TestCase/Service/WeeklyReportServiceTest.php)
- [tests/fixtures/bundle-pricing-cases.json](/Users/kevinchen/Foleys-dry-cleaning/tests/fixtures/bundle-pricing-cases.json)
- [tests/fixtures/price-cap-cases.json](/Users/kevinchen/Foleys-dry-cleaning/tests/fixtures/price-cap-cases.json)
- [tests/js/bundle-pricing.test.cjs](/Users/kevinchen/Foleys-dry-cleaning/tests/js/bundle-pricing.test.cjs)
- [tests/load/README.md](/Users/kevinchen/Foleys-dry-cleaning/tests/load/README.md)
- [tests/load/harness.php](/Users/kevinchen/Foleys-dry-cleaning/tests/load/harness.php)
- [webroot/js/manage-pricing.js](/Users/kevinchen/Foleys-dry-cleaning/webroot/js/manage-pricing.js)
- [webroot/js/order-builder.js](/Users/kevinchen/Foleys-dry-cleaning/webroot/js/order-builder.js)
- [webroot/js/order-pricing.js](/Users/kevinchen/Foleys-dry-cleaning/webroot/js/order-pricing.js)

Recovery copy created outside source control:
`/Users/kevinchen/Foleys-dry-cleaning/tmp/removed-test-garment-20261102.json`.

Verification: 59 targeted PHP tests and 147 JavaScript tests passed; the isolated
six-request HTTP test saved all six with correct prices and no missing requests.
The full PHP suite still has the three pre-existing directory-count/email tests
failing; no unrelated directory or email code was changed.

## Only-three-rules cleanup

The shared database was checked after deleting the older rules: only IDs 5, 6
and 7 remain, with prices $30 per pair, $32 per triple and $6 per Shirt at an
inclusive five-Shirt threshold. The previous clarification of five or more
shirts is retained. No retained rule was activated automatically.

Recovery snapshot: [deleted-discount-rules-20261102-1793597664905.json](/Users/kevinchen/Foleys-dry-cleaning/tmp/deleted-discount-rules-20261102-1793597664905.json).

Files modified for this follow-up:

- [ConfigureSpecialsCommand.php](/Users/kevinchen/Foleys-dry-cleaning/src/Command/ConfigureSpecialsCommand.php)
- [ConfigureSpecialsCommandTest.php](/Users/kevinchen/Foleys-dry-cleaning/tests/TestCase/Command/ConfigureSpecialsCommandTest.php)
- [README.md](/Users/kevinchen/Foleys-dry-cleaning/README.md)
- [pricing-specials.md](/Users/kevinchen/Foleys-dry-cleaning/docs/pricing-specials.md)

