<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesOrganization;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CustomerController extends Controller
{
    use ResolvesOrganization;

    public function __construct(private readonly AuditLogger $audit)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $this->assertPermission($request, 'customer.view');
        $org = $this->organization($request);

        $customers = Customer::query()
            ->where('organization_id', $org->id)
            ->when($request->query('q'), function ($q, $term) {
                $q->where(function ($inner) use ($term) {
                    $inner->where('display_name', 'like', "%{$term}%")
                        ->orWhere('email', 'like', "%{$term}%")
                        ->orWhere('phone_e164', 'like', "%{$term}%");
                });
            })
            ->orderBy('display_name')
            ->paginate(25);

        return response()->json($customers);
    }

    public function store(Request $request): JsonResponse
    {
        $this->assertPermission($request, 'customer.view');
        $org = $this->organization($request);
        $user = $request->user();

        $data = $request->validate([
            'type' => ['required', Rule::in(['individual', 'corporate'])],
            'display_name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email'],
            'phone_e164' => ['nullable', 'string', 'max:20'],
            'preferred_locale' => ['nullable', 'string', 'max:10'],
        ]);

        $customer = Customer::query()->create([
            ...$data,
            'organization_id' => $org->id,
            'branch_id' => $user->branch_id,
            'owner_user_id' => $user->id,
            'kyc_status' => 'not_started',
        ]);

        $this->audit->log(
            action: 'customer.created',
            subjectType: Customer::class,
            subjectId: $customer->id,
            after: $customer->only(['display_name', 'type', 'email']),
            actor: $user,
        );

        return response()->json(['data' => $customer], 201);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $this->assertPermission($request, 'customer.view');
        $org = $this->organization($request);

        $customer = Customer::query()
            ->where('organization_id', $org->id)
            ->findOrFail($id);

        return response()->json(['data' => $customer]);
    }
}
