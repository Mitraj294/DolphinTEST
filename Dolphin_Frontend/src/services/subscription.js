import axios from "axios";
import storage from "./storage";

const API_BASE_URL = process.env.VUE_APP_API_BASE_URL || "";

function authHeaders() {
  const authToken = storage.get("authToken");
  if (!authToken) return {};
  let token = authToken;
  if (typeof token === "object") {
    if (token?.token) token = token.token;
    else if (token?.access_token) token = token.access_token;
    else token = null;
  }
  if (!token) return {};
  return { Authorization: `Bearer ${token}` };
}

export async function getPlans() {
  const headers = authHeaders();
  const res = await axios.get(`${API_BASE_URL}/api/plans`, { headers });
  // Backend returns { plans: [...] }
  return res.data?.plans ?? res.data;
}

export async function createCheckoutSession(priceId, opts = {}) {
  const headers = authHeaders();
  const payload = { price_id: priceId, ...opts };
  const res = await axios.post(`${API_BASE_URL}/api/subscription/create-checkout-session`, payload, { headers });
  // Stripe Session endpoint returns { id, url }
  return res.data;
}

// Backwards-compatibility wrapper used throughout the app.
// Many components import `fetchSubscriptionStatus` so keep it available.
export async function fetchSubscriptionStatus() {
  const headers = authHeaders();
  try {
    const res = await axios.get(`${API_BASE_URL}/api/subscription/status`, { headers });
    const data = res.data || {};

    return {
      status: data.status || "none",
      plan_id: data.plan_id || null,
      plan_name: data.plan_name || data?.plan?.name || null,
      plan: data.plan || null,
      subscription_id: data.subscription_id || null,
      started_at: data.started_at || null,
      ends_at: data.ends_at || data.current_period_end || null,
      current_period_end: data.current_period_end || null,
      is_paused: !!data.is_paused,
      cancel_at_period_end: !!data.cancel_at_period_end,
      latest_amount_paid: data.latest_amount_paid || null,
      currency: data.currency || null,
      payment_method: data.payment_method || null,
    };
  } catch (err) {
    // Unauthorized or other error -> safe default
    // Log for debugging while returning a safe default
    console.warn('fetchSubscriptionStatus: failed to get subscription status', err?.message || err);
    return { status: "none" };
  }
}

export async function getActiveSubscription() {
  const headers = authHeaders();
  const res = await axios.get(`${API_BASE_URL}/api/subscription`, { headers });
  return res.data;
}

export async function getInvoices(params = {}) {
  const headers = authHeaders();
  // Backend exposes billing history at /api/billing/history
  // It returns an array of history records for the authenticated user (or organization owner when org_id provided)
  try {
    const res = await axios.get(`${API_BASE_URL}/api/billing/history`, { headers, params });
    return res.data || [];
  } catch (err) {
    // Return empty array on error to avoid breaking callers
    console.warn('getInvoices: failed to fetch billing history', err?.message || err);
    return [];
  }
}

export async function createBillingPortalSession() {
  const headers = authHeaders();
  const res = await axios.post(`${API_BASE_URL}/api/subscription/billing-portal`, {}, { headers });
  return res.data;
}
