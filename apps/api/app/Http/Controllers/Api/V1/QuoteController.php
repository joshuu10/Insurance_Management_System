<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesOrganization;
use App\Http\Controllers\Controller;
use App\Models\CarrierProduct;
use App\Models\Quote;
use App\Services\AuditLogger;
use App\Services\CarrierAppointmentGuard;
use App\Services\InsurerSubmissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpKernel\Exception\HttpException;

class QuoteController extends Controller
{
    use ResolvesOrganization;

    public function __construct(
        private readonly AuditLogger $audit,
        private readonly CarrierAppointmentGuard $appointments,
        private readonly InsurerSubmissionService $submissions,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $this->assertPermission($request, 'quote.create');
        $org = $this->organization($request);

        $quotes = Quote::query()
            ->with(['customer:id,display_name', 'carrier:id,legal_name,is_illustrative', 'carrierProduct:id,name'])
            ->where('organization_id', $org->id)
            ->orderByDesc('updated_at')
            ->paginate(25);

        return response()->json($quotes);
    }

    public function store(Request $request): JsonResponse
    {
        $this->assertPermission($request, 'quote.create');
        $org = $this->organization($request);
        $user = $request->user();

        $data = $request->validate([
            'customer_id' => ['required', 'uuid', Rule::exists('customers', 'id')->where('organization_id', $org->id)],
            'carrier_product_id' => ['required', 'uuid', 'exists:carrier_products,id'],
            'lead_id' => ['nullable', 'uuid', Rule::exists('leads', 'id')->where('organization_id', $org->id)],
            'risk_summary' => ['nullable', 'array'],
            'answers' => ['nullable', 'array'],
            'caveats' => ['nullable', 'string'],
        ]);

        $product = CarrierProduct::query()->with('versions')->findOrFail($data['carrier_product_id']);

        try {
            $this->appointments->assertCanQuote($org, $product->carrier_id);
        } catch (\RuntimeException $e) {
            throw new HttpException(422, $e->getMessage());
        }

        $version = $product->versions->firstWhere('is_current', true);

        $quote = Quote::query()->create([
            'organization_id' => $org->id,
            'branch_id' => $user->branch_id,
            'customer_id' => $data['customer_id'],
            'lead_id' => $data['lead_id'] ?? null,
            'carrier_id' => $product->carrier_id,
            'carrier_product_id' => $product->id,
            'product_version_id' => $version?->id,
            'owner_user_id' => $user->id,
            'status' => 'draft',
            'premium_classification' => 'awaiting_insurer',
            'premium_amount' => null,
            'currency' => 'TZS',
            'risk_summary' => $data['risk_summary'] ?? null,
            'answers' => $data['answers'] ?? null,
            'caveats' => $data['caveats'] ?? 'Premium awaiting authorized insurer confirmation. No cover is bound by this draft.',
            'valid_until' => now()->addDays(14),
        ]);

        $this->audit->log(
            action: 'quote.created',
            subjectType: Quote::class,
            subjectId: $quote->id,
            after: ['reference' => $quote->reference, 'status' => $quote->status],
            actor: $user,
        );

        return response()->json(['data' => $quote->load(['customer', 'carrier', 'carrierProduct'])], 201);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $this->assertPermission($request, 'quote.create');
        $org = $this->organization($request);

        $quote = Quote::query()
            ->with(['customer', 'carrier', 'carrierProduct'])
            ->where('organization_id', $org->id)
            ->findOrFail($id);

        return response()->json(['data' => $quote]);
    }

    public function validateDraft(Request $request, string $id): JsonResponse
    {
        $this->assertPermission($request, 'quote.create');
        $org = $this->organization($request);
        $quote = Quote::query()->where('organization_id', $org->id)->findOrFail($id);

        if ($quote->status !== 'draft') {
            throw new HttpException(422, 'Only draft quotes can be validated.');
        }

        $quote->status = 'validated';
        $quote->save();

        $this->audit->log(
            action: 'quote.validated',
            subjectType: Quote::class,
            subjectId: $quote->id,
            after: ['status' => 'validated'],
            actor: $request->user(),
        );

        return response()->json(['data' => $quote]);
    }

    public function submitToInsurer(Request $request, string $id): JsonResponse
    {
        $this->assertPermission($request, 'quote.create');
        $org = $this->organization($request);
        $quote = Quote::query()->where('organization_id', $org->id)->findOrFail($id);

        $data = $request->validate([
            'channel' => ['nullable', Rule::in(['manual', 'sandbox'])],
        ]);

        if ($quote->status === 'draft') {
            $quote->status = 'validated';
            $quote->save();
        }

        try {
            $this->appointments->assertCanQuote($org, $quote->carrier_id);
            $submission = $this->submissions->submitQuote(
                $quote,
                $request->user(),
                $data['channel'] ?? 'sandbox',
            );
        } catch (\RuntimeException|\InvalidArgumentException $e) {
            throw new HttpException(422, $e->getMessage());
        }

        return response()->json([
            'data' => [
                'quote' => $quote->fresh(),
                'submission' => $submission,
                'notice' => 'Submission recorded. No live insurer confirmation was fabricated.',
            ],
        ]);
    }

    public function recordInsurerConfirmation(Request $request, string $id): JsonResponse
    {
        $this->assertPermission($request, 'quote.approve');
        $org = $this->organization($request);
        $quote = Quote::query()->where('organization_id', $org->id)->findOrFail($id);

        $data = $request->validate([
            'premium_amount' => ['required', 'numeric', 'min:0'],
            'evidence_note' => ['required', 'string', 'min:10', 'max:2000'],
        ]);

        try {
            $quote = $this->submissions->recordInsurerConfirmedPremium(
                $quote,
                $request->user(),
                (string) $data['premium_amount'],
                $data['evidence_note'],
            );
        } catch (\RuntimeException|\InvalidArgumentException $e) {
            throw new HttpException(422, $e->getMessage());
        }

        return response()->json(['data' => $quote]);
    }
}
