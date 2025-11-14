import axios from 'axios';
import storage from './storage';
import { getApiBase } from '@/env';

const API_BASE_URL = getApiBase();

function normalizeToken(raw) {
  if (!raw) return null;
  if (typeof raw === 'string') return raw;
  if (raw.token) return raw.token;
  if (raw.access_token) return raw.access_token;
  return null;
}

function authHeaders() {
  const token = normalizeToken(storage.get('authToken'));
  return token ? { Authorization: `Bearer ${token}` } : {};
}

export async function getPlans() {
  try {
    const headers = authHeaders();
    const res = await axios.get(`${API_BASE_URL}/api/plans`, { headers });
    // Backend returns { plans: [...] }
    return res.data?.plans ?? res.data;
  } catch (err) {
    console.debug?.('getPlans failed:', err?.message || err);
    return [];
  }
}

export async function createCheckoutSession(priceId, opts = {}) {
  try {
    const headers = authHeaders();
    const payload = { price_id: priceId, ...opts };

    // If not authenticated (no bearer token), ensure we send an email when possible
    // to satisfy the guest endpoint validations. Try to read from stored user.
    if (!headers.Authorization && !payload.email) {
      try {
        const storageModule = await import('./storage');
        const storage = storageModule.default;
        const userObj = storage.get('user');
        const storedEmail = (userObj && userObj.email) || storage.get('userEmail') || null;
        if (storedEmail && !payload.email) payload.email = storedEmail;
      } catch (e) {
        console.debug?.('createCheckoutSession: could not read user email from storage', e);
        return;
      }
    }

    // Choose endpoint based on auth. If authenticated, use the secure
    // /api/stripe/checkout-session endpoint which doesn't require email.
    const url = headers.Authorization
      ? `${API_BASE_URL}/api/stripe/checkout-session`
      : `${API_BASE_URL}/api/subscription/create-checkout-session`;

    const res = await axios.post(url, payload, { headers });
    // Stripe Session endpoint returns { id, url }
    return res.data;
  } catch (err) {
    console.debug?.('createCheckoutSession failed:', err?.message || err);
    throw err;
  }
}

export async function fetchSubscriptionStatus(orgId = null) {
  // Always attempt to call the backend status endpoint. Previously we
  // short-circuited for non-organization-admin roles (returning 'none') to
  // avoid 403 responses, but that made it hard for callers to decide on UX.
  // Let the backend return 403/unathorized and translate that into a clear
  // `status: 'none'` payload with an explanatory `message` where appropriate.
  try {
    const headers = authHeaders();
    const url = orgId
      ? `${API_BASE_URL}/api/subscription/status?org_id=${encodeURIComponent(orgId)}`
      : `${API_BASE_URL}/api/subscription/status`;
    const res = await axios.get(url, { headers });
    const data = res.data || {};

    return {
      status: data.status || 'none',
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
      message: data.message || null,
      unauthorized: false,
    };
  } catch (err) {
    // If unauthorized (403), return a consistent payload indicating the
    // user cannot view/manage subscription for this org. Callers can use
    // `unauthorized` to surface alternate UX (CTA or info) instead of hiding
    // subscription state completely.
    const statusCode = err?.response?.status;
    if (statusCode === 403) {
      const body = err.response?.data || {};
      return {
        status: body.status || 'none',
        message: body.message || 'Unauthorized to view subscription status',
        unauthorized: true,
      };
    }

    console.debug && console.debug('fetchSubscriptionStatus: failed to get subscription status', err?.message || err);
    return { status: 'none', message: 'Unable to fetch subscription status', unauthorized: false };
  }
}

export async function getActiveSubscription() {
  try {
    const headers = authHeaders();
    const res = await axios.get(`${API_BASE_URL}/api/subscription`, { headers });
    return res.data;
  } catch (err) {
    console.debug && console.debug('getActiveSubscription failed:', err?.message || err);
    return null;
  }
}

export async function getInvoices(params = {}) {
  try {
    const headers = authHeaders();
    const res = await axios.get(`${API_BASE_URL}/api/billing/history`, { headers, params });
    return res.data || [];
  } catch (err) {
    console.debug?.('getInvoices: failed to fetch billing history', err?.message || err);
    return [];
  }
}

export async function createBillingPortalSession() {
  try {
    const headers = authHeaders();
    const res = await axios.post(
      `${API_BASE_URL}/api/subscription/billing-portal`,
      {},
      { headers }
    );
    return res.data;
  } catch (err) {
    console.debug && console.debug('createBillingPortalSession failed:', err?.message || err);
    throw err;
  }
}
