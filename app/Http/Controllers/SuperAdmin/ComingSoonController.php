<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Placeholder page for admin areas that are scaffolded but not built yet
 * (Subscriptions, Payments/Ledger, Affiliates, …). The area key is supplied by
 * the route via ->defaults('area', …) so a single controller serves them all.
 */
class ComingSoonController extends Controller
{
    public function __invoke(string $area): Response
    {
        return Inertia::render('SuperAdmin/ComingSoon', [
            'title' => __("super_admin.dashboard.nav.{$area}"),
            'blurb' => __("super_admin.coming_soon.{$area}"),
        ]);
    }
}
