<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Enums\AdminLevel;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminUserController extends Controller
{
    public function index(): Response
    {
        $users = User::withTrashed()
            ->orderByDesc('created_at')
            ->get(['id', 'name', 'email', 'original_email', 'email_verified_at', 'admin_level', 'last_login_at', 'created_at', 'deleted_at']);

        return Inertia::render('SuperAdmin/Users/Index', [
            'users' => $users,
        ]);
    }

    public function promote(Request $request, User $user): RedirectResponse
    {
        abort_unless($request->user()->isSuperAdmin(), 403);
        abort_if($user->isSuperAdmin(), 422, 'A Super Admin cannot be promoted to Site Admin.');

        $user->admin_level = AdminLevel::SiteAdmin;
        $user->save();

        return back()->with('success', "{$user->name} has been promoted to Site Admin.");
    }

    public function demote(Request $request, User $user): RedirectResponse
    {
        abort_unless($request->user()->isSuperAdmin(), 403);
        abort_if($user->isSuperAdmin(), 422, 'Super Admin access cannot be removed from this screen.');
        abort_unless($user->isSiteAdmin(), 422, 'This user is not a Site Admin.');

        $user->admin_level = null;
        $user->save();

        return back()->with('success', "Site Admin access removed from {$user->name}.");
    }
}
