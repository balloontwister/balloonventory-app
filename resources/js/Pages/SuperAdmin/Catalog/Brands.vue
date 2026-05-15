<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AppButton from '@/Components/AppButton.vue';
import AppInput from '@/Components/AppInput.vue';
import ImageGallery from '@/Components/ImageGallery.vue';
import ImageUpload from '@/Components/ImageUpload.vue';
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
    primary_color_hex: '',
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

// ── Inline edit ───────────────────────────────────────────────────────────────
const editingId = ref(null);
const editForm = useForm({
    name: '',
    abbreviation: '',
    primary_color_hex: '',
    sort_order: '',
    logo: null,
    logo_clear: false,
    _method: 'patch',
});

function startEdit(brand) {
    editingId.value = brand.id;
    editForm.name = brand.name;
    editForm.abbreviation = brand.abbreviation;
    editForm.primary_color_hex = brand.primary_color_hex ?? '';
    editForm.sort_order = brand.sort_order ?? '';
    editForm.logo = null;
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
    <Head :title="$t('catalog.brands.meta_title')" />

    <AuthenticatedLayout>
        <template #header>
            <h1
                class="font-display text-[22px] font-semibold tracking-h2 text-ink-primary"
            >
                {{ $t('catalog.heading') }}
            </h1>
        </template>

        <!-- Catalog nav tabs -->
        <div class="mb-6 flex gap-1 border-b border-border">
            <Link
                :href="route('super-admin.catalog.skus')"
                class="px-4 py-2.5 font-sans text-[14px] font-medium text-ink-secondary transition hover:text-ink-primary"
            >
                {{ $t('catalog.tabs.skus') }}
            </Link>
            <Link
                :href="route('super-admin.catalog.colors')"
                class="px-4 py-2.5 font-sans text-[14px] font-medium text-ink-secondary transition hover:text-ink-primary"
            >
                {{ $t('catalog.tabs.colors') }}
            </Link>
            <Link
                :href="route('super-admin.catalog.brands')"
                class="border-b-2 border-accent px-4 py-2.5 font-sans text-[14px] font-medium text-accent"
            >
                {{ $t('catalog.tabs.brands') }}
            </Link>
            <Link
                :href="route('super-admin.catalog.reference')"
                class="px-4 py-2.5 font-sans text-[14px] font-medium text-ink-secondary transition hover:text-ink-primary"
            >
                {{ $t('catalog.tabs.reference') }}
            </Link>
        </div>

        <!-- Toolbar -->
        <div class="mb-4 flex items-center justify-between">
            <p class="font-sans text-[13px] text-ink-secondary">
                {{
                    brands.length === 1
                        ? $t('catalog.brands.count_singular', {
                              count: brands.length,
                          })
                        : $t('catalog.brands.count_plural', {
                              count: brands.length,
                          })
                }}
            </p>
            <AppButton variant="primary" @click="showAdd = !showAdd">
                {{
                    showAdd
                        ? $t('catalog.actions.cancel')
                        : $t('catalog.brands.add_button')
                }}
            </AppButton>
        </div>

        <!-- Create form -->
        <div
            v-if="showAdd"
            class="bg-accent-soft/30 mb-6 rounded-lg border border-accent p-5"
        >
            <h2
                class="mb-4 font-sans text-[15px] font-semibold text-ink-primary"
            >
                {{ $t('catalog.brands.new_heading') }}
            </h2>
            <form @submit.prevent="submitAdd">
                <div class="flex flex-wrap items-end gap-3">
                    <div class="w-52">
                        <AppInput
                            :label="$t('catalog.brands.name_label')"
                            v-model="addForm.name"
                            :placeholder="$t('catalog.brands.name_placeholder')"
                            :error="addForm.errors.name"
                            required
                        />
                    </div>
                    <div class="w-32">
                        <AppInput
                            :label="$t('catalog.brands.abbreviation_label')"
                            v-model="addForm.abbreviation"
                            :placeholder="
                                $t('catalog.brands.abbreviation_placeholder')
                            "
                            :error="addForm.errors.abbreviation"
                            required
                        />
                    </div>
                    <div>
                        <label
                            class="mb-1 block font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary"
                        >
                            {{ $t('catalog.brands.color_label') }}
                        </label>
                        <div class="flex items-center gap-2">
                            <input
                                type="color"
                                v-model="addForm.primary_color_hex"
                                class="h-[42px] w-10 cursor-pointer rounded border border-border-strong bg-surface"
                            />
                            <input
                                type="text"
                                v-model="addForm.primary_color_hex"
                                placeholder="#000000"
                                maxlength="7"
                                class="w-28 rounded-md border border-border-strong bg-surface px-3 py-[10px] font-mono text-[13px] text-ink-primary focus:border-accent focus:outline-none focus:ring-[3px] focus:ring-accent-soft"
                            />
                        </div>
                        <p
                            v-if="addForm.errors.primary_color_hex"
                            class="mt-1 font-sans text-[13px] text-danger"
                        >
                            {{ addForm.errors.primary_color_hex }}
                        </p>
                    </div>
                    <div class="w-24">
                        <AppInput
                            :label="$t('catalog.brands.sort_order_label')"
                            type="number"
                            v-model="addForm.sort_order"
                            :error="addForm.errors.sort_order"
                        />
                    </div>
                    <div class="w-72">
                        <ImageUpload
                            :label="$t('catalog.brands.logo_label')"
                            v-model:file="addForm.logo"
                            :error="addForm.errors.logo"
                            :help-text="$t('catalog.brands.logo_help')"
                        />
                    </div>
                    <AppButton
                        type="submit"
                        variant="primary"
                        :disabled="addForm.processing"
                    >
                        {{
                            addForm.processing
                                ? $t('catalog.actions.saving')
                                : $t('catalog.brands.submit_add')
                        }}
                    </AppButton>
                </div>
            </form>
        </div>

        <div class="overflow-hidden rounded-lg border border-border">
            <table class="w-full">
                <thead>
                    <tr class="border-b border-border bg-background">
                        <th
                            class="w-16 px-4 py-2.5 text-left font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary"
                        >
                            {{ $t('catalog.brands.col_logo') }}
                        </th>
                        <th
                            class="px-4 py-2.5 text-left font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary"
                        >
                            {{ $t('catalog.brands.col_brand') }}
                        </th>
                        <th
                            class="px-4 py-2.5 text-left font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary"
                        >
                            {{ $t('catalog.brands.col_abbreviation') }}
                        </th>
                        <th
                            class="px-4 py-2.5 text-left font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary"
                        >
                            {{ $t('catalog.brands.col_color') }}
                        </th>
                        <th
                            class="px-4 py-2.5 text-right font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary"
                        >
                            {{ $t('catalog.brands.col_skus') }}
                        </th>
                        <th
                            class="px-4 py-2.5 text-right font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary"
                        >
                            {{ $t('catalog.brands.col_sort') }}
                        </th>
                        <th class="w-24 px-4 py-2.5"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    <template v-for="brand in brands" :key="brand.id">
                        <!-- View row -->
                        <tr
                            v-if="editingId !== brand.id"
                            class="hover:bg-accent-soft/40 group transition"
                        >
                            <td class="px-4 py-2">
                                <ImageGallery
                                    :urls="brand.logo_url"
                                    size="sm"
                                    :alt="`${brand.name} logo`"
                                />
                            </td>
                            <td
                                class="px-4 py-3 font-sans text-[14px] font-medium text-ink-primary"
                            >
                                {{ brand.name }}
                            </td>
                            <td
                                class="px-4 py-3 font-mono text-[13px] text-ink-secondary"
                            >
                                {{ brand.abbreviation }}
                            </td>
                            <td class="px-4 py-3">
                                <div
                                    v-if="brand.primary_color_hex"
                                    class="flex items-center gap-2"
                                >
                                    <span
                                        class="h-4 w-4 shrink-0 rounded-sm ring-1 ring-inset ring-black/10"
                                        :style="{
                                            backgroundColor:
                                                brand.primary_color_hex,
                                        }"
                                    />
                                    <span
                                        class="font-mono text-[12px] text-ink-tertiary"
                                        >{{ brand.primary_color_hex }}</span
                                    >
                                </div>
                                <span
                                    v-else
                                    class="font-sans text-[12px] text-ink-tertiary"
                                    >—</span
                                >
                            </td>
                            <td
                                class="px-4 py-3 text-right font-mono text-[13px] text-ink-secondary"
                            >
                                {{ brand.skus_count }}
                            </td>
                            <td
                                class="px-4 py-3 text-right font-mono text-[13px] text-ink-tertiary"
                            >
                                {{ brand.sort_order }}
                            </td>
                            <td class="px-4 py-3">
                                <div
                                    class="flex justify-end opacity-0 transition group-hover:opacity-100"
                                >
                                    <AppButton
                                        variant="ghost"
                                        size="sm"
                                        @click="startEdit(brand)"
                                    >
                                        {{ $t('catalog.actions.edit') }}
                                    </AppButton>
                                </div>
                            </td>
                        </tr>

                        <!-- Edit row -->
                        <tr v-else class="bg-accent-soft/20">
                            <td colspan="7" class="px-4 py-3">
                                <form @submit.prevent="submitEdit(brand)">
                                    <div class="flex flex-wrap items-end gap-3">
                                        <div class="w-44">
                                            <AppInput
                                                :label="
                                                    $t(
                                                        'catalog.brands.name_label',
                                                    )
                                                "
                                                v-model="editForm.name"
                                                :error="editForm.errors.name"
                                                required
                                            />
                                        </div>
                                        <div class="w-28">
                                            <AppInput
                                                :label="
                                                    $t(
                                                        'catalog.brands.abbreviation_label',
                                                    )
                                                "
                                                v-model="editForm.abbreviation"
                                                :error="
                                                    editForm.errors.abbreviation
                                                "
                                                required
                                            />
                                        </div>
                                        <div>
                                            <label
                                                class="mb-1 block font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary"
                                            >
                                                {{
                                                    $t(
                                                        'catalog.brands.color_label',
                                                    )
                                                }}
                                            </label>
                                            <div
                                                class="flex items-center gap-2"
                                            >
                                                <input
                                                    type="color"
                                                    v-model="
                                                        editForm.primary_color_hex
                                                    "
                                                    class="h-[42px] w-10 cursor-pointer rounded border border-border-strong bg-surface"
                                                />
                                                <input
                                                    type="text"
                                                    v-model="
                                                        editForm.primary_color_hex
                                                    "
                                                    placeholder="#000000"
                                                    maxlength="7"
                                                    class="w-28 rounded-md border border-border-strong bg-surface px-3 py-[10px] font-mono text-[13px] text-ink-primary focus:border-accent focus:outline-none focus:ring-[3px] focus:ring-accent-soft"
                                                />
                                            </div>
                                            <p
                                                v-if="
                                                    editForm.errors
                                                        .primary_color_hex
                                                "
                                                class="mt-1 font-sans text-[13px] text-danger"
                                            >
                                                {{
                                                    editForm.errors
                                                        .primary_color_hex
                                                }}
                                            </p>
                                        </div>
                                        <div class="w-24">
                                            <AppInput
                                                :label="
                                                    $t(
                                                        'catalog.brands.sort_order_label',
                                                    )
                                                "
                                                type="number"
                                                v-model="editForm.sort_order"
                                                :error="
                                                    editForm.errors.sort_order
                                                "
                                            />
                                        </div>
                                        <div class="w-72">
                                            <ImageUpload
                                                :label="
                                                    $t(
                                                        'catalog.brands.logo_label',
                                                    )
                                                "
                                                v-model:file="editForm.logo"
                                                v-model:clear="
                                                    editForm.logo_clear
                                                "
                                                :current-url="brand.logo_url"
                                                :error="editForm.errors.logo"
                                            />
                                        </div>
                                        <div class="flex gap-2">
                                            <AppButton
                                                type="submit"
                                                variant="primary"
                                                size="sm"
                                                :disabled="editForm.processing"
                                            >
                                                {{
                                                    editForm.processing
                                                        ? $t(
                                                              'catalog.actions.saving',
                                                          )
                                                        : $t(
                                                              'catalog.actions.save',
                                                          )
                                                }}
                                            </AppButton>
                                            <AppButton
                                                type="button"
                                                variant="secondary"
                                                size="sm"
                                                @click="cancelEdit"
                                            >
                                                {{
                                                    $t('catalog.actions.cancel')
                                                }}
                                            </AppButton>
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
