<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AppButton from '@/Components/AppButton.vue';
import AppInput from '@/Components/AppInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

defineProps({
    brands: { type: Array, required: true },
});

// ── Create form ───────────────────────────────────────────────────────────────
const showAdd = ref(false);
const addForm = useForm({
    name: '',
    abbreviation: '',
    brand_color_hex: '',
    sort_order: '',
    logo: null,
});

function submitAdd() {
    addForm.post(route('super-admin.catalog.brands.store'), {
        forceFormData: true,
        onSuccess: () => {
            addForm.reset();
            showAdd.value = false;
        },
    });
}

function onAddLogo(e) {
    addForm.logo = e.target.files?.[0] ?? null;
}

// ── Inline edit ───────────────────────────────────────────────────────────────
const editingId = ref(null);
const editForm = useForm({
    name: '',
    abbreviation: '',
    brand_color_hex: '',
    sort_order: '',
    logo: null,
    logo_clear: false,
    _method: 'patch',
});

function startEdit(brand) {
    editingId.value = brand.id;
    editForm.name = brand.name;
    editForm.abbreviation = brand.abbreviation;
    editForm.brand_color_hex = brand.brand_color_hex ?? '';
    editForm.sort_order = brand.sort_order ?? '';
    editForm.logo = null;
    editForm.logo_clear = false;
}

function onEditLogo(e) {
    editForm.logo = e.target.files?.[0] ?? null;
    editForm.logo_clear = false;
}

function submitEdit(brand) {
    // Use POST + _method spoofing because file uploads require multipart/form-data,
    // which PHP only parses from POST bodies.
    editForm.post(route('super-admin.catalog.brands.update', brand.id), {
        forceFormData: true,
        onSuccess: () => {
            editingId.value = null;
        },
    });
}

