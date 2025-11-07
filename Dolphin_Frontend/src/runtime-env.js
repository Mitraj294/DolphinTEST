// runtime-env.js
// This loads runtime env into globalThis.__env so the app can pick up VUE_APP_API_BASE_URL
// without rebuilding the bundle. It first looks for a global `globalThis.__env` (injected by host),
// otherwise tries to fetch /env.json (served from public folder).
export async function loadRuntimeEnv() {
  if (globalThis.__env) return;
  try {
    const res = await fetch("/env.json", { cache: "no-store" });
    if (res.ok) {
      const obj = await res.json();
      globalThis.__env = obj;
    } else {
      globalThis.__env = {};
    }
  } catch (e) {
    // Log the error for debugging rather than silently swallowing it.
    // Still set an empty env object as a safe fallback for the app.
    // eslint-disable-next-line no-console
    console.warn("Failed to load runtime env:", e);
    globalThis.__env = {};
  }
}
