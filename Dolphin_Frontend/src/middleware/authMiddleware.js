import { canAccess, ROLES } from '@/permissions.js';
import storage from '../services/storage';

const authMiddleware = {
  loginAny(email, role = 'superadmin') {
    storage.set('role', String(role).toLowerCase());
    storage.set('name', email.split('@')[0] || 'User');
    storage.set('email', email);
    return true;
  },
  isAuthenticated() {
    const role = storage.get('role');
    const normalized = role ? String(role).toLowerCase() : '';
    return !!normalized && Object.values(ROLES).includes(normalized);
  },
  getRole() {
    const role = storage.get('role') || '';
    return role ? String(role).toLowerCase() : '';
  },
  canAccess(type, name) {
    const role = storage.get('role');
    const normalized = role ? String(role).toLowerCase() : '';
    return canAccess(normalized, type, name);
  },
};

export default authMiddleware;
