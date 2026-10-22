<?php

namespace Tests\Feature;

use App\Models\Integration;
use App\Models\PlatformInvoice;
use App\Models\SaasTenant;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase5IntegrationsSaasTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DemoSeeder::class);
    }

    public function test_tenant_can_list_integrations_and_run_sandbox_probe(): void
    {
        $token = $this->loginTenant('admin@demo.wabantu.local');

        $list = $this->asToken($token)->getJson('/api/v1/integrations')->assertOk();
        $this->assertNotEmpty($list->json('data'));
        $this->assertSame('NOT_CONFIGURED', $list->json('data.0.status'));

        $id = collect($list->json('data'))->firstWhere('provider_key', 'tira_mis')['id'];

        $this->asToken($token)->postJson("/api/v1/integrations/{$id}/sandbox")->assertOk()
            ->assertJsonPath('data.status', 'SANDBOX');

        $probe = $this->asToken($token)->postJson("/api/v1/integrations/{$id}/sandbox-probe")->assertOk();
        $this->assertFalse($probe->json('data.response_meta.fabricated_confirmation'));
        $this->assertSame('succeeded', $probe->json('data.status'));
    }

    public function test_standalone_hides_platform_apis(): void
    {
        config(['wabantu.edition' => 'standalone']);

        $this->postJson('/api/v1/platform/auth/login', [
            'email' => 'platform@wabantu.local',
            'password' => 'Password1!',
        ])->assertNotFound();
    }

    public function test_saas_platform_provisions_tenant_and_issues_software_invoice(): void
    {
        config(['wabantu.edition' => 'saas']);

        $login = $this->postJson('/api/v1/platform/auth/login', [
            'email' => 'platform@wabantu.local',
            'password' => 'Password1!',
            'device_name' => 'phpunit',
        ])->assertOk();

        $platformToken = $login->json('token');
        $this->assertSame('platform', $login->json('user.scope'));

        $tenant = $this->asToken($platformToken)->postJson('/api/v1/platform/tenants', [
            'slug' => 'coastal-broker',
            'legal_name' => 'Coastal Brokerage Ltd (Fictional)',
            'plan_code' => 'starter',
        ])->assertCreated();

        $this->assertSame('active', $tenant->json('data.status'));
        $tenantId = $tenant->json('data.id');

        $invoice = $this->asToken($platformToken)->postJson("/api/v1/platform/tenants/{$tenantId}/invoices", [
            'amount' => 150000,
            'notes' => 'Monthly software fee',
        ])->assertCreated();

        $this->assertSame('platform_software', $invoice->json('data.ledger'));
        $this->assertNotSame('insurance_premium', $invoice->json('data.ledger'));

        $this->asToken($platformToken)->postJson("/api/v1/platform/tenants/{$tenantId}/suspend", [
            'reason' => 'Non-payment of software subscription',
        ])->assertOk()->assertJsonPath('data.status', 'suspended');

        $this->assertSame(1, SaasTenant::query()->where('slug', 'coastal-broker')->count());
        $this->assertSame(1, PlatformInvoice::query()->where('ledger', 'platform_software')->count());
    }

    public function test_tenant_user_cannot_call_platform_tenant_list(): void
    {
        config(['wabantu.edition' => 'saas']);
        $token = $this->loginTenant('admin@demo.wabantu.local');

        $this->asToken($token)->getJson('/api/v1/platform/tenants')->assertForbidden();
    }

    public function test_branding_update(): void
    {
        $token = $this->loginTenant('admin@demo.wabantu.local');

        $this->asToken($token)->putJson('/api/v1/settings/branding', [
            'primary_color' => '#0B1F3B',
            'display_name' => 'Demo Broker Brand',
        ])->assertOk()
            ->assertJsonPath('data.branding.display_name', 'Demo Broker Brand');
    }

    private function loginTenant(string $email): string
    {
        $this->flushHeaders();
        $this->app['auth']->forgetGuards();
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $email,
            'password' => 'Password1!',
            'device_name' => 'phpunit',
        ]);
        $response->assertOk();

        return $response->json('token');
    }

    private function asToken(string $token): static
    {
        $this->flushHeaders();
        $this->app['auth']->forgetGuards();

        return $this->withToken($token);
    }
}
