<script setup>
import { ref, watch, onMounted, computed } from 'vue';
import { trans } from 'laravel-vue-i18n';
import { useCameraScan } from '@/Composables/useCameraScan';

const emit = defineEmits(['detected', 'error', 'close']);

const videoRef = ref(null);
const containerRef = ref(null);
const flash = ref(false);
const errorMsg = ref(null);

let flashTimer = null;

const { start, stop, isScanning, isSupported, mode, error } = useCameraScan({
    onDetected(value) {
        flash.value = true;
        clearTimeout(flashTimer);
        flashTimer = setTimeout(() => {
            flash.value = false;
        }, 300);
        emit('detected', value);
    },
    onError(e) {
        errorMsg.value = friendlyError(e);
        emit('error', e);
    },
});

const useNative = computed(() => mode.value === 'native');
const useQuagga = computed(() => mode.value === 'quagga');

function friendlyError(e) {
    // getUserMedia rejects with DOMException — surface the most useful piece.
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

onMounted(async () => {
    if (!isSupported.value) {
        errorMsg.value = trans('scan.camera_unsupported');
        return;
    }

    // Native path attaches the camera stream to our <video>. Quagga path hands
    // Quagga our container <div> and lets it inject its own video/canvas.
    const target = useNative.value ? videoRef.value : containerRef.value;
    await start(target);
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

            <!-- Scan region overlay (above the video, below the flash). -->
            <div
                v-if="isScanning"
                class="pointer-events-none absolute inset-0 flex items-center justify-center"
            >
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
                    style="background: rgba(34, 197, 94, 0.3)"
                />
            </Transition>

            <!-- Error / unsupported state -->
            <div
                v-if="errorMsg"
                class="absolute inset-0 flex flex-col items-center justify-center gap-2 bg-black/80 p-4"
            >
                <p class="text-center font-sans text-[14px] text-white">
                    {{ errorMsg }}
                </p>
            </div>
        </div>

        <!-- Close button -->
        <button
            type="button"
            class="absolute right-3 top-3 flex h-9 w-9 items-center justify-center rounded-full bg-black/50 text-white hover:bg-black/70"
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
