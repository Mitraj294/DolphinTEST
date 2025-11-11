import axios from "axios";
import storage from "./storage";

const API_BASE_URL =
  (globalThis.__env && globalThis.__env.VUE_APP_API_BASE_URL) ||
  globalThis.VUE_APP_API_BASE_URL ||
  process.env.VUE_APP_API_BASE_URL ||
  "";

function authHeaders() {
  let token = storage.get("authToken");
  if (!token) return {};
  if (typeof token === "object") {
    token = token.token || token.access_token || null;
  }
  return token ? { Authorization: `Bearer ${token}` } : {};
}

export async function fetchUserResults(params = {}) {
  const headers = authHeaders();
  const url = `${API_BASE_URL}/api/assessment-results/user`;
  const res = await axios.get(url, { headers, params });
  // Expected shape: { results: [...], count }
  return res.data?.results || [];
}

export function normalizeBarData(result) {
  if (!result || typeof result !== "object") return null;

  const toPct = (v) => (typeof v === "number" ? Math.round(v * 100) : 0);

  const original = {
    a: toPct(result.self_a),
    b: toPct(result.self_b),
    c: toPct(result.self_c),
    d: toPct(result.self_d),
  };

  const adjusted = {
    a: toPct(result.adj_a),
    b: toPct(result.adj_b),
    c: toPct(result.adj_c),
    d: toPct(result.adj_d),
  };

  return { original, adjusted };
}
