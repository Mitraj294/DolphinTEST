import axios from 'axios';
import storage from './storage';

import { getApiBase } from '@/env';
const API_BASE_URL = getApiBase();

export async function fetchCurrentUser() {
  try {
    const raw = storage.get('authToken');
    let token = null;
    if (raw) {
      if (typeof raw === 'string') token = raw;
      else if (raw.token) token = raw.token;
      else if (raw.access_token) token = raw.access_token;
    }
    const headers = token ? { Authorization: `Bearer ${token}` } : {};
    const response = await axios.get(`${API_BASE_URL}/api/user`, { headers });
    return response.data;
  } catch (e) {
    console.debug?.('Error fetching current user:', e);
    return null;
  }
}
