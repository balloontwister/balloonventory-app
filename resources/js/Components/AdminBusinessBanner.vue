<script setup>
import { router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();

const viewing = computed(() => page.props.adminViewingBusiness ?? null);

function exit() {
    router.post(route('admin.businesses.stop-view'));
}
</script>

<template>
    <Teleport to="body">
        <div
            v-if="viewing"
            class="fixed inset-x-0 top-0 z-[100] flex h-9 items-center justify-center gap-3 border-b border-indigo-700 bg-indigo-600 px-4 text-white shadow-md"
            role="status"
        >
            <!-- icon: building-office -->
            <svg
                xmlns="http://www.w3.org/2000/svg"
                viewBox="0 0 20 20"
                fill="currentColor"
                class="h-4 w-4 shrink-0"
                aria-hidden="true"
            >
                <path
                    fill-rule="evenodd"
                    d="M1 2.75A.75.75 0 011.75 2h16.5a.75.75 0 010 1.5H18v8.75A2.75 2.75 0 0115.25 15h-1.072l.798 3.06a.75.75 0 01-1.452.38L13.41 18H6.59l-.114.44a.75.75 0 01-1.452-.38L5.823 15H4.75A2.75 2.75 0 012 12.25V3.5h-.25A.75.75 0 011 2.75zM7.373 15l-.391 1.5h6.037l-.392-1.5H7.373z"
                    clip-rule="evenodd"
                />
            </svg>
            <span class="font-sans text-[13px] font-medium">
                {{ $t('super_admin.businesses.view_as_banner', { name: viewing.name }) }}
            </span>
            <button
                type="button"
                class="rounded-md border border-white/40 bg-white/15 px-2.5 py-1 font-sans text-[12px] font-semibold text-white transition hover:bg-white/25"
                @click="exit"
            >
                {{ $t('super_admin.businesses.view_as_exit') }}
            </button>
        </div>
    </Teleport>
</template>
