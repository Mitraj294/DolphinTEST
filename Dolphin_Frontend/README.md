# Dolphin_Frontend — Vue.js application

This folder contains the Vue.js single-page application (SPA) that is the front-end for Dolphin. The project uses Vue + PrimeVue (and common Vue tooling). The app talks to the Laravel backend APIs.

## What it contains

- `src/` — Vue components, router, services and app entry points.
- `public/` — static assets (including an embedded copy of TinyMCE under `public/tinymce/`).
- `package.json` — npm scripts and dependencies.

## Requirements

- Node.js 16+ (or matching the project's package.json engines)
- npm or yarn

## Quick setup

```bash
cd Dolphin_Frontend
npm install
cp .env.example .env    # if present; otherwise edit `public/env.json` or runtime env files
npm run serve           # start dev server (the exact script may be `serve`, `dev` or `start` — check package.json)
```

If you intend to build a production bundle for local hosting only:

```bash
npm run build
# The build output will be in `dist//`. Serve locally with a static server if needed.
```

## Environment & runtime

- There are environment files in the repo (`.env`, `.env.example`). The front-end also uses `public/env.json` and `src/env.js` to load runtime settings.
- Configure the backend API base URL and any Stripe/public keys used by subscription features.

## Local development tips

- The project contains `permission.js`, `tokenMonitor.js`, and `tokenInterceptor.js` under `src/` — these handle auth and token refresh flows. Make sure the backend provides the matching auth endpoints.
- TinyMCE is vendored into `public/tinymce/` and referenced by the front-end.

## Deployment

- For local-only usage, API base URLs should remain http://127.0.0.1:8000 and CORS is locked to http://127.0.0.1:8080 on the backend (`Dolphin_Backend/config/cors.php`).

## Troubleshooting

- If the front-end cannot reach the backend, open the browser console and network tab to see requests and CORS errors. Update `config/cors.php` and `.env` accordingly on the backend.

---

Generated on: October 31, 2025
