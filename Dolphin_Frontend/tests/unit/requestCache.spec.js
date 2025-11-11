import requestCache from '../../src/services/requestCache.js';
import { describe, it, expect } from 'vitest';

describe('requestCache', () => {
  it('stores and retrieves values', () => {
    requestCache.clear();
    requestCache.set('foo', { a: 1 });
    const val = requestCache.get('foo');
    expect(val).toEqual({ a: 1 });
    expect(requestCache.has('foo')).toBe(true);
  });

  it('honors ttl and expires entries', async () => {
    requestCache.clear();
    requestCache.set('temp', 'x', 30); // 30ms
    expect(requestCache.get('temp')).toBe('x');
    await new Promise((r) => setTimeout(r, 60));
    expect(requestCache.get('temp')).toBeNull();
    expect(requestCache.has('temp')).toBe(false);
  });
});
