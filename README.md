# SalonOS — Salon Management Platform

SalonOS is a multi-tenant salon management platform with a Laravel 12 API and a React 19 SPA.

---

## Tech Stack

### Backend
- PHP 8.4+
- Laravel 12
- Laravel Sanctum
- SQLite/MySQL

### Frontend
- React 19
- Vite 8 + Laravel Vite plugin
- Tailwind CSS 4
- React Router 7
- TanStack Query
- React Hook Form + Zod
- Vitest + React Testing Library

---

## Prerequisites

- PHP **8.2+** (XAMPP PHP works; Laravel Herd is optional)
- Composer
- Node.js 18+
- npm

### Windows + Device Guard / blocked Herd PHP

If you see:

`php.exe was blocked by your organization's Device Guard policy`

Your shell is using **Laravel Herd's PHP**, which IT may block. Use **XAMPP PHP** instead:

```bat
scripts\dev.bat
```

Or run commands explicitly:

```bat
C:\xampp\php\php.exe artisan serve
C:\xampp\php\php.exe artisan migrate --seed
npm run dev
```

Do **not** rely on `composer run dev` if `where php` points to Herd.

---

## Setup

```bash
cd saloon-management
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
npm install
npm run build
```

### Environment

Important variables in `.env`:

| Variable | Purpose |
|---|---|
| `APP_URL` | Laravel app URL |
| `VITE_API_BASE_URL` | Frontend API base (defaults to `${APP_URL}/api`) |
| `VITE_USE_MOCK_AUTH` | Local mock auth mode |
| `OTP_EXPOSE_CODE_IN_RESPONSE` | Must be `false` outside local dev |
| `OTP_ALLOW_DEVELOPMENT_MASTER_CODE` | Must be `false` in production |

---

## Development

Run the full stack:

```bash
composer run dev
```

This starts:
- Laravel (`php artisan serve`)
- queue worker
- log tail
- Vite dev server

The SPA is served from Laravel via `resources/views/app.blade.php`.

---

## Testing

Backend tests use an in-memory SQLite database (see `phpunit.xml`). No PostgreSQL setup is required locally.

```bash
# Backend
php artisan test

# Frontend
npm test
```

Default seed loads **all local demo scenario packs** (reference data + Glow salon + subscription cases + affiliate flows):

```bash
php artisan migrate
php artisan db:seed
```

After seeding, the terminal prints login tables for Glow salon, subscription demos, affiliate partners, and a consolidated scenario catalog.

**Passwords:** salon demo accounts `Demo@123` · super admin `Admin@123` (`admin@salonos.com`)

Re-run an individual pack:

```bash
php artisan db:seed --class=DemoDataSeeder
php artisan db:seed --class=SubscriptionDemoSeeder
php artisan db:seed --class=AffiliateDemoSeeder
```

### Picking up new permissions on an existing database

`php artisan db:seed` truncates the permission table and re-syncs every system role, which
discards permissions granted to a salon's own custom roles. To pull in only the permissions
a new feature added, without touching anything else:

```bash
php artisan db:seed --class=PermissionBackfillSeeder
```

It creates the missing permissions, grants them to the system roles whose definition lists
them, and leaves existing permissions and their assignments alone.

CI runs both via GitHub Actions (`.github/workflows/ci.yml`).

---

## Architecture

```text
app/
├── Actions/              # Auth, onboarding business logic
├── Http/Controllers/     # Thin API controllers (v1)
├── Http/Requests/        # Validation + authorization
├── Http/Resources/       # API response shaping
├── Models/
├── Policies/             # Authorization policies
├── Repositories/         # User/Saloon data access
└── Support/              # Permissions, tenant scope, list query

resources/js/
├── lib/api.js            # Shared axios client
├── stores/auth.js        # Auth context provider
├── router/               # Route guards + permissions
└── views/                # Feature screens
```

### Security model
- Sanctum token auth for API
- RBAC via permission codes seeded in `PermissionSeeder`
- Tenant scoping via `saloon_id` on users/resources
- Master catalog (categories/products/services) managed by system admins
- Tenant catalog pricing via `/api/v1/catalog`
- Onboarding requires authenticated user (no email impersonation)

### Default roles
- `saloon_owner` — global owner role with full tenant permissions
- System admin — `is_system_admin` flag (not mass assignable)

---

## API Overview

