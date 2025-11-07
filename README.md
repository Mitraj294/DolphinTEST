# Dolphin

Monorepo containing the Dolphin web application: a Laravel (PHP) backend and a Vue.js frontend. This document gives a high-level overview, quick start instructions for local development, and links to the per-service READMEs.

## Contents

- `Dolphin_Backend/` — Laravel 8/9+ application (API + web controllers, mail, jobs, queues).
- `Dolphin_Frontend/` — Vue.js single-page application (SPA) that consumes the backend API.
- `start-dev.sh` and `stop-dev.sh` — convenience scripts to run or stop both apps locally.

## High-level architecture

- Backend: Laravel, uses Passport/Sanctum (see `config/passport.php` and `storage/oauth-*` keys), has job queues, observers, scheduled tasks and a set of APIs under `routes/api.php`.
- Frontend: Vue.js + PrimeVue (look under `Dolphin_Frontend/src/`), single-page app served separately via local dev server.
- Data: database migrations and seeders are in the backend `database/` folder. A `backup.sql` is included for reference.

## Quick start (local dev)

Prerequisites (typical):

- PHP 8.0+ (match project's composer.json), Composer, MySQL/MariaDB, Node 16+ / npm (or pnpm), Git.

Option A — one command (from repo root):

```bash
./start-dev.sh
```

This will:

- start Laravel at http://127.0.0.1:8000
- start a queue worker and a schedule worker (or a cron fallback)
- start the Vue dev server on http://127.0.0.1:8080 (or the next free port)

Use `./stop-dev.sh` to stop everything.

Database quick-setup (optional):

```bash
./scripts/setup-local-db.sh
```

This creates the local MySQL database and user using the credentials below and can import `Dolphin_Backend/backup.sql` if you wish.

Option B — manual steps:

1. Backend (from repo root):

```bash
cd Dolphin_Backend
composer install
cp .env.example .env
# configure DB credentials in .env
php artisan key:generate
php artisan migrate --seed
php artisan storage:link
php artisan serve --port=8000
```

2. Frontend (new terminal):

```bash
cd Dolphin_Frontend
npm install
# set env files (see .env.example)
npm run serve   # or npm run dev / npm run build depending on scripts in package.json
```

3. Open the frontend at the indicated dev URL and it should call the backend API.

## Production

This repository is configured to run locally only at the following URLs:

- Backend (Laravel): http://127.0.0.1:8000
- Frontend (Vue): http://127.0.0.1:8080

Remote deployment configs have been removed/disabled.

## Where to look next

- Backend README: `Dolphin_Backend/README.md` (Laravel details, env keys, queue worker/supervisor, tests).
- Frontend README: `Dolphin_Frontend/README.md` (local Vue dev server and build).

## Next steps

- Run the app locally, inspect `.env` files and `config/` to align services.
- Consider adding CI (GitHub Actions) for tests and linting, and deployment scripts for your target host.

---

Updated: November 3, 2025
