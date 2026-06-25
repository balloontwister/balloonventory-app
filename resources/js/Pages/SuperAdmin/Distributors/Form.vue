<script setup>
import AdminBackLink from '@/Components/AdminBackLink.vue';
import AppButton from '@/Components/AppButton.vue';
import AppInput from '@/Components/AppInput.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    distributor: { type: Object, default: null },
});

const isEdit = !!props.distributor;

// Pull known keys out of the existing config JSON so the advanced textarea
// only shows keys we don't cover with structured fields.
const KNOWN_CONFIG_KEYS = [
    'sku_strip_prefixes', 'sku_strip_suffixes',
    'request_delay_ms', 'request_jitter_ms', 'max_retries', 'max_pages',
    'has_json_api', 'collection_handle',
];

const existingConfig = props.distributor?.config ?? {};
const advancedConfigObj = Object.fromEntries(
    Object.entries(existingConfig).filter(([k]) => !KNOWN_CONFIG_KEYS.includes(k)),
);
const advancedConfigJson = Object.keys(advancedConfigObj).length
    ? JSON.stringify(advancedConfigObj, null, 2)
    : '';

const form = useForm({
    name: props.distributor?.name ?? '',
    slug: props.distributor?.slug ?? '',
    description: props.distributor?.description ?? '',
    platform_type: props.distributor?.platform_type ?? 'shopify',
    base_url: props.distributor?.base_url ?? '',
    sitemap_url: props.distributor?.sitemap_url ?? '',
    is_active: props.distributor?.is_active ?? true,
    sort_order: props.distributor?.sort_order ?? 0,
    // Structured config
    config_sku_strip_prefixes: existingConfig.sku_strip_prefixes?.join(', ') ?? '',
    config_sku_strip_suffixes: existingConfig.sku_strip_suffixes?.join(', ') ?? '',
    // Throttle fields default to null so a blank field falls back to the
    // adapter's own default rather than persisting the form default into config.
    // The effective default is shown as the input placeholder instead.
    config_request_delay_ms: existingConfig.request_delay_ms ?? null,
    config_request_jitter_ms: existingConfig.request_jitter_ms ?? null,
    config_max_retries: existingConfig.max_retries ?? null,
    config_max_pages: existingConfig.max_pages ?? null,
    config_has_json_api: existingConfig.has_json_api ?? true,
    config_collection_handle: existingConfig.collection_handle ?? '',
    // Advanced raw JSON for unlisted keys
    config_advanced: advancedConfigJson,
});

const showAdvanced = ref(false);
const isShopify = computed(() => form.platform_type === 'shopify');

function submit() {
    if (isEdit) {
        form.patch(route('admin.distributors.update', props.distributor.id));
    } else {
        form.post(route('admin.distributors.store'));
    }
}
</script>

