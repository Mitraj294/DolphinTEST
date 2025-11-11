import authService from '../../src/services/auth.js';
import storage from '../../src/services/storage.js';
import { describe, it, expect, beforeEach } from 'vitest';

describe('authService basic behavior', () => {
  beforeEach(() => {
    // ensure a clean storage state
    storage.clear();
  });

  it('setToken/getToken and removeToken', () => {
    authService.setToken('abc123', Date.now() + 1000);
    expect(authService.getToken()).toBe('abc123');
    expect(authService.isAuthenticated()).toBe(true);

    authService.removeToken();
    expect(authService.getToken()).toBeNull();
    expect(authService.isAuthenticated()).toBe(false);
  });
});
