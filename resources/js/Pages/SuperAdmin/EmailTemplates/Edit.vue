<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AppInput from '@/Components/AppInput.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    template: { type: Object, required: true },
    variables: { type: Object, required: true },
});

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success);
const flashError = computed(() => page.props.flash?.error);

const form = useForm({
    subject: props.template.subject ?? '',
    body_html: props.template.body_html ?? '',
    body_text: props.template.body_text ?? '',
    action: 'save',
});

const previewing = ref(false);
const deactivating = ref(false);

// ── Variable insertion ─────────────────────────────────────────────────────
const subjectRef = ref(null);
const bodyHtmlRef = ref(null);
const bodyTextRef = ref(null);

const focusedField = ref('body_html');

function trackFocus(field) {
    focusedField.value = field;
}

function insertToken(token) {
    const placeholder = `{{${token}}}`;
    const refMap = {
        subject: subjectRef,
        body_html: bodyHtmlRef,
        body_text: bodyTextRef,
    };
    const fieldRef = refMap[focusedField.value]?.value;
    // AppInput exposes an <input>; native ref points to the wrapper div, so query inside.
    const el =
        focusedField.value === 'subject'
            ? (fieldRef?.querySelector?.('input') ?? fieldRef)
            : fieldRef;
    if (!el || typeof el.selectionStart !== 'number') {
        form[focusedField.value] += placeholder;
        return;
    }
    const start = el.selectionStart;
    const end = el.selectionEnd;
    const current = form[focusedField.value];
    form[focusedField.value] =
        current.slice(0, start) + placeholder + current.slice(end);
    requestAnimationFrame(() => {
        el.focus();
        const pos = start + placeholder.length;
        el.setSelectionRange(pos, pos);
    });
}

// ── Auto-populate plain text from HTML ─────────────────────────────────────
function generatePlainText() {
    // Strip tags, collapse whitespace, preserve paragraph breaks.
    const stripped = form.body_html
        .replace(/<\s*br\s*\/?\s*>/gi, '\n')
        .replace(/<\/\s*(p|div|li|h[1-6])\s*>/gi, '\n\n')
        .replace(/<[^>]+>/g, '')
        .replace(/&nbsp;/g, ' ')
        .replace(/&amp;/g, '&')
        .replace(/&lt;/g, '<')
        .replace(/&gt;/g, '>')
        .replace(/[ \t]+\n/g, '\n')
        .replace(/\n{3,}/g, '\n\n')
        .trim();
    form.body_text = stripped;
}

// ── Save actions ───────────────────────────────────────────────────────────
function save(action = 'save') {
    form.action = action;
    form.patch(route('admin.email-templates.update', props.template.id), {
        preserveScroll: true,
    });
}

function deactivate() {
    deactivating.value = true;
}

function confirmDeactivate() {
    save('deactivate');
    deactivating.value = false;
}

function cancelDeactivate() {
    deactivating.value = false;
}

// ── Preview ────────────────────────────────────────────────────────────────
function sendPreview() {
    previewing.value = true;
    // Use router.post with the same payload — not useForm.post, since we don't
    // want to rebind errors from the preview path onto the editor form.
    form.transform((data) => ({
        subject: data.subject,
        body_html: data.body_html,
        body_text: data.body_text,
    }));
    form.post(route('admin.email-templates.preview', props.template.id), {
        preserveScroll: true,
        onFinish: () => {
            previewing.value = false;
            form.transform((data) => data); // reset transform
        },
    });
}

const tokens = computed(() => Object.entries(props.variables));
const hasErrors = computed(() => Object.keys(form.errors).length > 0);

function tokenLabel(name) {
    return '{' + '{' + name + '}' + '}';
}
</script>

