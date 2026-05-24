<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AppButton from '@/Components/AppButton.vue';
import BackLink from '@/Components/BackLink.vue';
import StockBadge from '@/Components/StockBadge.vue';
import FavoriteStar from '@/Components/FavoriteStar.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { computed } from 'vue';

const props = defineProps({
    sku: { type: Object, required: true },
    override: { type: Object, default: null },
    stockLevels: { type: Array, required: true },
    recentMovements: { type: Array, required: true },
    favoritesListId: { type: String, default: null },
    isFavorite: { type: Boolean, default: false },
    reorderQuantity: { type: [Number, String], default: null },
});

const displayName = computed(() => props.override?.custom_name || props.sku.name);

const totalFullBags = computed(() => props.stockLevels.reduce((sum, l) => sum + (l.full_bags ?? 0), 0));
const totalOpenBags = computed(() => props.stockLevels.reduce((sum, l) => sum + (l.open_bags ?? 0), 0));

const overrideForm = useForm({
    custom_name: props.override?.custom_name ?? '',
    custom_color_hex: props.override?.custom_color_hex ?? '',
    notes: props.override?.notes ?? '',
});

function saveOverride() {
    overrideForm.patch(route('inventory.override.update', props.sku.id), {
        preserveScroll: true,
    });
}

function directionLabel(direction) {
    const map = {
        in: trans('inventory.show.history_direction_in'),
        out: trans('inventory.show.history_direction_out'),
        removed: trans('inventory.show.history_direction_removed'),
        restored: trans('inventory.show.history_direction_restored'),
        adjusted: trans('inventory.show.history_direction_adjusted'),
    };
    return map[direction] ?? direction;
}

function movementSummary(movement) {
    const full = movement.full_bags_change ?? 0;
    const open = movement.open_bags_change ?? 0;
    if (full === 0 && open === 0) return '—';
    const parts = [];
    if (full !== 0) parts.push(`${full > 0 ? '+' : ''}${full} bags`);
    if (open !== 0) parts.push(`${open > 0 ? '+' : ''}${open} open`);
    return parts.join(', ');
}

function formatDate(value) {
    if (!value) return '—';
    return new Date(value).toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });
}

</script>

