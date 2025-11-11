import storage from '../../src/services/storage.js';
import { describe, it, expect, beforeEach } from 'vitest';

describe('storage', () => {
  beforeEach(() => {
    // make sure to start from a clean localStorage (jsdom)
    try {
      localStorage.clear();
    } catch (e) {
      // ignore when not available
    }
    storage.clear();
  });

  it('stores and reads JSON values', () => {
    storage.set('obj', { x: 1 });
    expect(storage.get('obj')).toEqual({ x: 1 });
  });

  it('normalizes role to lowercase', () => {
    storage.set('role', 'ADMIN');
    expect(storage.get('role')).toBe('admin');
  });
});
