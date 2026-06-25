<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\DistributorCatalogProposal;
use App\Models\Sku;
use App\Services\Distributors\DistributorProposalReviewService;
use App\Services\Distributors\ProposalPromotionResult;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class DistributorProposalController extends Controller
{
    public function __construct(private DistributorProposalReviewService $service) {}

    public function index(Request $request): Response
    {
        $request->validate([
            'status' => ['nullable', Rule::in([
                DistributorCatalogProposal::STATUS_PENDING,
                DistributorCatalogProposal::STATUS_AUTO_APPROVED,
                DistributorCatalogProposal::STATUS_APPROVED,
                DistributorCatalogProposal::STATUS_REJECTED,
            ])],
            'brand' => ['nullable', 'string', 'max:100'],
            'confidence' => ['nullable', Rule::in(['high', 'low'])],
        ]);

        $filters = $request->only(['status', 'brand', 'confidence']);

        return Inertia::render('SuperAdmin/Distributors/Proposals', [
            'proposals' => $this->service->paginate($filters),
            'filters' => $filters,
            'references' => $this->service->referenceOptions(),
            'gaps' => $this->service->referenceGaps(),
            'pendingCount' => DistributorCatalogProposal::pending()->count(),
        ]);
    }

    public function approve(Request $request, DistributorCatalogProposal $proposal): RedirectResponse
    {
        $result = $this->service->approve($proposal, $request->user()->id);

        return back()->with(...$this->flashForResult($result));
    }

    public function reject(Request $request, DistributorCatalogProposal $proposal): RedirectResponse
    {
        // A proposal that already materialised a catalog SKU can't be unwound by
        // a reject here — removing the product goes through normal catalog
        // deletion. Guard server-side so a direct request can't orphan the SKU.
        if ($proposal->resulting_sku_id !== null) {
            return back()->with('warning', __('flash.distributor_proposals.reject_blocked_has_sku'));
        }

        $this->service->reject($proposal, $request->user()->id);

        return back()->with('success', __('flash.distributor_proposals.rejected'));
    }

    public function mapToExisting(Request $request, DistributorCatalogProposal $proposal): RedirectResponse
    {
        $data = $request->validate([
            'sku_id' => ['required', 'string', 'exists:skus,id'],
        ]);

        try {
            $this->service->mapToExisting($proposal, Sku::findOrFail($data['sku_id']), $request->user()->id);
        } catch (ValidationException $e) {
            return back()->with('warning', $e->validator->errors()->first());
        }

        return back()->with('success', __('flash.distributor_proposals.mapped'));
    }

    public function update(Request $request, DistributorCatalogProposal $proposal): RedirectResponse
    {
        $data = $request->validate([
            'proposed_brand_id' => ['nullable', 'string', 'exists:brands,id'],
            'proposed_balloon_size_id' => ['nullable', 'string', 'exists:balloon_sizes,id'],
            'proposed_color_id' => ['nullable', 'string', 'exists:colors,id'],
            'proposed_count' => ['nullable', 'integer', 'min:1'],
            'proposed_warehouse_sku' => ['nullable', 'string', 'max:191'],
        ]);

        $this->service->edit($proposal, $data, $request->user()->id);

        return back()->with('success', __('flash.distributor_proposals.updated'));
    }

    /**
     * Translate a promotion outcome into a flash (level, message) pair.
     *
     * @return array{0: string, 1: string}
     */
    private function flashForResult(ProposalPromotionResult $result): array
    {
        return match ($result->status) {
            ProposalPromotionResult::STATUS_CREATED => ['success', __('flash.distributor_proposals.approved_created')],
            ProposalPromotionResult::STATUS_ALREADY_PROMOTED => ['success', __('flash.distributor_proposals.approved')],
            ProposalPromotionResult::STATUS_UPC_CONFLICT => ['warning', __('flash.distributor_proposals.approved_upc_conflict')],
            ProposalPromotionResult::STATUS_NEEDS_MAPPING => ['warning', __('flash.distributor_proposals.approved_needs_mapping', [
                'attributes' => implode(', ', $result->missingAttributes),
            ])],
            default => ['success', __('flash.distributor_proposals.approved')],
        };
    }
}
