<script setup>
import { ref, watch } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import Modal from '@/Components/Modal.vue';
import AppInput from '@/Components/AppInput.vue';
import AppButton from '@/Components/AppButton.vue';

const props = defineProps({
    show: { type: Boolean, default: false },
});

const emit = defineEmits(['close']);

const page = usePage();

const form = useForm({
    subject: '',
    message: '',
});

const sent = ref(false);

watch(
    () => props.show,
    (val) => {
        if (val) {
            sent.value = false;
            form.reset();
            form.clearErrors();
        }
    },
);

function submit() {
    form.post(route('support.contact'), {
        preserveScroll: true,
        onSuccess: () => {
            sent.value = true;
            form.reset();
        },
    });
}

function close() {
    emit('close');
}
</script>

<template>
    <Modal :show="show" max-width="lg" @close="close">
        <div class="p-6">
            <!-- Success state -->
            <template v-if="sent">
                <div class="flex flex-col items-center gap-4 py-4 text-center">
                    <div
                        class="flex h-12 w-12 items-center justify-center rounded-full bg-accent-soft"
                    >
                        <!-- icon: check -->
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            viewBox="0 0 20 20"
                            fill="currentColor"
                            class="h-6 w-6 text-accent"
                            aria-hidden="true"
                        >
                            <path
                                fill-rule="evenodd"
                                d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z"
                                clip-rule="evenodd"
                            />
                        </svg>
                    </div>
                    <div>
                        <p
                            class="font-sans text-[16px] font-semibold text-ink-primary"
                        >
                            {{ $t('support.sent.title') }}
                        </p>
                        <p
                            class="mt-1 font-sans text-[14px] text-ink-secondary"
                        >
                            {{ $t('support.sent.reply_to_before') }}
                            <span class="font-medium">{{
                                $page.props.auth.user.email
                            }}</span
                            >{{ $t('support.sent.reply_to_after') }}
                        </p>
                    </div>
                    <AppButton variant="secondary" size="sm" @click="close">
                        {{ $t('support.sent.close') }}
                    </AppButton>
                </div>
            </template>

            <!-- Form state -->
            <template v-else>
                <div class="mb-5 flex items-start justify-between">
                    <div>
                        <p
                            class="font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-accent"
                        >
                            {{ $t('support.eyebrow') }}
                        </p>
                        <h2
                            class="mt-0.5 font-sans text-[18px] font-semibold text-ink-primary"
                        >
                            {{ $t('support.heading') }}
                        </h2>
                        <p
                            class="mt-1 font-sans text-[13px] text-ink-secondary"
                        >
                            {{ $t('support.subheading') }}
                        </p>
                    </div>
                    <button
                        type="button"
                        class="ml-4 flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-md text-ink-tertiary transition hover:bg-background hover:text-ink-primary"
                        aria-label="Close"
                        @click="close"
                    >
                        <!-- icon: x-mark -->
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            viewBox="0 0 20 20"
                            fill="currentColor"
                            class="h-4 w-4"
                            aria-hidden="true"
                        >
                            <path
                                d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z"
                            />
                        </svg>
                    </button>
                </div>

                <form @submit.prevent="submit" class="flex flex-col gap-4">
                    <AppInput
                        id="support-subject"
                        :label="$t('support.subject_label')"
                        :required="true"
                        v-model="form.subject"
                        :placeholder="$t('support.subject_placeholder')"
                        :error="form.errors.subject"
                        :disabled="form.processing"
                    />

                    <div class="flex flex-col gap-1">
                        <label
                            for="support-message"
                            class="font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary"
                        >
                            {{ $t('support.message_label')
                            }}<span class="ml-0.5 text-danger">*</span>
                        </label>
                        <textarea
                            id="support-message"
                            v-model="form.message"
                            rows="5"
                            :placeholder="$t('support.message_placeholder')"
                            :disabled="form.processing"
                            class="w-full resize-y rounded-md border border-border-strong bg-surface px-3 py-[10px] font-sans text-[14px] text-ink-primary placeholder-ink-tertiary transition focus:border-accent focus:outline-none focus:ring-[3px] focus:ring-accent-soft disabled:cursor-not-allowed disabled:opacity-50"
                            :class="{
                                'border-danger focus:border-danger focus:ring-danger-soft':
                                    form.errors.message,
                            }"
                        />
                        <p
                            v-if="form.errors.message"
                            class="font-sans text-[13px] text-danger"
                        >
                            {{ form.errors.message }}
                        </p>
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-1">
                        <AppButton
                            variant="secondary"
                            type="button"
                            :disabled="form.processing"
                            @click="close"
                        >
                            {{ $t('support.cancel') }}
                        </AppButton>
                        <AppButton
                            variant="primary"
                            type="submit"
                            :disabled="form.processing"
                        >
                            {{
                                form.processing
                                    ? $t('support.submitting')
                                    : $t('support.submit')
                            }}
                        </AppButton>
                    </div>
                </form>
            </template>
        </div>
    </Modal>
</template>
