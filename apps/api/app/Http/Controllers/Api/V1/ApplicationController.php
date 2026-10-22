<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesOrganization;
use App\Http\Controllers\Controller;
use App\Models\InsuranceApplication;
use App\Models\InsurerSubmission;
use App\Models\Quote;
use App\Services\AuditLogger;
use App\Services\CarrierAppointmentGuard;
use App\Support\Uuid;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ApplicationController extends Controller
{
    use ResolvesOrganization;

    public function __construct(
        private readonly AuditLogger $audit,
        private readonly CarrierAppointmentGuard $appointments,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $this->assertPermission($request, 'quote.create');
        $org = $this->organization($request);

        $apps = InsuranceApplication::query()
            ->with(['customer:id,display_name', 'quote:id,reference,status'])
            ->where('organization_id', $org->id)
            ->orderByDesc('updated_at')
            ->paginate(25);

        return response()->json($apps);
    }

    public function store(Request $request): JsonResponse
    {
        $this->assertPermission($request, 'quote.create');
        $org = $this->organization($request);
        $user = $request->user();

        $data = $request->validate([
            'quote_id' => ['required', 'uuid', Rule::exists('quotes', 'id')->where('organization_id', $org->id)],
            'payload' => ['nullable', 'array'],
            'disclosure_acknowledged' => ['required', 'accepted'],
        ]);

        $quote = Quote::query()->where('organization_id', $org->id)->findOrFail($data['quote_id']);

        if ($quote->premium_classification !== 'insurer_confirmed') {
            throw new HttpException(422, 'Applications require an insurer-confirmed quote. Indicative/awaiting quotes cannot proceed.');
        }

        try {
            $this->appointments->assertCanQuote($org, $quote->carrier_id);
        } catch (\RuntimeException $e) {
            throw new HttpException(422, $e->getMessage());
        }

        $application = InsuranceApplication::query()->create([
            'organization_id' => $org->id,
            'customer_id' => $quote->customer_id,
            'quote_id' => $quote->id,
            'carrier_id' => $quote->carrier_id,
            'carrier_product_id' => $quote->carrier_product_id,
            'owner_user_id' => $user->id,
            'status' => 'draft',
            'payload' => $data['payload'] ?? ['from_quote' => $quote->reference],
            'disclosure_acknowledged' => true,
        ]);

        $this->audit->log(
            action: 'application.created',
            subjectType: InsuranceApplication::class,
            subjectId: $application->id,
            after: ['reference' => $application->reference],
            actor: $user,
        );

        return response()->json(['data' => $application], 201);
    }

    public function submit(Request $request, string $id): JsonResponse
    {
        $this->assertPermission($request, 'quote.create');
        $org = $this->organization($request);
        $user = $request->user();

        $application = InsuranceApplication::query()
            ->where('organization_id', $org->id)
            ->findOrFail($id);

        if ($application->status !== 'draft') {
            throw new HttpException(422, 'Only draft applications can be submitted.');
        }

        $channel = $request->validate([
            'channel' => ['nullable', Rule::in(['manual', 'sandbox'])],
        ])['channel'] ?? 'manual';

        $application->status = 'awaiting_insurer';
        $application->submitted_at = now();
        $application->save();

        $submission = InsurerSubmission::query()->create([
            'id' => Uuid::v7(),
            'organization_id' => $org->id,
            'subject_type' => 'application',
            'subject_id' => $application->id,
            'carrier_id' => $application->carrier_id,
            'channel' => $channel,
            'status' => $channel === 'sandbox' ? 'sandbox_ack' : 'queued',
            'integration_state' => $channel === 'sandbox' ? 'SANDBOX' : 'NOT_CONFIGURED',
            'packet' => [
                'application_reference' => $application->reference,
                'quote_id' => $application->quote_id,
                'notice' => 'Manual/sandbox submission — underwriting decision remains with insurer.',
            ],
            'staff_notes' => 'Staff queue for insurer handoff. No fabricated binder.',
            'created_by' => $user->id,
            'acknowledged_at' => $channel === 'sandbox' ? now() : null,
        ]);

        $this->audit->log(
            action: 'application.submitted',
            subjectType: InsuranceApplication::class,
            subjectId: $application->id,
            after: ['status' => $application->status, 'submission_id' => $submission->id],
            actor: $user,
        );

        return response()->json([
            'data' => [
                'application' => $application,
                'submission' => $submission,
            ],
        ]);
    }
}