<template>
    <Head
        :title="
            $t('super_admin.email_templates.meta_title', {
                label: template.label,
            })
        "
    />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-3">
                <Link
                    :href="route('admin.email-templates.index')"
                    class="rounded-md px-2 py-1 font-sans text-[13px] text-ink-secondary transition hover:bg-background hover:text-ink-primary"
                >
                    {{ $t('super_admin.email_templates.back_to_super_admin') }}
                </Link>
                <span class="text-ink-tertiary">/</span>
                <h1
                    class="font-display text-[20px] font-semibold tracking-h2 text-ink-primary"
                >
                    {{ template.label }}
                </h1>
                <span
                    v-if="template.is_active"
                    class="rounded-full bg-success-soft px-2.5 py-1 font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-success"
                >
                    {{ $t('super_admin.email_templates.status_live') }}
                </span>
                <span
                    v-else
                    class="rounded-full bg-accent-soft px-2.5 py-1 font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-accent"
                >
                    {{ $t('super_admin.email_templates.status_draft') }}
                </span>
            </div>
        </template>

        <div class="py-2">
            <!-- Flash banner -->
            <div
                v-if="flashSuccess"
                class="mb-4 rounded-md border border-success bg-success-soft px-4 py-3 font-sans text-[13px] text-success"
            >
                {{ flashSuccess }}
            </div>
            <div
                v-if="flashError"
                class="mb-4 rounded-md border border-danger bg-danger-soft px-4 py-3 font-sans text-[13px] text-danger"
            >
                {{ flashError }}
            </div>
            <div
                v-if="hasErrors"
                class="mb-4 rounded-md border border-danger bg-danger-soft px-4 py-3 font-sans text-[13px] text-danger"
            >
                {{ $t('super_admin.email_templates.errors_banner') }}
            </div>

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-[1fr_280px]">
                <!-- ── Form column ──────────────────────────────────────── -->
                <div class="flex flex-col gap-5">
                    <div class="rounded-lg border border-border bg-surface p-6">
                        <p class="font-sans text-[12px] text-ink-secondary">
                            <span class="font-medium text-ink-primary">
                                {{
                                    $t(
                                        'super_admin.email_templates.trigger_label',
                                    )
                                }}
                            </span>
                            {{ template.trigger_description }}
                        </p>
                    </div>

                    <!-- Subject -->
                    <div class="rounded-lg border border-border bg-surface p-6">
                        <div ref="subjectRef" @focusin="trackFocus('subject')">
                            <AppInput
                                id="email-subject"
                                :label="
                                    $t(
                                        'super_admin.email_templates.subject_label',
                                    )
                                "
                                v-model="form.subject"
                                :placeholder="
                                    $t(
                                        'super_admin.email_templates.subject_placeholder',
                                    )
                                "
                                :error="form.errors.subject"
                                :required="true"
                            />
                        </div>
                        <p class="mt-1 font-sans text-[12px] text-ink-tertiary">
                            {{
                                $t(
                                    'super_admin.email_templates.subject_counter',
                                    { length: form.subject.length },
                                )
                            }}
                        </p>
                    </div>

                    <!-- HTML body -->
                    <div class="rounded-lg border border-border bg-surface p-6">
                        <div class="flex items-end justify-between gap-2">
                            <label
                                for="body-html"
                                class="font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary"
                            >
                                {{
                                    $t(
                                        'super_admin.email_templates.body_html_label',
                                    )
                                }}<span class="ml-0.5 text-danger">*</span>
                            </label>
                            <p class="font-sans text-[12px] text-ink-tertiary">
                                {{
                                    $t(
                                        'super_admin.email_templates.body_html_help',
                                    )
                                }}
                            </p>
                        </div>
                        <textarea
                            id="body-html"
                            ref="bodyHtmlRef"
                            v-model="form.body_html"
                            rows="16"
                            :placeholder="
                                $t(
                                    'super_admin.email_templates.body_html_placeholder',
                                )
                            "
                            class="mt-2 w-full resize-y rounded-md border border-border-strong bg-surface px-3 py-2.5 font-mono text-[13px] leading-relaxed text-ink-primary placeholder-ink-tertiary transition focus:border-accent focus:outline-none focus:ring-[3px] focus:ring-accent-soft"
                            :class="{
                                'border-danger focus:border-danger focus:ring-danger-soft':
                                    form.errors.body_html,
                            }"
                            @focus="trackFocus('body_html')"
                        />
                        <p
                            v-if="form.errors.body_html"
                            class="mt-1 font-sans text-[13px] text-danger"
                        >
                            {{ form.errors.body_html }}
                        </p>
                    </div>

                    <!-- Plain text body -->
                    <div class="rounded-lg border border-border bg-surface p-6">
                        <div class="flex items-end justify-between gap-2">
                            <label
                                for="body-text"
                                class="font-sans text-[11px] font-semibold uppercase tracking-eyebrow text-ink-secondary"
                            >
                                {{
                                    $t(
                                        'super_admin.email_templates.body_text_label',
                                    )
                                }}<span class="ml-0.5 text-danger">*</span>
                            </label>
                            <button
                                type="button"
                                class="rounded-md px-2 py-1 font-sans text-[12px] font-medium text-accent transition hover:bg-accent-soft"
                                @click="generatePlainText"
                            >
                                {{
                                    $t(
                                        'super_admin.email_templates.generate_plain_text',
                                    )
                                }}
                            </button>
                        </div>
                        <textarea
                            id="body-text"
                            ref="bodyTextRef"
                            v-model="form.body_text"
                            rows="10"
                            :placeholder="
                                $t(
                                    'super_admin.email_templates.body_text_placeholder',
                                )
                            "
                            class="mt-2 w-full resize-y rounded-md border border-border-strong bg-surface px-3 py-2.5 font-mono text-[13px] leading-relaxed text-ink-primary placeholder-ink-tertiary transition focus:border-accent focus:outline-none focus:ring-[3px] focus:ring-accent-soft"
                            :class="{
                                'border-danger focus:border-danger focus:ring-danger-soft':
                                    form.errors.body_text,
                            }"
                            @focus="trackFocus('body_text')"
                        />
                        <p
                            v-if="form.errors.body_text"
                            class="mt-1 font-sans text-[13px] text-danger"
                        >
                            {{ form.errors.body_text }}
                        </p>
                    </div>

                    <!-- Action bar -->
                    <div
                        class="sticky bottom-0 z-10 rounded-lg border border-border bg-surface p-4 shadow-md"
                    >
                        <template v-if="deactivating">
                            <div class="flex items-center gap-3">
                                <p
                                    class="flex-1 font-sans text-[13px] text-ink-primary"
                                >
                                    {{
                                        $t(
                                            'super_admin.email_templates.deactivate_confirm_before',
                                        )
                                    }}
                                    <strong>{{ template.label }}</strong
                                    >{{
                                        $t(
                                            'super_admin.email_templates.deactivate_confirm_after',
                                        )
                                    }}
                                </p>
                                <button
                                    type="button"
                                    class="rounded-md bg-danger px-4 py-2 font-sans text-[13px] font-semibold text-white transition hover:bg-red-700"
                                    :disabled="form.processing"
                                    @click="confirmDeactivate"
                                >
                                    {{
                                        $t(
                                            'super_admin.email_templates.deactivate_yes',
                                        )
                                    }}
                                </button>
                                <button
                                    type="button"
                                    class="rounded-md border border-border-strong px-4 py-2 font-sans text-[13px] font-medium text-ink-secondary transition hover:bg-background"
                                    @click="cancelDeactivate"
                                >
                                    {{
                                        $t(
                                            'super_admin.email_templates.deactivate_cancel',
                                        )
                                    }}
                                </button>
                            </div>
                        </template>
                        <template v-else>
                            <div class="flex flex-wrap items-center gap-2">
                                <button
                                    type="button"
                                    class="rounded-md bg-accent px-4 py-2 font-sans text-[13px] font-semibold text-accent-on transition hover:bg-accent-hover disabled:cursor-not-allowed disabled:opacity-40"
                                    :disabled="form.processing"
                                    @click="save('save')"
                                >
                                    {{
                                        form.processing &&
                                        form.action === 'save'
                                            ? $t(
                                                  'super_admin.email_templates.save_draft_saving',
                                              )
                                            : $t(
                                                  'super_admin.email_templates.save_draft',
                                              )
                                    }}
                                </button>

                                <button
                                    v-if="!template.is_active"
                                    type="button"
                                    class="rounded-md bg-success px-4 py-2 font-sans text-[13px] font-semibold text-white transition hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-40"
                                    :disabled="form.processing"
                                    @click="save('activate')"
                                >
                                    {{
                                        form.processing &&
                                        form.action === 'activate'
                                            ? $t(
                                                  'super_admin.email_templates.save_activate_saving',
                                              )
                                            : $t(
                                                  'super_admin.email_templates.save_activate',
                                              )
                                    }}
                                </button>

                                <button
                                    v-if="template.is_active"
                                    type="button"
                                    class="rounded-md border border-danger px-4 py-2 font-sans text-[13px] font-semibold text-danger transition hover:bg-danger-soft"
                                    :disabled="form.processing"
                                    @click="deactivate"
                                >
                                    {{
                                        $t(
                                            'super_admin.email_templates.deactivate_button',
                                        )
                                    }}
                                </button>

                                <div class="ml-auto flex items-center gap-2">
                                    <button
                                        type="button"
                                        class="rounded-md border border-border-strong px-4 py-2 font-sans text-[13px] font-medium text-ink-primary transition hover:bg-background disabled:cursor-not-allowed disabled:opacity-40"
                                        :disabled="
                                            form.processing ||
                                            !form.body_html.trim() ||
                                            !form.subject.trim()
                                        "
                                        @click="sendPreview"
                                    >
                                        {{
                                            previewing
                                                ? $t(
                                                      'super_admin.email_templates.send_preview_sending',
                                                  )
                                                : $t(
                                                      'super_admin.email_templates.send_preview',
                                                  )
                                        }}
                                    </button>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                <!-- ── Sidebar ──────────────────────────────────────────── -->
                <aside class="flex flex-col gap-4">
                    <div class="rounded-lg border border-border bg-surface p-5">
                        <h3
                            class="font-sans text-[13px] font-semibold text-ink-primary"
                        >
                            {{
                                $t(
                                    'super_admin.email_templates.variables_heading',
                                )
                            }}
                        </h3>
                        <p
                            class="mt-1 font-sans text-[12px] text-ink-secondary"
                        >
                            {{
                                $t('super_admin.email_templates.variables_help')
                            }}
                        </p>

                        <ul class="mt-4 flex flex-col gap-3">
                            <li v-for="[name, def] in tokens" :key="name">
                                <button
                                    type="button"
                                    class="w-full rounded-md border border-border-strong bg-background px-3 py-2 text-left font-mono text-[12px] text-accent transition hover:bg-accent-soft hover:text-accent"
                                    @click="insertToken(name)"
                                >
                                    {{ tokenLabel(name) }}
                                </button>
                                <p
                                    class="mt-1 font-sans text-[12px] text-ink-secondary"
                                >
                                    {{ def.description }}
                                </p>
                                <p
                                    class="font-sans text-[11px] text-ink-tertiary"
                                >
                                    {{
                                        $t(
                                            'super_admin.email_templates.variables_preview_label',
                                        )
                                    }}
                                    <span class="font-mono">{{
                                        def.sample
                                    }}</span>
                                </p>
                            </li>
                        </ul>
                    </div>

                    <div
                        class="rounded-lg border border-dashed border-border-strong bg-background p-4"
                    >
                        <h4
                            class="font-sans text-[12px] font-semibold uppercase tracking-eyebrow text-ink-secondary"
                        >
                            {{ $t('super_admin.email_templates.tips_heading') }}
                        </h4>
                        <ul
                            class="mt-2 list-disc pl-4 font-sans text-[12px] leading-relaxed text-ink-secondary"
                        >
                            <li>
                                {{
                                    $t(
                                        'super_admin.email_templates.tips.chrome',
                                    )
                                }}
                            </li>
                            <li>
                                {{
                                    $t(
                                        'super_admin.email_templates.tips.plain_html',
                                    )
                                }}
                            </li>
                            <li>
                                {{
                                    $t(
                                        'super_admin.email_templates.tips.preview',
                                    )
                                }}
                            </li>
                            <li>
                                {{
                                    $t(
                                        'super_admin.email_templates.tips.activate',
                                    )
                                }}
                            </li>
                        </ul>
                    </div>
                </aside>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