function cancelEdit() {
    editingId.value = null;
}
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

        <!-- Toolbar -->
        <div class="mb-4 flex items-center justify-between">
            <p class="font-sans text-[13px] text-ink-secondary">
                {{ brands.length }} brand{{ brands.length !== 1 ? 's' : '' }}
            </p>
            <AppButton variant="primary" @click="showAdd = !showAdd">
                {{ showAdd ? 'Cancel' : '+ Add brand' }}
            </AppButton>
        </div>

        <!-- Create form -->
        <div v-if="showAdd" class="mb-6 rounded-lg border border-accent bg-accent-soft/30 p-5">
            <h2 class="mb-4 font-sans text-[15px] font-semibold text-ink-primary">New brand</h2>
            <form @submit.prevent="submitAdd">
                <div class="flex flex-wrap items-end gap-3">
                    <div class="w-52">
                        <AppInput label="Name *" v-model="addForm.name" placeholder="e.g. Sempertex" :error="addForm.errors.name" required />
                    </div>
                    <div class="w-32">
                        <AppInput label="Abbreviation *" v-model="addForm.abbreviation" placeholder="e.g. STX" :error="addForm.errors.abbreviation" required />
                    </div>
                    <div>
                        <label class="mb-1 block font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary">Brand color</label>
                        <div class="flex items-center gap-2">
                            <input type="color" v-model="addForm.brand_color_hex" class="h-[42px] w-10 cursor-pointer rounded border border-border-strong bg-surface" />
                            <input
                                type="text"
                                v-model="addForm.brand_color_hex"
                                placeholder="#000000"
                                maxlength="7"
                                class="w-28 rounded-md border border-border-strong bg-surface px-3 py-[10px] font-mono text-[13px] text-ink-primary focus:border-accent focus:outline-none focus:ring-[3px] focus:ring-accent-soft"
                            />
                        </div>
                        <p v-if="addForm.errors.brand_color_hex" class="mt-1 font-sans text-[13px] text-danger">{{ addForm.errors.brand_color_hex }}</p>
                    </div>
                    <div class="w-24">
                        <AppInput label="Sort order" type="number" v-model="addForm.sort_order" :error="addForm.errors.sort_order" />
                    </div>
                    <div>
                        <label class="mb-1 block font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary">Logo</label>
                        <input
                            type="file"
                            accept="image/png,image/jpeg,image/svg+xml"
                            @change="onAddLogo"
                            class="block w-full max-w-xs rounded-md border border-border-strong bg-surface px-2 py-1.5 font-sans text-[13px] text-ink-secondary file:mr-3 file:rounded-md file:border-0 file:bg-accent-soft file:px-3 file:py-1.5 file:font-sans file:text-[12px] file:font-medium file:text-accent"
                        />
                        <p class="mt-1 font-sans text-[11px] text-ink-tertiary">PNG / JPG / SVG, up to 1 MB.</p>
                        <p v-if="addForm.errors.logo" class="mt-1 font-sans text-[13px] text-danger">{{ addForm.errors.logo }}</p>
                    </div>
                    <AppButton type="submit" variant="primary" :disabled="addForm.processing">
                        {{ addForm.processing ? 'Saving…' : 'Add brand' }}
                    </AppButton>
                </div>
            </form>
        </div>

        <div class="overflow-hidden rounded-lg border border-border">
            <table class="w-full">
                <thead>
                    <tr class="border-b border-border bg-background">
                        <th class="w-16 px-4 py-2.5 text-left font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary">Logo</th>
                        <th class="px-4 py-2.5 text-left font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary">Brand</th>
                        <th class="px-4 py-2.5 text-left font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary">Abbrev.</th>
                        <th class="px-4 py-2.5 text-left font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary">Color</th>
                        <th class="px-4 py-2.5 text-right font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary">SKUs</th>
                        <th class="px-4 py-2.5 text-right font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary">Sort</th>
                        <th class="w-24 px-4 py-2.5"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    <template v-for="brand in brands" :key="brand.id">
                        <!-- View row -->
                        <tr v-if="editingId !== brand.id" class="group transition hover:bg-accent-soft/40">
                            <td class="px-4 py-2">
                                <img
                                    v-if="brand.logo_url"
                                    :src="brand.logo_url"
                                    :alt="`${brand.name} logo`"
                                    class="h-10 w-10 rounded-sm object-contain ring-1 ring-inset ring-border"
                                />
                                <div
                                    v-else
                                    class="flex h-10 w-10 items-center justify-center rounded-sm border border-dashed border-border bg-background font-sans text-[10px] uppercase tracking-eyebrow text-ink-tertiary"
                                >
                                    None
                                </div>
                            </td>
                            <td class="px-4 py-3 font-sans text-[14px] font-medium text-ink-primary">{{ brand.name }}</td>
                            <td class="px-4 py-3 font-mono text-[13px] text-ink-secondary">{{ brand.abbreviation }}</td>
                            <td class="px-4 py-3">
                                <div v-if="brand.brand_color_hex" class="flex items-center gap-2">
                                    <span
                                        class="h-4 w-4 shrink-0 rounded-sm ring-1 ring-inset ring-black/10"
                                        :style="{ backgroundColor: brand.brand_color_hex }"
                                    />
                                    <span class="font-mono text-[12px] text-ink-tertiary">{{ brand.brand_color_hex }}</span>
                                </div>
                                <span v-else class="font-sans text-[12px] text-ink-tertiary">—</span>
                            </td>
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
                            <td colspan="7" class="px-4 py-3">
                                <form @submit.prevent="submitEdit(brand)">
                                    <div class="flex flex-wrap items-end gap-3">
                                        <div class="w-44">
                                            <AppInput label="Name *" v-model="editForm.name" :error="editForm.errors.name" required />
                                        </div>
                                        <div class="w-28">
                                            <AppInput label="Abbreviation *" v-model="editForm.abbreviation" :error="editForm.errors.abbreviation" required />
                                        </div>
                                        <div>
                                            <label class="mb-1 block font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary">Brand color</label>
                                            <div class="flex items-center gap-2">
                                                <input type="color" v-model="editForm.brand_color_hex" class="h-[42px] w-10 cursor-pointer rounded border border-border-strong bg-surface" />
                                                <input
                                                    type="text"
                                                    v-model="editForm.brand_color_hex"
                                                    placeholder="#000000"
                                                    maxlength="7"
                                                    class="w-28 rounded-md border border-border-strong bg-surface px-3 py-[10px] font-mono text-[13px] text-ink-primary focus:border-accent focus:outline-none focus:ring-[3px] focus:ring-accent-soft"
                                                />
                                            </div>
                                            <p v-if="editForm.errors.brand_color_hex" class="mt-1 font-sans text-[13px] text-danger">{{ editForm.errors.brand_color_hex }}</p>
                                        </div>
                                        <div class="w-24">
                                            <AppInput label="Sort order" type="number" v-model="editForm.sort_order" :error="editForm.errors.sort_order" />
                                        </div>
                                        <div>
                                            <label class="mb-1 block font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary">
                                                Logo
                                                <span v-if="brand.logo_url" class="normal-case tracking-normal text-ink-tertiary"> (current shown)</span>
                                            </label>
                                            <div class="flex items-center gap-2">
                                                <img
                                                    v-if="brand.logo_url && !editForm.logo_clear"
                                                    :src="brand.logo_url"
                                                    class="h-10 w-10 shrink-0 rounded-sm object-contain ring-1 ring-inset ring-border"
                                                />
                                                <input
                                                    type="file"
                                                    accept="image/png,image/jpeg,image/svg+xml"
                                                    @change="onEditLogo"
                                                    class="block w-full max-w-xs rounded-md border border-border-strong bg-surface px-2 py-1.5 font-sans text-[13px] text-ink-secondary file:mr-3 file:rounded-md file:border-0 file:bg-accent-soft file:px-3 file:py-1.5 file:font-sans file:text-[12px] file:font-medium file:text-accent"
                                                />
                                            </div>
                                            <label v-if="brand.logo_url" class="mt-2 flex cursor-pointer items-center gap-2 font-sans text-[12px] text-ink-secondary">
                                                <input type="checkbox" v-model="editForm.logo_clear" class="h-3.5 w-3.5 accent-danger" />
                                                Remove current logo on save
                                            </label>
                                            <p v-if="editForm.errors.logo" class="mt-1 font-sans text-[13px] text-danger">{{ editForm.errors.logo }}</p>
                                        </div>
                                        <div class="flex gap-2">
                                            <AppButton type="submit" variant="primary" size="sm" :disabled="editForm.processing">
                                                {{ editForm.processing ? 'Saving…' : 'Save' }}
                                            </AppButton>
                                            <AppButton type="button" variant="secondary" size="sm" @click="cancelEdit">Cancel</AppButton>
                                        </div>
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
