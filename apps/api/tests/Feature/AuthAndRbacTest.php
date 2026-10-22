<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthAndRbacTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DemoSeeder::class);
    }

    public function test_health_endpoint_reports_standalone_edition(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('edition', 'standalone')
            ->assertJsonPath('integrations.tira', 'NOT_CONFIGURED');
    }

    public function test_login_returns_token_and_writes_audit(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@demo.wabantu.local',
            'password' => 'Password1!',
            'device_name' => 'phpunit',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['token', 'user' => ['id', 'email', 'roles', 'permissions']]);

        $this->assertDatabaseHas('audit_events', [
            'action' => 'auth.login',
        ]);
    }

    public function test_me_requires_authentication(): void
    {
        $this->getJson('/api/v1/me')->assertUnauthorized();
    }

    public function test_admin_can_complete_onboarding(): void
    {
        $token = $this->loginToken('admin@demo.wabantu.local');

        $response = $this->withToken($token)->putJson('/api/v1/onboarding/organization', [
            'intermediary_type' => 'brokerage',
            'licence_number' => 'TIRA-DEMO-0001',
            'licence_category' => 'broker',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.onboarding_completed', true)
            ->assertJsonPath('data.licence_number', 'TIRA-DEMO-0001');
    }

    public function test_agent_cannot_configure_tenant(): void
    {
        $token = $this->loginToken('agent@demo.wabantu.local');

        $this->withToken($token)->putJson('/api/v1/onboarding/organization', [
            'intermediary_type' => 'brokerage',
            'licence_number' => 'SHOULD-FAIL',
        ])->assertForbidden();
    }

    public function test_audit_events_cannot_be_deleted(): void
    {
        $this->loginToken('admin@demo.wabantu.local');
        $event = AuditEvent::query()->firstOrFail();

        $this->expectException(\RuntimeException::class);
        $event->delete();
    }

    public function test_invalid_credentials_are_rejected(): void
    {
        $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@demo.wabantu.local',
            'password' => 'wrong-password',
        ])->assertUnprocessable();
    }

    public function test_saas_edition_flag_is_readable_from_config(): void
    {
        config(['wabantu.edition' => 'saas']);

        $this->getJson('/api/v1/health')
            ->assertOk()
            ->assertJsonPath('edition', 'saas');
    }

    private function loginToken(string $email): string
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $email,
            'password' => 'Password1!',
            'device_name' => 'phpunit',
        ]);

        $response->assertOk();

        return $response->json('token');
    }
}
