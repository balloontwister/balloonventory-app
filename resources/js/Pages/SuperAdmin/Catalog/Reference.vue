<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AppButton from '@/Components/AppButton.vue';
import AppInput from '@/Components/AppInput.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { ref } from 'vue';

const props = defineProps({
    sizes: { type: Array, required: true },
    shapes: { type: Array, required: true },
    textures: { type: Array, required: true },
    colorFamilies: { type: Array, required: true },
    themes: { type: Array, required: true },
    materials: { type: Array, required: true },
    textureFamilies: { type: Array, required: true },
});

const activeTab = ref('sizes');

const tabKeys = [
    'sizes',
    'shapes',
    'textures',
    'color-families',
    'themes',
    'materials',
];

// Map tab key → props key + fields
const tabConfig = {
    sizes: {
        items: () => props.sizes,
        fields: ['name', 'size_category', 'sort_order'],
    },
    shapes: { items: () => props.shapes, fields: ['name', 'sort_order'] },
    textures: {
        items: () => props.textures,
        fields: ['name', 'texture_family_id', 'sort_order'],
    },
    'color-families': {
        items: () => props.colorFamilies,
        fields: ['name', 'fallback_color_hex', 'sort_order'],
    },
    themes: { items: () => props.themes, fields: ['name', 'sort_order'] },
    materials: { items: () => props.materials, fields: ['name', 'sort_order'] },
};

const sizeCategories = [
    'small',
    'medium',
    'large',
    'giant',
    'small_modeling',
    'large_modeling',
];

// ── Add form ──────────────────────────────────────────────────────────────────
const showAdd = ref(false);
const addForm = useForm({
    name: '',
    size_category: '',
    texture_family_id: '',
    fallback_color_hex: '',
    sort_order: '',
});

function submitAdd() {
    addForm.post(
        route('super-admin.catalog.reference.store', activeTab.value),
        {
            onSuccess: () => {
                addForm.reset();
                showAdd.value = false;
            },
        },
    );
}

// ── Inline edit ───────────────────────────────────────────────────────────────
const editingId = ref(null);
const editForm = useForm({
    name: '',
    size_category: '',
    texture_family_id: '',
    fallback_color_hex: '',
    sort_order: '',
});

function startEdit(item) {
    editingId.value = item.id;
    editForm.name = item.name;
    editForm.size_category = item.size_category ?? '';
    editForm.texture_family_id = item.texture_family_id ?? '';
    editForm.fallback_color_hex = item.fallback_color_hex ?? '';
    editForm.sort_order = item.sort_order ?? '';
}

function submitEdit(item) {
    editForm.patch(
        route('super-admin.catalog.reference.update', {
            table: activeTab.value,
            item: item.id,
        }),
        {
            onSuccess: () => {
                editingId.value = null;
            },
        },
    );
}

function cancelEdit() {
    editingId.value = null;
}

function destroy(item) {
    if (
        !confirm(trans('catalog.reference.delete_confirm', { name: item.name }))
    )
        return;
    router.delete(
        route('super-admin.catalog.reference.destroy', {
            table: activeTab.value,
            item: item.id,
        }),
        { preserveScroll: true },
    );
}

function switchTab(key) {
    activeTab.value = key;
    editingId.value = null;
    showAdd.value = false;
    addForm.reset();
}

const selectClass =
    'w-full rounded-md border border-border-strong bg-surface px-3 py-[10px] font-sans text-[14px] text-ink-primary focus:border-accent focus:outline-none focus:ring-[3px] focus:ring-accent-soft';
</script>

