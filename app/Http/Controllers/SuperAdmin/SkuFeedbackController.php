<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Enums\FeedbackStatus;
use App\Http\Controllers\Controller;
use App\Mail\SkuFeedbackReplyMail;
use App\Models\SkuFeedback;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SkuFeedbackController extends Controller
{
    public function index(Request $request): Response
    {
        $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::enum(FeedbackStatus::class)],
        ]);

        $feedback = SkuFeedback::query()
            ->with([
                'user:id,name,email',
                'business:id,name',
                'resolvedBy:id,name',
                'replies' => fn ($q) => $q->with('user:id,name'),
            ])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = $request->input('search');
                $q->where(fn ($inner) => $inner
                    ->where('sku_name', 'like', "%{$term}%")
                    ->orWhere('suggested_value', 'like', "%{$term}%")
                    ->orWhere('note', 'like', "%{$term}%"));
            })
            ->orderByDesc('created_at')
            ->paginate(50)
            ->withQueryString();

        return Inertia::render('SuperAdmin/SkuFeedback/Index', [
            'feedback' => $feedback,
            'filters' => $request->only(['search', 'status']),
            'openCount' => SkuFeedback::where('status', FeedbackStatus::Open)->count(),
        ]);
    }

    /**
     * Move a feedback report through its review lifecycle. Resolving or dismissing
     * stamps who reviewed it and when; reopening clears that trail.
     */
    public function updateStatus(Request $request, SkuFeedback $feedback): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::enum(FeedbackStatus::class)],
        ]);

        $status = FeedbackStatus::from($data['status']);

        $feedback->status = $status;

        if ($status === FeedbackStatus::Open) {
            $feedback->resolved_at = null;
            $feedback->resolved_by_user_id = null;
        } else {
            $feedback->resolved_at = now();
            $feedback->resolved_by_user_id = $request->user()->id;
        }

        $feedback->save();

        return back()->with('success', __('super_admin.dashboard.feedback.updated_flash'));
    }

    /**
     * Reply to the user who reported the issue, closing the loop. The message is
     * emailed to the reporter, recorded against the report, and — if still open —
     * the report is marked resolved (stamped with the replying admin).
     */
    public function reply(Request $request, SkuFeedback $feedback): RedirectResponse
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'max:10000'],
        ]);

        $recipient = $feedback->user?->email;

        if (! $recipient) {
            return back()->with('warning', __('super_admin.dashboard.feedback.reply_no_email'));
        }

        // Keep the emailed report context in English, matching the rest of the
        // (non-localized) transactional email templates.
        $fieldLabel = __('inventory.show.feedback_field_'.$feedback->field, [], 'en');

        try {
            Mail::to($recipient)->send(new SkuFeedbackReplyMail(
                $feedback,
                $data['body'],
                $fieldLabel,
                $feedback->user->name,
            ));
        } catch (\Throwable $e) {
            Log::error('Failed to send item feedback reply', [
                'feedback_id' => $feedback->id,
                'error' => $e->getMessage(),
            ]);

            return back()->with('warning', __('flash.feedback.reply_failed'));
        }

        $feedback->replies()->create([
            'user_id' => $request->user()->id,
            'body' => $data['body'],
        ]);

        // Replying closes the loop: an open report becomes resolved.
        if ($feedback->status === FeedbackStatus::Open) {
            $feedback->status = FeedbackStatus::Resolved;
            $feedback->resolved_at = now();
            $feedback->resolved_by_user_id = $request->user()->id;
            $feedback->save();
        }

        return back()->with('success', __('super_admin.dashboard.feedback.reply_sent_flash'));
    }
}
