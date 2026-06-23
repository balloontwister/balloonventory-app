<?php

namespace App\Http\Controllers;

use App\Models\BalloonList;
use App\Models\Bin;
use App\Models\Business;
use App\Models\BusinessInvitation;
use App\Models\BusinessSkuOverride;
use App\Models\ListItem;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Scopes\BusinessScope;
use App\Support\BusinessContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $business = Business::findOrFail(BusinessContext::currentId());

        $can = [
            'checkIn' => Gate::allows('inventory.check_in', $business),
            'checkOut' => Gate::allows('inventory.check_out', $business),
            'adjust' => Gate::allows('inventory.manual_adjust', $business),
            'addInventory' => Gate::allows('sku.create_private', $business) || Gate::allows('sku.edit_override', $business),
            'manageBusiness' => Gate::allows('business.edit_settings', $business),
            'viewCounts' => Gate::allows('inventory.view_counts', $business),
            'viewAuditLog' => Gate::allows('inventory.view_audit_log', $business),
            'jobView' => Gate::allows('job.view', $business),
        ];

        $lowStockData = $can['viewCounts']
            ? $this->buildLowStock()
            : ['items' => [], 'count' => 0];

        return Inertia::render('Dashboard', [
            'kpis' => [...$this->buildKpis(), 'lowStockCount' => $lowStockData['count']],
            'lowStock' => $can['viewCounts'] ? $lowStockData['items'] : [],
            'recentActivity' => $can['viewCounts'] ? $this->buildRecentActivity() : [],
            'nudges' => $this->buildNudges($user, $business),
            'pendingInvitations' => $this->buildPendingInvitations($user),
            'notifications' => $this->buildNotifications($user),
            'can' => $can,
        ]);
    }

    private function buildKpis(): array
    {
        $aggregate = StockLevel::selectRaw(
            'COUNT(DISTINCT sku_id) as distinct_skus, SUM(full_bags + open_bags) as total_bags'
        )->first();

        return [
            'distinctSkus' => (int) ($aggregate->distinct_skus ?? 0),
            'totalBags' => (int) ($aggregate->total_bags ?? 0),
            'binCount' => (int) Bin::count(),
        ];
    }

    /**
     * Favorites list items with planned_quantity set, flagged where sealed on-hand ≤ threshold.
     * Open bags are intentionally excluded from the on-hand tally.
     *
     * @return array{items: list<array<string,mixed>>, count: int}
     */
    private function buildLowStock(): array
    {
        $favoritesListId = BalloonList::where('is_business_favorites', true)->value('id');

        if (! $favoritesListId) {
            return ['items' => [], 'count' => 0];
        }

        $listItems = ListItem::where('list_id', $favoritesListId)
            ->whereNotNull('planned_quantity')
            ->with('sku:id,name')
            ->get();

        if ($listItems->isEmpty()) {
            return ['items' => [], 'count' => 0];
        }

        $skuIds = $listItems->pluck('sku_id');

        // One query: sealed on-hand per SKU across all bins (open_bags excluded by design).
        $onHandBySkuId = StockLevel::whereIn('sku_id', $skuIds)
            ->selectRaw('sku_id, SUM(full_bags) as on_hand')
            ->groupBy('sku_id')
            ->pluck('on_hand', 'sku_id');

        // One query: business-specific name overrides (BusinessScope auto-applied).
        $overrides = BusinessSkuOverride::whereIn('sku_id', $skuIds)
            ->get(['sku_id', 'custom_name'])
            ->keyBy('sku_id');

        $lowItems = $listItems
            ->map(function (ListItem $item) use ($onHandBySkuId, $overrides) {
                $onHand = (int) ($onHandBySkuId->get($item->sku_id) ?? 0);
                $threshold = (int) $item->planned_quantity;

                if ($onHand > $threshold) {
                    return null;
                }

                return [
                    'sku_id' => $item->sku_id,
                    'name' => $overrides->get($item->sku_id)?->custom_name ?? $item->sku?->name ?? '',
                    'on_hand' => $onHand,
                    'threshold' => $threshold,
                    'deficit' => $threshold - $onHand,
                ];
            })
            ->filter()
            ->sortByDesc('deficit')
            ->values();

        return [
            'items' => $lowItems->take(5)->all(),
            'count' => $lowItems->count(),
        ];
    }

    private function buildRecentActivity(): array
    {
        $movements = StockMovement::with([
            'sku:id,name',
            'user:id,name',
        ])
            ->orderByDesc('created_at')
            ->limit(6)
            ->get();

        $skuIds = $movements->pluck('sku_id')->unique()->filter();

        $overrides = $skuIds->isNotEmpty()
            ? BusinessSkuOverride::whereIn('sku_id', $skuIds)
                ->get(['sku_id', 'custom_name'])
                ->keyBy('sku_id')
            : collect();

        return $movements->map(fn (StockMovement $m) => [
            'id' => $m->id,
            'direction' => $m->direction->value,
            'full_bags_change' => $m->full_bags_change,
            'open_bags_change' => $m->open_bags_change,
            'sku_name' => $overrides->get($m->sku_id)?->custom_name ?? $m->sku?->name ?? '',
            'user_name' => $m->user?->name ?? '',
            'created_at' => $m->created_at,
        ])->all();
    }

    public function dismissNudge(Request $request): RedirectResponse
    {
        $businessScoped = ['clear_samples', 'onboarding', 'business_contact'];

        $validated = $request->validate([
            'key' => ['required', 'string', Rule::in([...$businessScoped, 'user_contact'])],
        ]);

        $key = in_array($validated['key'], $businessScoped)
            ? "{$validated['key']}:".BusinessContext::currentId()
            : $validated['key'];

        $user = $request->user();
        $dismissed = $user->dismissed_nudges ?? [];

        if (! in_array($key, $dismissed)) {
            $user->update(['dismissed_nudges' => [...$dismissed, $key]]);
        }

        return back();
    }

    /**
     * @return list<array{token: string, business_name: string, inviter_name: string, role_label: string}>
     */
    private function buildPendingInvitations(mixed $user): array
    {
        return BusinessInvitation::withoutGlobalScope(BusinessScope::class)
            ->with(['business', 'inviter'])
            ->where('invited_user_id', $user->id)
            ->where('status', BusinessInvitation::STATUS_PENDING)
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->get()
            ->map(fn (BusinessInvitation $inv) => [
                'token' => $inv->token,
                'business_name' => $inv->business->name,
                'inviter_name' => $inv->inviter->name,
                'role_label' => $this->roleLabel($inv->role),
            ])
            ->values()
            ->all();
    }

    /**
     * Unified, user-level notice feed (all businesses) from the notifications table.
     *
     * @return list<array{id: string, type: ?string, business_id: ?string, business_name: ?string, role_label: ?string, actor_name: ?string, created_at: mixed}>
     */
    private function buildNotifications(mixed $user): array
    {
        return $user->unreadNotifications()
            ->get()
            ->map(fn ($notification) => [
                'id' => $notification->id,
                'type' => $notification->data['type'] ?? null,
                'business_id' => $notification->data['business_id'] ?? null,
                'business_name' => $notification->data['business_name'] ?? null,
                'role_label' => $notification->data['role_label'] ?? null,
                'actor_name' => $notification->data['actor_name'] ?? null,
                'created_at' => $notification->created_at,
            ])
            ->all();
    }

    private function roleLabel(string $role): string
    {
        return match ($role) {
            'owner' => 'Owner',
            'manager' => 'Manager',
            'staff' => 'Artist',
            'guest' => 'Guest Artist',
            default => $role,
        };
    }

    private function buildNudges(mixed $user, Business $business): array
    {
        $dismissed = $user->dismissed_nudges ?? [];
        $bid = $business->id;
        $isDismissed = fn (string $key) => in_array($key, $dismissed);

        return [
            'hasSampleStock' => ! $isDismissed("clear_samples:{$bid}")
                && StockLevel::where('is_sample', true)->exists(),
            'emailVerified' => $user->email_verified_at !== null,
            'onboardingComplete' => $business->onboarding_completed_at !== null
                || $isDismissed("onboarding:{$bid}"),
            'userContactIncomplete' => ! $isDismissed('user_contact') && empty($user->phone),
            'businessContactIncomplete' => ! $isDismissed("business_contact:{$bid}")
                && empty($business->phone) && empty($business->contact_email),
        ];
    }
}
