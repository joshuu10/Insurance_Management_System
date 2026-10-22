<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesOrganization;
use App\Http\Controllers\Controller;
use App\Models\Claim;
use App\Models\Policy;
use App\Services\ClaimService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ClaimController extends Controller
{
    use ResolvesOrganization;

    public function __construct(private readonly ClaimService $claims)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $this->assertPermission($request, 'claim.submit');
        $org = $this->organization($request);

        $rows = Claim::query()
            ->with(['customer:id,display_name', 'policy:id,carrier_policy_number'])
            ->where('organization_id', $org->id)
            ->orderByDesc('updated_at')
            ->paginate(25);

        return response()->json($rows);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $this->assertPermission($request, 'claim.submit');
        $org = $this->organization($request);

        $claim = Claim::query()
            ->with(['customer', 'policy', 'timeline'])
            ->where('organization_id', $org->id)
            ->findOrFail($id);

        return response()->json(['data' => $claim]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->assertPermission($request, 'claim.submit');
        $org = $this->organization($request);

        $data = $request->validate([
            'policy_id' => ['required', 'uuid', Rule::exists('policies', 'id')->where('organization_id', $org->id)],
            'incident_at' => ['required', 'date'],
            'incident_location' => ['nullable', 'string', 'max:255'],
            'description' => ['required', 'string', 'min:10'],
            'police_reference' => ['nullable', 'string', 'max:120'],
            'declared_amount' => ['nullable', 'numeric', 'min:0'],
            'urgency' => ['nullable', Rule::in(['normal', 'high', 'critical'])],
        ]);

        $policy = Policy::query()->where('organization_id', $org->id)->findOrFail($data['policy_id']);

        try {
            $claim = $this->claims->createFnol($policy, $request->user(), $data);
        } catch (\RuntimeException $e) {
            throw new HttpException(422, $e->getMessage());
        }

        return response()->json([
            'data' => $claim,
            'notice' => 'Coverage is provisional. Agency does not assure payable cover or settle claims without delegated authority.',
        ], 201);
    }

    public function submit(Request $request, string $id): JsonResponse
    {
        $this->assertPermission($request, 'claim.submit');
        $org = $this->organization($request);
        $claim = Claim::query()->where('organization_id', $org->id)->findOrFail($id);

        try {
            $claim = $this->claims->submit($claim, $request->user());
        } catch (\RuntimeException $e) {
            throw new HttpException(422, $e->getMessage());
        }

        return response()->json(['data' => $claim]);
    }

    public function acknowledge(Request $request, string $id): JsonResponse
    {
        $this->assertPermission($request, 'claim.review');
        $org = $this->organization($request);
        $claim = Claim::query()->where('organization_id', $org->id)->findOrFail($id);

        try {
            $claim = $this->claims->acknowledge($claim, $request->user());
        } catch (\RuntimeException $e) {
            throw new HttpException(422, $e->getMessage());
        }

        return response()->json(['data' => $claim]);
    }

    public function insurerUpdate(Request $request, string $id): JsonResponse
    {
        $this->assertPermission($request, 'claim.review');
        $org = $this->organization($request);
        $claim = Claim::query()->where('organization_id', $org->id)->findOrFail($id);

        $data = $request->validate([
            'status' => ['required', 'string'],
            'insurer_reference' => ['nullable', 'string', 'max:255'],
            'insurer_approved_amount' => ['nullable', 'numeric', 'min:0'],
            'message' => ['nullable', 'string'],
        ]);

        try {
            $claim = $this->claims->recordInsurerUpdate($claim, $request->user(), $data);
        } catch (\RuntimeException $e) {
            throw new HttpException(422, $e->getMessage());
        }

        return response()->json(['data' => $claim]);
    }
}
