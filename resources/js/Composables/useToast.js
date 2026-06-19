import { reactive } from 'vue';

// Module-singleton toast store. Any component can import { pushToast } to raise
// a toast; <Toaster> renders them. Server flashes are bridged into this store by
// the Toaster itself (watching page.props.flash).
const toasts = reactive([]);

let nextId = 0;
const LIFETIME_MS = 4000;

/**
 * Show a toast.
 * @param {string} message
 * @param {'success'|'error'|'warning'|'info'} [type]
 */
function pushToast(message, type = 'success') {
    if (!message) return;
    const id = ++nextId;
    toasts.push({ id, message, type });
    setTimeout(() => dismissToast(id), LIFETIME_MS);
    return id;
}

function dismissToast(id) {
    const i = toasts.findIndex((t) => t.id === id);
    if (i !== -1) toasts.splice(i, 1);
}

export function useToast() {
    return { toasts, pushToast, dismissToast };
}

export { toasts, pushToast, dismissToast };
