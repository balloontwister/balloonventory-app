<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DeleteUserForm from './Partials/DeleteUserForm.vue';
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue';
import { Head } from '@inertiajs/vue3';

defineProps({
    mustVerifyEmail: { type: Boolean },
    status: { type: String },
});
</script>

<template>
    <Head title="Profile" />

    <AuthenticatedLayout>
        <template #header>
            <h1
                class="font-display text-[22px] font-semibold tracking-h2 text-ink-primary"
            >
                Profile
            </h1>
        </template>

        <div class="flex flex-col gap-6 py-2">
            <div
                class="rounded-lg border border-border bg-surface p-6 shadow-pop"
            >
                <UpdateProfileInformationForm
                    :must-verify-email="mustVerifyEmail"
                    :status="status"
                />
            </div>

            <div
                class="rounded-lg border border-border bg-surface p-6 shadow-pop"
            >
                <UpdatePasswordForm />
            </div>

            <div
                class="rounded-lg border border-border bg-surface p-6 shadow-pop"
            >
                <DeleteUserForm />
            </div>
        </div>
    </AuthenticatedLayout>
</template>
