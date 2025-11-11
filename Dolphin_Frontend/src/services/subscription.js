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
    const res = await axios.post(
      `${API_BASE_URL}/api/subscription/create-checkout-session`,
      payload,
      { headers }
    );
    // Stripe Session endpoint returns { id, url }
    return res.data;
  } catch (err) {
    console.debug?.('createCheckoutSession failed:', err?.message || err);
   return [];
  }
}

export async function fetchSubscriptionStatus(orgId = null) {
  try {
    const role = (storage.get('role') || '').toString().toLowerCase();
    if (role !== 'organizationadmin') return { status: 'none' };
  } catch (e) {
    console.debug(
      'fetchSubscriptionStatus: could not read role from storage, attempting API call',
      e
    );
  }

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
    };
  } catch (err) {
    console.debug &&
      console.debug(
        'fetchSubscriptionStatus: failed to get subscription status',
        err?.message || err
      );
    return { status: 'none' };
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
