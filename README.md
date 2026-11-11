# Foley's Dry Cleaning Portal

A CakePHP application for barristers to register dry-cleaning items for collection.

## Local development

The local configuration uses SQLite at `tmp/foleys.sqlite`. Initialise the schema and start the application with:

```sh
bin/cake migrations migrate
bin/cake server
```

Open `http://localhost:8765` in a browser. The public request form will show a catalogue notice until approved service items and prices are added.

## Foundation included

- Service-item, discount-rule, order, and order-item database tables.
- A public collection request form that supports multiple item quantities.
- Server-side price calculation and percentage or fixed-value discounts.
- A request-received confirmation screen.
- SQLite for local development; production credentials remain external configuration.

## Still to build

- Secure administrator sign-in and screens to manage catalogue items, prices, and discounts.
- Email notification to the collection team.
- Weekly reporting, Excel table export, and scheduled report email.
- The confirmed brand design and deployment configuration.

![Build Status](https://github.com/cakephp/app/actions/workflows/ci.yml/badge.svg?branch=5.x)
[![Total Downloads](https://img.shields.io/packagist/dt/cakephp/app.svg?style=flat-square)](https://packagist.org/packages/cakephp/app)
[![PHPStan](https://img.shields.io/badge/PHPStan-level%208-brightgreen.svg?style=flat-square)](https://github.com/phpstan/phpstan)

A skeleton for creating applications with [CakePHP](https://cakephp.org) 5.x.

The framework source code can be found here: [cakephp/cakephp](https://github.com/cakephp/cakephp).

## Installation

1. Download [Composer](https://getcomposer.org/doc/00-intro.md) or update `composer self-update`.
2. Run `php composer.phar create-project --prefer-dist cakephp/app [app_name]`.

If Composer is installed globally, run

```bash
composer create-project --prefer-dist cakephp/app
```

In case you want to use a custom app dir name (e.g. `/myapp/`):

```bash
composer create-project --prefer-dist cakephp/app myapp
```

You can now either use your machine's webserver to view the default home page, or start
up the built-in webserver with:

```bash
bin/cake server -p 8765
```

Then visit `http://localhost:8765` to see the welcome page.

## Demo app

Check out the [5.x-demo branch](https://github.com/cakephp/app/tree/5.x-demo), which contains demo migrations and a seeder.
See the [README](https://github.com/cakephp/app/blob/5.x-demo/README.md) on how to get it running.

## Update

Since this skeleton is a starting point for your application and various files
would have been modified as per your needs, there isn't a way to provide
automated upgrades, so you have to do any updates manually.

## Configuration

Read and edit the environment specific `config/app_local.php` and set up the
`'Datasources'` and any other configuration relevant for your application.
Other environment agnostic settings can be changed in `config/app.php`.

## Layout

The app skeleton uses [Milligram](https://milligram.io/) (v1.3) minimalist CSS
framework by default. You can, however, replace it with any other library or
custom styles.

## Contributors

Andrew Turner (CEO):
Strategic vision and quality control

Amanda Tougher (Administration manager):
Strategic vision and quality control

Kevin Chen (IT business analyst)
Project manager and developer

## Automatic email delivery

The portal sends notifications through Resend over HTTPS. PHP `mail()` and SMTP
are not suitable for this Render Free deployment.

1. In Resend, add and verify a sending domain that you control. Have the domain's
   DNS administrator add the exact records shown by Resend. An API key alone
   does not verify the domain. HTTP 403 with “Domain not verified” means this step
   is incomplete.
2. Set `RESEND_API_KEY` and `EMAIL_FROM` on the server running the app. `EMAIL_FROM`
   accepts an address or `Foley's List <sender@your-verified-domain>`.
   Render variables do not configure localhost; set the same variables in the
   local PHP server environment, or use the ignored `config/app_local.php`.
   Never commit keys or place them in browser JavaScript.
3. Deploy the updated code. In the administrator Weekly report page, save the
   nominated mailbox and use **Send test email**. Delivery errors appear in
   Render's service Logs; locally they appear in `logs/error.log` (or
   `logs/cli-error.log` for commands). Successful API acceptance logs an email ID.
   Check the matching email in Resend for its delivered/bounced status.

Each saved request attempts the team notification and barrister confirmation
independently. Email failure leaves the request saved and shows a warning.
Weekly report messages include the Excel attachment; scheduling still requires
running `bin/cake send_weekly_report` on the selected day.

## Form security

Email and login values are validated on the server. CakePHP ORM binds database
values; templates use escaped HTML/FormHelper output. Passwords are compared
exactly and are never altered by stripping HTML or SQL-looking punctuation.
CSRF checks remain enabled. Regression tests cover injection payloads and safely
stored addresses containing valid punctuation.

## Barrister name suggestions

The request model is `src/Model/Table/OrdersTable.php`. New requests save a
required `barrister_name` alongside the required email address. Older requests
with no saved name show a dash in the admin tables and a blank name in Excel.

### Report window and Excel downloads

The Weekly report page, Excel download, and scheduled report attachment include
all undownloaded submitted requests up to the report generation time, regardless
of submission date. Requests remain pending until they are downloaded or removed
by an administrator. Removed requests and their items are retained and can be
restored from the Removed requests link on All requests.

Downloads are `.xlsx` workbooks with a native `BarristerRequests` Excel Table,
filterable headers, frozen headings, numeric prices, and Melbourne
date/time cells. The sheet contains only the table starting at A1, without title,
summary, footer or spacer rows. The page still displays the total billed, while
the export retains each line's discounted cost. User-provided
names are stored as literal text, never executable formulas.
Clicking Download Excel generates the file and then marks only its included
requests as downloaded, clearing them from the report for all administrators.
New requests arriving during generation remain pending. Requests and item records are never deleted, and remain
visible in Incoming requests. Generation/reset is transactional: generation or
database failures do not clear requests. The reset happens when the server
generates the file, not when the browser confirms it was saved (browsers cannot
provide that confirmation). Scheduled email generation does not reset the report.
Downloads require a CSRF-protected POST. The old `.csv` URL redirects to the report page.

Before using this change, run `bin/cake migrations migrate` against the intended
database to add the nullable `orders.report_downloaded_at` column, then run
`bin/cake schema_cache clear` to discard any cached pre-migration schema.

Excel generation requires PHP's `zip` and `dom` extensions. The Docker image
installs `zip`; `dom` is included in its PHP base image. The styled template is
`resources/reports/weekly-report-template.xlsx`, populated server-side by
`src/Service/WeeklyReportWorkbook.php` without a Node runtime or an Excel licence.

The name field in `templates/Orders/add.php` searches the supplied public
directory after two characters. Selecting a suggestion fills in its matching
email; users can also enter their details manually. Changing a selected name
clears its auto-filled email unless the user has already edited that email.

`resources/data/barristers.json` contains the 364 name/email pairs imported from
columns A–C of `Mailing List (as at Sept 2026)` in the supplied workbook. Other
columns and sheets are not included. The deployed app reads this JSON, so it
does not need Excel installed or access to the original file in Downloads.
To refresh the directory, replace its `barristers` entries with updated
`{"name": "Full Name", "email": "address@example.com"}` records. Keep duplicate
names with different addresses as separate records so users can choose the
correct entry.

## Piece specials and safe garment removal (2 November 2026)

See [pricing-specials.md](docs/pricing-specials.md) for the original offers,
the deletion repair, tests, and deployment/activation instructions.

The two-piece $30 and three-piece $32 specials repeat across disjoint groups.
Piece specials use eligible garments in ascending price order. Repeatable
bundles are combined within that order, and each bundle must reduce its selected
garments’ price. Five or more Shirts receive a $6 single-piece rate.
Bar robes are excluded from every discount. Jabots are excluded from piece
specials. Garments receiving a unit-price offer are priced separately and cannot
also enter piece bundles. Other garments qualify regardless of legacy cap flags.
Exact discounted line costs are persisted for reports and Excel.

The three offers were activated by the user on 2 November 2026. The older rules
have been deleted. This update changes the pricing code without modifying live
database rules. No commit, push or deployment is performed automatically.

Garment removal preserves referenced history by archiving. Stale schema
metadata is refreshed automatically; an unused garment can also be deleted
before the archive field exists. The shared archive and discounted-line fields
have already been added without running unrelated migrations. Their migration
files are idempotent for future deployments.

## Report and automatic request confirmations

The downloadable report has eight columns, ending at H (`Amount (Inc TAX)`).
Email address, Item type and Quantity are omitted. It retains one row per
garment type and the native `BarristerRequests` table. Amount is the saved
discounted line charge, without additional tax. Download/reset rules are unchanged.

Resend confirmations greet the barrister by their first name and include an HTML
table of Melbourne submission date, garment, quantity and discounted cost.
Admin is copied at `admin@foleys.com.au`; `EMAIL_FROM` still sets the sender.
Edit the email in `templates/email/html/request_submitted.php`.

`webroot/js/request-submit.js` disables the submit button and blocks repeat form
submissions while the first request is pending. Invalid required fields stay
editable, and browser-history restoration re-enables the button.

The old Office Script in `resources/automation/DailyLaundry.ts` is retained only
for older workbooks. New eight-column reports are not inputs to that flow.
See [report-import-format.md](docs/report-import-format.md) for the current column map.
