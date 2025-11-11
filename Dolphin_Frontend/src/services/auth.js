import axios from 'axios';
import storage from './storage';

const AUTH_TOKEN_KEY = 'authToken';
const TOKEN_EXPIRY_KEY = 'tokenExpiry';
const USER_ID_KEY = 'user_id';

/**
 * Small helper to parse stored expiry values.
 * Accepts either an ISO string or a numeric timestamp.
 * Returns epoch ms or null.
 */
function parseExpiry(expiry) {
  if (!expiry) return null;
  if (typeof expiry === 'number') return expiry;
  const t = Date.parse(expiry);
  return Number.isNaN(t) ? null : t;
}

const FIVE_MINUTES_MS = 5 * 60 * 1000;

const authService = {
  /** Store token and optional expiry (ISO string or timestamp). */
  setToken(token, expiresAt = null) {
    if (token) {
      storage.set(AUTH_TOKEN_KEY, token);
    } else {
      storage.remove(AUTH_TOKEN_KEY);
    }

    if (expiresAt) {
      storage.set(TOKEN_EXPIRY_KEY, expiresAt);
    }

    this.setAxiosAuthHeader(token);
  },

  /** Return token or null if missing/expired. Side-effect: removes expired token. */
  getToken() {
    const token = storage.get(AUTH_TOKEN_KEY);
    if (!token) return null;

    if (this.isTokenExpired()) {
      this.removeToken();
      return null;
    }

    return token;
  },

  /** True if current time is at or past expiry. No expiry stored => not expired. */
  isTokenExpired() {
    const expiryRaw = storage.get(TOKEN_EXPIRY_KEY);
    const expiry = parseExpiry(expiryRaw);
    if (!expiry) return false;
    return Date.now() >= expiry;
  },

  removeToken() {
    storage.remove(AUTH_TOKEN_KEY);
    storage.remove(TOKEN_EXPIRY_KEY);
    storage.remove(USER_ID_KEY);
    this.setAxiosAuthHeader(null);
  },

  setAxiosAuthHeader(token) {
    if (token) {
      axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;
    } else {
      delete axios.defaults.headers.common['Authorization'];
    }
  },

  /**
   * Login and accept either of these backend shapes:
   * - { access_token, refresh_token, expires_in, user }
   * - { token, user, expires_at }
   * Returns { user, token } on success.
   */
  async login(email, password) {
    try {
      const response = await axios.post('/api/login', { email, password });
      const data = response.data || {};

      const token = data.token || data.access_token || null;
      const user = data.user || null;

      // Normalize expiry: prefer expires_at, else derive from expires_in (seconds)
      let expiresAt = null;
      if (data.expires_at) expiresAt = data.expires_at;
      else if (typeof data.expires_in === 'number') {
        expiresAt = new Date(Date.now() + data.expires_in * 1000).toISOString();
      }

      this.setToken(token, expiresAt);

      if (user?.id) storage.set(USER_ID_KEY, user.id);

      return { user, token };
    } catch (err) {
      // bubble up the error after logging for debugging
      console.debug && console.debug('authService.login error:', err?.response || err);
      throw err;
    }
  },

  async logout() {
    try {
      await axios.post('/api/logout');
    } catch (err) {
      // ignore API errors but still clear local state
      console.debug && console.debug('authService.logout warning:', err?.response || err);
    } finally {
      this.removeToken();
    }
  },

  isAuthenticated() {
    return !!this.getToken();
  },

  /** True if token will expire within the next 5 minutes. */
  isTokenExpiringSoon() {
    const expiryRaw = storage.get(TOKEN_EXPIRY_KEY);
    const expiry = parseExpiry(expiryRaw);
    if (!expiry) return false;
    return expiry - Date.now() <= FIVE_MINUTES_MS;
  },

  init() {
    // Use getToken to automatically clear expired tokens and set axios header
    const token = this.getToken();
    this.setAxiosAuthHeader(token);
  },

  getUserId() {
    return storage.get(USER_ID_KEY);
  },
};

// Initialize auth service
authService.init();

export default authService;
