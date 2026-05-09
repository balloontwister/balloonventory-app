<script setup>
import { ref, computed, onMounted } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head } from '@inertiajs/vue3';

defineProps({
    email: { type: String, required: true },
    status: { type: String, default: null },
});

const digits = ref(['', '', '', '', '', '']);
const inputs = ref([]);
const resendCooldown = ref(0);
let cooldownTimer = null;

const form = useForm({ code: '' });

const code = computed(() => digits.value.join(''));
const hasError = computed(() => !!form.errors.code);

onMounted(() => inputs.value[0]?.focus());

function onInput(index, event) {
    const val = event.target.value.replace(/\D/g, '').slice(-1);
    digits.value[index] = val;

    if (val && index < 5) {
        inputs.value[index + 1]?.focus();
    }

    if (code.value.length === 6) {
        submit();
    }
}

function onKeydown(index, event) {
    if (event.key === 'Backspace' && !digits.value[index] && index > 0) {
        inputs.value[index - 1]?.focus();
    }
}

function onPaste(event) {
    const text = event.clipboardData
        .getData('text')
        .replace(/\D/g, '')
        .slice(0, 6);
    if (!text) return;
    event.preventDefault();
    text.split('').forEach((char, i) => {
        digits.value[i] = char;
    });
    const nextEmpty = text.length < 6 ? text.length : 5;
    inputs.value[nextEmpty]?.focus();
    if (text.length === 6) submit();
}

function submit() {
    form.code = code.value;
    form.post(route('verification.submit-code'), {
        onError: () => {
            digits.value = ['', '', '', '', '', ''];
            inputs.value[0]?.focus();
        },
    });
}

function resend() {
    if (resendCooldown.value > 0) return;
    router.post(
        route('verification.resend-code'),
        {},
        {
            preserveScroll: true,
            onSuccess: () => startCooldown(),
        },
    );
}

function startCooldown() {
    resendCooldown.value = 60;
    cooldownTimer = setInterval(() => {
        resendCooldown.value--;
        if (resendCooldown.value <= 0) clearInterval(cooldownTimer);
    }, 1000);
}
</script>

<template>
    <GuestLayout>
        <Head title="Verify your email" />

        <div class="flex flex-col gap-5">
            <div>
                <h1
                    class="font-display text-[20px] font-semibold tracking-h3 text-ink-primary"
                >
                    Check your email
                </h1>
                <p class="mt-1 font-sans text-[14px] text-ink-secondary">
                    We sent a 6-digit code to
                    <span class="font-medium text-ink-primary">{{ email }}</span
                    >. Enter it below to verify your account.
                </p>
            </div>

            <!-- Success flash -->
            <div
                v-if="status"
                class="rounded-md border border-success bg-success-soft px-3 py-2 font-sans text-[13px] text-ink-primary"
            >
                {{ status }}
            </div>

            <!-- 6-digit input -->
            <div class="flex justify-between gap-2">
                <input
                    v-for="(digit, i) in digits"
                    :key="i"
                    ref="inputs"
                    type="text"
                    inputmode="numeric"
                    maxlength="1"
                    :value="digit"
                    :class="[
                        'h-14 w-full rounded-md border-2 text-center font-mono text-[22px] font-semibold text-ink-primary transition-colors focus:outline-none',
                        hasError
                            ? 'border-danger bg-danger-soft focus:border-danger'
                            : 'border-border bg-background focus:border-accent',
                    ]"
                    @input="onInput(i, $event)"
                    @keydown="onKeydown(i, $event)"
                    @paste="onPaste"
                />
            </div>

            <!-- Error -->
            <p v-if="hasError" class="font-sans text-[13px] text-danger">
                {{ form.errors.code }}
            </p>

            <!-- Submit -->
            <button
                type="button"
                :disabled="code.length < 6 || form.processing"
                class="w-full rounded-md bg-accent py-2.5 font-sans text-[15px] font-semibold text-accent-on transition hover:bg-accent-hover disabled:cursor-not-allowed disabled:opacity-40"
                @click="submit"
            >
                {{ form.processing ? 'Verifying…' : 'Verify account' }}
            </button>

            <!-- Resend -->
            <p class="text-center font-sans text-[13px] text-ink-secondary">
                Didn't receive it?
                <button
                    type="button"
                    :disabled="resendCooldown > 0"
                    class="font-medium text-accent transition hover:underline disabled:cursor-not-allowed disabled:opacity-50"
                    @click="resend"
                >
                    {{
                        resendCooldown > 0
                            ? `Resend in ${resendCooldown}s`
                            : 'Resend code'
                    }}
                </button>
            </p>
        </div>
    </GuestLayout>
</template>
