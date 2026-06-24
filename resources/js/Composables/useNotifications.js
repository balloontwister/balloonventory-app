// Shared notification rendering helpers, used by NotificationCard, the bell
// dropdown, and (later) the notifications page. Keep the type → message map in
// one place so every surface renders a notification identically.

const MESSAGE_KEYS = {
    business_access_granted: 'dashboard.notifications.business_access_granted',
    invitation_accepted: 'dashboard.notifications.invitation_accepted',
    member_left: 'dashboard.notifications.member_left',
    member_role_changed: 'dashboard.notifications.member_role_changed',
    site_admin_granted: 'dashboard.notifications.site_admin_granted',
    site_admin_revoked: 'dashboard.notifications.site_admin_revoked',
    account_frozen: 'dashboard.notifications.account_frozen',
    account_thawed: 'dashboard.notifications.account_thawed',
};

export function notificationMessageKey(notification) {
    return MESSAGE_KEYS[notification.type] ?? null;
}

export function notificationMessageParams(notification) {
    return {
        role: notification.role_label ?? '',
        business: notification.business_name ?? '',
        name: notification.actor_name ?? '',
    };
}

// Compact relative time ("now", "5m", "3h", "2d") with a date fallback past a
// week. Units stay short and language-neutral; the full timestamp lives on the
// notifications page.
export function notificationTimeAgo(iso) {
    if (!iso) {
        return '';
    }

    const then = new Date(iso).getTime();
    if (Number.isNaN(then)) {
        return '';
    }

    const seconds = Math.max(0, Math.floor((Date.now() - then) / 1000));

    if (seconds < 60) {
        return 'now';
    }
    if (seconds < 3600) {
        return `${Math.floor(seconds / 60)}m`;
    }
    if (seconds < 86400) {
        return `${Math.floor(seconds / 3600)}h`;
    }
    if (seconds < 604800) {
        return `${Math.floor(seconds / 86400)}d`;
    }

    return new Date(iso).toLocaleDateString();
}
