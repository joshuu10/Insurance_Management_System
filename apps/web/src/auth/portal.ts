import type { AuthUser } from '../api/client';

export function isPortalUser(user: AuthUser | null | undefined): boolean {
  if (!user) return false;
  return Boolean(user.is_portal || user.customer_id || user.roles?.includes('customer_portal'));
}

export function postLoginPath(user: AuthUser | null | undefined): string {
  return isPortalUser(user) ? '/portal' : '/';
}
