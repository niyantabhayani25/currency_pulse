# CurrencyPulse

A currency tracking and historical rate reporting app built with Laravel 13, Vue 3, and Inertia.js.

## Tech Stack

**Backend:** PHP 8.3 · Laravel 13 · Fortify (auth) · Sanctum (SPA cookie auth) · MySQL  
**Frontend:** Vue 3 · TypeScript · Inertia.js v3 · Tailwind CSS v4 · Vite 8  
**Routing:** Wayfinder (typed TS route helpers) · Ziggy (named routes in JS)

---

## APIs

### Frankfurter — `api.frankfurter.app`
Free public API, no key needed. Handles all live and historical rate fetching:

| Use | Endpoint |
|-----|----------|
| Live rates (dashboard) | `GET /latest?from=USD&to=EUR,GBP,...` |
| Historical rates (reports) | `GET /{start}..{end}?from=USD&to=EUR` |
| Currency list (seeder) | `GET /currencies` |

### CurrencyLayer — `api.currencylayer.com`
Optional paid API. Set `CURRENCY_LAYER_API_KEY` in `.env` to enable. The service class (`CurrencyLayerService`) is wired in the container but not called by default — Frankfurter covers all rate fetching without a key.

### Synthetic Rate Service (local fallback)
No external API. Pure-math fallback for historical reports when Frankfurter fails (unsupported currency or network outage). Generates deterministic rates using `crc32("{currency}_{date}")` — the same currency + date always returns the same float. Report pages flag synthetic data with a notice.

---

## Local Setup

### Prerequisites
- PHP 8.3+, Composer
- Node.js 20+
- MySQL 8.0+

### Steps

```bash
#git clone <repo-url> && cd five9

#unzip file CurrencyPulse_Deliverable.zip
cd five99
composer install
npm install

cp .env.example .env
php artisan key:generate

# Create the MySQL database first, then configure .env:
# DB_DATABASE=currency_pulse
# DB_USERNAME=your_username
# DB_PASSWORD=your_password

php artisan migrate
php artisan db:seed --class=CurrencySeeder
```

Then open **three terminals**:

```bash
# Terminal 1 — PHP dev server
php artisan serve

# Terminal 2 — Vite (HMR + Wayfinder codegen)
npm run dev

# Terminal 3 — Queue worker (required for report generation)
php artisan queue:work
```

Visit `http://localhost:8000`. Register an account — the currency list will already be seeded.

> **Reports:** The scheduler triggers `reports:process` every 15 minutes. During development, run it manually instead:
> ```bash
> php artisan reports:process
> ```

### Environment variables

| Variable | Required | Description |
|----------|----------|-------------|
| `APP_KEY` | Yes | Set by `php artisan key:generate` |
| `CURRENCY_LAYER_API_KEY` | No | Only if switching live rates to CurrencyLayer |

Queue and cache use the database driver out of the box (`QUEUE_CONNECTION=database`, `CACHE_STORE=database`), meaning jobs and cache are stored in your MySQL database — no Redis or extra services required for local development.

---

## Report Generation

```
POST /api/reports  →  Report (status: pending)
                           ↓
                  [scheduler every 15 min]
                  php artisan reports:process
                           ↓
                  GenerateReportJob dispatched
                           ↓
              HistoricalRateResolver::resolve()
                    /              \
           Frankfurter API      SyntheticRateService
           (real ECB data)      (deterministic fallback)
                    \              /
             report_results rows inserted
                           ↓
                  Report (status: completed)
```

- Job retries 3 times with backoff 30 s → 120 s
- On permanent failure: `status: failed`, error message stored
- `data_source` column records which service provided the rates (`frankfurter` or `synthetic`)

---

## Two-Factor Authentication

Yes — enabled via Fortify. Users manage it under **Settings → Security**:

1. Click **Enable 2FA** — a QR code appears for any TOTP app (Google Authenticator, Authy, etc.)
2. Confirm the setup code to activate
3. Recovery codes are shown once — save them
4. On next login, users are prompted for a 6-digit TOTP code

To disable: return to Settings → Security and click **Disable 2FA**.

---

## Useful Commands

```bash
php artisan reports:process                  # manually dispatch all pending report jobs
php artisan queue:work                       # process jobs from the database queue
php artisan db:seed --class=CurrencySeeder  # refresh the currency list
php artisan migrate:fresh --seed             # wipe and rebuild the database
php artisan tinker                           # REPL for exploring models
```
