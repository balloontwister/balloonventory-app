import { ref } from 'vue';

// The three Inventory sub-views, keyed by the InventoryTabs `active` value.
const ROUTE_BY_TAB = {
    items: 'inventory.index',
    bins: 'inventory.bins.index',
    lists: 'inventory.lists.index',
};

export const INVENTORY_ROUTES = Object.values(ROUTE_BY_TAB);

const STORAGE_KEY = 'inventory.lastView';
const DEFAULT_ROUTE = 'inventory.index';

function readStored() {
    try {
        const stored = window.localStorage.getItem(STORAGE_KEY);
        return INVENTORY_ROUTES.includes(stored) ? stored : DEFAULT_ROUTE;
    } catch {
        return DEFAULT_ROUTE;
    }
}

// Module-level ref so the layout's Inventory link reacts to view changes within
// the SPA, while localStorage backs it across full reloads and new sessions.
const lastInventoryRoute = ref(readStored());

/**
 * Remembers which Inventory view (By item / By bin / By list) the user last
 * looked at, so the Inventory nav entry points return them there. InventoryTabs
 * records the active tab on mount; the layout reads `lastInventoryRoute`.
 */
export function useInventoryView() {
    function remember(tab) {
        const routeName = ROUTE_BY_TAB[tab];
        if (!routeName) {
            return;
        }
        lastInventoryRoute.value = routeName;
        try {
            window.localStorage.setItem(STORAGE_KEY, routeName);
        } catch {
            /* ignore storage errors */
        }
    }

    return { lastInventoryRoute, remember };
}
