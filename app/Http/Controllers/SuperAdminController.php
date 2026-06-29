<?php

namespace App\Http\Controllers;

use App\Enums\FeedbackStatus;
use App\Models\BarcodeLinkAudit;
use App\Models\Business;
use App\Models\EmailLog;
use App\Models\LoginEvent;
use App\Models\Sku;
use App\Models\SkuFeedback;
use App\Models\SupportTicket;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

class SuperAdminController extends Controller
{
    /**
     * The admin landing dashboard: a role-aware grid of summary cards, one per
     * admin area, each linking to its detail page. Only lightweight counts are
     * computed here — the detail (user tables, ticket threads, email volume,
     * template list) lives on the individual area pages.
     */
    public function dashboard(): Response
    {
        return Inertia::render('SuperAdmin/Dashboard', [
            'summary' => [
                'users' => [
                    'total' => User::count(),
                    'new_7d' => User::where('created_at', '>=', now()->subDays(7))->count(),
                    'frozen' => User::whereNotNull('frozen_at')->count(),
                ],
                'businesses' => [
                    'total' => Business::count(),
                    'new_7d' => Business::where('created_at', '>=', now()->subDays(7))->count(),
                    'frozen' => Business::whereNotNull('frozen_at')->count(),
                ],
                'catalog' => [
                    'skus' => Sku::whereNull('owned_by_business_id')->count(),
                ],
                'feedback' => [
                    'open' => SkuFeedback::where('status', FeedbackStatus::Open)->count(),
                ],
                'tickets' => [
                    'open' => SupportTicket::whereNull('archived_at')->count(),
                ],
                'barcode' => [
                    'total' => BarcodeLinkAudit::whereNull('reverted_at')->count(),
                    'recent' => BarcodeLinkAudit::where('created_at', '>=', now()->subDays(7))->count(),
                ],
                'email' => [
                    'sent_30d' => EmailLog::where('sent_at', '>=', now()->subDays(30))->count(),
                    'today' => EmailLog::whereDate('sent_at', today())->count(),
                ],
                'login' => [
                    'failed_7d' => LoginEvent::whereIn('event', [LoginEvent::FAILED, LoginEvent::LOCKOUT])
                        ->where('created_at', '>=', now()->subDays(7))
                        ->count(),
                ],
            ],
        ]);
    }
}
