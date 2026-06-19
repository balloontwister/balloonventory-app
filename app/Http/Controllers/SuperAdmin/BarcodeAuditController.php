<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\BarcodeLinkAudit;
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
            ->with(['user:id,name', 'business:id,name'])
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
}
