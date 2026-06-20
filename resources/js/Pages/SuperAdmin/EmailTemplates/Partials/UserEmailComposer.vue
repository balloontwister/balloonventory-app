<script setup>
import { onUnmounted, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Modal from '@/Components/Modal.vue';
import AppButton from '@/Components/AppButton.vue';

const props = defineProps({
    // Pre-selected recipient { id, name, email } when arriving via ?user=, else null.
    composeUser: { type: Object, default: null },
    // Templates with copy, offered as editable starting drafts: { key, label, subject, body_text }.
    draftTemplates: { type: Array, default: () => [] },
    appUrl: { type: String, default: '' },
});

const recipient = ref(props.composeUser);

const form = useForm({
    user_id: props.composeUser?.id ?? '',
    subject: '',
    body: '',
    template_key: '',
});

// ── Recipient search ──────────────────────────────────────────────────────
const query = ref('');
const results = ref([]);
const searching = ref(false);
let searchTimer = null;

function onSearchInput() {
    clearTimeout(searchTimer);
    const term = query.value.trim();
    if (term === '') {
        results.value = [];
        return;
    }
    searching.value = true;
    searchTimer = setTimeout(async () => {
        try {
            const { data } = await window.axios.get(route('admin.users.search'), {
                params: { q: term },
            });
            results.value = data.users ?? [];
        } catch {
            results.value = [];
        } finally {
            searching.value = false;
        }
    }, 250);
}

function selectRecipient(user) {
    recipient.value = user;
    form.user_id = user.id;
    query.value = '';
    results.value = [];
}

function changeRecipient() {
    recipient.value = null;
    form.user_id = '';
}

onUnmounted(() => clearTimeout(searchTimer));

// ── Template draft loading ────────────────────────────────────────────────
function fillToken(text) {
    return (text ?? '')
        .replaceAll('{{user_name}}', recipient.value?.name ?? '')
        .replaceAll('{{app_url}}', props.appUrl ?? '');
}

function loadTemplate() {
    if (form.template_key === '') return;
    const tpl = props.draftTemplates.find((t) => t.key === form.template_key);
    if (!tpl) return;
    form.subject = fillToken(tpl.subject);
    form.body = fillToken(tpl.body_text);
}

// ── Send (with confirmation) ──────────────────────────────────────────────
const showConfirm = ref(false);

function openConfirm() {
    if (!form.user_id) return;
    showConfirm.value = true;
}

function send() {
    form.post(route('admin.user-emails.store'), {
        preserveScroll: true,
        onSuccess: () => {
            showConfirm.value = false;
            form.reset('subject', 'body', 'template_key');
        },
        onError: () => {
            showConfirm.value = false;
        },
    });
}
</script>

<template>
    <section class="rounded-lg border border-border bg-surface p-6">
        <h2
            class="font-display text-[17px] font-semibold tracking-h3 text-ink-primary"
        >
            {{ $t('super_admin.dashboard.compose_heading') }}
        </h2>
        <p class="mt-1 font-sans text-[13px] text-ink-secondary">
            {{ $t('super_admin.dashboard.compose_subheading') }}
        </p>

        <form @submit.prevent="openConfirm" class="mt-5 flex flex-col gap-4">
            <!-- Recipient -->
            <div class="flex flex-col gap-1">
                <label
                    class="font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary"
                >
                    {{ $t('super_admin.dashboard.compose_recipient') }}
                </label>

                <div
                    v-if="recipient"
                    class="flex items-center justify-between gap-3 rounded-md border border-border bg-background px-3 py-2"
                >
                    <span class="min-w-0 font-sans text-[14px] text-ink-primary">
                        <span class="font-medium">{{ recipient.name }}</span>
                        <span class="text-ink-tertiary"> · {{ recipient.email }}</span>
                    </span>
                    <button
                        type="button"
                        class="shrink-0 font-sans text-[13px] font-medium text-accent hover:underline"
                        @click="changeRecipient"
                    >
                        {{ $t('super_admin.dashboard.compose_change') }}
                    </button>
                </div>

                <div v-else class="relative">
                    <input
                        v-model="query"
                        type="text"
                        :placeholder="$t('super_admin.dashboard.compose_pick_recipient')"
                        class="w-full rounded-md border border-border-strong bg-surface px-3 py-[10px] font-sans text-[14px] text-ink-primary placeholder-ink-tertiary transition focus:border-accent focus:outline-none focus:ring-[3px] focus:ring-accent-soft"
                        @input="onSearchInput"
                    />
                    <ul
                        v-if="results.length"
                        class="absolute z-10 mt-1 max-h-64 w-full overflow-auto rounded-md border border-border bg-surface py-1 shadow-lg"
                    >
                        <li v-for="u in results" :key="u.id">
                            <button
                                type="button"
                                class="block w-full px-3 py-2 text-left font-sans text-[14px] text-ink-primary transition hover:bg-background"
                                @click="selectRecipient(u)"
                            >
                                <span class="font-medium">{{ u.name }}</span>
                                <span class="text-ink-tertiary"> · {{ u.email }}</span>
                            </button>
                        </li>
                    </ul>
                    <p
                        v-else-if="query.trim() !== '' && !searching"
                        class="mt-1 font-sans text-[13px] text-ink-tertiary"
                    >
                        {{ $t('super_admin.dashboard.compose_no_results') }}
                    </p>
                </div>
                <p
                    v-if="form.errors.user_id"
                    class="font-sans text-[13px] text-danger"
                >
                    {{ form.errors.user_id }}
                </p>
            </div>

            <!-- Start from a template -->
            <div v-if="draftTemplates.length" class="flex flex-col gap-1">
                <label
                    for="compose-template"
                    class="font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary"
                >
                    {{ $t('super_admin.dashboard.compose_load_template') }}
                </label>
                <select
                    id="compose-template"
                    v-model="form.template_key"
                    class="w-full rounded-md border border-border-strong bg-surface px-3 py-[10px] font-sans text-[14px] text-ink-primary focus:border-accent focus:outline-none focus:ring-[3px] focus:ring-accent-soft"
                    @change="loadTemplate"
                >
                    <option value="">
                        {{ $t('super_admin.dashboard.compose_load_template_none') }}
                    </option>
                    <option v-for="t in draftTemplates" :key="t.key" :value="t.key">
                        {{ t.label }}
                    </option>
                </select>
            </div>

            <!-- Subject -->
            <div class="flex flex-col gap-1">
                <label
                    for="compose-subject"
                    class="font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary"
                >
                    {{ $t('super_admin.dashboard.compose_subject') }}
                </label>
                <input
                    id="compose-subject"
                    v-model="form.subject"
                    type="text"
                    :placeholder="$t('super_admin.dashboard.compose_subject_placeholder')"
                    :disabled="form.processing"
                    class="w-full rounded-md border border-border-strong bg-surface px-3 py-[10px] font-sans text-[14px] text-ink-primary placeholder-ink-tertiary transition focus:border-accent focus:outline-none focus:ring-[3px] focus:ring-accent-soft disabled:cursor-not-allowed disabled:opacity-50"
                    :class="{
                        'border-danger focus:border-danger focus:ring-danger-soft':
                            form.errors.subject,
                    }"
                />
                <p
                    v-if="form.errors.subject"
                    class="font-sans text-[13px] text-danger"
                >
                    {{ form.errors.subject }}
                </p>
            </div>

            <!-- Body -->
            <div class="flex flex-col gap-1">
                <label
                    for="compose-body"
                    class="font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary"
                >
                    {{ $t('super_admin.dashboard.compose_body') }}
                </label>
                <textarea
                    id="compose-body"
                    v-model="form.body"
                    rows="8"
                    :placeholder="$t('super_admin.dashboard.compose_body_placeholder')"
                    :disabled="form.processing"
                    class="w-full resize-y rounded-md border border-border-strong bg-surface px-3 py-[10px] font-sans text-[14px] text-ink-primary placeholder-ink-tertiary transition focus:border-accent focus:outline-none focus:ring-[3px] focus:ring-accent-soft disabled:cursor-not-allowed disabled:opacity-50"
                    :class="{
                        'border-danger focus:border-danger focus:ring-danger-soft':
                            form.errors.body,
                    }"
                />
                <p
                    v-if="form.errors.body"
                    class="font-sans text-[13px] text-danger"
                >
                    {{ form.errors.body }}
                </p>
            </div>

            <div class="flex items-center justify-end">
                <AppButton variant="primary" type="submit" :disabled="form.processing || !form.user_id">
                    {{ $t('super_admin.dashboard.compose_send') }}
                </AppButton>
            </div>
        </form>

        <!-- Confirmation -->
        <Modal :show="showConfirm" max-width="md" @close="showConfirm = false">
            <div class="p-6">
                <h3 class="font-sans text-[16px] font-semibold text-ink-primary">
                    {{ $t('super_admin.dashboard.compose_confirm_title') }}
                </h3>
                <p class="mt-2 font-sans text-[14px] text-ink-secondary">
                    {{
                        $t('super_admin.dashboard.compose_confirm_body', {
                            name: recipient?.name,
                            email: recipient?.email,
                        })
                    }}
                </p>
                <p class="mt-3 font-sans text-[14px] font-medium text-ink-primary">
                    {{ form.subject }}
                </p>
                <div class="mt-5 flex items-center justify-end gap-3">
                    <AppButton
                        variant="secondary"
                        type="button"
                        :disabled="form.processing"
                        @click="showConfirm = false"
                    >
                        {{ $t('super_admin.dashboard.compose_confirm_cancel') }}
                    </AppButton>
                    <AppButton
                        variant="primary"
                        type="button"
                        :disabled="form.processing"
                        @click="send"
                    >
                        {{ form.processing ? $t('super_admin.dashboard.compose_sending') : $t('super_admin.dashboard.compose_confirm_send') }}
                    </AppButton>
                </div>
            </div>
        </Modal>
    </section>
</template>
