<script setup>
import { ref, watch, onMounted, onBeforeUnmount, computed } from 'vue';
import { trans } from 'laravel-vue-i18n';
import { useCameraScan } from '@/Composables/useCameraScan';

const emit = defineEmits(['detected', 'close']);

const videoRef = ref(null);
const containerRef = ref(null);
const flash = ref(false);
const errorMsg = ref(null);
const capturedValue = ref(null); // The detected UPC, shown briefly before close.

let flashTimer = null;
let captureTimer = null;

const CAPTURE_DISPLAY_MS = 700; // How long to show the "Got it!" confirmation.

const { start, stop, isScanning, isSupported, mode, error } = useCameraScan({
    onDetected(value) {
        // Bail if we've already captured (multiple frames can fire before the
        // cooldown engages on slower devices).
        if (capturedValue.value) return;

        flash.value = true;
        capturedValue.value = value;

        // Emit the detected value RIGHT AWAY so the parent can start the
        // lookup in parallel with the visible confirmation. The parent does
        // NOT close the modal on `detected` anymore — we own that timing
        // here and emit `close` after the user has had a moment to see what
        // was captured.
        emit('detected', value);

        clearTimeout(flashTimer);
        flashTimer = setTimeout(() => {
            flash.value = false;
        }, 300);

        clearTimeout(captureTimer);
        captureTimer = setTimeout(() => {
            // Stop scanning and tell the parent to close the modal. The
            // parent's loader/toast will then take over.
            stop();
            emit('close');
        }, CAPTURE_DISPLAY_MS);
    },
    onError(e) {
        // DON'T emit close on error. We surface the message inside the modal
        // and let the user choose to retry or close. This prevents the
        // confusing "camera disappears with no explanation" behavior.
        errorMsg.value = friendlyError(e);
    },
});

const useNative = computed(() => mode.value === 'native');
const useQuagga = computed(() => mode.value === 'quagga');

function friendlyError(e) {
    if (e?.name === 'NotAllowedError' || e?.name === 'PermissionDeniedError') {
        return trans('scan.camera_permission_denied');
    }
    if (e?.name === 'NotFoundError' || e?.name === 'DevicesNotFoundError') {
        return trans('scan.camera_not_found');
    }
    if (e?.name === 'NotReadableError') {
        return trans('scan.camera_in_use');
    }
    return e?.message ?? trans('scan.camera_error');
}

async function startCamera() {
    errorMsg.value = null;
    capturedValue.value = null;

    if (!isSupported.value) {
        errorMsg.value = trans('scan.camera_unsupported');
        return;
    }

    const target = useNative.value ? videoRef.value : containerRef.value;
    if (!target) return;

    await start(target);
}

onMounted(() => {
    startCamera();
});

onBeforeUnmount(() => {
    clearTimeout(flashTimer);
    clearTimeout(captureTimer);
});

watch(error, (e) => {
    if (e) {
        errorMsg.value = friendlyError(e);
    }
});

function handleClose() {
    stop();
    emit('close');
}

async function handleRetry() {
    stop();
    await startCamera();
}
</script>

<template>
    <div class="relative overflow-hidden rounded-lg bg-black">
        <!-- Viewfinder — native mode renders our own <video>; quagga mode
             renders a container that Quagga injects its own video/canvas into. -->
        <div class="relative aspect-[3/4] w-full sm:aspect-[4/3]">
            <video
                v-if="useNative"
                ref="videoRef"
                autoplay
                playsinline
                muted
                class="absolute inset-0 h-full w-full object-cover"
            />

            <div
                v-else-if="useQuagga"
                ref="containerRef"
                class="quagga-target absolute inset-0 h-full w-full overflow-hidden"
            />

            <!-- Scan region overlay + instruction text -->
            <div
                v-if="isScanning && !capturedValue"
                class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center gap-3"
            >
                <p
                    class="rounded bg-black/50 px-2 py-1 text-center font-sans text-[12px] text-white"
                >
                    {{ $t('scan.camera_hint') }}
                </p>
                <div
                    class="flex h-32 w-4/5 items-center justify-center rounded-lg border-2 border-white/60"
                >
                    <div
                        class="h-[2px] w-full animate-pulse"
                        style="
                            background: linear-gradient(
                                90deg,
                                transparent 0%,
                                #ef4444 50%,
                                transparent 100%
                            );
                        "
                    />
                </div>
            </div>

            <!-- Green flash on detection -->
            <Transition
                enter-active-class="transition duration-75 ease-out"
                enter-from-class="opacity-0"
                leave-active-class="transition duration-200 ease-in"
                leave-to-class="opacity-0"
            >
                <div
                    v-if="flash"
                    class="pointer-events-none absolute inset-0"
                    style="background: rgba(34, 197, 94, 0.35)"
                />
            </Transition>

            <!-- "Got it!" confirmation — visible from detection until close. -->
            <Transition
                enter-active-class="transition duration-150 ease-out"
                enter-from-class="opacity-0 scale-95"
                leave-active-class="transition duration-150 ease-in"
                leave-to-class="opacity-0"
            >
                <div
                    v-if="capturedValue"
                    class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center gap-2"
                >
                    <div
                        class="rounded-full bg-success px-4 py-1.5 font-sans text-[14px] font-semibold text-white shadow-pop"
                    >
                        {{ $t('scan.captured') }}
                    </div>
                    <div
                        class="rounded bg-black/70 px-3 py-1 font-mono text-[13px] text-white"
                    >
                        {{ capturedValue }}
                    </div>
                </div>
            </Transition>

            <!-- Error state with Retry — only shown when something failed. -->
            <div
                v-if="errorMsg"
                class="absolute inset-0 flex flex-col items-center justify-center gap-3 bg-black/85 p-4"
            >
                <p class="text-center font-sans text-[14px] text-white">
                    {{ errorMsg }}
                </p>
                <div class="flex gap-2">
                    <button
                        type="button"
                        class="rounded-md bg-accent px-4 py-2 font-sans text-[14px] font-semibold text-accent-on hover:bg-accent-hover"
                        @click="handleRetry"
                    >
                        {{ $t('scan.camera_retry') }}
                    </button>
                    <button
                        type="button"
                        class="rounded-md border border-white/30 bg-transparent px-4 py-2 font-sans text-[14px] text-white hover:bg-white/10"
                        @click="handleClose"
                    >
                        {{ $t('scan.close') }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Close (X) button — always available, top-right of the viewfinder. -->
        <button
            type="button"
            class="absolute right-3 top-3 flex h-10 w-10 items-center justify-center rounded-full bg-black/60 text-white hover:bg-black/80"
            :aria-label="$t('scan.close')"
            @click="handleClose"
        >
            <svg
                xmlns="http://www.w3.org/2000/svg"
                viewBox="0 0 20 20"
                fill="currentColor"
                class="h-5 w-5"
            >
                <path
                    d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z"
                />
            </svg>
        </button>
    </div>
</template>

<style scoped>
/* Quagga2 injects a <video> and a <canvas> directly into the target element
   with their own inline width/height attributes. Force both to fill the
   viewfinder so the overlay aligns visually. */
.quagga-target :deep(video),
.quagga-target :deep(canvas) {
    position: absolute;
    inset: 0;
    width: 100% !important;
    height: 100% !important;
    object-fit: cover;
}
</style>
