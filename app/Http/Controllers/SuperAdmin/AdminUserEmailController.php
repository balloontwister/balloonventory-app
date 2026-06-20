<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Mail\AdminUserMessageMail;
use App\Models\AdminUserMessage;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

class AdminUserEmailController extends Controller
{
    /**
     * Send a one-off email composed by an admin to a specific user. The message
     * is wrapped in the standard mail chrome, logged (via LogSentEmail, which
     * resolves the user_id from the recipient address), and stored so the body
     * is reviewable later on the user detail page.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            // whereNull(deleted_at): never email a pruned account (exists ignores soft-deletes).
            'user_id' => ['required', 'string', Rule::exists('users', 'id')->whereNull('deleted_at')],
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:10000'],
            'template_key' => ['nullable', 'string', 'max:255'],
        ]);

        $recipient = User::findOrFail($data['user_id']);

        try {
            Mail::to($recipient)->send(new AdminUserMessageMail(
                $recipient,
                $data['subject'],
                $data['body'],
            ));
        } catch (\Throwable $e) {
            Log::error('Failed to send admin user email', [
                'recipient_id' => $recipient->id,
                'sender_id' => $request->user()->id,
                'error' => $e->getMessage(),
            ]);

            return back()->with('warning', __('flash.user_email.failed'));
        }

        AdminUserMessage::create([
            'user_id' => $recipient->id,
            'sender_user_id' => $request->user()->id,
            'subject' => $data['subject'],
            'body' => $data['body'],
            'template_key' => $data['template_key'] ?? null,
        ]);

        return back()->with('success', __('flash.user_email.sent', ['name' => $recipient->name]));
    }

    /**
     * Lightweight typeahead for the composer's recipient picker.
     */
    public function search(Request $request): JsonResponse
    {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $term = $data['q'] ?? '';

        $users = User::query()
            ->when($term !== '', fn ($q) => $q->where(fn ($inner) => $inner
                ->where('name', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%")))
            ->orderBy('name')
            ->limit(15)
            ->get(['id', 'name', 'email']);

        return response()->json(['users' => $users]);
    }
}
