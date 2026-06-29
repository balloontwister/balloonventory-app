import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

/**
 * Timestamp formatters that render the absolute (UTC) instant in the signed-in
 * user's saved timezone preference, falling back to the browser's timezone and
 * then UTC. Every viewer therefore sees the same moment expressed in their own
 * frame.
 *
 * Use `timeZoneLabel` (a short abbreviation such as "EDT") in a table/section
 * heading so the zone is shown once rather than repeated on every row. Only
 * surface it where an actual clock time is displayed — date-only columns don't
 * need it.
 */
export function useDateTime() {
    const page = usePage();

    // `undefined` lets Intl fall back to the browser's timezone when the user
    // has no saved preference.
    const timeZone = () => page.props.auth?.user?.timezone || undefined;

    function formatDate(value) {
        if (!value) {
            return '—';
        }

        return new Date(value).toLocaleDateString(undefined, {
            timeZone: timeZone(),
            month: 'short',
            day: 'numeric',
            year: 'numeric',
        });
    }

    function formatDateTime(value) {
        if (!value) {
            return '—';
        }

        return new Date(value).toLocaleString(undefined, {
            timeZone: timeZone(),
            month: 'short',
            day: 'numeric',
            year: 'numeric',
            hour: 'numeric',
            minute: '2-digit',
        });
    }

    const timeZoneLabel = computed(() => {
        try {
            const parts = new Intl.DateTimeFormat(undefined, {
                timeZone: timeZone(),
                timeZoneName: 'short',
            }).formatToParts(new Date());

            return parts.find((part) => part.type === 'timeZoneName')?.value ?? '';
        } catch {
            return '';
        }
    });

    return { formatDate, formatDateTime, timeZoneLabel };
}
