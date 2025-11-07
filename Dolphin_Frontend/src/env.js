export function getApiBase() {
  return (
    globalThis.__env?.VUE_APP_API_BASE_URL ||
    globalThis.VUE_APP_API_BASE_URL ||
    process.env.VUE_APP_API_BASE_URL ||
    ""
  );
}
