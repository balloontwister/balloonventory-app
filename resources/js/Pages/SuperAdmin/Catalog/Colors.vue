<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AppButton from '@/Components/AppButton.vue';
import AppInput from '@/Components/AppInput.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { ref, computed } from 'vue';

const props = defineProps({
    colorFamilies: { type: Array, required: true },
    brands: { type: Array, required: true },
});

// ── Add form ──────────────────────────────────────────────────────────────────
const addForm = useForm({
    name: '',
    color_family_id: '',
    brand_id: '',
    color_hex: '',
    sort_order: '',
    description: '',
});

function submitAdd() {
    addForm.post(route('super-admin.catalog.colors.store'), {
        onSuccess: () => {
            addForm.reset();
            showAddForm.value = false;
        },
    });
}

const showAddForm = ref(false);

// ── Inline edit ───────────────────────────────────────────────────────────────
const editingId = ref(null);
const editForm = useForm({
    name: '',
    color_family_id: '',
    brand_id: '',
    color_hex: '',
    sort_order: '',
    description: '',
});

function startEdit(color) {
    editingId.value = color.id;
    editForm.name = color.name;
    editForm.color_family_id = color.color_family_id;
    editForm.brand_id = color.brand_id ?? '';
    editForm.color_hex = color.color_hex ?? '';
    editForm.sort_order = color.sort_order ?? '';
    editForm.description = color.description ?? '';
}

function submitEdit(color) {
    editForm.patch(route('super-admin.catalog.colors.update', color.id), {
        onSuccess: () => {
            editingId.value = null;
        },
    });
}

function cancelEdit() {
    editingId.value = null;
}

function destroy(color) {
    if (!confirm(trans('catalog.colors.delete_confirm', { name: color.name })))
        return;
    router.delete(route('super-admin.catalog.colors.destroy', color.id), {
        preserveScroll: true,
    });
}

// ── Helpers ───────────────────────────────────────────────────────────────────
const allColors = computed(() =>
    props.colorFamilies.flatMap((f) => f.colors ?? []),
);
const totalColors = computed(() => allColors.value.length);

function brandAbbr(brandId) {
    if (!brandId) return null;
    return props.brands.find((b) => b.id === brandId)?.abbreviation ?? null;
}

const selectClass =
    'w-full rounded-md border border-border-strong bg-surface px-3 py-[10px] font-sans text-[14px] text-ink-primary focus:border-accent focus:outline-none focus:ring-[3px] focus:ring-accent-soft';
</script>

