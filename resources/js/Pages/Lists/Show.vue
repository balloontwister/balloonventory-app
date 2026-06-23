<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import BackLink from '@/Components/BackLink.vue';
import ListContents from '@/Components/ListContents.vue';
import Modal from '@/Components/Modal.vue';
import AppButton from '@/Components/AppButton.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    list: { type: Object, required: true },
});

const confirmingDelete = ref(false);

function deleteList() {
    router.delete(route('lists.destroy', { list: props.list.id }), {
        onFinish: () => (confirmingDelete.value = false),
    });
}

function unarchive() {
    useForm({ archived: false }).patch(route('lists.update', { list: props.list.id }));
}
</script>

<template>
    <Head :title="list.name" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-2">
                <BackLink
                    :href="route('lists.index')"
                    :label="$t('nav.lists_jobs')"
                />
                <div class="flex items-center gap-3">
                    <svg
                        v-if="list.is_business_favorites"
                        xmlns="http://www.w3.org/2000/svg"
                        viewBox="0 0 20 20"
                        fill="currentColor"
                        class="h-6 w-6 text-accent"
                    >
                        <path
                            fill-rule="evenodd"
                            d="M10.868 2.884c-.321-.772-1.415-.772-1.736 0l-1.83 4.401-4.753.381c-.833.067-1.171 1.107-.536 1.651l3.62 3.102-1.106 4.637c-.194.813.691 1.456 1.405 1.02L10 15.591l4.069 2.485c.713.436 1.598-.207 1.404-1.02l-1.106-4.637 3.62-3.102c.635-.544.297-1.584-.536-1.65l-4.752-.382-1.831-4.401z"
                            clip-rule="evenodd"
                        />
                    </svg>
                    <h1
                        class="font-display text-[22px] font-semibold text-ink-primary"
                    >
                        {{ list.name }}
                    </h1>

                    <div class="ml-auto flex items-center gap-2">
                        <!-- Visibility badge -->
                        <span
                            v-if="list.visibility === 'owner_editable'"
                            class="rounded-full bg-accent-soft px-2.5 py-0.5 font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-accent"
                        >
                            {{ $t('lists.form.visibility_owner_editable').split(' — ')[0] }}
                        </span>
                        <span
                            v-else-if="list.visibility === 'private'"
                            class="rounded-full bg-ink-tertiary/10 px-2.5 py-0.5 font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary"
                        >
                            {{ $t('lists.form.visibility_private').split(' — ')[0] }}
                        </span>

                        <Link
                            v-if="list.can.edit"
                            :href="route('lists.edit', { list: list.id })"
                            class="rounded-md border border-border-strong bg-surface px-3 py-2 font-sans text-[13px] font-medium text-ink-primary transition hover:bg-background"
                        >
                            {{ $t('lists.detail.edit') }}
                        </Link>
                        <button
                            v-if="list.can.delete"
                            type="button"
                            class="rounded-md border border-border-strong bg-surface px-3 py-2 font-sans text-[13px] font-medium text-danger transition hover:bg-danger-soft"
                            @click="confirmingDelete = true"
                        >
                            {{ $t('lists.detail.delete') }}
                        </button>
                    </div>
                </div>
            </div>
        </template>

        <div class="flex flex-col gap-4">
            <!-- Notes -->
            <div
                v-if="list.notes"
                class="rounded-lg border border-border bg-surface px-4 py-3"
            >
                <p class="font-sans text-[14px] text-ink-secondary">{{ list.notes }}</p>
            </div>

            <!-- Archived notice -->
            <div
                v-if="list.archived_at"
                class="flex items-center gap-3 rounded-lg border border-border bg-background px-4 py-3"
            >
                <span class="rounded-full bg-ink-tertiary/10 px-2.5 py-0.5 font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary">
                    {{ $t('lists.detail.archived_badge') }}
                </span>
                <p class="flex-1 font-sans text-[13px] text-ink-secondary">
                    {{ $t('lists.detail.archived_notice') }}
                </p>
                <button
                    v-if="list.can.manage_visibility"
                    type="button"
                    class="shrink-0 rounded-md border border-border-strong bg-surface px-3 py-1.5 font-sans text-[13px] font-medium text-ink-primary transition hover:bg-background"
                    @click="unarchive"
                >
                    {{ $t('lists.detail.unarchive') }}
                </button>
            </div>

            <div class="rounded-lg border border-border bg-surface">
                <ListContents :list="list" />
            </div>
        </div>

        <!-- Delete confirmation -->
        <Modal :show="confirmingDelete" @close="confirmingDelete = false">
            <div class="p-6">
                <h2
                    class="font-display text-[18px] font-semibold text-ink-primary"
                >
                    {{ $t('lists.detail.delete_confirm_title') }}
                </h2>
                <p class="mt-2 font-sans text-[14px] text-ink-secondary">
                    {{
                        $t('lists.detail.delete_confirm_body', {
                            list: list.name,
                        })
                    }}
                </p>
                <div class="mt-6 flex justify-end gap-2">
                    <AppButton
                        variant="secondary"
                        @click="confirmingDelete = false"
                    >
                        {{ $t('common.cancel') }}
                    </AppButton>
                    <AppButton variant="danger" @click="deleteList">
                        {{ $t('lists.detail.delete') }}
                    </AppButton>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
