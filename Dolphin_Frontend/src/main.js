// src/main.js
import { createApp } from "vue";
import App from "./App.vue";
import "./assets/global.css";
import "./assets/modelcssnotificationandassesment.css";
import "./assets/table.css";
import router from "./router";
import { fetchSubscriptionStatus } from "./services/subscription";
import { fetchCurrentUser } from "./services/user";

import installIgnoreExtensionErrors from "@/boot/ignoreExtensionErrors";
import { loadRuntimeEnv } from "./runtime-env";
import storage from "./services/storage";
import tokenMonitor from "./services/tokenMonitor";

import "./services/tokenInterceptor";

// PrimeVue Imports
import PrimeVue from "primevue/config"; // Import PrimeVue configuration
import ConfirmService from "primevue/confirmationservice";
import ToastService from "primevue/toastservice"; // Import ToastService

// PrimeVue Styles (choose a theme and include primevue.min.css)
import "primevue/resources/primevue.min.css"; // Core PrimeVue styles
import "primevue/resources/themes/lara-light-blue/theme.css"; // Recommended theme, or choose another

// Font Awesome and PrimeIcons
import "@fortawesome/fontawesome-free/css/all.min.css"; // Font Awesome for your custom icons
import "primeflex/primeflex.css";
import "primeicons/primeicons.css"; // PrimeIcons for PrimeVue components
import Button from "primevue/button";
import Calendar from "primevue/calendar";
import ConfirmDialog from "primevue/confirmdialog";
import Toast from "primevue/toast";
async function bootstrap() {
  await loadRuntimeEnv();
  // Install in-page guard to suppress noisy browser extension errors (non-fatal)
  try {
    installIgnoreExtensionErrors();
  } catch (e) {
    console.warn("Could not install extension error filter", e);
  }
  const app = createApp(App);

  // Install PrimeVue and its services
  app.use(PrimeVue); // Initialize PrimeVue
  app.use(ToastService); // Install the ToastService globally
  app.use(ConfirmService); // Install the ConfirmService globally for ConfirmDialog
  app.component("Toast", Toast); // Register Toast globally
  app.component("ConfirmDialog", ConfirmDialog);
  app.component("Calendar", Calendar); // Register Calendar globally
  app.component("Button", Button); // Register PrimeVue Button globally

  return app;
}

// Sync encrypted storage role with backend user role on app start

// Check if this is a guest access scenario (like subscription plans with guest_code)
const isGuestAccess = () => {
  const urlParams = new URLSearchParams(globalThis.location.search);
  const hasGuestParams =
    urlParams.has("guest_code") ||
    urlParams.has("guest_token") ||
    urlParams.has("lead_id") ||
    urlParams.has("email");
  console.log("isGuestAccess check:", {
    url: globalThis.location.href,
    search: globalThis.location.search,
    hasGuestParams,
    guest_code: urlParams.get("guest_code"),
    guest_token: urlParams.get("guest_token"),
    lead_id: urlParams.get("lead_id"),
    email: urlParams.get("email"),
  });
  return hasGuestParams;
};

const authToken = storage.get("authToken");
console.log("Main.js startup:", {
  authToken: !!authToken,
  isGuest: isGuestAccess(),
});
// Create the app instance then run startup logic that depends on it.
try {
  const app = await bootstrap();

  if (authToken && !isGuestAccess()) {
    try {
      const user = await fetchCurrentUser();

      // Also fetch subscription status so refreshing the page reflects current state immediately
      try {
        const status = await fetchSubscriptionStatus();
        if (status) {
          storage.set("subscriptionStatus", status);
        }
      } catch (err) {
        console.warn("Could not fetch subscription status on startup", err);
      }

      if (user?.role) {
        const localRole = storage.get("role");
        if (user.role !== localRole) {
          storage.set("role", user.role);
        }

        // Start token monitoring after successful authentication check
        tokenMonitor.startMonitoring({
          checkInterval: 5 * 60 * 1000, // Check every 5 minutes
          warningThreshold: 10 * 60 * 1000, // Warn when 10 minutes left
          onExpiringSoon: (seconds) => {
            console.warn(
              `Your session will expire in ${Math.round(seconds / 60)} minutes`
            );
            // You could show a toast notification here
          },
          onExpired: () => {
            console.log("Session expired, redirecting to login");
            // Force redirect to login page
            globalThis.location.href = "/login";
          },
        });
      }
    } catch (err) {
      console.warn("Could not fetch current user on startup", err);
    } finally {
      app.use(router);
      app.mount("#app");
    }
  } else {
    app.use(router);
    app.mount("#app");
  }
} catch (err) {
  // If bootstrap fails (e.g. loadRuntimeEnv), log and fail gracefully
  console.error("Failed to bootstrap app:", err);
}
