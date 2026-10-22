<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Policy;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerPortalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DemoSeeder::class);
    }

    private function portalToken(): string
    {
        $login = $this->postJson('/api/v1/auth/login', [
            'email' => 'customer@demo.wabantu.local',
            'password' => 'Password1!',
            'device_name' => 'test',
        ])->assertOk();

        return $login->json('token');
    }

    public function test_customer_login_marks_portal_user(): void
    {
        $this->postJson('/api/v1/auth/login', [
            'email' => 'customer@demo.wabantu.local',
            'password' => 'Password1!',
        ])
            ->assertOk()
            ->assertJsonPath('user.is_portal', true)
            ->assertJsonPath('user.roles.0', 'customer_portal')
            ->assertJsonStructure(['user' => ['customer_id', 'customer' => ['id', 'display_name']]]);
    }

    public function test_portal_lists_own_policies_only(): void
    {
        $token = $this->portalToken();
        $customer = Customer::query()->where('email', 'customer@demo.wabantu.local')->firstOrFail();

        $response = $this->withToken($token)->getJson('/api/v1/portal/policies');

        $response->assertOk()
            ->assertJsonPath('data.0.customer_id', $customer->id);

        foreach ($response->json('data') as $row) {
            $this->assertSame($customer->id, $row['customer_id']);
        }
    }

    public function test_portal_can_draft_and_submit_fnol_on_own_policy(): void
    {
        $token = $this->portalToken();
        $policy = Policy::query()->where('carrier_policy_number', 'UHAI-MOT-DEMO-1001')->firstOrFail();

        $create = $this->withToken($token)->postJson('/api/v1/portal/claims', [
            'policy_id' => $policy->id,
            'incident_at' => now()->subDay()->toIso8601String(),
            'description' => 'Rear bumper damage after parking collision (demo FNOL).',
            'urgency' => 'normal',
        ])->assertCreated();

        $claimId = $create->json('data.id');

        $this->withToken($token)
            ->postJson("/api/v1/portal/claims/{$claimId}/submit")
            ->assertOk()
            ->assertJsonPath('data.status', 'submitted');
    }

    public function test_portal_user_cannot_use_staff_finance(): void
    {
        $token = $this->portalToken();

        $this->withToken($token)
            ->getJson('/api/v1/finance/overview')
            ->assertForbidden();
    }

    public function test_staff_user_cannot_access_portal_routes(): void
    {
        $login = $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@demo.wabantu.local',
            'password' => 'Password1!',
        ])->assertOk();

        $this->withToken($login->json('token'))
            ->getJson('/api/v1/portal/policies')
            ->assertForbidden();
    }

    public function test_portal_cannot_claim_against_another_customer_policy(): void
    {
        $token = $this->portalToken();
        $customer = Customer::query()->where('email', 'customer@demo.wabantu.local')->firstOrFail();
        $orgId = $customer->organization_id;

        $other = Customer::query()->create([
            'organization_id' => $orgId,
            'branch_id' => $customer->branch_id,
            'type' => 'individual',
            'display_name' => 'Other Person',
            'email' => 'other@example.tz',
            'kyc_status' => 'pending',
        ]);

        $foreignPolicy = Policy::query()->create([
            'organization_id' => $orgId,
            'branch_id' => $customer->branch_id,
            'customer_id' => $other->id,
            'carrier_id' => Policy::query()->firstOrFail()->carrier_id,
            'carrier_policy_number' => 'FOREIGN-999',
            'status' => 'active',
            'verification_status' => 'not_checked',
            'effective_from' => now()->subMonth(),
            'effective_to' => now()->addYear(),
            'currency' => 'TZS',
            'provenance' => 'manual_staff_entry',
            'version' => 1,
        ]);

        $this->withToken($token)->postJson('/api/v1/portal/claims', [
            'policy_id' => $foreignPolicy->id,
            'incident_at' => now()->toIso8601String(),
            'description' => 'Should not be allowed against another customer policy.',
        ])->assertStatus(422);
    }
}
