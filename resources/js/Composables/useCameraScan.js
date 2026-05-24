import { onUnmounted, ref } from 'vue';

// How long to suppress repeat detections of the same barcode (ms). The
// BarcodeDetector loop and the Quagga path both honor this.
const COOLDOWN_MS = 1500;

// How often the native loop calls detect(). Lower = faster recognition, higher
// CPU/battery. 200ms is a good balance on modern devices.
const SCAN_INTERVAL_MS = 200;

// Formats the native BarcodeDetector should recognize.
const BARCODE_FORMATS = [
    'ean_13',
    'ean_8',
    'upc_a',
    'upc_e',
    'code_128',
    'code_39',
];

// Quagga2's equivalent readers list.
const QUAGGA_READERS = [
    'ean_reader',
    'ean_8_reader',
    'upc_reader',
    'upc_e_reader',
    'code_128_reader',
    'code_39_reader',
];

/**
 * Camera barcode scanning with a two-tier backend:
 *  - 'native' — uses the browser's BarcodeDetector API (Chrome/Edge, Android Chrome).
 *               We acquire the camera via getUserMedia() and feed frames from
 *               our own <video> element into BarcodeDetector.detect().
 *  - 'quagga' — uses @ericblade/quagga2, lazy-imported. Quagga manages its OWN
 *               camera stream and inserts its own <video> + <canvas> into a
 *               container element we hand it. We do NOT call getUserMedia
 *               ourselves on this path — doing so used to fight Quagga for the
 *               camera and silently broke scanning on iOS Safari.
 *
 * The mode is decided once on construction (via `checkSupport()`) and exposed
 * as `mode.value` so the component can render the right element (a <video>
 * for native, a container <div> for quagga).
 */
