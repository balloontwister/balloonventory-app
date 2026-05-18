import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

export function useBusiness() {
    const page = usePage();

    const business = computed(() => page.props.business ?? null);
    const businesses = computed(() => page.props.businesses ?? []);
    const membership = computed(() => page.props.membership ?? null);

    const businessColor = computed(() => business.value?.color ?? '#6D28D9');
    const businessName = computed(() => business.value?.name ?? '');
    const businessLogoUrl = computed(() => business.value?.logoUrl ?? null);
    const userRole = computed(() => membership.value?.role ?? null);

    return {
        business,
        businesses,
        membership,
        businessColor,
        businessName,
        businessLogoUrl,
        userRole,
    };
}
