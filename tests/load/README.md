# Isolated localhost request load test

See [RESULTS.md](RESULTS.md) for the measured local runs and their limitations.

Run from the project folder in VS Code's integrated terminal. This tests the real
request controller, models, validation, CSRF middleware, pricing and database
transaction under concurrent HTTP submissions. It does not post to your existing
localhost website, Neon or Render, and does not load `config/app_local.php`.

## Requirements and commands

- macOS/Linux, PHP CLI with `pcntl`, POSIX, curl and `pdo_sqlite`, and Composer dependencies.
- k6 on PATH (`brew install k6` on macOS), or `K6_BIN` pointing to its executable.
  The runner also recognises a standalone k6 binary under ignored `tmp/load-tools/k6-*/k6`.

```sh
# Smoke: five complete submissions over five seconds.
php tests/load/run.php --requests=5 --duration=5

# Rush: 600 submissions scheduled over a minute (10/second).
php tests/load/run.php --requests=600 --duration=60

# Stronger spike with a month's synthetic history already present (60/second).
php tests/load/run.php --requests=600 --duration=10 --history=18000

# Extreme burst: schedule 600 submissions within one second, then let them finish.
php tests/load/run.php --requests=600 --duration=1 --history=18000
```

Each command creates a new database and starts a loopback-only PHP server on a
fresh port, with eight worker subprocesses by default. `--workers=16` changes
worker count (allowed 2-32). This is a PHP development-server test, not an Apache
or production hosting benchmark. Stop with Ctrl+C; the runner stops only the
server process group and k6 process it created. No existing web server is stopped.

`--duration` is the arrival window, not a promise that every response finishes
inside it. Clients may take up to 60 additional seconds to finish. k6 virtual users
are allocated independently of PHP workers (up to 1,000). Dropped iterations mean
the client could not schedule all planned work; they are not proof of DB data loss.

Every run builds the actual app schema with its migrations, replaces only that
new database's catalogue/discount fixtures, and optionally seeds synthetic
history. The fixed catalogue is Shirt, Suit jacket, Bar robe and Jabot. Three
order profiles independently check price caps, exclusions, percentage and fixed
discounts, with expected discounted totals $21.20, $34.20 and $42.40.

## Safety boundaries

- No production source/configuration changes or migrations are performed.
- The test uses a separate CakePHP bootstrap; live database URLs, Resend keys,
  SMTP URLs, administrator credentials and local configuration are not used.
- Mail always uses CakePHP's non-sending `DebugTransport`. The normal application's
  two-recipient behaviour is unchanged. Synthetic addresses are not a mail safety
  mechanism by themselves; the non-sending transport is the safety mechanism.
- The test client accepts only a generated `http://127.0.0.1:<port>` target.
  The server requires a per-run token and matching Host header and rejects
  non-loopback clients. The client checks the matching isolated-server identity
  before posting. The normal site has no test endpoint or test-mode switch.
- All cookies, caches, logs, databases and results live in ignored
  `tmp/load-tests/<run-id>/`. Test records are retained there for inspection,
  never added to the user's live order list. No automatic data cleanup occurs.
- k6 uses a generated empty config and a restricted environment, with anonymous
  usage reporting disabled. No Grafana Cloud account is needed.
- The real CSRF token and cookies are fetched, not bypassed. Each simulated user
  gets a fresh cookie jar. A receipt is checked for the expected discounted price.
- No automatic retries are made: duplicate/missing submissions must remain visible.

## Results and verification

The terminal prints the run directory and final reconciliation. Files there:

- `manifest.json`: planned request count, fixture profiles, run ID and local access token.
- `k6-summary.json`: complete HTTP and submission metrics, checks and thresholds.
- `verification.json`: missing/duplicate identities, item/price integrity, timings and final pass/fail.
- `requests.sqlite` (and SQLite WAL files): inspectable isolated test records.
- `server.log` and, if any application errors occur, `application.log`.

Recheck stored records without starting the server or sending any requests:

```sh
php tests/load/verify-orders.php tmp/load-tests/REPLACE_WITH_RUN_ID/manifest.json
```

A run passes only if all planned requests were attempted and confirmed, no
scheduled submissions were dropped, all HTTP/check thresholds pass, and each
request is saved exactly once with the expected identity, complete item lines,
prices and timestamps. There must be no orphan items or unexpected extra orders.
The proposed latency target is p95 below 3 seconds for the full form/submit/receipt
flow, with zero submission errors. A saved order can still have a failed receipt
request, so HTTP acceptance and database integrity are reported separately.

## What this does NOT prove

The local database is **SQLite in WAL mode**, not PostgreSQL/Neon. Its locking,
compute, disk, connection limits and latency differ from Neon. An empty database
alone is also not representative of years of accumulated data; use `--history`.
These results cannot certify the Neon Free plan or Render's production capacity,
monthly quotas, cold-start behaviour or email-provider performance. The next step
for that is an explicitly isolated Neon test project and equivalent Render test
service. This local harness intentionally refuses remote targets/databases rather
than risk the real records. A passing finite load test cannot guarantee zero
future outages or request loss under every possible failure.
