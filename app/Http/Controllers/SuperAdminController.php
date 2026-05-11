<?php

namespace App\Http\Controllers;

use App\Models\EmailLog;
use App\Models\Sku;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class SuperAdminController extends Controller
{
    public function dashboard(Request $request): Response
    {
        $showArchived = $request->boolean('showArchived');

        return Inertia::render('SuperAdmin/Dashboard', [
            'stats' => $this->stats(),
            'recentUsers' => $this->recentUsers(),
            'recentlyActive' => $this->recentlyActive(),
            'pendingVerification' => $this->pendingVerification(),
            'recentlyPruned' => $this->recentlyPruned(),
            'emailByDay' => $this->emailByDay(),
            'emailByMonth' => $this->emailByMonth(),
            'supportTickets' => $this->supportTickets($showArchived),
            'showArchivedTickets' => $showArchived,
        ]);
    }

    private function stats(): array
    {
        return [
            'total_users' => User::count(),
            'verified_users' => User::whereNotNull('email_verified_at')->count(),
            'unverified_users' => User::whereNull('email_verified_at')->count(),
            'new_users_7d' => User::where('created_at', '>=', now()->subDays(7))->count(),
            'new_users_30d' => User::where('created_at', '>=', now()->subDays(30))->count(),
            'shared_skus' => Sku::whereNull('owned_by_business_id')->count(),
            'emails_sent_30d' => EmailLog::where('sent_at', '>=', now()->subDays(30))->count(),
            'emails_sent_today' => EmailLog::whereDate('sent_at', today())->count(),
        ];
    }

    private function recentUsers(): array
    {
        return User::withTrashed()
            ->orderByDesc('created_at')
            ->limit(10)
            ->get(['id', 'name', 'email', 'original_email', 'email_verified_at', 'created_at', 'deleted_at'])
            ->toArray();
    }

    private function recentlyPruned(): array
    {
        return User::onlyTrashed()
            ->orderByDesc('deleted_at')
            ->limit(20)
            ->get(['id', 'name', 'original_email', 'created_at', 'deleted_at'])
            ->toArray();
    }

    private function recentlyActive(): array
    {
        return User::whereNotNull('last_login_at')
            ->orderByDesc('last_login_at')
            ->limit(10)
            ->get(['id', 'name', 'email', 'last_login_at'])
            ->toArray();
    }

    private function pendingVerification(): array
    {
        return User::whereNull('email_verified_at')
            ->orderBy('created_at')
            ->get(['id', 'name', 'email', 'created_at'])
            ->toArray();
    }

    private function emailByDay(): array
    {
        return EmailLog::select(
                DB::raw('DATE(sent_at) as date'),
                DB::raw('COUNT(*) as count'),
                'mailable'
            )
            ->where('sent_at', '>=', now()->subDays(30))
            ->groupBy('date', 'mailable')
            ->orderBy('date')
            ->get()
            ->toArray();
    }

    private function supportTickets(bool $showArchived = false): array
    {
        return SupportTicket::with('replies')
            ->when(
                $showArchived,
                fn ($q) => $q->whereNotNull('archived_at'),
                fn ($q) => $q->whereNull('archived_at'),
            )
            ->orderByDesc('created_at')
            ->limit(50)
            ->get(['id', 'user_id', 'user_name', 'user_email', 'subject', 'body', 'archived_at', 'created_at'])
            ->toArray();
    }

    private function emailByMonth(): array
    {
        return EmailLog::select(
                DB::raw("DATE_FORMAT(sent_at, '%Y-%m') as month"),
                DB::raw('COUNT(*) as count'),
                'mailable'
            )
            ->where('sent_at', '>=', now()->subMonths(12))
            ->groupBy('month', 'mailable')
            ->orderBy('month')
            ->get()
            ->toArray();
    }
}
