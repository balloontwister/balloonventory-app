<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    backups: { type: Array, required: true },
});

const runningBackup = ref(false);
const confirmingDelete = ref(null);
const renamingFile = ref(null);
const renameValue = ref('');
const renameError = ref(null);
const renamingSaving = ref(false);

function runBackup() {
    runningBackup.value = true;
    router.post(
        route('admin.backups.store'),
        {},
        {
            onFinish: () => (runningBackup.value = false),
        },
    );
}

function confirmDelete(filename) {
    confirmingDelete.value = filename;
    cancelRename();
}

function cancelDelete() {
    confirmingDelete.value = null;
}

function deleteBackup(filename) {
    router.delete(route('admin.backups.destroy', filename), {
        preserveScroll: true,
        onFinish: () => (confirmingDelete.value = null),
    });
}

function startRename(filename) {
    renamingFile.value = filename;
    renameValue.value = filename;
    renameError.value = null;
    confirmingDelete.value = null;
}

function cancelRename() {
    renamingFile.value = null;
    renameValue.value = '';
    renameError.value = null;
}

function saveRename(oldFilename) {
    renamingSaving.value = true;
    renameError.value = null;
    router.patch(
        route('admin.backups.rename', oldFilename),
        {
            new_filename: renameValue.value,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                renamingFile.value = null;
                renameValue.value = '';
            },
            onError: (errors) => {
                renameError.value = errors.new_filename ?? null;
            },
            onFinish: () => (renamingSaving.value = false),
        },
    );
}

function formatDate(val) {
    return new Date(val).toLocaleString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    });
}
</script>

