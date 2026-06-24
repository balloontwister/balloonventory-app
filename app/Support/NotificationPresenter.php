<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Notifications\DatabaseNotification;

/**
 * Maps stored notifications to the frontend item shape. Single source of the
 * shape for both the shared `notifications` prop (bell + dropdown) and the
 * dashboard / notifications page.
 */
class NotificationPresenter
{
    /**
     * @return array{id: string, type: ?string, business_id: ?string, business_name: ?string, role_label: ?string, actor_name: ?string, created_at: mixed, read_at: mixed}
     */
    public static function present(DatabaseNotification $notification): array
    {
        $data = $notification->data;

        return [
            'id' => $notification->id,
            'type' => $data['type'] ?? null,
            'business_id' => $data['business_id'] ?? null,
            'business_name' => $data['business_name'] ?? null,
            'role_label' => $data['role_label'] ?? null,
            'actor_name' => $data['actor_name'] ?? null,
            'created_at' => $notification->created_at,
            'read_at' => $notification->read_at,
        ];
    }

    /**
     * The latest notifications (read and unread), newest first.
     *
     * @return list<array<string, mixed>>
     */
    public static function recent(User $user, int $limit = 10, bool $unreadOnly = false): array
    {
        $query = $unreadOnly ? $user->unreadNotifications() : $user->notifications();

        return $query
            ->limit($limit)
            ->get()
            ->map(fn (DatabaseNotification $n) => self::present($n))
            ->all();
    }

    /**
     * A page of notifications for the notification center, newest first.
     * Pass $filter = 'unread' to limit to unread.
     */
    public static function paginated(User $user, ?string $filter = null, int $perPage = 20): LengthAwarePaginator
    {
        $query = $filter === 'unread' ? $user->unreadNotifications() : $user->notifications();

        return $query->paginate($perPage)
            ->withQueryString()
            ->through(fn (DatabaseNotification $n) => self::present($n));
    }
}
