<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesOrganization;
use App\Http\Controllers\Controller;
use App\Models\Cancellation;
use App\Models\Document;
use App\Models\Endorsement;
use App\Models\Policy;
use App\Services\PolicyLifecycleService;
use App\Support\Uuid;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpKernel\Exception\HttpException;

class PolicyController extends Controller
{
    use ResolvesOrganization;

    public function __construct(private readonly PolicyLifecycleService $lifecycle)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $this->assertPermission($request, 'policy.view');
        $org = $this->organization($request);

        $policies = Policy::query()
            ->with(['customer:id,display_name', 'carrier:id,legal_name', 'carrierProduct:id,name'])
            ->where('organization_id', $org->id)
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->orderByDesc('effective_to')
            ->paginate(25);

        return response()->json($policies);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $this->assertPermission($request, 'policy.view');
        $org = $this->organization($request);

        $policy = Policy::query()
            ->with(['customer', 'carrier', 'carrierProduct', 'endorsements', 'cancellations', 'renewalCases'])
            ->where('organization_id', $org->id)
            ->findOrFail($id);

        return response()->json(['data' => $policy]);
    }

    public function intake(Request $request): JsonResponse
    {
        $this->assertPermission($request, 'policy.issue.authorized');
        $org = $this->organization($request);

        $data = $request->validate([
            'customer_id' => ['required', 'uuid', Rule::exists('customers', 'id')->where('organization_id', $org->id)],
            'carrier_id' => ['required', 'uuid', 'exists:carriers,id'],
            'carrier_product_id' => ['nullable', 'uuid', 'exists:carrier_products,id'],
            'quote_id' => ['nullable', 'uuid', Rule::exists('quotes', 'id')->where('organization_id', $org->id)],
            'carrier_policy_number' => ['required', 'string', 'max:120'],
            'cover_note_number' => ['nullable', 'string', 'max:120'],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['required', 'date', 'after:effective_from'],
            'sum_insured' => ['nullable', 'numeric', 'min:0'],
            'premium_amount' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'verification_status' => ['nullable', Rule::in(['not_checked', 'submitted', 'pending', 'confirmed', 'mismatch', 'rejected', 'unavailable'])],
            'verification_reference' => ['nullable', 'string', 'max:255'],
            'source_document_ref' => ['nullable', 'string', 'max:255'],
            'insured_risks' => ['nullable', 'array'],
            'intake_notes' => ['nullable', 'string'],
            'status' => ['nullable', Rule::in(['pending_issuance', 'active', 'pending_premium_confirmation'])],
        ]);

        try {
            $policy = $this->lifecycle->intakeInsurerIssued($org, $request->user(), $data);
        } catch (\RuntimeException $e) {
            throw new HttpException(422, $e->getMessage());
        }

        return response()->json(['data' => $policy->load(['customer', 'carrier'])], 201);
    }

    public function requestEndorsement(Request $request, string $id): JsonResponse
    {
        $this->assertPermission($request, 'policy.endorse');
        $org = $this->organization($request);
        $policy = Policy::query()->where('organization_id', $org->id)->findOrFail($id);

        $data = $request->validate([
            'type' => ['required', Rule::in(['add_asset', 'remove_asset', 'change_address', 'change_sum_insured', 'change_beneficiary', 'other'])],
            'description' => ['required', 'string', 'min:5'],
            'requested_changes' => ['nullable', 'array'],
            'effective_from' => ['nullable', 'date'],
        ]);

        try {
            $endorsement = $this->lifecycle->requestEndorsement($policy, $request->user(), $data);
        } catch (\RuntimeException $e) {
            throw new HttpException(422, $e->getMessage());
        }

        return response()->json(['data' => $endorsement], 201);
    }

    public function applyEndorsement(Request $request, string $endorsementId): JsonResponse
    {
        $this->assertPermission($request, 'policy.endorse');
        $org = $this->organization($request);

        $endorsement = Endorsement::query()
            ->where('organization_id', $org->id)
            ->findOrFail($endorsementId);

        $data = $request->validate([
            'insurer_approval_ref' => ['required', 'string', 'min:3', 'max:255'],
        ]);

        try {
            $endorsement = $this->lifecycle->applyInsurerApprovedEndorsement(
                $endorsement,
                $request->user(),
                $data['insurer_approval_ref'],
            );
        } catch (\RuntimeException $e) {
            throw new HttpException(422, $e->getMessage());
        }

        return response()->json(['data' => $endorsement]);
    }

    public function requestCancellation(Request $request, string $id): JsonResponse
    {
        $this->assertPermission($request, 'policy.endorse');
        $org = $this->organization($request);
        $policy = Policy::query()->where('organization_id', $org->id)->findOrFail($id);

        $data = $request->validate([
            'reason' => ['required', 'string', 'min:5'],
            'effective_from' => ['required', 'date'],
        ]);

        try {
            $cancellation = $this->lifecycle->requestCancellation($policy, $request->user(), $data);
        } catch (\RuntimeException $e) {
            throw new HttpException(422, $e->getMessage());
        }

        return response()->json(['data' => $cancellation], 201);
    }

    public function confirmCancellation(Request $request, string $cancellationId): JsonResponse
    {
        $this->assertPermission($request, 'policy.issue.authorized');
        $org = $this->organization($request);

        $cancellation = Cancellation::query()
            ->where('organization_id', $org->id)
            ->findOrFail($cancellationId);

        $data = $request->validate([
            'insurer_confirmation_ref' => ['required', 'string', 'min:3'],
            'refund_amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        try {
            $cancellation = $this->lifecycle->confirmCancellation(
                $cancellation,
                $request->user(),
                $data['insurer_confirmation_ref'],
                isset($data['refund_amount']) ? (string) $data['refund_amount'] : null,
            );
        } catch (\RuntimeException $e) {
            throw new HttpException(422, $e->getMessage());
        }

        return response()->json(['data' => $cancellation]);
    }

    public function openRenewal(Request $request, string $id): JsonResponse
    {
        $this->assertPermission($request, 'policy.view');
        $org = $this->organization($request);
        $policy = Policy::query()->where('organization_id', $org->id)->findOrFail($id);

        $case = $this->lifecycle->openRenewalCase($policy, $request->user());

        return response()->json(['data' => $case], 201);
    }

    public function registerDocument(Request $request, string $id): JsonResponse
    {
        $this->assertPermission($request, 'policy.view');
        $org = $this->organization($request);
        $policy = Policy::query()->where('organization_id', $org->id)->findOrFail($id);

        $data = $request->validate([
            'purpose' => ['required', Rule::in(['policy_schedule', 'cover_note', 'kyc', 'invoice', 'other'])],
            'issuer' => ['required', Rule::in(['agency', 'insurer', 'customer', 'third_party'])],
            'original_filename' => ['required', 'string', 'max:255'],
            'mime_type' => ['nullable', 'string', 'max:120'],
            'content_hash' => ['nullable', 'string', 'max:128'],
            'classification' => ['nullable', 'string', 'max:40'],
        ]);

        $document = Document::query()->create([
            'id' => Uuid::v7(),
            'organization_id' => $org->id,
            'subject_type' => 'policy',
            'subject_id' => $policy->id,
            'purpose' => $data['purpose'],
            'issuer' => $data['issuer'],
            'original_filename' => $data['original_filename'],
            'mime_type' => $data['mime_type'] ?? null,
            'storage_path' => null, // binary upload deferred; metadata registry only in this slice
            'content_hash' => $data['content_hash'] ?? null,
            'classification' => $data['classification'] ?? 'internal',
            'status' => 'metadata_only',
            'uploaded_by' => $request->user()->id,
        ]);

        return response()->json([
            'data' => $document,
            'notice' => 'Document metadata registered. Binary object storage upload comes in a later hardening slice.',
        ], 201);
    }

    public function upcomingRenewals(Request $request): JsonResponse
    {
        $this->assertPermission($request, 'policy.view');
        $org = $this->organization($request);
        $withinDays = (int) $request->query('within_days', 60);

        $policies = Policy::query()
            ->with(['customer:id,display_name', 'carrier:id,legal_name'])
            ->where('organization_id', $org->id)
            ->where('status', 'active')
            ->whereBetween('effective_to', [now(), now()->addDays($withinDays)])
            ->orderBy('effective_to')
            ->get();

        return response()->json(['data' => $policies, 'within_days' => $withinDays]);
    }
}
