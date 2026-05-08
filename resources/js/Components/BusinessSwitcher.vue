<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import RoleBadge from '@/Components/RoleBadge.vue';
import { useBusiness } from '@/Composables/useBusiness';

const { business, businesses, userRole, businessColor } = useBusiness();

const open = ref(false);

function switchBusiness(id) {
    open.value = false;
    router.post(route('business.switch', { business: id }), {}, { preserveScroll: false });
}
</script>

<template>
    <div class="relative">
        <!-- trigger -->
        <button
            type="button"
            class="flex w-full items-center justify-between gap-2 rounded-md px-2 py-2 text-left transition hover:bg-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent"
            :style="{ borderLeft: `4px solid ${businessColor}` }"
            @click="open = !open"
        >
            <div class="min-w-0 pl-2">
                <p class="truncate font-sans text-[15px] font-semibold leading-snug text-ink-primary">
                    {{ business?.name ?? 'No business selected' }}
                </p>
                <p class="font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary">
                    {{ userRole ?? '' }}
                </p>
            </div>
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor" class="h-4 w-4 flex-shrink-0 text-ink-tertiary">
                <path fill-rule="evenodd" d="M4.22 6.22a.75.75 0 011.06 0L8 8.94l2.72-2.72a.75.75 0 111.06 1.06l-3.25 3.25a.75.75 0 01-1.06 0L4.22 7.28a.75.75 0 010-1.06z" clip-rule="evenodd" />
            </svg>
        </button>

        <!-- dropdown -->
        <div
            v-if="open"
            class="absolute left-0 right-0 z-40 mt-1 overflow-hidden rounded-md border border-border bg-surface shadow-pop"
        >
            <button
                v-for="biz in businesses"
                :key="biz.id"
                type="button"
                class="flex w-full items-center gap-3 px-3 py-2.5 text-left transition hover:bg-background"
                @click="switchBusiness(biz.id)"
            >
                <span
                    class="h-1.5 w-1.5 flex-shrink-0 rounded-full"
                    :style="{ backgroundColor: biz.color ?? '#A1A1AA' }"
                />
                <div class="min-w-0 flex-1">
                    <p class="truncate font-sans text-[14px] text-ink-primary">{{ biz.name }}</p>
                    <RoleBadge :role="biz.pivot?.role ?? 'guest'" />
                </div>
                <!-- checkmark for current -->
                <svg
                    v-if="biz.id === business?.id"
                    xmlns="http://www.w3.org/2000/svg"
                    viewBox="0 0 16 16"
                    fill="currentColor"
                    class="h-4 w-4 flex-shrink-0 text-accent"
                >
                    <path fill-rule="evenodd" d="M12.416 3.376a.75.75 0 01.208 1.04l-5 7.5a.75.75 0 01-1.154.114l-3-3a.75.75 0 011.06-1.06l2.353 2.353 4.493-6.74a.75.75 0 011.04-.207z" clip-rule="evenodd" />
                </svg>
            </button>

            <div class="border-t border-border px-3 py-2">
                <a
                    :href="route('settings.businesses')"
                    class="block font-sans text-[13px] text-ink-secondary hover:text-accent"
                >
                    Manage businesses
                </a>
            </div>
        </div>

        <!-- backdrop -->
        <div v-if="open" class="fixed inset-0 z-30" @click="open = false" />
    </div>
</template>