export function useCameraScan({ onDetected, onError } = {}) {
    const isScanning = ref(false);
    const isSupported = ref(false);
    const mode = ref(null); // 'native' | 'quagga' | null
    const error = ref(null);

    let nativeVideo = null;
    let quaggaContainer = null;

    let stream = null;
    let scanTimer = null;
    let cooldownTimer = null;
    let barcodeDetector = null;
    let quaggaInstance = null;
    let destroyed = false;

    function checkSupport() {
        // Prefer the native API where available — it's faster, lighter, and
        // doesn't need a 156KB library download.
        if (typeof window !== 'undefined' && 'BarcodeDetector' in window) {
            try {
                barcodeDetector = new window.BarcodeDetector({
                    formats: BARCODE_FORMATS,
                });
                mode.value = 'native';
                isSupported.value = true;
                return;
            } catch {
                // Constructor can throw if the requested formats aren't
                // supported. Fall through to quagga.
            }
        }

        // Fallback path: quagga2 will handle its own getUserMedia call inside
        // Quagga.init() — we just need to confirm the browser CAN request the
        // camera at all.
        if (
            typeof navigator !== 'undefined' &&
            navigator.mediaDevices?.getUserMedia
        ) {
            mode.value = 'quagga';
            isSupported.value = true;
            return;
        }

        mode.value = null;
        isSupported.value = false;
    }

    /**
     * Start scanning.
     *
     * For native mode, pass the <video> element you want the camera attached to.
     * For quagga mode, pass the container <div> Quagga should inject its own
     * video/canvas into.
     *
     * The component decides which element type to render based on `mode.value`.
     */
    async function start(element) {
        if (destroyed || !element) return;

        try {
            if (mode.value === 'native') {
                nativeVideo = element;
                await startNative();
            } else if (mode.value === 'quagga') {
                quaggaContainer = element;
                await startQuagga();
            } else {
                throw new Error(
                    'Camera scanning is not supported on this device.',
                );
            }
        } catch (e) {
            error.value = e;
            onError?.(e);
        }
    }

    async function startNative() {
        stream = await navigator.mediaDevices.getUserMedia({
            video: {
                facingMode: { ideal: 'environment' },
                width: { ideal: 1280 },
                height: { ideal: 720 },
            },
            audio: false,
        });

        if (destroyed) {
            stream.getTracks().forEach((t) => t.stop());
            stream = null;
            return;
        }

        nativeVideo.srcObject = stream;
        // iOS requires `playsinline` (handled at template level) AND a play()
        // call inside a user-gesture chain. The camera button click handles
        // that part — by the time we get here, we're already in that chain.
        await nativeVideo.play();

        isScanning.value = true;
        scheduleNativeDetect();
    }

    function scheduleNativeDetect() {
        if (destroyed || !isScanning.value) return;

        scanTimer = setTimeout(runNativeDetect, SCAN_INTERVAL_MS);
    }

    async function runNativeDetect() {
        if (
            destroyed ||
            !isScanning.value ||
            !barcodeDetector ||
            !nativeVideo
        ) {
            return;
        }

        try {
            // BarcodeDetector can choke if the video has zero dimensions
            // (camera not fully started); guard so the loop doesn't die.
            if (nativeVideo.readyState >= 2 && nativeVideo.videoWidth > 0) {
                const barcodes = await barcodeDetector.detect(nativeVideo);

                if (barcodes.length > 0 && !cooldownTimer) {
                    handleDetection(barcodes[0].rawValue);
                }
            }
        } catch {
            // Transient detect failures are normal (focus changes, frame
            // skips). Just keep looping.
        }

        scheduleNativeDetect();
    }

    async function startQuagga() {
        // Lazy import: 156KB only paid by browsers that need the fallback.
        const Quagga = (await import('@ericblade/quagga2')).default;

        if (destroyed) return;

        await new Promise((resolve, reject) => {
            Quagga.init(
                {
                    inputStream: {
                        name: 'Live',
                        type: 'LiveStream',
                        target: quaggaContainer,
                        constraints: {
                            facingMode: { ideal: 'environment' },
                            width: { ideal: 1280 },
                            height: { ideal: 720 },
                        },
                        area: {
                            top: '25%',
                            right: '10%',
                            left: '10%',
                            bottom: '25%',
                        },
                    },
                    locator: { patchSize: 'medium', halfSample: true },
                    decoder: { readers: QUAGGA_READERS },
                    locate: true,
                    // Workers can fail on iOS Safari and some embedded
                    // WebViews. Disable them when concurrency is low; the
                    // perf hit is small at 10fps.
                    numOfWorkers:
                        typeof navigator !== 'undefined' &&
                        navigator.hardwareConcurrency >= 4
                            ? 2
                            : 0,
                    frequency: 10,
                },
                (err) => (err ? reject(err) : resolve()),
            );
        });

        if (destroyed) {
            try {
                Quagga.stop();
            } catch {
                // ignore
            }
            return;
        }

        Quagga.start();
        quaggaInstance = Quagga;
        isScanning.value = true;

        Quagga.onDetected((result) => {
            if (destroyed || cooldownTimer) return;

            const value = result?.codeResult?.code;
            if (!value) return;

            handleDetection(value);
        });
    }

    function handleDetection(value) {
        if (
            typeof navigator !== 'undefined' &&
            typeof navigator.vibrate === 'function'
        ) {
            try {
                navigator.vibrate(50);
            } catch {
                // vibrate can throw in some sandboxed contexts
            }
        }

        onDetected?.(value);

        cooldownTimer = setTimeout(() => {
            cooldownTimer = null;
        }, COOLDOWN_MS);
    }

    function stop() {
        isScanning.value = false;

        if (scanTimer) {
            clearTimeout(scanTimer);
            scanTimer = null;
        }

        if (cooldownTimer) {
            clearTimeout(cooldownTimer);
            cooldownTimer = null;
        }

        if (quaggaInstance) {
            try {
                quaggaInstance.offDetected();
            } catch {
                // ignore — quagga2 sometimes throws if not initialized
            }
            try {
                quaggaInstance.stop();
            } catch {
                // ignore
            }
            quaggaInstance = null;
        }

        if (stream) {
            stream.getTracks().forEach((t) => t.stop());
            stream = null;
        }

        if (nativeVideo) {
            try {
                nativeVideo.pause();
                nativeVideo.srcObject = null;
            } catch {
                // ignore
            }
        }
    }

    function destroy() {
        destroyed = true;
        stop();
        barcodeDetector = null;
        nativeVideo = null;
        quaggaContainer = null;
    }

    checkSupport();

    onUnmounted(() => {
        destroy();
    });

    return { start, stop, isScanning, isSupported, mode, error };
}