<template>
    <Head :title="isEdit ? 'Edit Distributor' : 'Add Distributor'" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-3">
                <AdminBackLink :href="route('admin.distributors.index')" label="Distributors" />
                <h1 class="font-display text-[22px] font-semibold tracking-h2 text-ink-primary">
                    {{ isEdit ? 'Edit' : 'Add' }} Distributor
                </h1>
            </div>
        </template>

        <div class="mx-auto max-w-2xl px-4 py-6 sm:px-6 lg:px-8">
            <form class="space-y-6" @submit.prevent="submit">

                <!-- Basic info -->
                <div class="space-y-4 rounded-lg border border-border bg-surface p-6 shadow-pop">
                    <h2 class="text-sm font-semibold uppercase tracking-wider text-ink-tertiary">Basic info</h2>

                    <div>
                        <label class="block text-sm font-medium text-ink-primary">Name</label>
                        <AppInput v-model="form.name" class="mt-1 w-full" required />
                        <p v-if="form.errors.name" class="mt-1 text-xs text-red-600">{{ form.errors.name }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-ink-primary">Slug</label>
                        <AppInput v-model="form.slug" class="mt-1 w-full" required />
                        <p v-if="form.errors.slug" class="mt-1 text-xs text-red-600">{{ form.errors.slug }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-ink-primary">Platform Type</label>
                        <select
                            v-model="form.platform_type"
                            class="mt-1 block w-full rounded-md border-border bg-surface px-3 py-2 text-sm shadow-sm focus:border-accent focus:ring-accent"
                        >
                            <option value="shopify">Shopify</option>
                            <option value="bigcommerce">BigCommerce</option>
                        </select>
                        <p v-if="form.errors.platform_type" class="mt-1 text-xs text-red-600">{{ form.errors.platform_type }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-ink-primary">Base URL</label>
                        <AppInput v-model="form.base_url" class="mt-1 w-full" placeholder="https://example.com" required />
                        <p v-if="form.errors.base_url" class="mt-1 text-xs text-red-600">{{ form.errors.base_url }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-ink-primary">
                            Sitemap URL
                            <span class="font-normal text-ink-tertiary">(optional — auto-detected if blank)</span>
                        </label>
                        <AppInput v-model="form.sitemap_url" class="mt-1 w-full" placeholder="https://example.com/sitemap.xml" />
                        <p v-if="form.errors.sitemap_url" class="mt-1 text-xs text-red-600">{{ form.errors.sitemap_url }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-ink-primary">Description</label>
                        <textarea
                            v-model="form.description"
                            rows="2"
                            class="mt-1 block w-full rounded-md border-border bg-surface px-3 py-2 text-sm shadow-sm focus:border-accent focus:ring-accent"
                        />
                        <p v-if="form.errors.description" class="mt-1 text-xs text-red-600">{{ form.errors.description }}</p>
                    </div>

                    <div class="flex items-center gap-6">
                        <div>
                            <label class="block text-sm font-medium text-ink-primary">Sort Order</label>
                            <AppInput v-model.number="form.sort_order" type="number" class="mt-1 w-24" />
                        </div>
                        <div class="flex items-center gap-2 pt-5">
                            <input
                                id="is_active"
                                v-model="form.is_active"
                                type="checkbox"
                                class="h-4 w-4 rounded border-border text-accent focus:ring-accent"
                            />
                            <label for="is_active" class="text-sm font-medium text-ink-primary">Active</label>
                        </div>
                    </div>
                </div>

                <!-- Product matching -->
                <div class="space-y-4 rounded-lg border border-border bg-surface p-6 shadow-pop">
                    <h2 class="text-sm font-semibold uppercase tracking-wider text-ink-tertiary">Product Matching</h2>

                    <div>
                        <label class="block text-sm font-medium text-ink-primary">
                            SKU strip prefixes
                            <span class="font-normal text-ink-tertiary">(comma-separated)</span>
                        </label>
                        <AppInput
                            v-model="form.config_sku_strip_prefixes"
                            class="mt-1 w-full"
                            placeholder="LRK-, HAV-"
                        />
                        <p v-if="form.errors.config_sku_strip_prefixes" class="mt-1 text-xs text-red-600">{{ form.errors.config_sku_strip_prefixes }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-ink-primary">
                            SKU strip suffixes
                            <span class="font-normal text-ink-tertiary">(comma-separated)</span>
                        </label>
                        <AppInput
                            v-model="form.config_sku_strip_suffixes"
                            class="mt-1 w-full"
                            placeholder="-UNIT"
                        />
                        <p v-if="form.errors.config_sku_strip_suffixes" class="mt-1 text-xs text-red-600">{{ form.errors.config_sku_strip_suffixes }}</p>
                    </div>
                </div>

                <!-- Fetch settings -->
                <div class="space-y-4 rounded-lg border border-border bg-surface p-6 shadow-pop">
                    <h2 class="text-sm font-semibold uppercase tracking-wider text-ink-tertiary">Fetch Settings</h2>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-ink-primary">Request delay (ms)</label>
                            <AppInput v-model.number="form.config_request_delay_ms" type="number" min="0" placeholder="500" class="mt-1 w-full" />
                            <p v-if="form.errors.config_request_delay_ms" class="mt-1 text-xs text-red-600">{{ form.errors.config_request_delay_ms }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-ink-primary">Request jitter (ms)</label>
                            <AppInput v-model.number="form.config_request_jitter_ms" type="number" min="0" placeholder="0" class="mt-1 w-full" />
                            <p v-if="form.errors.config_request_jitter_ms" class="mt-1 text-xs text-red-600">{{ form.errors.config_request_jitter_ms }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-ink-primary">Max retries</label>
                            <AppInput v-model.number="form.config_max_retries" type="number" min="0" placeholder="3" class="mt-1 w-full" />
                            <p v-if="form.errors.config_max_retries" class="mt-1 text-xs text-red-600">{{ form.errors.config_max_retries }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-ink-primary">Max pages</label>
                            <AppInput v-model.number="form.config_max_pages" type="number" min="1" placeholder="500" class="mt-1 w-full" />
                            <p v-if="form.errors.config_max_pages" class="mt-1 text-xs text-red-600">{{ form.errors.config_max_pages }}</p>
                        </div>
                    </div>
                </div>

                <!-- Shopify API settings -->
                <div v-if="isShopify" class="space-y-4 rounded-lg border border-border bg-surface p-6 shadow-pop">
                    <h2 class="text-sm font-semibold uppercase tracking-wider text-ink-tertiary">Shopify API</h2>

                    <div class="flex items-center gap-2">
                        <input
                            id="has_json_api"
                            v-model="form.config_has_json_api"
                            type="checkbox"
                            class="h-4 w-4 rounded border-border text-accent focus:ring-accent"
                        />
                        <label for="has_json_api" class="text-sm font-medium text-ink-primary">Use JSON API</label>
                        <span class="text-xs text-ink-tertiary">(recommended for all Shopify stores)</span>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-ink-primary">Collection handle</label>
                        <AppInput v-model="form.config_collection_handle" class="mt-1 w-full" placeholder="all" />
                        <p v-if="form.errors.config_collection_handle" class="mt-1 text-xs text-red-600">{{ form.errors.config_collection_handle }}</p>
                    </div>
                </div>

                <!-- Advanced raw config (collapsible) -->
                <div class="rounded-lg border border-border bg-surface shadow-pop">
                    <button
                        type="button"
                        class="flex w-full items-center justify-between px-6 py-4 text-left font-sans text-sm font-medium text-ink-secondary transition hover:text-ink-primary"
                        @click="showAdvanced = !showAdvanced"
                    >
                        <span>Advanced: raw config</span>
                        <span class="text-ink-tertiary">{{ showAdvanced ? '▲' : '▼' }}</span>
                    </button>
                    <div v-if="showAdvanced" class="border-t border-border px-6 pb-5 pt-4">
                        <p class="mb-2 font-sans text-[12px] text-ink-tertiary">
                            Extra keys not covered by the structured fields above. Must be valid JSON.
                            The structured fields always take priority.
                        </p>
                        <textarea
                            v-model="form.config_advanced"
                            rows="6"
                            class="block w-full rounded-md border-border bg-surface px-3 py-2 font-mono text-sm shadow-sm focus:border-accent focus:ring-accent"
                            placeholder="{}"
                        />
                        <p v-if="form.errors.config_advanced" class="mt-1 text-xs text-red-600">{{ form.errors.config_advanced }}</p>
                    </div>
                </div>

                <!-- Submit -->
                <div class="flex items-center gap-3">
                    <AppButton type="submit" variant="primary" :disabled="form.processing">
                        {{ isEdit ? 'Save Changes' : 'Create Distributor' }}
                    </AppButton>
                    <AppButton :href="route('admin.distributors.index')" variant="ghost">
                        Cancel
                    </AppButton>
                </div>
            </form>
        </div>
    </AuthenticatedLayout>
</template>
