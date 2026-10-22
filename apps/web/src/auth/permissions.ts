import type { AuthUser } from '../api/client';

export function can(user: AuthUser | null | undefined, permission: string): boolean {
  return Boolean(user?.permissions?.includes(permission));
}

export function canAny(user: AuthUser | null | undefined, permissions: string[]): boolean {
  if (!permissions.length) return true;
  return permissions.some((p) => can(user, p));
}

export function canAll(user: AuthUser | null | undefined, permissions: string[]): boolean {
  return permissions.every((p) => can(user, p));
}

/** Nav / route visibility: user needs any of the listed permissions (empty = any authenticated user). */
export type NavItem = {
  path: string;
  key: string;
  anyOf: string[];
};

export const NAV_ITEMS: NavItem[] = [
  { path: '/', key: 'dashboard', anyOf: [] },
  { path: '/customers', key: 'customers', anyOf: ['customer.view'] },
  { path: '/leads', key: 'leads', anyOf: ['customer.view'] },
  { path: '/quotes', key: 'quotes', anyOf: ['quote.create'] },
  { path: '/policies', key: 'policies', anyOf: ['policy.view'] },
  { path: '/claims', key: 'claims', anyOf: ['claim.submit'] },
  { path: '/finance', key: 'finance', anyOf: ['finance.reconcile', 'commission.approve'] },
  { path: '/settings', key: 'settings', anyOf: ['integration.manage', 'tenant.configure'] },
];

export function visibleNav(user: AuthUser | null | undefined): NavItem[] {
  return NAV_ITEMS.filter((item) => canAny(user, item.anyOf));
}
