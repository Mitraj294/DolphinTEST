# Dolphin_Backend — Laravel application

This folder contains the server-side Laravel application for Dolphin: API endpoints, scheduled jobs, mail templates, background jobs, observers, and the database layer.

## What it contains
- `artisan` — Laravel CLI entrypoint.
- `app/` — application code (Controllers, Models, Jobs, Mail, Services, Observers).
- `bootstrap/`, `config/`, `database/` — framework and DB configuration, migrations, factories, seeders.
- `public/` — frontend entry for server-hosted pages and static assets.
- `storage/` — logs, file storage and OAuth keys (private/public keys are present in `storage/`).
- `tests/` — PHPUnit tests (Unit and Feature).

## Requirements
- PHP 8.0+ (check `composer.json` for exact requirement)
- Composer
- MySQL or MariaDB
- Node.js & npm (for frontend assets, if built here)

## Quick setup

1. Install composer dependencies

```bash
cd Dolphin_Backend
composer install --no-interaction --prefer-dist
```

2. Environment

```bash
cp .env.example .env
# Edit .env to set DB_HOST, DB_DATABASE, DB_USERNAME, DB_PASSWORD, APP_URL and mail/redis settings
php artisan key:generate
```

3. Database & seed

```bash
php artisan migrate --seed
# or import provided backup: mysql -u user -p database < backup.sql
```

4. Storage link and oauth keys

```bash
php artisan storage:link
# If oauth keys are missing, generate Passport keys or copy provided keys from storage/
```

5. Run the app

```bash
php artisan serve --port=8000
# Or use `start.sh` for a more production-like setup if provided
```

## Queues and background workers

- The repository includes `supervisor-dolphin-queue-worker.conf` to run queue workers under Supervisor.
- Locally, run `php artisan queue:work` or `php artisan horizon` (if Horizon is configured).

## Tests

Run PHPUnit tests:

```bash
composer install --dev
./vendor/bin/phpunit
```

## Common tasks and troubleshooting
- If migrations fail, verify DB credentials in `.env` and that the DB server is reachable.
- If email isn't sending, check `config/mail.php` and credentials in `.env`.
- OAuth: Storage contains `oauth-private.key` and `oauth-public.key`. If missing, run `php artisan passport:install`.

### Native Assessment Engine (Replaces external dolphin project)

The assessment scoring logic is now fully implemented in PHP and no longer depends on the legacy `dolphin-project-main` (C++/Python) folder or its `input` / `results` tables. Key components:

- `app/Services/AssessmentEngine/WeightRepository.php` — loads weight dictionaries from `resources/assessment_weights`.
- `app/Services/AssessmentEngine/ScoreCalculator.php` — computes per-category ratios (A/B/C/D) and decision approach.
- `app/Services/AssessmentEngine/WordNormalizer.php` — normalizes selected words to match weight keys.
- `app/Services/AssessmentCalculationService.php` — orchestrates extraction from `assessment_responses` and persists rows into `assessment_results`.

Recompute historical results if needed:

```bash
php artisan assessments:recompute --rebuild
# Limit scope:
php artisan assessments:recompute --user=42 --attempt=1 --rebuild
```

Safely removing the old external folder:
1. Delete or archive `dolphin-project-main`.
2. Remove any related ENV vars (e.g., `PYTHON_PATH`).
3. Optionally drop legacy external tables if they existed (`input`, `results`) after confirming nothing references them.

The methods `isDolphinExecutableAvailable()` and `buildDolphinExecutable()` now always return success for backward compatibility with existing controllers.

## Local-only notes
- This project is locked to localhost for development:
	- Backend: http://127.0.0.1:8000
	- Frontend: http://127.0.0.1:8080
- CORS is restricted to the local frontend in `config/cors.php`.
- Default DB credentials in `.env.example` are:
	- DB: dolphin_clean
	- User: dolphin123
	- Pass: dolphin123

See `build.sh`, `start.sh` at repo root for build and startup steps used by this project.
`supervisor-dolphin-queue-worker.conf` is an example Supervisor unit for background workers.

---
Generated on: October 31, 2025
