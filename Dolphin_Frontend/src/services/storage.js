import CryptoJS from 'crypto-js';

const STORAGE_KEY = process.env.VUE_APP_STORAGE_KEY || 'dolphin_secret_key';
// Key used to notify other tabs about a logout event. Value is timestamp.
const LOGOUT_BROADCAST_KEY = process.env.VUE_APP_LOGOUT_BROADCAST_KEY || 'dolphin_logout';

// Use localStorage so auth/session data is shared across tabs/windows.
// This makes login persistent when opening the app in a new tab. When the
// application wants to logout, call `storage.broadcastLogout()` which will
// write to localStorage and other tabs will receive a storage event.
const storage = {
  _logoutListeners: new Set(),

  set(key, value) {
    try {
      // Normalize role values to lowercase to keep role checks consistent
      if (key === 'role' && typeof value === 'string') {
        value = value.toLowerCase();
      }
      const stringValue = JSON.stringify(value);
      try {
        const encrypted = CryptoJS.AES.encrypt(stringValue, STORAGE_KEY).toString();
        localStorage.setItem(key, encrypted);
      } catch (e) {
        // fallback: store as plain text if encryption fails
        console.debug &&
          console.debug(`Encryption failed for key "${key}". Storing as plain text.`, e);
        localStorage.setItem(key, stringValue);
      }
    } catch (e) {
      // JSON.stringify failed
      console.debug && console.debug(`Could not stringify value for key "${key}".`, e);
    }
  },

  get(key) {
    let raw;
    try {
      raw = localStorage.getItem(key);
    } catch (e) {
      console.debug && console.debug(`localStorage.getItem failed for key "${key}"`, e);
      return null;
    }
    if (!raw) return null;

    // Detect CryptoJS AES output (it commonly starts with 'U2FsdGVk') and only
    // attempt decryption when appropriate. Otherwise try to parse plain JSON
    // or return the raw string.
    const looksEncrypted = typeof raw === 'string' && raw.startsWith('U2FsdGVk');

    if (looksEncrypted) {
      try {
        const bytes = CryptoJS.AES.decrypt(raw, STORAGE_KEY);
        const decrypted = bytes.toString(CryptoJS.enc.Utf8);
        if (decrypted) {
          return JSON.parse(decrypted);
        }
        return null; // Decryption may result in an empty string, which is not valid JSON.
      } catch (e) {
        console.debug &&
          console.debug(
            `Decryption or JSON parse failed for key "${key}". Returning raw value.`,
            e
          );
        return raw;
      }
    }

    // Not encrypted: try to parse JSON, otherwise return raw string
    try {
      const parsed = JSON.parse(raw);
      // Normalize role reads as well
      if (key === 'role' && typeof parsed === 'string') return parsed.toLowerCase();
      return parsed;
    } catch (e) {
      console.debug && console.debug(`JSON parse failed for key "${key}". Returning raw value.`, e);
      // If raw string was stored (unencrypted), normalize role as needed
      if (key === 'role' && typeof raw === 'string') return raw.toLowerCase();
      return raw;
    }
  },

  remove(key) {
    try {
      localStorage.removeItem(key);
    } catch (e) {
      console.debug && console.debug(`localStorage.removeItem failed for key "${key}"`, e);
    }
  },

  // Clear storage but keep the logout broadcast key so other tabs can still
  // receive future logout broadcasts. This avoids removing the special
  // broadcast key which is used for cross-tab signaling.
  clear() {
    try {
      // Remove all keys except LOGOUT_BROADCAST_KEY
      for (let i = localStorage.length - 1; i >= 0; i--) {
        const k = localStorage.key(i);
        if (k && k !== LOGOUT_BROADCAST_KEY) localStorage.removeItem(k);
      }
    } catch (e) {
      console.debug && console.debug('localStorage.clear fallback failed', e);
    }
  },

  // Broadcast a logout event to other tabs by writing a timestamp to
  // LOGOUT_BROADCAST_KEY. Other tabs listen to the storage event and will
  // clear their storage. This function also triggers local listeners.
  broadcastLogout() {
    try {
      localStorage.setItem(LOGOUT_BROADCAST_KEY, String(Date.now()));
    } catch (e) {
      console.debug && console.debug('Failed to broadcast logout:', e);
    }
    // Also clear in the current tab and notify listeners immediately
    try {
      this.clear();
    } catch (e) {
      console.debug && console.debug('Error clearing storage on logout:', e);
    }
    this._notifyLogoutListeners();
  },

  onLogout(cb) {
    if (typeof cb === 'function') this._logoutListeners.add(cb);
  },

  offLogout(cb) {
    if (typeof cb === 'function') this._logoutListeners.delete(cb);
  },

  _notifyLogoutListeners() {
    for (const cb of Array.from(this._logoutListeners)) {
      try {
        cb();
      } catch (e) {
        // swallow listener errors
        console.debug && console.debug('Logout listener error:', e);
      }
    }
  },
};

// Listen for logout broadcasts from other tabs (only in browsers)
if (typeof window !== 'undefined' && typeof window.addEventListener === 'function') {
  window.addEventListener('storage', (e) => {
    if (!e.key) return;
    if (e.key === LOGOUT_BROADCAST_KEY) {
      // Clear local storage in this tab (do not remove the broadcast key so
      // events can still be delivered later). Then notify any in-memory listeners.
      try {
        storage.clear();
      } catch (err) {
        console.debug && console.debug('Error clearing storage on logout broadcast:', err);
      }
      storage._notifyLogoutListeners();
    }
  });
}

export default storage;
