<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AppButton from '@/Components/AppButton.vue';
import AppInput from '@/Components/AppInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    brands: { type: Array, required: true },
});

const editingId = ref(null);
const editForm  = useForm({ name: '', abbreviation: '', sort_order: '' });

function startEdit(brand) {
    editingId.value        = brand.id;
    editForm.name          = brand.name;
    editForm.abbreviation  = brand.abbreviation;
    editForm.sort_order    = brand.sort_order ?? '';
}

function submitEdit(brand) {
    editForm.patch(route('super-admin.catalog.brands.update', brand.id), {
        onSuccess: () => { editingId.value = null; },
    });
}

function cancelEdit() { editingId.value = null; }
</script>

<template>
    <Head title="Catalog — Brands" />

    <AuthenticatedLayout>
        <template #header>
            <h1 class="font-display text-[22px] font-semibold tracking-h2 text-ink-primary">Catalog</h1>
        </template>

        <!-- Catalog nav tabs -->
        <div class="mb-6 flex gap-1 border-b border-border">
            <Link :href="route('super-admin.catalog.skus')" class="px-4 py-2.5 font-sans text-[14px] font-medium text-ink-secondary transition hover:text-ink-primary">SKUs</Link>
            <Link :href="route('super-admin.catalog.colors')" class="px-4 py-2.5 font-sans text-[14px] font-medium text-ink-secondary transition hover:text-ink-primary">Colors</Link>
            <Link :href="route('super-admin.catalog.brands')" class="border-b-2 border-accent px-4 py-2.5 font-sans text-[14px] font-medium text-accent">Brands</Link>
            <Link :href="route('super-admin.catalog.reference')" class="px-4 py-2.5 font-sans text-[14px] font-medium text-ink-secondary transition hover:text-ink-primary">Reference Data</Link>
        </div>

        <p class="mb-4 font-sans text-[13px] text-ink-secondary">{{ brands.length }} brand{{ brands.length !== 1 ? 's' : '' }}. New brands are added by running the BrandSeeder after updating the seeder file.</p>

        <div class="overflow-hidden rounded-lg border border-border">
            <table class="w-full">
                <thead>
                    <tr class="border-b border-border bg-background">
                        <th class="px-4 py-2.5 text-left font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary">Brand</th>
                        <th class="px-4 py-2.5 text-left font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary">Abbrev.</th>
                        <th class="px-4 py-2.5 text-right font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary">SKUs</th>
                        <th class="px-4 py-2.5 text-right font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary">Sort</th>
                        <th class="w-24 px-4 py-2.5"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    <template v-for="brand in brands" :key="brand.id">
                        <!-- View row -->
                        <tr v-if="editingId !== brand.id" class="group transition hover:bg-accent-soft/40">
                            <td class="px-4 py-3 font-sans text-[14px] font-medium text-ink-primary">{{ brand.name }}</td>
                            <td class="px-4 py-3 font-mono text-[13px] text-ink-secondary">{{ brand.abbreviation }}</td>
                            <td class="px-4 py-3 text-right font-mono text-[13px] text-ink-secondary">{{ brand.skus_count }}</td>
                            <td class="px-4 py-3 text-right font-mono text-[13px] text-ink-tertiary">{{ brand.sort_order }}</td>
                            <td class="px-4 py-3">
                                <div class="flex justify-end opacity-0 transition group-hover:opacity-100">
                                    <AppButton variant="ghost" size="sm" @click="startEdit(brand)">Edit</AppButton>
                                </div>
                            </td>
                        </tr>

                        <!-- Edit row -->
                        <tr v-else class="bg-accent-soft/20">
                            <td colspan="5" class="px-4 py-3">
                                <form @submit.prevent="submitEdit(brand)" class="flex flex-wrap items-end gap-3">
                                    <div class="w-44">
                                        <AppInput label="Name *" v-model="editForm.name" :error="editForm.errors.name" required />
                                    </div>
                                    <div class="w-28">
                                        <AppInput label="Abbreviation *" v-model="editForm.abbreviation" :error="editForm.errors.abbreviation" required />
                                    </div>
                                    <div class="w-24">
                                        <AppInput label="Sort order" type="number" v-model="editForm.sort_order" :error="editForm.errors.sort_order" />
                                    </div>
                                    <div class="flex gap-2">
                                        <AppButton type="submit" variant="primary" size="sm" :disabled="editForm.processing">Save</AppButton>
                                        <AppButton type="button" variant="secondary" size="sm" @click="cancelEdit">Cancel</AppButton>
                                    </div>
                                </form>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </AuthenticatedLayout>
</template>
