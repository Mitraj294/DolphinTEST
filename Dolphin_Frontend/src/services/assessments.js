import axios from 'axios';

const API_BASE_URL = process.env.VUE_APP_API_BASE_URL || '';

// Normalize a stored form definition to an array of option strings.
// This is a compact, resilient version extracted from the component.
export function normalizeFormDefinition(def) {
  let parsed = def;
  try {
    let attempts = 0;
    while (typeof parsed === 'string' && attempts < 5) {
      const trimmed = parsed.trim();
      if (trimmed.startsWith('[') || trimmed.startsWith('{') || trimmed.startsWith('"')) {
        parsed = JSON.parse(parsed);
        attempts++;
      } else break;
    }
  } catch (e) {
    parsed = def;
  }

  if (Array.isArray(parsed)) {
    // map to strings
    return parsed.map((it) => (typeof it === 'string' ? it : it?.label || it?.text || String(it)));
  }
  if (parsed && typeof parsed === 'object') {
    const arr = parsed.options || parsed.choices;
    if (Array.isArray(arr)) return arr.map((it) => (typeof it === 'string' ? it : it?.label || it?.text || String(it)));
  }
  return [];
}

export async function fetchAssessments(params = {}, headers = {}) {
  const res = await axios.get(`${API_BASE_URL}/api/assessments-list`, { params, headers });
  return res.data;
}

export async function fetchResponses(params = {}, headers = {}) {
  const res = await axios.get(`${API_BASE_URL}/api/assessment-responses`, { params, headers });
  return res.data;
}

export async function fetchAssignments(headers = {}) {
  const res = await axios.get(`${API_BASE_URL}/api/organization-assessments/assigned-list`, { headers });
  return res.data?.assigned || [];
}

export async function fetchSubmissions(headers = {}) {
  const res = await axios.get(`${API_BASE_URL}/api/assessment-submissions`, { headers });
  return res.data;
}

export async function submitResponses(body = {}, headers = {}) {
  const res = await axios.post(`${API_BASE_URL}/api/assessment-responses`, body, { headers });
  return res.data;
}

export default {
  normalizeFormDefinition,
  fetchAssessments,
  fetchResponses,
  fetchAssignments,
  fetchSubmissions,
  submitResponses,
};