<template>
    <Head :title="$t('catalog.colors.meta_title')" />

    <AuthenticatedLayout>
        <template #header>
            <h1
                class="font-display text-[22px] font-semibold tracking-h2 text-ink-primary"
            >
                {{ $t('catalog.heading') }}
            </h1>
        </template>

        <!-- Catalog nav tabs (shared) -->
        <div class="mb-6 flex gap-1 border-b border-border">
            <Link
                :href="route('super-admin.catalog.skus')"
                class="px-4 py-2.5 font-sans text-[14px] font-medium text-ink-secondary transition hover:text-ink-primary"
            >
                {{ $t('catalog.tabs.skus') }}
            </Link>
            <Link
                :href="route('super-admin.catalog.colors')"
                class="border-b-2 border-accent px-4 py-2.5 font-sans text-[14px] font-medium text-accent"
            >
                {{ $t('catalog.tabs.colors') }}
            </Link>
            <Link
                :href="route('super-admin.catalog.brands')"
                class="px-4 py-2.5 font-sans text-[14px] font-medium text-ink-secondary transition hover:text-ink-primary"
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
                    totalColors === 1
                        ? $t('catalog.colors.count_singular', {
                              count: totalColors,
                              families: colorFamilies.length,
                          })
                        : $t('catalog.colors.count_plural', {
                              count: totalColors,
                              families: colorFamilies.length,
                          })
                }}
            </p>
            <AppButton variant="primary" @click="showAddForm = !showAddForm">
                {{
                    showAddForm
                        ? $t('catalog.actions.cancel')
                        : $t('catalog.colors.add_button')
                }}
            </AppButton>
        </div>

        <!-- Add form -->
        <div
            v-if="showAddForm"
            class="bg-accent-soft/30 mb-6 rounded-lg border border-accent p-5"
        >
            <h2
                class="mb-4 font-sans text-[15px] font-semibold text-ink-primary"
            >
                {{ $t('catalog.colors.new_heading') }}
            </h2>
            <form @submit.prevent="submitAdd">
                <div
                    class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6"
                >
                    <div class="sm:col-span-2">
                        <AppInput
                            :label="$t('catalog.colors.name_label')"
                            v-model="addForm.name"
                            :placeholder="$t('catalog.colors.name_placeholder')"
                            :error="addForm.errors.name"
                            required
                        />
                    </div>
                    <div>
                        <label
                            class="mb-1 block font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary"
                        >
                            {{ $t('catalog.colors.family_label') }}
                        </label>
                        <select
                            v-model="addForm.color_family_id"
                            required
                            :class="selectClass"
                        >
                            <option value="">
                                {{ $t('catalog.colors.select_placeholder') }}
                            </option>
                            <option
                                v-for="f in colorFamilies"
                                :key="f.id"
                                :value="f.id"
                            >
                                {{ f.name }}
                            </option>
                        </select>
                        <p
                            v-if="addForm.errors.color_family_id"
                            class="mt-1 font-sans text-[13px] text-danger"
                        >
                            {{ addForm.errors.color_family_id }}
                        </p>
                    </div>
                    <div>
                        <label
                            class="mb-1 block font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary"
                        >
                            {{ $t('catalog.colors.brand_label') }}
                        </label>
                        <select v-model="addForm.brand_id" :class="selectClass">
                            <option value="">
                                {{ $t('catalog.colors.brand_generic') }}
                            </option>
                            <option
                                v-for="b in brands"
                                :key="b.id"
                                :value="b.id"
                            >
                                {{ b.abbreviation }}
                            </option>
                        </select>
                    </div>
                    <div>
                        <label
                            class="mb-1 block font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary"
                        >
                            {{ $t('catalog.colors.hex_label') }}
                        </label>
                        <div class="flex items-center gap-2">
                            <input
                                type="color"
                                v-model="addForm.color_hex"
                                class="h-[42px] w-10 cursor-pointer rounded border border-border-strong bg-surface"
                            />
                            <input
                                type="text"
                                v-model="addForm.color_hex"
                                placeholder="#000000"
                                maxlength="7"
                                class="w-full rounded-md border border-border-strong bg-surface px-3 py-[10px] font-mono text-[13px] text-ink-primary focus:border-accent focus:outline-none focus:ring-[3px] focus:ring-accent-soft"
                            />
                        </div>
                        <p
                            v-if="addForm.errors.color_hex"
                            class="mt-1 font-sans text-[13px] text-danger"
                        >
                            {{ addForm.errors.color_hex }}
                        </p>
                    </div>
                    <div class="flex items-end">
                        <AppButton
                            type="submit"
                            variant="primary"
                            :disabled="addForm.processing"
                            class="w-full justify-center"
                        >
                            {{
                                addForm.processing
                                    ? $t('catalog.actions.saving')
                                    : $t('catalog.colors.submit_add')
                            }}
                        </AppButton>
                    </div>
                </div>
            </form>
        </div>

        <!-- Color families + colors -->
        <div class="flex flex-col gap-6">
            <div
                v-for="family in colorFamilies"
                :key="family.id"
                class="overflow-hidden rounded-lg border border-border"
            >
                <!-- Family header -->
                <div
                    class="flex items-center gap-3 border-b border-border bg-background px-4 py-2.5"
                >
                    <span
                        v-if="family.color_hex"
                        class="h-4 w-4 shrink-0 rounded-sm ring-1 ring-inset ring-black/10"
                        :style="{ backgroundColor: family.color_hex }"
                    />
                    <span
                        class="font-sans text-[13px] font-semibold text-ink-primary"
                        >{{ family.name }}</span
                    >
                    <span class="font-sans text-[13px] text-ink-tertiary">{{
                        (family.colors ?? []).length
                    }}</span>
                </div>

                <!-- Colors table -->
                <table v-if="(family.colors ?? []).length" class="w-full">
                    <tbody class="divide-y divide-border">
                        <template
                            v-for="color in family.colors"
                            :key="color.id"
                        >
                            <!-- View row -->
                            <tr
                                v-if="editingId !== color.id"
                                class="hover:bg-accent-soft/30 group transition"
                            >
                                <td class="w-10 px-4 py-2.5">
                                    <span
                                        v-if="color.color_hex"
                                        class="inline-block h-5 w-5 rounded-sm ring-1 ring-inset ring-black/10"
                                        :style="{
                                            backgroundColor: color.color_hex,
                                        }"
                                    />
                                    <span
                                        v-else
                                        class="inline-block h-5 w-5 rounded-sm border border-border bg-background"
                                    />
                                </td>
                                <td
                                    class="px-3 py-2.5 font-sans text-[14px] text-ink-primary"
                                >
                                    {{ color.name }}
                                </td>
                                <td class="px-3 py-2.5">
                                    <span
                                        v-if="brandAbbr(color.brand_id)"
                                        class="font-mono text-[12px] text-ink-secondary"
                                        >{{ brandAbbr(color.brand_id) }}</span
                                    >
                                    <span
                                        v-else
                                        class="font-sans text-[12px] text-ink-tertiary"
                                    >
                                        {{ $t('catalog.colors.brand_generic') }}
                                    </span>
                                </td>
                                <td
                                    class="px-3 py-2.5 font-mono text-[12px] text-ink-tertiary"
                                >
                                    {{ color.color_hex ?? '—' }}
                                </td>
                                <td class="px-4 py-2.5">
                                    <div
                                        class="flex items-center justify-end gap-1 opacity-0 transition group-hover:opacity-100"
                                    >
                                        <AppButton
                                            variant="ghost"
                                            size="sm"
                                            @click="startEdit(color)"
                                        >
                                            {{ $t('catalog.actions.edit') }}
                                        </AppButton>
                                        <AppButton
                                            variant="ghost"
                                            size="sm"
                                            class="text-danger hover:bg-danger-soft"
                                            @click="destroy(color)"
                                        >
                                            {{ $t('catalog.actions.delete') }}
                                        </AppButton>
                                    </div>
                                </td>
                            </tr>

                            <!-- Edit row -->
                            <tr v-else class="bg-accent-soft/20">
                                <td colspan="5" class="px-4 py-3">
                                    <form @submit.prevent="submitEdit(color)">
                                        <div
                                            class="flex flex-wrap items-end gap-3"
                                        >
                                            <div class="w-44">
                                                <AppInput
                                                    :label="
                                                        $t(
                                                            'catalog.colors.edit_name_label',
                                                        )
                                                    "
                                                    v-model="editForm.name"
                                                    :error="
                                                        editForm.errors.name
                                                    "
                                                    required
                                                />
                                            </div>
                                            <div class="w-36">
                                                <label
                                                    class="mb-1 block font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary"
                                                >
                                                    {{
                                                        $t(
                                                            'catalog.colors.family_label',
                                                        )
                                                    }}
                                                </label>
                                                <select
                                                    v-model="
                                                        editForm.color_family_id
                                                    "
                                                    required
                                                    :class="selectClass"
                                                >
                                                    <option
                                                        v-for="f in colorFamilies"
                                                        :key="f.id"
                                                        :value="f.id"
                                                    >
                                                        {{ f.name }}
                                                    </option>
                                                </select>
                                            </div>
                                            <div class="w-28">
                                                <label
                                                    class="mb-1 block font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary"
                                                >
                                                    {{
                                                        $t(
                                                            'catalog.colors.brand_label',
                                                        )
                                                    }}
                                                </label>
                                                <select
                                                    v-model="editForm.brand_id"
                                                    :class="selectClass"
                                                >
                                                    <option value="">
                                                        {{
                                                            $t(
                                                                'catalog.colors.brand_generic',
                                                            )
                                                        }}
                                                    </option>
                                                    <option
                                                        v-for="b in brands"
                                                        :key="b.id"
                                                        :value="b.id"
                                                    >
                                                        {{ b.abbreviation }}
                                                    </option>
                                                </select>
                                            </div>
                                            <div class="flex items-end gap-2">
                                                <input
                                                    type="color"
                                                    v-model="editForm.color_hex"
                                                    class="h-[42px] w-10 cursor-pointer rounded border border-border-strong bg-surface"
                                                />
                                                <div class="w-28">
                                                    <AppInput
                                                        :label="
                                                            $t(
                                                                'catalog.colors.hex_short_label',
                                                            )
                                                        "
                                                        v-model="
                                                            editForm.color_hex
                                                        "
                                                        placeholder="#000000"
                                                        :error="
                                                            editForm.errors
                                                                .color_hex
                                                        "
                                                    />
                                                </div>
                                            </div>
                                            <div class="flex gap-2">
                                                <AppButton
                                                    type="submit"
                                                    variant="primary"
                                                    size="sm"
                                                    :disabled="
                                                        editForm.processing
                                                    "
                                                >
                                                    {{
                                                        $t('catalog.actions.save')
                                                    }}
                                                </AppButton>
                                                <AppButton
                                                    type="button"
                                                    variant="secondary"
                                                    size="sm"
                                                    @click="cancelEdit"
                                                >
                                                    {{
                                                        $t(
                                                            'catalog.actions.cancel',
                                                        )
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

                <p
                    v-else
                    class="px-4 py-3 font-sans text-[13px] text-ink-tertiary"
                >
                    {{ $t('catalog.colors.empty_family') }}
                </p>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
