<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import BackLink from '@/Components/BackLink.vue';
import ImageUpload from '@/Components/ImageUpload.vue';
import DeleteUserForm from './Partials/DeleteUserForm.vue';
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';

defineProps({
    mustVerifyEmail: { type: Boolean },
    status: { type: String },
});

const page = usePage();
const avatarForm = useForm({ avatar: null, avatar_clear: false });
const submitAvatar = () =>
    avatarForm.post(route('profile.avatar.update'), { forceFormData: true });
</script>

<template>
    <Head :title="$t('profile.meta_title')" />

    <AuthenticatedLayout>
        <template #header>
            <BackLink :href="route('account.index')" :label="$t('nav.account')" />
            <h1
                class="font-display text-[22px] font-semibold tracking-h2 text-ink-primary"
            >
                {{ $t('profile.heading') }}
            </h1>
        </template>

        <div class="flex flex-col gap-6 py-2">
            <!-- ── Avatar ────────────────────────────────────────────────── -->
            <div
                class="rounded-lg border border-border bg-surface p-6 shadow-pop"
            >
                <h2
                    class="font-display text-[17px] font-semibold tracking-h3 text-ink-primary"
                >
                    {{ $t('profile.avatar.heading') }}
                </h2>
                <p class="mt-1 font-sans text-[13px] text-ink-secondary">
                    {{ $t('profile.avatar.subheading') }}
                </p>

                <div class="mt-5 flex items-start gap-6">
                    <img
                        :src="page.props.auth.avatarUrl"
                        class="h-20 w-20 shrink-0 rounded-full object-cover ring-2 ring-border"
                        :alt="$t('profile.avatar.preview_alt')"
                    />

                    <div class="flex flex-col gap-4">
                        <ImageUpload
                            v-model:file="avatarForm.avatar"
                            v-model:clear="avatarForm.avatar_clear"
                            :current-url="page.props.auth.avatarUrl"
                            :help-text="$t('profile.avatar.help')"
                            :error="avatarForm.errors.avatar"
                            accept="image/png,image/jpeg,image/webp,image/svg+xml"
                        />

                        <div class="flex items-center gap-4">
                            <button
                                type="button"
                                :disabled="avatarForm.processing || (!avatarForm.avatar && !avatarForm.avatar_clear)"
                                class="rounded-md bg-accent px-4 py-2 font-sans text-[14px] font-semibold text-accent-on transition hover:bg-accent-hover disabled:opacity-40"
                                @click="submitAvatar"
                            >
                                {{ $t('profile.avatar.submit') }}
                            </button>

                            <Transition
                                enter-active-class="transition-opacity duration-200"
                                enter-from-class="opacity-0"
                                leave-active-class="transition-opacity duration-200"
                                leave-to-class="opacity-0"
                            >
                                <span
                                    v-if="avatarForm.recentlySuccessful"
                                    class="rounded-md border border-success bg-success-soft px-3 py-1.5 font-sans text-[13px] text-ink-primary"
                                >
                                    {{ $t('profile.avatar.saved') }}
                                </span>
                            </Transition>
                        </div>
                    </div>
                </div>
            </div>

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
