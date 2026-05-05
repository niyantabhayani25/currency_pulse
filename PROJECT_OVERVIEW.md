# CurrencyPulse — Project Overview

> A currency tracking and historical rate reporting application. Users select up to 5 currencies, watch live USD-based exchange rates, and request historical reports that run as background jobs.

---

## Table of Contents

1. [What the App Does](#1-what-the-app-does)
2. [Tech Stack](#2-tech-stack)
3. [Project Structure](#3-project-structure)
4. [Database Schema](#4-database-schema)
5. [Backend Architecture](#5-backend-architecture)
6. [External APIs & Rate Services](#6-external-apis--rate-services)
7. [Report Generation Pipeline](#7-report-generation-pipeline)
8. [Frontend Architecture](#8-frontend-architecture)
9. [Authentication & Security](#9-authentication--security)
10. [API Reference](#10-api-reference)
11. [Local Setup](#11-local-setup)
12. [Development Workflow](#12-development-workflow)
13. [Key Design Decisions](#13-key-design-decisions)

---

## 1. What the App Does

CurrencyPulse has two core features:

**Live Rate Dashboard**
- The user registers and picks up to 5 currencies they care about (e.g. EUR, GBP, INR).
- The dashboard shows real-time USD-based exchange rates for those currencies, refreshed every 10 minutes via the Frankfurter API.
- The selection is saved per-user in the database and synced on every login.

**Historical Reports**
- The user picks a currency, a time range (One Year / Six Months / One Month), and clicks "Request Report".
- The app creates a report record in `pending` status and dispatches a background job.
- The job fetches historical rates from the Frankfurter API, applies carry-forward logic for weekends and public holidays (ECB does not publish on those days), and stores each data point in `report_results`.
- Once done, status becomes `completed` and the user can view a chart + data table showing how the rate moved over time.

---

## 2. Tech Stack

### Backend

| Layer | Technology | Version | Role |
|---|---|---|---|
| Language | PHP | 8.3 | Runtime |
| Framework | Laravel | 13 | HTTP, queues, ORM, auth scaffolding |
| Authentication | Laravel Fortify | — | Registration, login, 2FA, password reset |
| API auth | Laravel Sanctum | — | SPA cookie-based auth for the API layer |
| Database | MySQL | 8.0+ | Relational database; queue and cache also stored here via database driver |
| Queue driver | Database | — | Jobs stored in `jobs` table, processed by `queue:work` |
| Cache driver | Database | — | Rate responses cached in `cache` table |
| Test framework | PestPHP | 4 | Feature + unit tests |

### Frontend

| Layer | Technology | Version | Role |
|---|---|---|---|
| UI framework | Vue 3 | 3.x | Component-based UI |
| Language | TypeScript | 5.x | Type-safe JS across all components |
| Server–Client bridge | Inertia.js | 3.x | Renders Vue pages server-driven; no separate API for page loads |
| Styling | Tailwind CSS | 4 | Utility-first CSS (v4 uses CSS-based config, no `tailwind.config.js`) |
| Build tool | Vite | 8 | HMR in dev, bundling for production |
| Routing helpers | Wayfinder | — | Auto-generates typed TypeScript route helpers from Laravel routes; SSR-safe |
| Toast notifications | vue-sonner | — | Non-blocking success/error notifications |

---

## 3. Project Structure

```
five9/
├── app/
│   ├── Console/Commands/
│   │   └── ProcessReportsCommand.php     # php artisan reports:process
│   ├── Enums/
│   │   ├── ReportInterval.php            # monthly | weekly | daily
│   │   ├── ReportRange.php               # one_year | six_months | one_month + datePoints()
│   │   └── ReportStatus.php              # pending | processing | completed | failed
│   ├── Http/Controllers/
│   │   ├── Api/
│   │   │   ├── CurrencyController.php    # GET/PUT /api/currencies
│   │   │   └── ReportController.php      # CRUD /api/reports
│   │   ├── DashboardController.php       # Inertia page: /dashboard
│   │   └── ReportPageController.php      # Inertia page: /reports/{id}
│   ├── Jobs/
│   │   └── GenerateReportJob.php         # Queued job; 3 retries, backoff 30s/120s
│   ├── Models/
│   │   ├── Currency.php
│   │   ├── Report.php
│   │   ├── ReportResult.php
│   │   ├── User.php
│   │   └── UserCurrency.php              # Pivot: user ↔ currency selection
│   └── Services/
│       ├── FrankfurterService.php        # Frankfurter API wrapper (primary)
│       ├── CurrencyLayerService.php      # CurrencyLayer wrapper (optional, unused by default)
│       ├── SyntheticRateService.php      # Deterministic local fallback
│       └── HistoricalRateResolver.php    # Orchestrates Frankfurter → Synthetic fallback
├── database/
│   ├── factories/                        # Test factories for all models
│   ├── migrations/                       # Schema history
│   └── seeders/CurrencySeeder.php        # Seeds currencies table from Frankfurter /currencies
├── resources/
│   ├── css/app.css                       # Tailwind v4 entry (uses @import "tailwindcss")
│   └── js/
│       ├── app.ts                        # Inertia app bootstrap
│       ├── components/                   # Reusable Vue components
│       ├── composables/useApi.ts         # Axios wrapper with CSRF + error handling
│       ├── layouts/CurrencyLayout.vue    # Main app shell (nav + toast host)
│       ├── pages/
│       │   ├── Dashboard.vue             # Main dashboard page
│       │   └── Report/Show.vue           # Report detail page (chart + table)
│       ├── routes/                       # Auto-generated by Wayfinder (do not edit)
│       └── types/index.ts                # Shared TypeScript interfaces
├── routes/
│   ├── web.php                           # Inertia page routes
│   ├── api.php                           # JSON API routes (Sanctum-protected)
│   ├── auth.php                          # Fortify auth routes
│   └── settings.php                      # Profile/security settings routes
└── tests/
    └── Feature/Api/                      # PestPHP feature tests
```

---

## 4. Database Schema

### `users`
Standard Laravel users table extended with Fortify 2FA columns (`two_factor_secret`, `two_factor_recovery_codes`, `two_factor_confirmed_at`).

### `currencies`
| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| code | char(3) UNIQUE | ISO 4217 — e.g. `EUR`, `INR`, `GBP` |
| name | string | Full name — e.g. "Euro" |
| symbol | string(10) nullable | e.g. `€`, `₹` |

Seeded once via `CurrencySeeder` which fetches the list from Frankfurter's `/currencies` endpoint.

### `user_currencies` (pivot)
| Column | Type | Notes |
|---|---|---|
| user_id | FK → users | cascade delete |
| currency_id | FK → currencies | restrict delete |
| UNIQUE | (user_id, currency_id) | one currency per user, no duplicates |

Stores which currencies a user has selected. Max 5 enforced in the API request validation.

### `reports`
| Column | Type | Notes |
|---|---|---|
| user_id | FK → users | cascade delete |
| currency_id | FK → currencies | restrict delete |
| range | string(20) | `one_year` / `six_months` / `one_month` |
| interval | string(10) | `monthly` / `weekly` / `daily` |
| status | string(15) | `pending` → `processing` → `completed` / `failed` |
| data_source | string(20) nullable | `frankfurter` or `synthetic` (set on completion) |
| error_message | text nullable | Set on permanent failure |
| completed_at | timestamp nullable | |

Indexed on `(user_id, status)` and `(user_id, created_at)` for the dashboard list query.

### `report_results`
| Column | Type | Notes |
|---|---|---|
| report_id | FK → reports | cascade delete |
| date | date | The date this rate applies to |
| rate | decimal(15,6) nullable | USD → target currency rate |
| UNIQUE | (report_id, date) | one row per date per report |

One row per data point. Null rate means no data was available even after fallback (should not happen in practice).

---

## 5. Backend Architecture

### Request lifecycle (Inertia page load)

```
Browser GET /dashboard
    → Laravel router → auth middleware
    → DashboardController
    → Inertia::render('Dashboard', [ rangePairs ])
    → Blade view renders Inertia root div
    → Vue hydrates in browser
    → Dashboard.vue mounts → calls /api/currencies + /api/reports in parallel
```

Inertia handles the page shell (title, layout, initial props) server-side. All data after mount comes from the JSON API endpoints.

### Service container bindings (AppServiceProvider)

```php
// FrankfurterService gets its base URL from config
$this->app->bind(FrankfurterService::class, fn () =>
    new FrankfurterService(config('services.frankfurter.url'))
);

// CurrencyLayerService gets its API key from .env (optional)
$this->app->bind(CurrencyLayerService::class, fn () =>
    new CurrencyLayerService(
        config('services.currency_layer.url'),
        config('services.currency_layer.key') ?? ''
    )
);
```

### Enums as domain types

`ReportRange` is a backed enum that encodes business logic:

- `allowedInterval()` — enforces which interval is valid for each range (e.g. One Year always uses Monthly)
- `label()` — human-readable string shown in UI
- `datePoints()` — generates the exact date strings the job will fetch rates for:
  - **One Year / Monthly**: 1st of each month, 12 dates, going back 12 months
  - **Six Months / Weekly**: each Monday, 26 dates, going back 26 weeks
  - **One Month / Daily**: each calendar day, 30 dates, going back 30 days

---

## 6. External APIs & Rate Services

### Frankfurter (`api.frankfurter.app`)

Free, no API key required. Uses European Central Bank (ECB) data. **Primary data source for everything.**

| Use | Endpoint | Caching |
|---|---|---|
| Live rates (dashboard) | `GET /latest?from=USD&to=EUR,GBP,...` | 10 minutes |
| Historical rates (reports) | `GET /{start}..{end}?from=USD&to=EUR` | 1 hour |
| Currency list (seeder) | `GET /currencies` | 24 hours |

**Important ECB behaviour:** The ECB does not publish rates on weekends or public holidays (e.g. Christmas, New Year, May Day). Frankfurter returns no entry for those days. The `FrankfurterService` handles this with carry-forward logic: it fetches 7 days before the requested start date so the prior Friday/business day rate is always available in the window. Weekend/holiday dates then inherit the most recent prior business day rate.

**Offline resilience:** All three HTTP calls catch `ConnectionException`. If the network is unavailable, rate fetches return `null` (triggering fallback to synthetic data), and the currency list falls back to what is already cached or seeded in the DB.

### CurrencyLayer (`api.currencylayer.com`)

Optional paid API. The service class (`CurrencyLayerService`) is wired in the container but **not called anywhere in the main application flow**. It exists as a ready drop-in replacement if Frankfurter ever becomes unavailable or insufficient. Enable by setting `CURRENCY_LAYER_API_KEY` in `.env`.

### SyntheticRateService (local, no network)

A pure-PHP fallback that generates deterministic rates from a hash:

```php
// Same currency + date always produces the same float — no randomness
$hash = abs(crc32("{$currency}_{$date}"));
$rate = 0.5 + ($hash % 200) / 100.0;   // range: 0.50 – 2.49
```

Used automatically when Frankfurter returns null (unsupported currency or network outage). Report pages flag synthetic data with a visible notice so users know the rates are not real. The `data_source` column on the `reports` table records which service was used.

---

## 7. Report Generation Pipeline

```
User clicks "Request Report" on Dashboard
        │
        ▼
POST /api/reports  ────────────────────────────────────────────────────────┐
        │                                                                   │
        ▼                                                                   │
StoreReportRequest validates:                                               │
  - currency_id exists in currencies table                                  │
  - range is one_year | six_months | one_month                              │
        │                                                                   │
        ▼                                                                   │
Report created: { status: pending, data_source: null }                      │
        │                                                                   │
        │     [Background — scheduler every 15 min, or manually]            │
        ▼                                                                   │
php artisan reports:process ────────────────────────────────────────────────┘
        │
        ▼
GenerateReportJob dispatched to queue
        │
        ▼
queue:work picks up job
        │
        ├─ Guard check: skip if status ≠ pending (prevents double-processing)
        │
        ├─ Set status → processing
        │
        ▼
HistoricalRateResolver::resolve($report)
        │
        ├─ Get date points from $report->range->datePoints()
        │
        ├─ Call FrankfurterService::getHistoricalRates($currency, $dates)
        │    ├─ Extend fetch window 7 days back (for weekend/holiday coverage)
        │    ├─ Call GET /2025-04-24..2026-04-01?from=USD&to=INR
        │    ├─ Normalise: {"2025-07-01": {"INR": 85.56}} → {"2025-07-01": 85.56}
        │    └─ Carry-forward: fill missing dates using prior business day rate
        │
        ├─ If Frankfurter returns null → fall back to SyntheticRateService
        │
        ▼
DB transaction:
  INSERT INTO report_results (report_id, date, rate) VALUES (...)  [one row per date]
  UPDATE reports SET status=completed, data_source=frankfurter, completed_at=now()
        │
        ▼
Job done. Dashboard status badge updates on next poll/refresh.

On permanent failure (after 3 retries with 30s / 120s backoff):
  UPDATE reports SET status=failed, error_message=...
```

### Retry strategy
- `$tries = 3` — up to 3 attempts
- `$backoff = [30, 120]` — wait 30 s before attempt 2, 120 s before attempt 3
- On final failure: `failed()` callback marks the report and stores the error message

---

## 8. Frontend Architecture

### Inertia.js — the glue layer

Inertia eliminates the need for a separate REST API just for page rendering. Laravel controllers return `Inertia::render('PageName', $props)` instead of JSON or a Blade view. The browser receives a fully-rendered HTML page on first load, then subsequent navigations are XHR requests that swap only the page component — no full reload, no separate frontend router.

```
Laravel Controller                Vue Component
─────────────────                ─────────────
Inertia::render('Dashboard',  →  pages/Dashboard.vue receives
  ['rangePairs' => [...]])         defineProps<{ rangePairs: RangePair[] }>()
```

### Wayfinder — type-safe routing

Wayfinder reads your Laravel routes and auto-generates TypeScript helper files in `resources/js/routes/`. Instead of the Ziggy `route('dashboard')` global (which fails during SSR), you import a typed function:

```ts
import { dashboard, logout } from '@/routes'
import { show as showReport } from '@/routes/reports'

dashboard.url()           // → "/dashboard"
showReport.url(report.id) // → "/reports/42"
```

These imports work in both the browser and the Inertia SSR Node.js process.

### Data flow on the Dashboard

```
onMounted()
    │
    ├── GET /api/currencies  ──→  { currencies[], selected_ids[], rates{} }
    │       currencies → all available to pick from (from DB, excluding USD)
    │       selected_ids → user's saved selection
    │       rates → live rates for selected currencies (from Frankfurter /latest)
    │
    └── GET /api/reports  ──→  { reports[] }
            status, currency, range_label, created_at for each report
```

The `useApi` composable wraps Axios with automatic CSRF token handling and a consistent error response so every caller just checks `if (data)` without catching exceptions.

### Key components

| Component | Purpose |
|---|---|
| `CurrencyLayout.vue` | Sticky top nav, flash toast, `<Toaster>` host |
| `Dashboard.vue` | Currency selector, live rates table, report request form, report job list |
| `Report/Show.vue` | Stat cards (latest/high/low), line chart (Chart.js), data table with % change |
| `CurrencySelector.vue` | Multi-select toggle grid, enforces max 5 |
| `RatesTable.vue` | Displays live USD rates for selected currencies |
| `StatusBadge.vue` | Color-coded badge: pending (gray) / processing (amber) / completed (green) / failed (red) |
| `SectionCard.vue` | Consistent card wrapper with title, subtitle, and optional header slot |

### Tailwind CSS v4

Tailwind v4 dropped `tailwind.config.js`. Configuration lives in CSS:

```css
/* resources/css/app.css */
@import "tailwindcss";

@theme {
  --font-display: 'Inter', sans-serif;
  --font-body:    'Inter', sans-serif;
  --font-mono:    'JetBrains Mono', monospace;
}
```

---

## 9. Authentication & Security

### Registration & Login

Handled entirely by **Laravel Fortify**. The routes (`/register`, `/login`, `/logout`, `/forgot-password`, `/reset-password`) are defined in `routes/auth.php` and rendered as Inertia pages in `resources/js/pages/auth/`.

On successful registration, an event listener in `AppServiceProvider` flashes a welcome message to the session.

### SPA Session Auth (Sanctum)

The frontend communicates with `/api/*` endpoints using Laravel Sanctum's SPA cookie authentication. The browser holds a `laravel_session` cookie (HttpOnly) — no bearer tokens. The `auth:sanctum` middleware on all API routes validates this session cookie.

### Two-Factor Authentication (TOTP)

Enabled via Fortify. Users manage it at **Settings → Security**:

1. Click **Enable 2FA** — a QR code is generated for any TOTP app (Google Authenticator, Authy, 1Password, etc.)
2. Scan the QR code and enter the 6-digit code to confirm setup
3. Recovery codes are shown once — the user must save them
4. On every subsequent login, after email/password, Fortify redirects to `/two-factor-challenge` where the user enters their current 6-digit TOTP
5. **Disable 2FA**: return to Settings → Security → Disable

The `two_factor_secret` stored in the DB is encrypted with the app key. Recovery codes are hashed.

### Authorization

Report ownership is enforced in `ReportController` using Laravel's `authorize()` method with a `ReportPolicy`. Users can only view and delete their own reports. The `GenerateReportJob` also guards against double-processing if multiple workers pick up the same job.

---

## 10. API Reference

All routes are under `/api` and protected by `auth:sanctum`.

### Currencies

```
GET  /api/currencies
     Response: { currencies[], selected_ids[], rates{} }
     currencies   — all available currencies (excluding USD)
     selected_ids — IDs of currencies the user has selected
     rates        — live Frankfurter rates for selected currencies (null if none selected)

PUT  /api/currencies
     Body: { currency_ids: number[] }   (1–5 IDs, must exist in DB)
     Response: { selected_ids: number[] }
     Replaces the user's entire selection in a transaction.
```

### Reports

```
GET    /api/reports
       Response: { reports[] }
       Returns all reports for the auth user, newest first.
       Each item: { id, currency{ code }, range_label, status, created_at }

POST   /api/reports
       Body: { currency_id: number, range: "one_year"|"six_months"|"one_month" }
       Response: { report }
       Creates a pending report. Does NOT dispatch the job — reports:process does that.

GET    /api/reports/{id}
       Response: full report with results[]
       Policy: user must own the report.

DELETE /api/reports/{id}
       Response: 204 No Content
       Policy: user must own the report.
```

---

## 11. Local Setup

### Prerequisites
- PHP 8.3+ with `php-pdo_mysql` and `php-curl` extensions enabled
- MySQL 8.0+
- Composer
- Node.js 20+

### Steps

```bash
# 1. Install dependencies
composer install
npm install

# 2. Environment
cp .env.example .env
php artisan key:generate
# Then open .env and fill in DB_DATABASE, DB_USERNAME, DB_PASSWORD

# 3. Create the MySQL database
mysql -u root -p -e "CREATE DATABASE currency_pulse CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# 4. Run migrations and seed currencies
php artisan migrate
php artisan db:seed --class=CurrencySeeder

# 4. Run three terminals in parallel:

# Terminal 1 — PHP dev server
php artisan serve

# Terminal 2 — Vite (HMR + Wayfinder codegen)
npm run dev

# Terminal 3 — Queue worker (required for report generation)
php artisan queue:work
```

Visit `http://localhost:8000`. Register an account — currencies are already seeded.

> **Important:** After changing any PHP service file, restart the queue worker (Ctrl+C → `php artisan queue:work`) so the worker process picks up the new code. PHP loads files once at worker startup.

> **Reports:** The scheduler triggers `reports:process` every 15 minutes in production. In development, run it manually after requesting a report:
> ```bash
> php artisan reports:process
> ```

### Environment variables

| Variable | Required | Default | Description |
|---|---|---|---|
| `APP_KEY` | Yes | — | Set by `key:generate` |
| `DB_CONNECTION` | Yes | `mysql` | Must match your MySQL setup |
| `DB_HOST` | Yes | `127.0.0.1` | MySQL host |
| `DB_PORT` | Yes | `3306` | MySQL port |
| `DB_DATABASE` | Yes | `currency_pulse` | Must be created in MySQL first |
| `DB_USERNAME` | Yes | — | MySQL username |
| `DB_PASSWORD` | Yes | — | MySQL password |
| `QUEUE_CONNECTION` | No | `database` | Jobs stored in MySQL `jobs` table |
| `CACHE_STORE` | No | `database` | Cache stored in MySQL `cache` table |
| `MAIL_MAILER` | No | `log` | Emails written to `storage/logs/laravel.log` |
| `CURRENCY_LAYER_API_KEY` | No | — | Only needed if switching live rates to CurrencyLayer |

---

## 12. Development Workflow

### Running tests

```bash
php artisan test              # all tests
php artisan test --filter=Api # only API tests
```

Tests use `RefreshDatabase` with an in-memory SQLite database (configured in `phpunit.xml` independently of the app's MySQL config) and `Http::fake()` to mock all external API calls — no MySQL connection or internet required to run the test suite. This is intentional: tests run fast and in complete isolation from your local database.

### Manually processing reports

```bash
php artisan reports:process
```

Dispatches `GenerateReportJob` for every report with `status = pending`. The job runs synchronously if `QUEUE_CONNECTION=sync`, or asynchronously if the queue worker is running.

### Resetting the database

```bash
php artisan migrate:fresh --seed
```

Drops all tables, re-runs migrations, and re-seeds currencies from Frankfurter.

### Clearing the cache

```bash
php artisan cache:clear
```

Clears all cached Frankfurter responses from the `cache` table. Useful when debugging rate discrepancies.

### Useful commands

```bash
php artisan tinker                          # REPL — explore models, test services
php artisan queue:work                      # Process background jobs
php artisan reports:process                 # Dispatch pending report jobs
php artisan db:seed --class=CurrencySeeder  # Refresh currency list
php artisan migrate:fresh --seed            # Full DB reset
php artisan cache:clear                     # Clear rate caches
```

---

## 13. Key Design Decisions

### Why Inertia instead of a separate Vue SPA?

A separate SPA would require a fully documented REST API, CORS configuration, separate auth tokens, and a second deploy target. Inertia gives the feel of a SPA (no page reloads, instant transitions) while keeping the backend as a conventional Laravel app. The controllers stay simple — they just pass data to Vue pages.

### Why MySQL?

MySQL 8.0 is a production-grade relational database with strong support for concurrent writes — important for the queue worker processing multiple report jobs and for the cache table being read/written simultaneously by the API. All migrations use `utf8mb4` charset and `utf8mb4_unicode_ci` collation, which correctly handles multi-byte characters in currency names.

Queue jobs (`jobs` table) and cached API responses (`cache` table) also live in MySQL via Laravel's database driver — no Redis or additional services are required for local development or standard deployments.

### Why Frankfurter over CurrencyLayer?

Frankfurter is free, requires no API key, and provides ECB data which is the authoritative source for most major currency pairs. CurrencyLayer is a paid API — keeping it as a wired-but-inactive service means the switch is a single line of code change in `CurrencyController` if needed.

### Why the SyntheticRateService?

Historical reports should always produce output, even if Frankfurter is down or the currency is not ECB-tracked (e.g. some exotic currencies). The synthetic service uses `crc32("{currency}_{date}")` to produce stable, deterministic rates — the same currency/date pair always returns the same number. The UI flags synthetic data so users are not misled.

### Why carry-forward rates for weekends/holidays?

The ECB does not publish exchange rates on weekends or public holidays (New Year, May Day, Christmas, etc.). When a report date falls on a non-publishing day, the most recent available prior business-day rate is used. This matches the standard financial industry convention (which this application follows for data visualization purposes). The fetch window is extended 7 days before the requested start so that even a range-start date that falls on a holiday has prior data available.

### Why Wayfinder instead of Ziggy?

Ziggy injects a `window.route()` global at runtime — it is not available in the Inertia SSR Node.js process, causing server-side rendering errors. Wayfinder generates static TypeScript files that are imported as ES modules — they work identically in both browser and Node.js SSR environments.

### Why queue jobs for report generation?

Fetching up to a year of exchange rates from an external API, processing the data, and inserting 12–30 rows takes several seconds — too long for a synchronous HTTP request. The queue decouples the user's request from the processing: the POST returns instantly with `pending`, and the user can close the browser and come back later to see `completed`.
