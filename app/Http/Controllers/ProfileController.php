<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Models\Business;
use App\Models\Membership;
use App\Services\Account\AccountDeletionService;
use App\Services\ImageAttachmentService;
use App\Support\Countries;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function __construct(
        private readonly ImageAttachmentService $images,
        private readonly AccountDeletionService $accountDeletion,
    ) {}

    public function edit(Request $request): Response
    {
        return Inertia::render('Profile/Edit', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => session('status'),
            'countries' => Countries::all(),
            'accountDeletion' => [
                'soleOwnerBusinesses' => $this->soleOwnerHandoffData($request->user()),
            ],
        ]);
    }

    /**
     * Businesses the user solely owns, each with the members eligible to take
     * over, so the delete dialog can offer a successor choice before submitting.
     *
     * @return array<int, array{id: string, name: string, candidates: array<int, array{userId: string, name: string, role: string}>}>
     */
    private function soleOwnerHandoffData($user): array
    {
        return $this->accountDeletion->soleOwnerBusinesses($user)
            ->map(fn (Business $business): array => [
                'id' => $business->id,
                'name' => $business->name,
                'candidates' => $this->accountDeletion->handoffCandidates($business, $user)
                    ->map(fn (Membership $membership): array => [
                        'userId' => $membership->user_id,
                        'name' => $membership->user->name,
                        'role' => $membership->role,
                    ])
                    ->all(),
            ])
            ->all();
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit');
    }

    public function updateAvatar(Request $request): RedirectResponse
    {
        $request->validate([
            'avatar' => ['nullable', 'mimes:png,jpg,jpeg,webp,svg', 'max:5120'],
        ]);

        $user = $request->user();

        if ($request->hasFile('avatar')) {
            $this->images->set($user, 'avatar', $request->file('avatar'));
        } elseif ($request->boolean('avatar_clear')) {
            $this->images->clear($user, 'avatar');
        }

        return back()->with('success', __('flash.profile.avatar_updated'));
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'password' => ['required', 'current_password'],
            'handoffs' => ['array'],
            'handoffs.*' => ['nullable', 'string'],
        ]);

        $user = $request->user();

        DB::transaction(function () use ($user, $validated) {
            $this->accountDeletion->handleSelfDeletion($user, $validated['handoffs'] ?? []);
        });

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
