<script setup>
import { router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();

const impersonating = computed(() => page.props.impersonating ?? null);

function exit() {
    router.post(route('impersonate.stop'));
}
</script>

<template>
    <Teleport to="body">
        <div
            v-if="impersonating"
            class="fixed inset-x-0 top-0 z-[100] flex h-9 items-center justify-center gap-3 border-b border-amber-600 bg-amber-500 px-4 text-amber-950 shadow-md"
            role="status"
        >
            <!-- icon: question-mark-circle -->
            <svg
                xmlns="http://www.w3.org/2000/svg"
                viewBox="0 0 20 20"
                fill="currentColor"
                class="h-4 w-4 shrink-0"
                aria-hidden="true"
            >
                <path
                    fill-rule="evenodd"
                    d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.94 6.94a.75.75 0 11-1.061-1.061 3 3 0 112.871 5.026v.345a.75.75 0 01-1.5 0v-.5c0-.72.57-1.172 1.081-1.287A1.5 1.5 0 108.94 6.94zM10 15a1 1 0 100-2 1 1 0 000 2z"
                    clip-rule="evenodd"
                />
            </svg>
            <span class="font-sans text-[13px] font-medium">
                {{
                    $t('impersonation.banner.viewing', {
                        name: impersonating.userName,
                    })
                }}
            </span>
            <button
                type="button"
                class="rounded-md border border-amber-700/40 bg-amber-50/80 px-2.5 py-1 font-sans text-[12px] font-semibold text-amber-900 transition hover:bg-amber-50"
                @click="exit"
            >
                {{ $t('impersonation.banner.exit') }}
            </button>
        </div>
    </Teleport>
</template>
