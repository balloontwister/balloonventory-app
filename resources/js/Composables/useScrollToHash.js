import { onMounted } from 'vue';

/**
 * On mount, scroll the element whose id matches the current URL hash into view.
 *
 * Used by list pages so that returning from a detail page (via a back link
 * ending in `#sku-<id>` / `#color-<id>`) re-centres the originating row.
 *
 * `getElementById` is used rather than `querySelector` so a bare `#` or any
 * unusual hash can never throw an invalid-selector error. The scroll is
 * deferred one animation frame so it runs after Inertia resets scroll
 * position on a visit, rather than racing it.
 */
export function useScrollToHash() {
    onMounted(() => {
        const id = window.location.hash.slice(1);

        if (!id) {
            return;
        }

        requestAnimationFrame(() => {
            const el = document.getElementById(id);

            if (el) {
                el.scrollIntoView({ block: 'center' });
            }
        });
    });
}
