<script setup>
import Dropdown from '@/Components/Dropdown.vue';
import { router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    buttonClass: {
        type: String,
        default: '',
    },
});

const supportedLocales = computed(() => usePage().props.supportedLocales ?? []);
const currentLocale = computed(() => usePage().props.locale ?? 'en');

function switchLocale(locale) {
    router.post(
        route('locale.switch'),
        { locale },
        {
            preserveState: false,
            onSuccess: () => window.location.reload(),
        },
    );
}
</script>

<template>
    <Dropdown align="left" width="40">
        <template #trigger>
            <button
                :class="buttonClass"
                :aria-label="$t('nav.language')"
                type="button"
            >
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.5"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    class="h-4 w-4 flex-shrink-0"
                >
                    <circle cx="12" cy="12" r="10" />
                    <path d="M12 2a14.5 14.5 0 0 0 0 20 14.5 14.5 0 0 0 0-20" />
                    <path d="M2 12h20" />
                </svg>
            </button>
        </template>

        <template #content>
            <div class="py-1">
                <button
                    v-for="item in supportedLocales"
                    :key="item.code"
                    class="flex w-full items-center gap-2 px-4 py-2 text-start text-sm leading-5 transition duration-150 ease-in-out"
                    :class="
                        item.code === currentLocale
                            ? 'font-semibold text-accent'
                            : 'text-gray-700 hover:bg-gray-100 focus:bg-gray-100'
                    "
                    @click="switchLocale(item.code)"
                >
                    {{ item.label }}
                    <svg
                        v-if="item.code === currentLocale"
                        xmlns="http://www.w3.org/2000/svg"
                        viewBox="0 0 20 20"
                        fill="currentColor"
                        class="h-4 w-4"
                    >
                        <path
                            fill-rule="evenodd"
                            d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z"
                            clip-rule="evenodd"
                        />
                    </svg>
                </button>
            </div>
        </template>
    </Dropdown>
</template>
