<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import { roleLabelFor } from '@/Composables/useBusiness';
import { useForm } from '@inertiajs/vue3';
import { nextTick, ref } from 'vue';

const props = defineProps({
    // Businesses the user solely owns. Each needs a successor decision (or is
    // frozen). Shape: { id, name, candidates: [{ userId, name, role }] }.
    soleOwnerBusinesses: { type: Array, default: () => [] },
});

const confirmingDeletion = ref(false);
const passwordInput = ref(null);

// handoffs maps business id -> chosen successor user id; '' means "freeze".
const initialHandoffs = () =>
    Object.fromEntries(props.soleOwnerBusinesses.map((b) => [b.id, '']));

const form = useForm({ password: '', handoffs: initialHandoffs() });

const openConfirm = () => {
    confirmingDeletion.value = true;
    nextTick(() => passwordInput.value.focus());
};

const closeConfirm = () => {
    confirmingDeletion.value = false;
    form.clearErrors();
    form.reset();
    form.handoffs = initialHandoffs();
};

const deleteUser = () => {
    form.delete(route('profile.destroy'), {
        preserveScroll: true,
        onSuccess: () => closeConfirm(),
        onError: () => passwordInput.value.focus(),
    });
};
</script>

<template>
    <section class="flex flex-col gap-5">
        <div>
            <h2
                class="font-display text-[17px] font-semibold tracking-h3 text-ink-primary"
            >
                {{ $t('profile.delete.heading') }}
            </h2>
            <p class="mt-1 font-sans text-[13px] text-ink-secondary">
                {{ $t('profile.delete.subheading') }}
            </p>
        </div>

        <div>
            <button
                type="button"
                class="rounded-md border border-danger px-4 py-2 font-sans text-[14px] font-semibold text-danger transition hover:bg-danger-soft"
                @click="openConfirm"
            >
                {{ $t('profile.delete.open_button') }}
            </button>
        </div>

        <!-- Confirmation overlay -->
        <Transition
            enter-active-class="transition-opacity duration-150"
            enter-from-class="opacity-0"
            leave-active-class="transition-opacity duration-150"
            leave-to-class="opacity-0"
        >
            <div
                v-if="confirmingDeletion"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 px-4"
                @click.self="closeConfirm"
            >
                <div
                    class="max-h-[85vh] w-full overflow-y-auto rounded-lg border border-border bg-surface p-6 shadow-modal"
                    :class="
                        soleOwnerBusinesses.length ? 'max-w-lg' : 'max-w-md'
                    "
                >
                    <h3
                        class="font-display text-[18px] font-semibold tracking-h3 text-ink-primary"
                    >
                        {{ $t('profile.delete.confirm_heading') }}
                    </h3>
                    <p class="mt-2 font-sans text-[13px] text-ink-secondary">
                        {{ $t('profile.delete.confirm_body') }}
                    </p>

                    <!-- Owner handoff: assign a successor, or leave to freeze -->
                    <div
                        v-if="soleOwnerBusinesses.length"
                        class="mt-5 border-t border-border pt-5"
                    >
                        <h4
                            class="font-display text-[14px] font-semibold tracking-h3 text-ink-primary"
                        >
                            {{ $t('profile.delete.handoff_heading') }}
                        </h4>
                        <p
                            class="mt-1 font-sans text-[13px] text-ink-secondary"
                        >
                            {{ $t('profile.delete.handoff_intro') }}
                        </p>

                        <div class="mt-4 flex flex-col gap-4">
                            <div
                                v-for="business in soleOwnerBusinesses"
                                :key="business.id"
                            >
                                <template v-if="business.candidates.length">
                                    <InputLabel
                                        :for="`handoff-${business.id}`"
                                        :value="
                                            $t(
                                                'profile.delete.handoff_assign_label',
                                                { business: business.name },
                                            )
                                        "
                                    />
                                    <select
                                        :id="`handoff-${business.id}`"
                                        v-model="form.handoffs[business.id]"
                                        class="mt-1 block w-full rounded-md border-border bg-surface font-sans text-[14px] text-ink-primary shadow-sm focus:border-accent focus:ring-accent"
                                    >
                                        <option value="">
                                            {{
                                                $t(
                                                    'profile.delete.handoff_freeze_option',
                                                )
                                            }}
                                        </option>
                                        <option
                                            v-for="candidate in business.candidates"
                                            :key="candidate.userId"
                                            :value="candidate.userId"
                                        >
                                            {{ candidate.name }} —
                                            {{ roleLabelFor(candidate.role) }}
                                        </option>
                                    </select>
                                </template>
                                <p
                                    v-else
                                    class="font-sans text-[13px] text-ink-secondary"
                                >
                                    {{
                                        $t(
                                            'profile.delete.handoff_no_members',
                                            { business: business.name },
                                        )
                                    }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="mt-5">
                        <InputLabel
                            for="delete-password"
                            :value="$t('profile.delete.password_label')"
                            class="sr-only"
                        />
                        <TextInput
                            id="delete-password"
                            ref="passwordInput"
                            v-model="form.password"
                            type="password"
                            class="block w-full"
                            :placeholder="
                                $t('profile.delete.password_placeholder')
                            "
                            @keyup.enter="deleteUser"
                        />
                        <InputError
                            :message="form.errors.password"
                            class="mt-1"
                        />
                    </div>

                    <div class="mt-5 flex justify-end gap-3">
                        <button
                            type="button"
                            class="rounded-md border border-border px-4 py-2 font-sans text-[14px] font-medium text-ink-secondary transition hover:bg-background"
                            @click="closeConfirm"
                        >
                            {{ $t('profile.delete.cancel') }}
                        </button>
                        <button
                            type="button"
                            :disabled="form.processing"
                            class="rounded-md bg-danger px-4 py-2 font-sans text-[14px] font-semibold text-white transition hover:opacity-90 disabled:opacity-40"
                            @click="deleteUser"
                        >
                            {{ $t('profile.delete.submit') }}
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </section>
</template>
