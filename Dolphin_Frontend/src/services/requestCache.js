/**
 * Lightweight request cache used by the frontend.
 * Provides get/set/has/remove and optional TTL support.
 * This is an in-memory cache (per-page). It's intentionally simple.
 */
const cache = new Map();

function nowMs() {
  return Date.now();
}

export default {
  set(key, value, ttlMs = null) {
    const entry = { value };
    if (ttlMs && typeof ttlMs === 'number') entry.expiresAt = nowMs() + ttlMs;
    cache.set(key, entry);
  },

  get(key) {
    const entry = cache.get(key);
    if (!entry) return null;
    if (entry.expiresAt && nowMs() >= entry.expiresAt) {
      cache.delete(key);
      return null;
    }
    return entry.value;
  },

  has(key) {
    return this.get(key) !== null;
  },

  remove(key) {
    cache.delete(key);
  },

  clear() {
    cache.clear();
  },
};