<template>
    <Head :title="$t('super_admin.backups.meta_title')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between gap-4">
                <h1
                    class="font-display text-[22px] font-semibold tracking-h2 text-ink-primary"
                >
                    {{ $t('super_admin.backups.heading') }}
                </h1>
                <Link
                    :href="route('admin.dashboard')"
                    class="font-sans text-[13px] text-ink-secondary transition hover:text-ink-primary"
                >
                    {{ $t('super_admin.dashboard.back') }}
                </Link>
            </div>
        </template>

        <div class="mx-auto max-w-4xl px-4 py-8 sm:px-6 lg:px-8">
            <!-- Description + Backup Now -->
            <div class="mb-6 flex items-start justify-between gap-4">
                <p class="text-sm text-ink-secondary">
                    {{ $t('super_admin.backups.description') }}
                </p>

                <button
                    :disabled="runningBackup"
                    class="inline-flex shrink-0 items-center gap-2 rounded-lg bg-accent px-4 py-2 text-sm font-semibold text-white transition hover:bg-accent-hover disabled:opacity-60"
                    @click="runBackup"
                >
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        viewBox="0 0 20 20"
                        fill="currentColor"
                        class="h-4 w-4"
                    >
                        <path
                            fill-rule="evenodd"
                            d="M10 1a.75.75 0 0 1 .75.75v1.5a.75.75 0 0 1-1.5 0v-1.5A.75.75 0 0 1 10 1ZM5.05 3.05a.75.75 0 0 1 1.06 0l1.062 1.06A.75.75 0 1 1 6.11 5.173L5.05 4.11a.75.75 0 0 1 0-1.06Zm9.9 0a.75.75 0 0 1 0 1.06l-1.06 1.062a.75.75 0 0 1-1.062-1.061l1.061-1.061a.75.75 0 0 1 1.06 0ZM3 8a.75.75 0 0 1 .75-.75h1.5a.75.75 0 0 1 0 1.5h-1.5A.75.75 0 0 1 3 8Zm11 0a.75.75 0 0 1 .75-.75h1.5a.75.75 0 0 1 0 1.5h-1.5A.75.75 0 0 1 14 8Zm-6.828 2.828a.75.75 0 0 1 0 1.061L6.11 12.95a.75.75 0 0 1-1.06-1.06l1.06-1.062a.75.75 0 0 1 1.061 0Zm3.594-3.317a.75.75 0 0 1 1.06 0l1.062 1.06a.75.75 0 0 1-1.062 1.061l-1.06-1.06a.75.75 0 0 1 0-1.061ZM10 13a.75.75 0 0 1 .75.75v1.5a.75.75 0 0 1-1.5 0v-1.5A.75.75 0 0 1 10 13ZM7 10a3 3 0 1 1 6 0 3 3 0 0 1-6 0Z"
                            clip-rule="evenodd"
                        />
                    </svg>
                    {{
                        runningBackup
                            ? $t('super_admin.backups.running')
                            : $t('super_admin.backups.run_backup')
                    }}
                </button>
            </div>

            <!-- Empty state -->
            <div
                v-if="backups.length === 0"
                class="rounded-xl border border-border bg-surface py-16 text-center text-ink-tertiary"
            >
                {{ $t('super_admin.backups.empty') }}
            </div>

            <!-- Backup list -->
            <div
                v-else
                class="overflow-hidden rounded-xl border border-border bg-surface"
            >
                <table class="w-full text-sm">
                    <thead>
                        <tr
                            class="border-b border-border bg-background text-left text-xs font-semibold uppercase tracking-wide text-ink-tertiary"
                        >
                            <th class="px-4 py-3">
                                {{ $t('super_admin.backups.col_filename') }}
                            </th>
                            <th class="px-4 py-3">
                                {{ $t('super_admin.backups.col_size') }}
                            </th>
                            <th class="px-4 py-3">
                                {{ $t('super_admin.backups.col_created') }}
                            </th>
                            <th class="px-4 py-3 text-right">
                                {{ $t('super_admin.backups.col_actions') }}
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <tr v-for="backup in backups" :key="backup.filename">
                            <!-- Filename — shows editable input when renaming this row -->
                            <td class="px-4 py-3">
                                <template
                                    v-if="renamingFile === backup.filename"
                                >
                                    <input
                                        v-model="renameValue"
                                        type="text"
                                        :placeholder="
                                            $t(
                                                'super_admin.backups.rename_placeholder',
                                            )
                                        "
                                        class="w-full rounded border border-border bg-background px-2 py-1 font-mono text-xs text-ink-primary focus:border-accent focus:outline-none"
                                        @keyup.enter="
                                            saveRename(backup.filename)
                                        "
                                        @keyup.escape="cancelRename"
                                    />
                                    <p
                                        v-if="renameError"
                                        class="mt-1 text-xs text-red-500"
                                    >
                                        {{ renameError }}
                                    </p>
                                </template>
                                <span
                                    v-else
                                    class="font-mono text-xs text-ink-primary"
                                    >{{ backup.filename }}</span
                                >
                            </td>

                            <td class="px-4 py-3 text-ink-secondary">
                                {{ backup.size_kb }} KB
                            </td>
                            <td class="px-4 py-3 text-ink-secondary">
                                {{ formatDate(backup.created_at) }}
                            </td>

                            <!-- Actions -->
                            <td class="px-4 py-3 text-right">
                                <!-- Rename: save / cancel -->
                                <div
                                    v-if="renamingFile === backup.filename"
                                    class="flex items-center justify-end gap-2"
                                >
                                    <button
                                        :disabled="renamingSaving"
                                        class="rounded bg-accent px-2 py-1 text-xs font-medium text-white hover:bg-accent-hover disabled:opacity-60"
                                        @click="saveRename(backup.filename)"
                                    >
                                        {{
                                            renamingSaving
                                                ? $t(
                                                      'super_admin.backups.running',
                                                  )
                                                : $t(
                                                      'super_admin.backups.rename_save',
                                                  )
                                        }}
                                    </button>
                                    <button
                                        class="rounded bg-background px-2 py-1 text-xs font-medium text-ink-secondary hover:text-ink-primary"
                                        @click="cancelRename"
                                    >
                                        {{
                                            $t(
                                                'super_admin.backups.rename_cancel',
                                            )
                                        }}
                                    </button>
                                </div>

                                <!-- Delete: confirm / cancel -->
                                <div
                                    v-else-if="
                                        confirmingDelete === backup.filename
                                    "
                                    class="flex items-center justify-end gap-2"
                                >
                                    <span class="text-xs text-ink-secondary">{{
                                        $t('super_admin.backups.delete_confirm')
                                    }}</span>
                                    <button
                                        class="rounded bg-red-600 px-2 py-1 text-xs font-medium text-white hover:bg-red-700"
                                        @click="deleteBackup(backup.filename)"
                                    >
                                        {{
                                            $t('super_admin.backups.delete_yes')
                                        }}
                                    </button>
                                    <button
                                        class="rounded bg-background px-2 py-1 text-xs font-medium text-ink-secondary hover:text-ink-primary"
                                        @click="cancelDelete"
                                    >
                                        {{
                                            $t(
                                                'super_admin.backups.delete_cancel',
                                            )
                                        }}
                                    </button>
                                </div>

                                <!-- Default: download / rename / delete -->
                                <div
                                    v-else
                                    class="flex items-center justify-end gap-3"
                                >
                                    <a
                                        :href="
                                            route(
                                                'admin.backups.download',
                                                backup.filename,
                                            )
                                        "
                                        class="text-accent hover:underline"
                                    >
                                        {{ $t('super_admin.backups.download') }}
                                    </a>
                                    <button
                                        class="text-ink-secondary hover:text-ink-primary hover:underline"
                                        @click="startRename(backup.filename)"
                                    >
                                        {{ $t('super_admin.backups.rename') }}
                                    </button>
                                    <button
                                        class="text-red-500 hover:underline"
                                        @click="confirmDelete(backup.filename)"
                                    >
                                        {{ $t('super_admin.backups.delete') }}
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
