import { onMounted, onUnmounted, ref } from 'vue';

const SCAN_MIN_LENGTH = 4;
const SCAN_TIMEOUT_MS = 80; // scanners complete in <80ms; human typing is slower

export function useScanField(onScan) {
    const inputRef = ref(null);
    let buffer = '';
    let bufferTimer = null;

    function focusInput() {
        inputRef.value?.focus();
    }

    function flushBuffer() {
        if (buffer.length >= SCAN_MIN_LENGTH) {
            onScan?.(buffer);
        }
        buffer = '';
        clearTimeout(bufferTimer);
        bufferTimer = null;
    }

    function handleGlobalKeydown(e) {
        const isOtherInput =
            e.target !== inputRef.value &&
            (e.target.tagName === 'INPUT' ||
                e.target.tagName === 'TEXTAREA' ||
                e.target.isContentEditable);

        if (e.key === 'Enter') {
            if (buffer.length >= SCAN_MIN_LENGTH) {
                flushBuffer();
                focusInput();
                e.preventDefault();
            }
            return;
        }

        if (e.key.length === 1 && !e.ctrlKey && !e.metaKey && !e.altKey) {
            if (!isOtherInput) {
                buffer += e.key;
                clearTimeout(bufferTimer);
                bufferTimer = setTimeout(() => {
                    buffer = '';
                }, SCAN_TIMEOUT_MS);
            }
        }
    }

    onMounted(() => {
        document.addEventListener('keydown', handleGlobalKeydown);
        focusInput();
    });

    onUnmounted(() => {
        document.removeEventListener('keydown', handleGlobalKeydown);
        clearTimeout(bufferTimer);
    });

    return { inputRef, focusInput };
}
