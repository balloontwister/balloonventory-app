<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Enums\FeedbackStatus;
use App\Http\Controllers\Controller;
use App\Models\SkuFeedback;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
            ->with(['user:id,name', 'business:id,name', 'resolvedBy:id,name'])
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
}
