<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\BarcodeLinkAudit;
use App\Models\Sku;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BarcodeAuditController extends Controller
{
    public function index(Request $request): Response
    {
        $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $audits = BarcodeLinkAudit::query()
            ->with(['user:id,name', 'business:id,name', 'revertedBy:id,name'])
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = $request->input('search');
                $q->where(fn ($inner) => $inner
                    ->where('barcode', 'like', "%{$term}%")
                    ->orWhere('sku_name', 'like', "%{$term}%"));
            })
            ->orderByDesc('created_at')
            ->paginate(50)
            ->withQueryString();

        return Inertia::render('SuperAdmin/BarcodeAudits/Index', [
            'audits' => $audits,
            'filters' => $request->only(['search']),
        ]);
    }

    /**
     * Undo a barcode link: clear the code off the SKU (only if it still holds the
     * exact value this audit recorded — never clobber a newer link) and stamp the
     * audit row reverted, so the trail shows who undid it and when.
     */
    public function revert(Request $request, BarcodeLinkAudit $audit): RedirectResponse
    {
        if ($audit->reverted_at !== null) {
            return back()->with('warning', __('super_admin.dashboard.barcode_audits.already_reverted'));
        }

        $sku = Sku::withTrashed()->find($audit->sku_id);

        if ($sku !== null && $sku->{$audit->field} === $audit->barcode) {
            $sku->{$audit->field} = null;
            $sku->save();
        }

        $audit->reverted_at = now();
        $audit->reverted_by_user_id = $request->user()->id;
        $audit->save();

        return back()->with('success', __('super_admin.dashboard.barcode_audits.reverted_flash'));
    }
}
