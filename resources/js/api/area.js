import { useAuthStore } from '@/stores/auth';

/**
 * The current management area for the signed-in user. Organizations live under
 * /org (API) and org.* (routes); admins under /admin and admin.*. A session is
 * only ever one or the other, so these can be derived from the auth role.
 */
export function isOrgArea() {
    return useAuthStore().isOrganization;
}

/**
 * Prefix an API path with the active area, e.g. apiBase() + '/templates'.
 * Admin behaviour is unchanged (defaults to /admin).
 */
export function apiBase() {
    return isOrgArea() ? '/org' : '/admin';
}

/**
 * Resolve a shared route name to the active area, e.g. rn('templates') →
 * 'admin.templates' or 'org.templates'. Lets the same page components link
 * correctly from either portal.
 */
export function rn(name) {
    return (isOrgArea() ? 'org.' : 'admin.') + name;
}
