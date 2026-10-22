<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesOrganization;
use App\Http\Controllers\Controller;
use App\Models\Integration;
use App\Services\AuditLogger;
use App\Services\Integrations\IntegrationHub;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

class IntegrationController extends Controller
{
    use ResolvesOrganization;

    public function __construct(
        private readonly IntegrationHub $hub,
        private readonly AuditLogger $audit,
    ) {
    }

    public function catalog(Request $request): JsonResponse
    {
        $this->assertPermission($request, 'integration.manage');

        return response()->json(['data' => $this->hub->catalog()]);
    }

    public function index(Request $request): JsonResponse
    {
        $this->assertPermission($request, 'integration.manage');
        $org = $this->organization($request);
        $this->hub->ensureDefaults($org);

        $rows = Integration::query()
            ->where('organization_id', $org->id)
            ->orderBy('provider_key')
            ->get();

        return response()->json([
            'data' => $rows,
            'notice' => 'Statuses reflect adapter readiness. SANDBOX never equals live insurer/TIRA confirmation.',
        ]);
    }

    public function enableSandbox(Request $request, string $id): JsonResponse
    {
        $this->assertPermission($request, 'integration.manage');
        $org = $this->organization($request);
        $integration = Integration::query()->where('organization_id', $org->id)->findOrFail($id);

        try {
            $integration = $this->hub->enableSandbox($integration);
        } catch (\RuntimeException $e) {
            throw new HttpException(422, $e->getMessage());
        }

        $this->audit->log(
            action: 'integration.sandbox_enabled',
            subjectType: Integration::class,
            subjectId: $integration->id,
            after: ['status' => $integration->status],
            actor: $request->user(),
        );

        return response()->json(['data' => $integration]);
    }

    public function sandboxProbe(Request $request, string $id): JsonResponse
    {
        $this->assertPermission($request, 'integration.manage');
        $org = $this->organization($request);
        $integration = Integration::query()->where('organization_id', $org->id)->findOrFail($id);

        try {
            $run = $this->hub->runSandboxProbe($integration);
        } catch (\RuntimeException $e) {
            throw new HttpException(422, $e->getMessage());
        }

        return response()->json([
            'data' => $run,
            'notice' => 'Sandbox probe only — fabricated live confirmations are forbidden.',
        ]);
    }

    public function updateBranding(Request $request): JsonResponse
    {
        $this->assertPermission($request, 'tenant.configure');
        $org = $this->organization($request);

        $data = $request->validate([
            'primary_color' => ['nullable', 'string', 'max:20'],
            'accent_color' => ['nullable', 'string', 'max:20'],
            'logo_url' => ['nullable', 'url', 'max:500'],
            'display_name' => ['nullable', 'string', 'max:255'],
        ]);

        $branding = array_merge($org->branding ?? [], $data);
        $org->branding = $branding;
        $org->save();

        return response()->json(['data' => $org->only(['id', 'legal_name', 'branding'])]);
    }
}
