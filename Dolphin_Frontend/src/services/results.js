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

// Projection mapping helper for new wiring charts.
// Maps category scores (A,B,C,D) + decision approach into rows.
// Assumptions (can be refined later):
//  Row order: Collaborative(A) vs Independent, Internal Processor(B) vs External Processor,
//             Urgency(C) vs Methodical, Unstructured(D) vs Structured, Decision Approach(dec_approach)
// Each row value is a percentage (0-100) leaning toward the left trait (e.g., Collaborative).
// For display we use left-lean %; right-lean can be inferred as 100 - value.
export function buildProjectionData(result, type = 'original') {
  if (!result) return null;
  const toPct = (v) => (typeof v === 'number' ? Math.round(v * 100) : 0);
  const isOriginal = type === 'original';
  const prefix = isOriginal ? 'self_' : 'adj_';
  const data = {
    collaborative: toPct(result[prefix + 'a']),
    internalProcessor: toPct(result[prefix + 'b']),
    urgency: toPct(result[prefix + 'c']),
    unstructured: toPct(result[prefix + 'd']),
    decision: toPct(result.dec_approach),
  };
  return data;
}
