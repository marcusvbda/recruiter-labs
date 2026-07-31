# recruiter-labs

A multi-tenant recruiting SaaS boilerplate: Laravel 13 + Filament 5 for the admin panel (companies, candidates, jobs, referrals, settings), Inertia.js + React for anything outside Filament's scope.

## Requirements

- PHP 8.4
- Composer
- Node.js + npm
- PostgreSQL (the default `.env` is configured for `pgsql`)

## Setup

```bash
cp .env.example .env
composer install
npm install
php artisan key:generate
```

Create a local Postgres database matching the `DB_*` values in `.env`, then run migrations and seed the initial data:

```bash
php artisan migrate --seed
```

Seeding creates:

- A `default` plan (used by every new company).
- An admin user, `admin@user.com`, with the password from `.env`'s `ADMIN_PASSWORD`.

## Running locally

Pick one of the following. All of them serve the app at `http://localhost:8000` unless noted otherwise.

### Option A — `composer run dev` (recommended for day-to-day development)

Runs the PHP dev server, the queue listener, and the Vite dev server together in one terminal:

```bash
composer run dev
```

### Option B — Artisan + Vite separately

```bash
php artisan serve
npm run dev
```

`php artisan serve` serves the app (both the Filament panel at `/admin` and any Inertia/React pages). `npm run dev` starts Vite, which compiles and hot-reloads the Inertia/React frontend — keep it running alongside `artisan serve` any time you're working on Inertia pages, otherwise the browser will complain about missing/stale built assets.

### Option C — Octane with FrankenPHP

For running the app on Octane instead of the plain PHP dev server:

```bash
php artisan octane:start --server=frankenphp
```

The `frankenphp` binary is downloaded automatically the first time you run `php artisan octane:install --server=frankenphp` (already done for this project — see `config/octane.php`). Vite still needs to run separately for Inertia pages:

```bash
npm run dev
```

## Testing

```bash
php artisan test --compact
```

## Code style

```bash
vendor/bin/pint --dirty --format agent
```
