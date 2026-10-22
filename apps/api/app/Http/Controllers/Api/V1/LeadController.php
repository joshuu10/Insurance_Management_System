<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesOrganization;
use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LeadController extends Controller
{
    use ResolvesOrganization;

    public function __construct(private readonly AuditLogger $audit)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $this->assertPermission($request, 'customer.view');
        $org = $this->organization($request);

        $leads = Lead::query()
            ->with(['customer:id,display_name', 'owner:id,name'])
            ->where('organization_id', $org->id)
            ->when($request->query('stage'), fn ($q, $stage) => $q->where('stage', $stage))
            ->orderByDesc('updated_at')
            ->paginate(25);

        return response()->json($leads);
    }

    public function store(Request $request): JsonResponse
    {
        $this->assertPermission($request, 'quote.create');
        $org = $this->organization($request);
        $user = $request->user();

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'customer_id' => ['nullable', 'uuid', Rule::exists('customers', 'id')->where('organization_id', $org->id)],
            'source' => ['nullable', 'string', 'max:80'],
            'stage' => ['nullable', Rule::in(['new', 'contacted', 'qualified', 'assessment', 'quote_requested', 'insurer_response', 'negotiation', 'won', 'lost'])],
            'expected_premium_estimate' => ['nullable', 'numeric', 'min:0'],
            'expected_premium_note' => ['nullable', 'string', 'max:500'],
            'blockers' => ['nullable', 'string'],
        ]);

        $lead = Lead::query()->create([
            ...$data,
            'organization_id' => $org->id,
            'branch_id' => $user->branch_id,
            'owner_user_id' => $user->id,
            'source' => $data['source'] ?? 'manual',
            'stage' => $data['stage'] ?? 'new',
            'expected_premium_note' => $data['expected_premium_note'] ?? 'Estimate only — not an insurer-confirmed premium.',
            'currency' => 'TZS',
        ]);

        $this->audit->log(
            action: 'lead.created',
            subjectType: Lead::class,
            subjectId: $lead->id,
            after: $lead->only(['title', 'stage']),
            actor: $user,
        );

        return response()->json(['data' => $lead->load(['customer', 'owner'])], 201);
    }

    public function updateStage(Request $request, string $id): JsonResponse
    {
        $this->assertPermission($request, 'quote.create');
        $org = $this->organization($request);

        $data = $request->validate([
            'stage' => ['required', Rule::in(['new', 'contacted', 'qualified', 'assessment', 'quote_requested', 'insurer_response', 'negotiation', 'won', 'lost'])],
        ]);

        $lead = Lead::query()->where('organization_id', $org->id)->findOrFail($id);
        $before = ['stage' => $lead->stage];
        $lead->update(['stage' => $data['stage']]);

        $this->audit->log(
            action: 'lead.stage_updated',
            subjectType: Lead::class,
            subjectId: $lead->id,
            before: $before,
            after: ['stage' => $lead->stage],
            actor: $request->user(),
        );

        return response()->json(['data' => $lead]);
    }
}
