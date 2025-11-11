// Helper to register PrimeVue components when an app instance is available.
import Toast from 'primevue/toast';

export function registerPrimeVueComponents(app) {
  if (!app || typeof app.component !== 'function') return;
  app.component('Toast', Toast);
}

// The file is intentionally minimal — the real registration happens in `main.js`.
