<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesOrganization;
use App\Http\Controllers\Controller;
use App\Models\PremiumInstruction;
use App\Services\PolicyLifecycleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpKernel\Exception\HttpException;

class PremiumInstructionController extends Controller
{
    use ResolvesOrganization;

    public function __construct(private readonly PolicyLifecycleService $lifecycle)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $this->assertPermission($request, 'policy.view');
        $org = $this->organization($request);

        $rows = PremiumInstruction::query()
            ->with(['carrier:id,legal_name'])
            ->where('organization_id', $org->id)
            ->orderByDesc('created_at')
            ->paginate(25);

        return response()->json($rows);
    }

    public function store(Request $request): JsonResponse
    {
        $this->assertPermission($request, 'policy.issue.authorized');
        $org = $this->organization($request);

        $data = $request->validate([
            'policy_id' => ['nullable', 'uuid', Rule::exists('policies', 'id')->where('organization_id', $org->id)],
            'quote_id' => ['nullable', 'uuid', Rule::exists('quotes', 'id')->where('organization_id', $org->id)],
            'carrier_id' => ['required', 'uuid', 'exists:carriers,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency' => ['nullable', 'string', 'size:3'],
            'beneficiary_name' => ['required', 'string', 'max:255'],
            'beneficiary_account' => ['nullable', 'string', 'max:120'],
            'beneficiary_channel' => ['nullable', Rule::in(['bank_transfer', 'mobile_money', 'card_link'])],
            'payer_instructions' => ['nullable', 'string'],
            'routes_to_insurer' => ['nullable', 'boolean'],
        ]);

        try {
            $instruction = $this->lifecycle->createPremiumInstruction($org, $request->user(), $data);
        } catch (\RuntimeException $e) {
            throw new HttpException(422, $e->getMessage());
        }

        return response()->json([
            'data' => $instruction,
            'notice' => 'Insurer-directed payment instruction. Live payment provider remains NOT_CONFIGURED.',
        ], 201);
    }
}
