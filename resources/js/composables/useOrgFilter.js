import { computed, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';

/**
 * Drives the admin "filter a listing by organization" behaviour from the URL
 * (?organization={uuid}&org_name={name}, set by the org detail page). Keeps the
 * given store's `organization` filter in sync and refetches when it changes.
 */
export function useOrgFilter(store) {
    const route = useRoute();
    const router = useRouter();

    const active = computed(() => Boolean(route.query.organization));
    const orgName = computed(() => route.query.org_name || 'this organization');

    // Seed before the page's initial fetch.
    store.filters.organization = route.query.organization || '';

    watch(
        () => route.query.organization,
        (value) => {
            store.filters.organization = value || '';
            if ('page' in store.filters) store.filters.page = 1;
            store.fetch();
        }
    );

    function clear() {
        const query = { ...route.query };
        delete query.organization;
        delete query.org_name;
        router.replace({ query });
    }

    return { active, orgName, clear };
}
