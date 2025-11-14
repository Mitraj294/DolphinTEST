**Purpose**: Short, focused guidance to help AI coding agents be productive in this repository.

**Repository layout (big picture)**
- **`Dolphin_Backend/`**: Laravel PHP backend. Key files: `artisan`, `composer.json`, `build.sh`, `phpunit.xml`, `phpcs.xml`, `phpstan-baseline.neon`.
- **`Dolphin_Frontend/`**: Vue 3 frontend. Key files: `package.json` (scripts: `serve`, `build`, `test`), `src/` (app code), `public/tinymce/` (custom TinyMCE plugins).
- Root helpers: `start-dev.sh` / `stop-dev.sh` to run the full dev environment locally (starts Laravel + queue + scheduler + Vue dev server).

**High-level architecture & data flow**
- The app is a classic SPA (Vue 3) + API (Laravel) pattern. The frontend communicates with backend API routes defined under `Dolphin_Backend/routes/` (see `api.php` / `web.php`).
- Background jobs use Laravel queues and scheduler: queue workers are started in `start-dev.sh` and `Dolphin_Backend/build.sh` prepares Passport keys and runs migrations for production.

**Developer workflows (concrete commands)**
- Start full development environment (dev server, queue worker, scheduler):
  - `./start-dev.sh` (runs backend `php artisan serve`, `artisan queue:work`, scheduler and `npm run serve` for frontend)
  - Stop with Ctrl+C or `./stop-dev.sh`.
- Backend local build (render / production oriented):
  - `cd Dolphin_Backend && ./build.sh` (runs `composer install`, caches config/routes/views, runs migrations, sets up Passport and storage symlink).
- Backend quick commands:
  - Serve: `php artisan serve --host=127.0.0.1 --port=8000` (start-dev.sh wraps this)
  - Run tests: `cd Dolphin_Backend && php artisan test` or `./vendor/bin/phpunit` (project has `phpunit.xml`)
  - Lint/static analysis: `phpcs` (config in `phpcs.xml`), `phpstan` using the baseline.
- Frontend quick commands:
  - Install deps: `cd Dolphin_Frontend && npm install`
  - Dev server: `cd Dolphin_Frontend && npm run serve` (note: `serve` runs `scripts/copy-tinymce.js` before `vue-cli-service serve`)
  - Build: `cd Dolphin_Frontend && npm run build`
  - Tests: `cd Dolphin_Frontend && npm test` (uses `vitest` in `package.json`).

**Project-specific conventions & patterns**
- TinyMCE plugins are edited under `Dolphin_Frontend/public/tinymce/plugins/*` and the `scripts/copy-tinymce.js` helper copies plugin assets into the served build during `npm run serve`. If you modify plugins, ensure `copy-tinymce.js` still includes your new files.
- Backend auth: Laravel Passport is used (see `Dolphin_Backend/build.sh` for `passport:keys` / `passport:install`). Make API auth changes in `app/Providers` and `config/auth.php`.
- Background jobs: queue worker starts with `artisan queue:work` and scheduler uses `artisan schedule:work` or a cron fallback (see `start-dev.sh` behavior).
- Config / env: Backend configuration is in `Dolphin_Backend/config/*`. Releases expect environment-based config (typical Laravel `.env`). The build script runs `php artisan config:cache` and route/view caching for production.

**Files and locations to edit for common tasks (examples)**
- Add an API endpoint: add route to `Dolphin_Backend/routes/api.php` and implement controller at `Dolphin_Backend/app/Http/Controllers/...`.
- Add a frontend view/component: add component in `Dolphin_Frontend/src/` and import/register it via `Dolphin_Frontend/src/main.js` or the relevant router file.
- Add a TinyMCE plugin change: edit `Dolphin_Frontend/public/tinymce/plugins/<plugin>/plugin.js` and verify `scripts/copy-tinymce.js` copies it during `npm run serve`.

**Testing & CI hints (discoverable)**
- Backend has `phpunit.xml` and likely uses `php artisan test` in CI. PHPStan and PHPCS configs are present; respect those standards.
- Frontend uses `vitest` for unit tests; `npm test` runs the test suite.

**Integration & external dependencies**
- OAuth: Laravel Passport (keys installed during `build.sh`).
- Node ecosystem for the frontend: Vue CLI, `tinymce` and `@tinymce/tinymce-vue` are present. Ensure Node version matches local environment used by developers/CI.
- Composer / PHP dependencies are managed via `composer.json` in `Dolphin_Backend`.

**Agent behavior guidelines (concise)**
- Be conservative editing shared infra: change `start-dev.sh`, `stop-dev.sh`, or `Dolphin_Backend/build.sh` only when necessary, and prefer suggesting small, reversible changes.
- When adding backend routes/controllers, run through the simple local flow: `./start-dev.sh` → exercise the API → run `php artisan route:list` to confirm registration.
- When modifying frontend assets under `public/tinymce`, also update `scripts/copy-tinymce.js` and verify `npm run serve` reproduces the change.
- Respect existing lint/config files: `Dolphin_Backend/phpcs.xml`, `phpstan-baseline.neon`, and frontend `eslint`/`prettier` configs. If a proposed change requires config updates, explain reasons and update only the minimal config.

**If you need clarification**
- Ask the maintainer which Node/PHP versions are expected if not present locally. Confirm whether you should modify production caching steps (config/route/view cache) or only development flows.

Please review — I can iterate on any unclear sections or add CI-specific notes if you point me to the CI config.