| Area | Endpoints |
|---|---|
| Auth | `/api/v1/public/login`, `/register`, forgot-password, `/logout` |
| Onboarding | `/api/v1/onboarding/*` (auth required) |
| Master catalog | `/api/v1/categories`, `/products`, `/services` |
| Tenant catalog | `/api/v1/catalog` |
| Customers | `/api/v1/customers`, `/api/v1/customer-tags` |
| Appointments | `/api/v1/appointments` |
| Appointment payment | `/api/v1/appointments/{id}/capture-payment`, `/refund-payment` |
| POS | `/api/v1/pos/catalog`, `/api/v1/pos/checkout` |
| Inventory | `/api/v1/inventory/summary`, `/inventory/stock`, `/inventory/movements`, `/suppliers`, `/purchase-orders` |
| Appointment receipt (PDF) | `/api/v1/appointments/{id}/receipt` |
| Analytics | `/api/v1/analytics/summary` |
| Salon settings | `/api/v1/salon/settings`, `/api/v1/salon/business-profile` |
| Staff / Roles | `/api/v1/staff`, `/api/v1/roles` |
| Admin | `/api/v1/admin/onboarding`, `/api/v1/saloons`, `/api/v1/admin/platform-settings` |

---

## Default super admin (local only)

Seeded by `SuperAdminSeeder`:
- Email: `admin@salonos.com`
- Password: `Admin@123`

Change these before any shared/staging deployment.

---

## Demo logins (local only)

After `php artisan migrate --seed`, all scenario packs below are seeded automatically.

**Salon demo password:** `Demo@123` · **Super admin:** `admin@salonos.com` / `Admin@123`

### Platform super admin

| Email | Role |
|-------|------|
| `admin@salonos.com` | Platform super admin — onboarding, all salons, platform settings |

### Glow demo salon — full product walkthrough (`DemoDataSeeder`)

| Email | Role |
|-------|------|
| `owner@glowdemo.com` | Franchise owner |
| `manager.andheri@glowdemo.com` | Andheri branch manager |
| `manager.bandra@glowdemo.com` | Bandra branch manager |
| `stylist.andheri@glowdemo.com` | Stylist (Andheri) |
| `stylist.bandra@glowdemo.com` | Stylist (Bandra) |

Includes branches, catalog, customers, appointments, POS payments, inventory, and stock.

### Subscription lifecycle (`SubscriptionDemoSeeder`)

| Email | Scenario | Expected behavior |
|-------|----------|-------------------|
| `free.expired@demo.test` | Free trial expired | Portal locked → upgrade via **Billing** |
| `paid.expired@demo.test` | Paid plan expired | Read-only → renew via **Billing** |
| `free.active@demo.test` | Free trial active | Full access, ~15 days left |
| `paid.expiring@demo.test` | Paid plan expiring | Full access, renews in ~7 days |

Salon owners can view plan status under **Finance → Billing** (`/billing`).

### Affiliate program (`AffiliateDemoSeeder`)

3 affiliate partners with referred salons, commissions, and withdrawal requests. Partner emails are printed in the terminal during seed (password: `Demo@123`).

---

## Code quality

```bash
./vendor/bin/pint
npm run build
```

---

## Tenant configuration (multi-tenant SaaS)

Each salon can override platform defaults for branding, email, SMS, notifications, regional settings, and invoices.

**Resolution order:** salon override → platform default (`platform_settings`) → config default (`config/tenant_settings.php`).

| Who | Where in the app |
|-----|------------------|
| Salon owner | **Settings → Business Profile** / **Salon Configuration** |
| Platform admin | **Settings → Workspace Settings** (platform-wide defaults) |

Salons without custom config automatically inherit platform defaults (email, SMS, logo, colors, etc.).

After pulling tenant-configuration changes, run:

```bash
php artisan migrate
```

This creates `salon_settings` and adds `invoice_number` / `reminder_sent_at` on appointments.

---

## Scheduled tasks (cron)

The app uses Laravel's scheduler for subscription expiry, renewal reminders, and **hourly appointment reminders**.

| Command | Schedule | Purpose |
|---------|----------|---------|
| `subscriptions:expire` | Daily 00:15 | Mark expired subscriptions |
| `subscriptions:send-renewal-reminders` | Daily 09:00 | Email salon owners before renewal |
| `appointments:send-reminders` | Hourly | Customer appointment reminders (per-salon `reminder_hours_before`) |

**Production:** add to system cron (runs every minute):

```bash
* * * * * cd /path/to/saloon-management && php artisan schedule:run >> /dev/null 2>&1
```

**Local (Windows / XAMPP):** keep the scheduler running in a separate terminal:

```bat
scripts\schedule-dev.bat
```

Or run a one-off dry run:

```bash
php artisan appointments:send-reminders --dry-run
php artisan subscriptions:send-renewal-reminders --dry-run
```

---

## Notes

- **Receipt PDFs:** Open any appointment (calendar or POS) and use **Download Receipt**. Uses tenant invoice settings (prefix, GST, footer).
- Salon owners configure portal branding under **Settings → Salon Configuration**; colors apply live via CSS theme variables.
- Subscription plan, expiry, renewal, and upgrade flows are available under **Finance → Billing** for salon owners.
- Use PHP 8.2+ locally (8.4 recommended per `composer.json`).
