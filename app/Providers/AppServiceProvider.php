<?php

namespace App\Providers;

use App\Models\BalloonList;
use App\Models\Business;
use App\Models\Job;
use App\Models\Membership;
use App\Models\PendingUpcScan;
use App\Models\Sku;
use App\Models\SkuErrorReport;
use App\Policies\BalloonListPolicy;
use App\Policies\BusinessPolicy;
use App\Policies\InventoryPolicy;
use App\Policies\JobPolicy;
use App\Policies\LocalPricePolicy;
use App\Policies\MembershipPolicy;
use App\Policies\PendingUpcScanPolicy;
use App\Policies\SkuErrorReportPolicy;
use App\Policies\SkuPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        // Shared hosting MariaDB instances often cap index key length at 1000 bytes.
        // utf8mb4 uses 4 bytes/char, so varchar(255) = 1020 bytes > 1000. Cap at 191.
        Schema::defaultStringLength(191);

        Vite::prefetch(concurrency: 3);

        $this->registerModelPolicies();
        $this->registerNamedGates();
    }

    private function registerModelPolicies(): void
    {
        Gate::policy(Sku::class, SkuPolicy::class);
        Gate::policy(Job::class, JobPolicy::class);
        Gate::policy(BalloonList::class, BalloonListPolicy::class);
        Gate::policy(Membership::class, MembershipPolicy::class);
        Gate::policy(Business::class, BusinessPolicy::class);
        Gate::policy(PendingUpcScan::class, PendingUpcScanPolicy::class);
        Gate::policy(SkuErrorReport::class, SkuErrorReportPolicy::class);
    }

    /**
     * Register named gate abilities following the $user->can('scope.action', $business) pattern
     * described in PERMISSIONS.md. All inventory/catalog/list/job gates take (User, Business).
     */
    private function registerNamedGates(): void
    {
        $inventory = app(InventoryPolicy::class);
        Gate::define('inventory.check_in', fn ($u, $b) => $inventory->checkIn($u, $b));
        Gate::define('inventory.check_out', fn ($u, $b) => $inventory->checkOut($u, $b));
        Gate::define('inventory.manual_adjust', fn ($u, $b) => $inventory->manualAdjust($u, $b));
        Gate::define('inventory.override_count', fn ($u, $b) => $inventory->overrideCount($u, $b));
        Gate::define('inventory.view_counts', fn ($u, $b) => $inventory->viewCounts($u, $b));
        Gate::define('inventory.view_audit_log', fn ($u, $b) => $inventory->viewAuditLog($u, $b));

        $sku = app(SkuPolicy::class);
        Gate::define('sku.create_private', fn ($u, $b) => $sku->createPrivate($u, $b));
        Gate::define('sku.edit_override', fn ($u, $b) => $sku->editOverride($u, $b));

        $list = app(BalloonListPolicy::class);
        Gate::define('list.view', fn ($u, $b) => $list->viewAny($u, $b));
        Gate::define('list.create', fn ($u, $b) => $list->create($u, $b));
        Gate::define('favorites.edit', fn ($u, $b) => $list->editFavorites($u, $b));

        $job = app(JobPolicy::class);
        Gate::define('job.view', fn ($u, $b) => $job->viewAny($u, $b));
        Gate::define('job.create', fn ($u, $b) => $job->create($u, $b));

        $lp = app(LocalPricePolicy::class);
        Gate::define('local_price.view', fn ($u, $b) => $lp->viewAny($u, $b));
        Gate::define('local_price.edit', fn ($u, $b) => $lp->manage($u, $b));

        $biz = app(BusinessPolicy::class);
        Gate::define('business.edit_settings', fn ($u, $b) => $biz->editSettings($u, $b));
    }
}
