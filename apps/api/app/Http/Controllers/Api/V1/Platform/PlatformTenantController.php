<?php

namespace App\Http\Controllers\Api\V1\Platform;

use App\Http\Controllers\Controller;
use App\Models\PlatformInvoice;
use App\Models\PlatformUser;
use App\Models\SaasTenant;
use App\Models\SubscriptionPlan;
use App\Services\SaaS\TenantProvisioningService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class PlatformTenantController extends Controller
{
    public function __construct(private readonly TenantProvisioningService $provisioning)
    {
    }

    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string'],
        ]);

        /** @var PlatformUser|null $user */
        $user = PlatformUser::query()->where('email', $credentials['email'])->first();

        if (! $user || ! $user->is_active || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages(['email' => ['Invalid platform credentials.']]);
        }

        $token = $user->createToken($credentials['device_name'] ?? 'platform')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'scope' => 'platform',
            ],
            'notice' => 'Platform operators do not receive tenant policyholder PII by default.',
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $this->assertPlatform($request);

        return response()->json([
            'data' => SaasTenant::query()->with(['subscriptions.plan'])->orderBy('legal_name')->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->assertPlatform($request);

        $data = $request->validate([
            'slug' => ['required', 'alpha_dash', 'max:60'],
            'legal_name' => ['required', 'string', 'max:255'],
            'trading_name' => ['nullable', 'string', 'max:255'],
            'primary_domain' => ['nullable', 'string', 'max:255'],
            'plan_code' => ['nullable', 'string'],
            'intermediary_type' => ['nullable', 'string'],
        ]);

        try {
            $tenant = $this->provisioning->provision($data);
        } catch (\RuntimeException $e) {
            throw new HttpException(422, $e->getMessage());
        }

        return response()->json(['data' => $tenant], 201);
    }

    public function suspend(Request $request, string $id): JsonResponse
    {
        $this->assertPlatform($request);
        $tenant = SaasTenant::query()->findOrFail($id);

        $data = $request->validate([
            'reason' => ['required', 'string', 'min:5'],
        ]);

        $tenant = $this->provisioning->suspend($tenant, $data['reason']);

        return response()->json([
            'data' => $tenant,
            'notice' => 'Suspension must not unlawfully deny customers access to important records — export/retention policy applies.',
        ]);
    }

    public function issueInvoice(Request $request, string $id): JsonResponse
    {
        $this->assertPlatform($request);
        $tenant = SaasTenant::query()->findOrFail($id);

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'notes' => ['nullable', 'string'],
        ]);

        $invoice = $this->provisioning->issueSoftwareInvoice($tenant, (float) $data['amount'], $data['notes'] ?? '');

        return response()->json([
            'data' => $invoice,
            'notice' => 'Ledger=platform_software — never commingled with insurance premiums.',
        ], 201);
    }

    public function plans(Request $request): JsonResponse
    {
        $this->assertPlatform($request);

        return response()->json([
            'data' => SubscriptionPlan::query()->where('is_active', true)->orderBy('price_monthly')->get(),
        ]);
    }

    public function invoices(Request $request): JsonResponse
    {
        $this->assertPlatform($request);

        return response()->json([
            'data' => PlatformInvoice::query()->orderByDesc('created_at')->limit(50)->get(),
        ]);
    }

    private function assertPlatform(Request $request): void
    {
        $user = $request->user();
        if (! $user instanceof PlatformUser) {
            throw new HttpException(403, 'Platform authentication required.');
        }
    }
}