<template>
    <Head :title="$t('catalog.reference.meta_title')" />

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
                class="px-4 py-2.5 font-sans text-[14px] font-medium text-ink-secondary transition hover:text-ink-primary"
            >
                {{ $t('catalog.tabs.brands') }}
            </Link>
            <Link
                :href="route('super-admin.catalog.reference')"
                class="border-b-2 border-accent px-4 py-2.5 font-sans text-[14px] font-medium text-accent"
            >
                {{ $t('catalog.tabs.reference') }}
            </Link>
        </div>

        <!-- Inner sub-tabs -->
        <div class="mb-4 flex flex-wrap gap-1">
            <button
                v-for="key in tabKeys"
                :key="key"
                type="button"
                class="rounded-md px-3 py-1.5 font-sans text-[13px] font-medium transition"
                :class="
                    activeTab === key
                        ? 'bg-accent text-accent-on'
                        : 'bg-background text-ink-secondary hover:bg-border'
                "
                @click="switchTab(key)"
            >
                {{ $t(`catalog.reference.tabs.${key}`) }}
                <span class="ml-1 font-mono text-[11px] opacity-70">{{
                    tabConfig[key].items().length
                }}</span>
            </button>
        </div>

        <!-- Add button + form -->
        <div class="mb-4 flex items-center justify-end">
            <AppButton variant="primary" @click="showAdd = !showAdd">
                {{
                    showAdd
                        ? $t('catalog.actions.cancel')
                        : $t('catalog.reference.add_button')
                }}
            </AppButton>
        </div>

        <div
            v-if="showAdd"
            class="bg-accent-soft/30 mb-5 rounded-lg border border-accent p-4"
        >
            <form
                @submit.prevent="submitAdd"
                class="flex flex-wrap items-end gap-3"
            >
                <div class="w-52">
                    <AppInput
                        :label="$t('catalog.reference.name_label')"
                        v-model="addForm.name"
                        :error="addForm.errors.name"
                        required
                    />
                </div>

                <div v-if="activeTab === 'sizes'" class="w-44">
                    <label
                        class="mb-1 block font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary"
                    >
                        {{ $t('catalog.reference.category_label') }}
                    </label>
                    <select
                        v-model="addForm.size_category"
                        required
                        :class="selectClass"
                    >
                        <option value="">
                            {{ $t('catalog.reference.select_placeholder') }}
                        </option>
                        <option v-for="c in sizeCategories" :key="c" :value="c">
                            {{ c }}
                        </option>
                    </select>
                </div>

                <div v-if="activeTab === 'textures'" class="w-44">
                    <label
                        class="mb-1 block font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary"
                    >
                        {{ $t('catalog.reference.family_label') }}
                    </label>
                    <select
                        v-model="addForm.texture_family_id"
                        required
                        :class="selectClass"
                    >
                        <option value="">
                            {{ $t('catalog.reference.select_placeholder') }}
                        </option>
                        <option
                            v-for="f in textureFamilies"
                            :key="f.id"
                            :value="f.id"
                        >
                            {{ f.name }}
                        </option>
                    </select>
                    <p
                        v-if="addForm.errors.texture_family_id"
                        class="mt-1 font-sans text-[13px] text-danger"
                    >
                        {{ addForm.errors.texture_family_id }}
                    </p>
                </div>

                <div
                    v-if="activeTab === 'color-families'"
                    class="flex items-end gap-2"
                >
                    <input
                        type="color"
                        v-model="addForm.fallback_color_hex"
                        class="h-[42px] w-10 cursor-pointer rounded border border-border-strong bg-surface"
                    />
                    <div class="w-28">
                        <AppInput
                            :label="$t('catalog.reference.hex_label')"
                            v-model="addForm.fallback_color_hex"
                            placeholder="#000000"
                            :error="addForm.errors.fallback_color_hex"
                        />
                    </div>
                </div>

                <div class="w-24">
                    <AppInput
                        :label="$t('catalog.reference.sort_order_label')"
                        type="number"
                        v-model="addForm.sort_order"
                    />
                </div>

                <AppButton
                    type="submit"
                    variant="primary"
                    :disabled="addForm.processing"
                >
                    {{ $t('catalog.actions.add') }}
                </AppButton>
            </form>
        </div>

        <!-- Items table -->
        <div class="overflow-hidden rounded-lg border border-border">
            <table class="w-full">
                <thead>
                    <tr class="border-b border-border bg-background">
                        <th
                            class="px-4 py-2.5 text-left font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary"
                        >
                            {{ $t('catalog.reference.col_name') }}
                        </th>
                        <th
                            v-if="activeTab === 'sizes'"
                            class="px-4 py-2.5 text-left font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary"
                        >
                            {{ $t('catalog.reference.col_category') }}
                        </th>
                        <th
                            v-if="activeTab === 'textures'"
                            class="px-4 py-2.5 text-left font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary"
                        >
                            {{ $t('catalog.reference.col_family') }}
                        </th>
                        <th
                            v-if="activeTab === 'color-families'"
                            class="px-4 py-2.5 text-left font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary"
                        >
                            {{ $t('catalog.reference.col_hex') }}
                        </th>
                        <th
                            class="px-4 py-2.5 text-right font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary"
                        >
                            {{ $t('catalog.reference.col_sort') }}
                        </th>
                        <th class="w-28 px-4 py-2.5"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    <tr v-if="tabConfig[activeTab].items().length === 0">
                        <td
                            colspan="6"
                            class="px-4 py-8 text-center font-sans text-[14px] text-ink-tertiary"
                        >
                            {{ $t('catalog.reference.empty') }}
                        </td>
                    </tr>
                    <template
                        v-for="item in tabConfig[activeTab].items()"
                        :key="item.id"
                    >
                        <!-- View row -->
                        <tr
                            v-if="editingId !== item.id"
                            class="hover:bg-accent-soft/40 group transition"
                        >
                            <td
                                class="px-4 py-3 font-sans text-[14px] text-ink-primary"
                            >
                                {{ item.name }}
                            </td>
                            <td
                                v-if="activeTab === 'sizes'"
                                class="px-4 py-3 font-sans text-[13px] text-ink-secondary"
                            >
                                {{ item.size_category }}
                            </td>
                            <td
                                v-if="activeTab === 'textures'"
                                class="px-4 py-3 font-sans text-[13px] text-ink-secondary"
                            >
                                {{ item.texture_family?.name ?? '—' }}
                            </td>
                            <td
                                v-if="activeTab === 'color-families'"
                                class="px-4 py-3"
                            >
                                <div class="flex items-center gap-2">
                                    <span
                                        v-if="item.fallback_color_hex"
                                        class="h-4 w-4 shrink-0 rounded-sm ring-1 ring-inset ring-black/10"
                                        :style="{
                                            backgroundColor:
                                                item.fallback_color_hex,
                                        }"
                                    />
                                    <span
                                        class="font-mono text-[12px] text-ink-tertiary"
                                        >{{
                                            item.fallback_color_hex ?? '—'
                                        }}</span
                                    >
                                </div>
                            </td>
                            <td
                                class="px-4 py-3 text-right font-mono text-[13px] text-ink-tertiary"
                            >
                                {{ item.sort_order }}
                            </td>
                            <td class="px-4 py-3">
                                <div
                                    class="flex justify-end gap-1 opacity-0 transition group-hover:opacity-100"
                                >
                                    <AppButton
                                        variant="ghost"
                                        size="sm"
                                        @click="startEdit(item)"
                                    >
                                        {{ $t('catalog.actions.edit') }}
                                    </AppButton>
                                    <AppButton
                                        variant="ghost"
                                        size="sm"
                                        class="text-danger hover:bg-danger-soft"
                                        @click="destroy(item)"
                                    >
                                        {{ $t('catalog.actions.delete') }}
                                    </AppButton>
                                </div>
                            </td>
                        </tr>

                        <!-- Edit row -->
                        <tr v-else class="bg-accent-soft/20">
                            <td colspan="6" class="px-4 py-3">
                                <form
                                    @submit.prevent="submitEdit(item)"
                                    class="flex flex-wrap items-end gap-3"
                                >
                                    <div class="w-52">
                                        <AppInput
                                            :label="
                                                $t(
                                                    'catalog.reference.name_label',
                                                )
                                            "
                                            v-model="editForm.name"
                                            :error="editForm.errors.name"
                                            required
                                        />
                                    </div>
                                    <div
                                        v-if="activeTab === 'sizes'"
                                        class="w-44"
                                    >
                                        <label
                                            class="mb-1 block font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary"
                                        >
                                            {{
                                                $t(
                                                    'catalog.reference.category_label',
                                                )
                                            }}
                                        </label>
                                        <select
                                            v-model="editForm.size_category"
                                            required
                                            :class="selectClass"
                                        >
                                            <option
                                                v-for="c in sizeCategories"
                                                :key="c"
                                                :value="c"
                                            >
                                                {{ c }}
                                            </option>
                                        </select>
                                    </div>
                                    <div
                                        v-if="activeTab === 'textures'"
                                        class="w-44"
                                    >
                                        <label
                                            class="mb-1 block font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary"
                                        >
                                            {{
                                                $t(
                                                    'catalog.reference.family_label',
                                                )
                                            }}
                                        </label>
                                        <select
                                            v-model="editForm.texture_family_id"
                                            required
                                            :class="selectClass"
                                        >
                                            <option value="">
                                                {{
                                                    $t(
                                                        'catalog.reference.select_placeholder',
                                                    )
                                                }}
                                            </option>
                                            <option
                                                v-for="f in textureFamilies"
                                                :key="f.id"
                                                :value="f.id"
                                            >
                                                {{ f.name }}
                                            </option>
                                        </select>
                                        <p
                                            v-if="
                                                editForm.errors.texture_family_id
                                            "
                                            class="mt-1 font-sans text-[13px] text-danger"
                                        >
                                            {{
                                                editForm.errors.texture_family_id
                                            }}
                                        </p>
                                    </div>
                                    <div
                                        v-if="activeTab === 'color-families'"
                                        class="flex items-end gap-2"
                                    >
                                        <input
                                            type="color"
                                            v-model="
                                                editForm.fallback_color_hex
                                            "
                                            class="h-[42px] w-10 cursor-pointer rounded border border-border-strong bg-surface"
                                        />
                                        <div class="w-28">
                                            <AppInput
                                                :label="
                                                    $t(
                                                        'catalog.reference.hex_label',
                                                    )
                                                "
                                                v-model="
                                                    editForm.fallback_color_hex
                                                "
                                                placeholder="#000000"
                                                :error="
                                                    editForm.errors
                                                        .fallback_color_hex
                                                "
                                            />
                                        </div>
                                    </div>
                                    <div class="w-24">
                                        <AppInput
                                            :label="
                                                $t(
                                                    'catalog.reference.sort_order_label',
                                                )
                                            "
                                            type="number"
                                            v-model="editForm.sort_order"
                                        />
                                    </div>
                                    <div class="flex gap-2">
                                        <AppButton
                                            type="submit"
                                            variant="primary"
                                            size="sm"
                                            :disabled="editForm.processing"
                                        >
                                            {{ $t('catalog.actions.save') }}
                                        </AppButton>
                                        <AppButton
                                            type="button"
                                            variant="secondary"
                                            size="sm"
                                            @click="cancelEdit"
                                        >
                                            {{ $t('catalog.actions.cancel') }}
                                        </AppButton>
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
