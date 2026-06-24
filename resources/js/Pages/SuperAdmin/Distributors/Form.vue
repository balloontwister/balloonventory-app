<script setup>
import AdminBackLink from '@/Components/AdminBackLink.vue';
import AppButton from '@/Components/AppButton.vue';
import AppInput from '@/Components/AppInput.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps({
    distributor: { type: Object, default: null },
});

const isEdit = !!props.distributor;

const form = useForm({
    name: props.distributor?.name ?? '',
    slug: props.distributor?.slug ?? '',
    description: props.distributor?.description ?? '',
    platform_type: props.distributor?.platform_type ?? 'shopify',
    base_url: props.distributor?.base_url ?? '',
    sitemap_url: props.distributor?.sitemap_url ?? '',
    config: props.distributor?.config ? JSON.stringify(props.distributor.config) : '',
    is_active: props.distributor?.is_active ?? true,
    sort_order: props.distributor?.sort_order ?? 0,
});

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
            <form @submit.prevent="submit" class="space-y-6">
                <div class="rounded-lg border border-border bg-surface p-6 shadow-pop space-y-4">
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
                            <span class="text-ink-tertiary font-normal">(optional — auto-detected if blank)</span>
                        </label>
                        <AppInput v-model="form.sitemap_url" class="mt-1 w-full" placeholder="https://example.com/sitemap.xml" />
                        <p v-if="form.errors.sitemap_url" class="mt-1 text-xs text-red-600">{{ form.errors.sitemap_url }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-ink-primary">
                            Config JSON
                            <span class="text-ink-tertiary font-normal">(optional — platform-specific settings)</span>
                        </label>
                        <textarea
                            v-model="form.config"
                            rows="3"
                            class="mt-1 block w-full rounded-md border-border bg-surface px-3 py-2 text-sm shadow-sm focus:border-accent focus:ring-accent font-mono"
                            placeholder='{"collection_handle": "all", "has_json_api": true}'
                        />
                        <p v-if="form.errors.config" class="mt-1 text-xs text-red-600">{{ form.errors.config }}</p>
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
                            <p v-if="form.errors.sort_order" class="mt-1 text-xs text-red-600">{{ form.errors.sort_order }}</p>
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
