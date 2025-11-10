import axios from "axios";
import router from "../router";
import storage from "./storage";

// NOTE: do NOT capture runtime env at module import time because
// `loadRuntimeEnv()` runs asynchronously before app bootstrap. Resolve
// client credentials and API base lazily at request-time so the values
// injected into `/env.json` are used.

// Helpers
const isTokenExpired = (expiry) => {
  if (!expiry) return false;
  const now = Date.now();
  return now >= new Date(expiry).getTime();
};

const handleTokenExpiry = () => {
  console.log("Token expired, clearing storage and redirecting to login");
  storage.clear();
  router.push("/login");
  return Promise.reject(new Error("Token expired"));
};

const handleUnauthorized = (error) => {
  console.log(
    "Received 401 response, clearing storage and redirecting to login"
  );
  storage.clear();
  router.push("/login");
  return Promise.reject(error);
};

const handleSubscriptionExpired = (error, data) => {
  console.log("Subscription expired, updating storage");

  storage.set("subscription_status", "expired");
  if (data.subscription_end)
    storage.set("subscription_end", data.subscription_end);
  if (data.subscription_id)
    storage.set("subscription_id", data.subscription_id);

  const currentPath = router.currentRoute.value.path;
  const allowedPages = [
    "/manage-subscription",
    "/subscriptions/plans",
    "/profile",
    "/organizations/billing-details",
  ];

  const isOnAllowedPage = allowedPages.some(
    (page) => currentPath === page || currentPath.startsWith(page)
  );

  if (isOnAllowedPage) {
    console.log("Already on allowed page, not redirecting");
  } else {
    console.log("Redirecting to manage subscription page");
    router.push("/manage-subscription");
  }

  return Promise.reject(error);
};

// Interceptors

// Request interceptor
axios.interceptors.request.use(
  (config) => {
    // normalize auth token from storage (support string or object shapes)
    let authToken = storage.get("authToken");
    const tokenExpiry = storage.get("tokenExpiry");

    if (authToken && typeof authToken === "object") {
      if (authToken.token) authToken = authToken.token;
      else if (authToken.access_token) authToken = authToken.access_token;
      else authToken = null;
    }

    if (authToken && isTokenExpired(tokenExpiry)) {
      return handleTokenExpiry();
    }

    if (authToken && typeof authToken === "string") {
      config.headers = config.headers || {};
      // only set Authorization if not already set
      if (!config.headers.Authorization && !config.headers.authorization) {
        config.headers.Authorization = `Bearer ${authToken}`;
      }
    }

    return config;
  },
  (error) => Promise.reject(error)
);

// Response interceptor
axios.interceptors.response.use(
  (response) => response,
  (error) => {
    // If unauthorized, try refresh token flow once before redirecting to login
    if (error.response?.status === 401) {
      const refreshToken = storage.get("refreshToken");
      const originalRequest = error.config;

      // Only attempt refresh if we have a refresh token and haven't retried yet
      if (refreshToken && !originalRequest._retry) {
          originalRequest._retry = true;
          // Resolve runtime values here so that `/env.json` (loaded at app
          // bootstrap) is used by the refresh request.
          const API_BASE_URL = (globalThis.__env && globalThis.__env.VUE_APP_API_BASE_URL) || globalThis.VUE_APP_API_BASE_URL || process.env.VUE_APP_API_BASE_URL || "";
          const CLIENT_ID = (globalThis.__env && globalThis.__env.VUE_APP_CLIENT_ID) || process.env.VUE_APP_CLIENT_ID || "";
          const CLIENT_SECRET = (globalThis.__env && globalThis.__env.VUE_APP_CLIENT_SECRET) || process.env.VUE_APP_CLIENT_SECRET || "";

          // Sanity-check runtime-provided client credentials so failed refreshes
          // due to missing env values are easier to spot in the browser devtools.
          if (!CLIENT_ID || !CLIENT_SECRET) {
            console.warn('OAuth client_id or client_secret appears empty. Ensure /env.json contains runtime credentials.');
          }

          return axios
            .post(`${API_BASE_URL}/oauth/token`, {
              grant_type: "refresh_token",
              refresh_token: refreshToken,
              client_id: CLIENT_ID,
              client_secret: CLIENT_SECRET,
            })
          .then((res) => {
            const newAccessToken = res.data.access_token;
            const newRefreshToken = res.data.refresh_token;
            if (newAccessToken) {
              storage.set("authToken", newAccessToken);
              if (newRefreshToken) storage.set("refreshToken", newRefreshToken);
              // update default header and original request header
              axios.defaults.headers.common["Authorization"] =
                `Bearer ${newAccessToken}`;
              originalRequest.headers = originalRequest.headers || {};
              originalRequest.headers["Authorization"] = `Bearer ${newAccessToken}`;
              return axios(originalRequest);
            }
            return handleUnauthorized(error);
          })
          .catch((e) => {
            console.error("Refresh token failed:", e?.response?.data || e?.message || e);
            return handleUnauthorized(error);
          });
      }
      return handleUnauthorized(error);
    }

    if (
      error.response?.status === 403 &&
      error.response.data?.status === "expired" &&
      error.response.data.redirect_url
    ) {
      return handleSubscriptionExpired(error, error.response.data);
    }

    return Promise.reject(error);
  }
);

// Export configured axios instance via a local alias to avoid re-exporting the
// imported default directly (keeps interceptors applied and satisfies linter).
const http = axios;
export default http;
