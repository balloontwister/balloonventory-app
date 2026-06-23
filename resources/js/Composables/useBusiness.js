import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

export const ROLE_LABELS = {
    owner: 'Owner',
    manager: 'Manager',
    staff: 'Artist',
    guest: 'Guest',
    none: 'No Access',
};

export function roleLabelFor(role) {
    return ROLE_LABELS[role] ?? role;
}

export function useBusiness() {
    const page = usePage();

    const business = computed(() => page.props.business ?? null);
    const businesses = computed(() => page.props.businesses ?? []);
    const membership = computed(() => page.props.membership ?? null);

    const businessColor = computed(() => business.value?.color ?? '#6D28D9');
    const businessName = computed(() => business.value?.name ?? '');
    const businessLogoUrl = computed(() => business.value?.logoUrl ?? null);
    const userRole = computed(() => membership.value?.role ?? null);
    const userRoleLabel = computed(() => roleLabelFor(userRole.value));

    return {
        business,
        businesses,
        membership,
        businessColor,
        businessName,
        businessLogoUrl,
        userRole,
        userRoleLabel,
    };
}
