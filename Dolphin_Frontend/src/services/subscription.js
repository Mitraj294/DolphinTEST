import axios from "axios";
import storage from "./storage";

export async function fetchSubscriptionStatus() {
  let authToken = storage.get("authToken");
  // Support multiple token shapes: string, { token }, { access_token }
  if (authToken && typeof authToken === "object") {
    if (authToken.token) authToken = authToken.token;
    else if (authToken.access_token) authToken = authToken.access_token;
    else authToken = null;
  }
  if (typeof authToken !== "string") authToken = null;

  const API_BASE_URL = process.env.VUE_APP_API_BASE_URL || "http://127.0.0.1:8000";
  const headers = authToken ? { Authorization: `Bearer ${authToken}` } : {};

  try {
    const res = await axios.get(`${API_BASE_URL}/api/subscription/status`, { headers });
    const data = res.data || {};

    // Normalize payload shape for consumers (router guards, UI components)
    const normalized = {
      status: data.status || "none",
      planId: data.plan_id || null,
      subscriptionId: data.subscription_id || null,
      startedAt: data.started_at || null,
      endsAt: data.ends_at || data.current_period_end || null,
      currentPeriodEnd: data.current_period_end || null,
      isPaused: !!data.is_paused,
      cancelAtPeriodEnd: !!data.cancel_at_period_end,
      amountPaid: data.latest_amount_paid || null,
      currency: data.currency || null,
      paymentMethod: data.payment_method || null,
      paymentMethodType: data.payment_method_type || null,
      paymentMethodBrand: data.payment_method_brand || null,
      paymentMethodLast4: data.payment_method_last4 || null,
    };

    if (normalized.status && normalized.status !== "none") {
      storage.set("subscription_status", normalized.status);
    } else {
      storage.remove("subscription_status");
    }

    return normalized;
  } catch (err) {
    if (err?.response?.status === 401) {
      console.warn("Subscription status request returned 401 Unauthorized (missing/invalid token). Clearing cached status.");
      storage.remove("subscription_status");
    } else {
      console.warn("Subscription status request failed", {
        status: err?.response?.status,
        message: err?.message,
      });
    }
    // Return a safe default object so callers can rely on shape
    return {
      status: "none",
      planId: null,
      subscriptionId: null,
      startedAt: null,
      endsAt: null,
      currentPeriodEnd: null,
      isPaused: false,
      cancelAtPeriodEnd: false,
      amountPaid: null,
      currency: null,
      paymentMethod: null,
      paymentMethodType: null,
      paymentMethodBrand: null,
      paymentMethodLast4: null,
    };
  }
}
