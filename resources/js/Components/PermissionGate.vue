<script setup>
import { useBusiness } from '@/Composables/useBusiness';

defineProps({
    permission: { type: String, required: true },
    allowed: { type: Boolean, required: true },
    destructive: { type: Boolean, default: false },
});

const { businessName } = useBusiness();

const roleForPermission = {
    'inventory.manual_adjust': 'Manager',
    'inventory.override_count': 'Manager',
    'inventory.view_audit_log': 'Manager',
    'sku.create_private': 'Manager',
    'sku.edit_override': 'Manager',
    'upc.manage': 'Manager',
    'upc.resolve_pending': 'Manager',
    'business.edit_settings': 'Owner',
};

function requiredRole(permission) {
    return roleForPermission[permission] ?? 'Manager';
}
</script>

<template>
    <div
        class="relative inline-flex"
        :title="
            !allowed
                ? `Requires ${requiredRole(permission)} role in ${businessName}`
                : undefined
        "
    >
        <div
            :class="
                !allowed
                    ? 'pointer-events-none cursor-not-allowed select-none opacity-40'
                    : ''
            "
            class="inline-flex items-center gap-1.5"
        >
            <!-- lock glyph for destructive gated actions -->
            <svg
                v-if="!allowed && destructive"
                xmlns="http://www.w3.org/2000/svg"
                viewBox="0 0 16 16"
                class="h-3.5 w-3.5 flex-shrink-0 text-ink-tertiary"
                fill="currentColor"
            >
                <path
                    fill-rule="evenodd"
                    d="M8 1a3 3 0 00-3 3v1H4a1 1 0 00-1 1v7a1 1 0 001 1h8a1 1 0 001-1V6a1 1 0 00-1-1H11V4a3 3 0 00-3-3zm0 1.5A1.5 1.5 0 019.5 4v1h-3V4A1.5 1.5 0 018 2.5zM8 9a1 1 0 110 2 1 1 0 010-2z"
                    clip-rule="evenodd"
                />
            </svg>

            <slot />
        </div>
    </div>
</template>
