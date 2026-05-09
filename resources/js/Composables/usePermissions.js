import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

export function usePermissions() {
    const page = usePage();

    const permissions = computed(() => page.props.permissions ?? []);

    function can(permission) {
        return permissions.value.includes(permission);
    }

    function canAny(...permissionList) {
        return permissionList.some((p) => can(p));
    }

    return { can, canAny, permissions };
}