<template>
    <Head :title="displayName" />

    <AuthenticatedLayout>
        <template #header>
            <BackLink :href="route('inventory.index')" :label="$t('inventory.show.back')" />
        </template>

        <div class="mx-auto max-w-2xl space-y-8">

            <!-- SKU header -->
            <div class="flex items-start gap-3">
                <span
                    v-if="sku.color?.color_hex"
                    class="mt-1 inline-block h-5 w-5 shrink-0 rounded ring-1 ring-inset ring-black/10"
                    :style="{ backgroundColor: sku.color.color_hex }"
                />
                <div class="min-w-0 flex-1">
                    <h1 class="font-display text-[22px] font-semibold text-ink-primary">
                        {{ displayName }}
                    </h1>
                    <p v-if="override?.custom_name" class="mt-0.5 font-sans text-[13px] text-ink-tertiary">
                        Catalog name: {{ sku.name }}
                    </p>
                    <div class="mt-1 flex flex-wrap gap-x-4 gap-y-1 font-sans text-[13px] text-ink-secondary">
                        <span v-if="sku.brand">{{ sku.brand.name }}</span>
                        <span v-if="sku.balloon_size">{{ sku.balloon_size.name }}</span>
                        <span v-if="sku.color">{{ sku.color.name }}</span>
                        <span v-if="sku.material">{{ sku.material.name }}</span>
                    </div>
                </div>
                <FavoriteStar
                    v-if="favoritesListId"
                    :sku-id="sku.id"
                    :is-favorite="isFavorite"
                    :favorite-list-id="favoritesListId"
                />
            </div>

            <!-- Stock section -->
            <section>
                <h2 class="mb-3 font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary">
                    {{ $t('inventory.show.section_stock') }}
                </h2>
                <div class="flex items-center gap-4">
                    <StockBadge :full-bags="totalFullBags" :open-bags="totalOpenBags" />
                    <span class="font-sans text-[13px] text-ink-secondary">
                        across {{ stockLevels.length === 1 ? '1 bin' : `${stockLevels.length} bins` }}
                    </span>
                </div>

                <!-- Per-bin breakdown (if multiple) -->
                <div v-if="stockLevels.length > 1" class="mt-3 space-y-1">
                    <div
                        v-for="level in stockLevels"
                        :key="level.id"
                        class="flex items-center justify-between rounded-md border border-border px-3 py-2"
                    >
                        <span class="font-sans text-[13px] text-ink-secondary">
                            {{ level.bin?.location?.name ?? 'Default' }}
                            <span class="mx-1 text-ink-tertiary">/</span>
                            {{ level.bin?.name ?? 'Default' }}
                        </span>
                        <StockBadge :full-bags="level.full_bags ?? 0" :open-bags="level.open_bags ?? 0" />
                    </div>
                </div>

                <!-- Reorder quantity from Favorites -->
                <div v-if="reorderQuantity !== null" class="mt-3 flex items-center gap-2">
                    <span class="font-sans text-[13px] text-ink-secondary">{{ $t('inventory.show.reorder_label') }}:</span>
                    <span class="font-mono text-[13px] font-medium text-ink-primary">{{ reorderQuantity }}</span>
                    <span class="font-sans text-[12px] text-ink-tertiary">— {{ $t('inventory.show.reorder_hint') }}</span>
                </div>
            </section>

            <!-- Override / customizations section -->
            <section>
                <h2 class="mb-3 font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary">
                    {{ $t('inventory.show.section_override') }}
                </h2>

                <form @submit.prevent="saveOverride" class="flex flex-col gap-4">
                    <!-- Custom name -->
                    <div class="flex flex-col gap-1">
                        <label
                            for="custom-name"
                            class="font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary"
                        >
                            {{ $t('inventory.show.override_custom_name_label') }}
                        </label>
                        <input
                            id="custom-name"
                            v-model="overrideForm.custom_name"
                            type="text"
                            :placeholder="$t('inventory.show.override_custom_name_placeholder')"
                            class="rounded-md border border-border-strong bg-surface px-3 py-2 font-sans text-[14px] text-ink-primary placeholder-ink-tertiary focus:border-accent focus:outline-none focus:ring-[3px] focus:ring-accent-soft"
                            :class="{ 'border-danger focus:border-danger focus:ring-danger-soft': overrideForm.errors.custom_name }"
                        />
                        <p v-if="overrideForm.errors.custom_name" class="font-sans text-[13px] text-danger">
                            {{ overrideForm.errors.custom_name }}
                        </p>
                    </div>

                    <!-- Custom color hex -->
                    <div class="flex flex-col gap-1">
                        <label
                            for="custom-color"
                            class="font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary"
                        >
                            {{ $t('inventory.show.override_color_hex_label') }}
                        </label>
                        <div class="flex items-center gap-2">
                            <input
                                id="custom-color"
                                v-model="overrideForm.custom_color_hex"
                                type="text"
                                placeholder="#RRGGBB"
                                maxlength="7"
                                class="w-32 rounded-md border border-border-strong bg-surface px-3 py-2 font-mono text-[14px] text-ink-primary placeholder-ink-tertiary focus:border-accent focus:outline-none focus:ring-[3px] focus:ring-accent-soft"
                                :class="{ 'border-danger focus:border-danger focus:ring-danger-soft': overrideForm.errors.custom_color_hex }"
                            />
                            <span
                                v-if="overrideForm.custom_color_hex?.match(/^#[0-9a-fA-F]{6}$/)"
                                class="inline-block h-7 w-7 rounded ring-1 ring-inset ring-black/10"
                                :style="{ backgroundColor: overrideForm.custom_color_hex }"
                            />
                        </div>
                        <p v-if="overrideForm.errors.custom_color_hex" class="font-sans text-[13px] text-danger">
                            {{ overrideForm.errors.custom_color_hex }}
                        </p>
                    </div>

                    <!-- Notes -->
                    <div class="flex flex-col gap-1">
                        <label
                            for="notes"
                            class="font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary"
                        >
                            {{ $t('inventory.show.override_notes_label') }}
                        </label>
                        <textarea
                            id="notes"
                            v-model="overrideForm.notes"
                            rows="3"
                            :placeholder="$t('inventory.show.override_notes_placeholder')"
                            class="resize-y rounded-md border border-border-strong bg-surface px-3 py-2 font-sans text-[14px] text-ink-primary placeholder-ink-tertiary focus:border-accent focus:outline-none focus:ring-[3px] focus:ring-accent-soft"
                            :class="{ 'border-danger focus:border-danger focus:ring-danger-soft': overrideForm.errors.notes }"
                        />
                        <p v-if="overrideForm.errors.notes" class="font-sans text-[13px] text-danger">
                            {{ overrideForm.errors.notes }}
                        </p>
                    </div>

                    <div class="flex justify-end">
                        <AppButton
                            variant="primary"
                            type="submit"
                            :disabled="overrideForm.processing"
                        >
                            {{ $t('inventory.show.override_save') }}
                        </AppButton>
                    </div>
                </form>
            </section>

            <!-- Recent activity section -->
            <section>
                <h2 class="mb-3 font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary">
                    {{ $t('inventory.show.section_history') }}
                </h2>

                <p v-if="recentMovements.length === 0" class="font-sans text-[14px] text-ink-tertiary">
                    {{ $t('inventory.show.history_no_activity') }}
                </p>

                <div v-else class="overflow-hidden rounded-lg border border-border">
                    <table class="w-full">
                        <tbody class="divide-y divide-border">
                            <tr
                                v-for="movement in recentMovements"
                                :key="movement.id"
                                class="px-3 py-3"
                            >
                                <td class="px-3 py-2.5 font-sans text-[13px] text-ink-secondary">
                                    {{ formatDate(movement.created_at) }}
                                </td>
                                <td class="px-3 py-2.5">
                                    <span class="font-sans text-[13px] font-medium text-ink-primary">
                                        {{ directionLabel(movement.direction) }}
                                    </span>
                                </td>
                                <td class="px-3 py-2.5 font-mono text-[13px] text-ink-secondary">
                                    {{ movementSummary(movement) }}
                                </td>
                                <td class="px-3 py-2.5 font-sans text-[13px] text-ink-secondary">
                                    {{ movement.user?.name ?? '—' }}
                                </td>
                                <td class="px-3 py-2.5 font-sans text-[13px] text-ink-tertiary">
                                    {{ movement.notes ?? '' }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
