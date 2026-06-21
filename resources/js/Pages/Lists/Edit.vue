<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import BackLink from '@/Components/BackLink.vue';
import AppInput from '@/Components/AppInput.vue';
import AppButton from '@/Components/AppButton.vue';
import { Head, useForm, Link } from '@inertiajs/vue3';

const props = defineProps({
    list: { type: Object, required: true },
});

const form = useForm({
    name: props.list.name,
    notes: props.list.notes ?? '',
});

function submit() {
    form.patch(route('lists.update', { list: props.list.id }));
}
</script>

<template>
    <Head :title="$t('lists.edit.meta_title')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-2">
                <BackLink
                    :href="route('lists.show', { list: list.id })"
                    :label="list.name"
                />
                <h1 class="font-display text-[22px] font-semibold text-ink-primary">
                    {{ $t('lists.edit.heading') }}
                </h1>
            </div>
        </template>

        <form
            class="flex max-w-lg flex-col gap-5 rounded-lg border border-border bg-surface p-6"
            @submit.prevent="submit"
        >
            <AppInput
                id="name"
                v-model="form.name"
                :label="$t('lists.form.name_label')"
                :placeholder="$t('lists.form.name_placeholder')"
                :error="form.errors.name"
                required
            />

            <div class="flex flex-col gap-1">
                <label
                    for="notes"
                    class="font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary"
                >
                    {{ $t('lists.form.notes_label') }}
                </label>
                <textarea
                    id="notes"
                    v-model="form.notes"
                    rows="3"
                    class="w-full rounded-md border border-border-strong bg-surface px-3 py-[10px] font-sans text-[14px] text-ink-primary placeholder-ink-tertiary transition focus:border-accent focus:outline-none focus:ring-[3px] focus:ring-accent-soft"
                    :placeholder="$t('lists.form.notes_placeholder')"
                />
                <p v-if="form.errors.notes" class="text-[13px] text-danger">
                    {{ form.errors.notes }}
                </p>
            </div>

            <div class="flex justify-end gap-2">
                <Link
                    :href="route('lists.show', { list: list.id })"
                    class="rounded-md border border-border-strong bg-surface px-4 py-[10px] font-sans text-[14px] font-medium text-ink-primary transition hover:bg-background"
                >
                    {{ $t('common.cancel') }}
                </Link>
                <AppButton type="submit" :disabled="form.processing">
                    {{ $t('lists.form.save_submit') }}
                </AppButton>
            </div>
        </form>
    </AuthenticatedLayout>
</template>
