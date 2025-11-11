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

export async function fetchUserResults(params = {}) {
  try {
    const headers = authHeaders();
    const url = `${API_BASE_URL}/api/assessment-results/user`;
    const res = await axios.get(url, { headers, params });
    // Expected shape: { results: [...], count }
    return res.data?.results || [];
  } catch (err) {
    console.debug && console.debug('fetchUserResults failed:', err?.message || err);
    return [];
  }
}

export function normalizeBarData(result) {
  if (!result || typeof result !== 'object') return null;

  const toPct = (v) => (typeof v === 'number' ? Math.round(v * 100) : 0);

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
