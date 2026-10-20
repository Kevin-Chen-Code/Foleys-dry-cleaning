# Local load-test results — 20 October 2026

## Scope

Tested the actual request controller/model/pricing transaction through HTTP,
including CSRF cookies, form submission, receipt and expected discounted price.
Each run used a newly migrated local SQLite database, synthetic records,
non-sending CakePHP DebugTransport and a loopback-only PHP development server
configured with 8 worker subprocesses. Existing Neon records, app_local.php,
the user's localhost server and production mail configuration were not used.

Environment: macOS arm64, PHP 8.4.8, SQLite 3.50.1, Grafana k6 2.3.0.
The official standalone k6 archive was checked against SHA-256
`b2417a3038edc5fe81dc178a889237724b595c5c9cfed875822008e46e862c7d`.
The binary/archive remain under ignored `tmp/load-tools/`; no global install.

## Validated runs

| Scenario | Planned / confirmed / saved | Pre-existing synthetic orders | Dropped submissions | Full-flow p95 | Full-flow maximum |
| --- | --- | --- | --- | --- | --- |
| Smoke, 5 arrivals over 5 seconds | 5 / 5 / 5 | 0 | 0 | 79.4 ms | 88 ms |
| Rush, 600 arrivals over 60 seconds | 600 / 600 / 600 | 0 | 0 | 31 ms | 91 ms |
| Spike, 600 arrivals over 10 seconds | 600 / 600 / 600 | 18,000 | 0 | 12 ms | 96 ms |
| Extreme burst, 600 arrivals over 1 second | 600 / 600 / 600 | 18,000 | 0 | 1,017.1 ms | 1,242 ms |

Every listed run passed all HTTP, receipt, scheduling, latency and reconciliation
checks. There were no missing/duplicate request identities, incomplete or orphan
garment rows, incorrect prices, unexpected orders or HTTP failures. The extreme
burst's k6 samples observed up to 194 active virtual users. That sample is not a
claim that all 600 users were simultaneous or that the sample captures the exact
instantaneous maximum. One-second arrivals were allowed to finish afterwards.

Run directories, relative to the project root (IDs use UTC timestamps):

- Smoke: `tmp/load-tests/lt2026101923030137674dca/`
- Rush: `tmp/load-tests/lt20261019230332afd497c2/`
- Spike: `tmp/load-tests/lt202610192306497de4abc3/`
- Extreme: `tmp/load-tests/lt20261019230600647f7c73/`

Each directory retains `verification.json`, `k6-summary.json`, the isolated
database and server log. The extreme/spike databases each contain 18,600 orders
and 43,400 item lines in total. Other ignored directories can include development
runs that failed while the harness was being built; the table above identifies
the validated runs rather than treating every artifact as a passing test.

Example independent recheck:

```sh
php tests/load/verify-orders.php tmp/load-tests/lt20261019230600647f7c73/manifest.json
```

Additional regression checks: 59 PHPUnit tests / 1,747 assertions and 9 JavaScript
pricing tests passed, with PHP lint and whitespace checks clean. Six new harness
tests cover safety boundaries, overriding fake remote DB/mail environment values,
and detecting missing/duplicate orders, wrong prices and incomplete item lines.

## Interpretation and limitations

This is evidence that the current application saved every valid request in these
local burst scenarios. It is **not** a Neon Free or Render capacity certification:
SQLite locking/disk/compute, local latency and the PHP development server differ
from PostgreSQL and the deployed hosting environment. Network outages, monthly
quotas, real email latency, production reporting load, sustained all-day traffic
and automatic retry/idempotency behaviour were not established by these tests.

The safe next step for provider-specific evidence is an isolated Neon test
project and an equivalent Render service, with synthetic data and non-sending
email configuration. Do not repoint this local harness at live records; it
deliberately rejects remote targets and databases. A finite passing test cannot
guarantee zero lost requests in every future failure mode.
