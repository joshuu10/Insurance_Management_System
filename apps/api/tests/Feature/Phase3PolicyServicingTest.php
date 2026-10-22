<?php

namespace Tests\Feature;

use App\Models\Carrier;
use App\Models\Customer;
use App\Models\Policy;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase3PolicyServicingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DemoSeeder::class);
    }

    public function test_lists_seeded_active_policy_and_upcoming_renewals(): void
    {
        $token = $this->login('agent@demo.wabantu.local');

        $this->asToken($token)->getJson('/api/v1/policies')
            ->assertOk()
            ->assertJsonPath('data.0.carrier_policy_number', 'UHAI-MOT-DEMO-1001');

        $this->asToken($token)->getJson('/api/v1/policies/renewals/upcoming?within_days=60')
            ->assertOk()
            ->assertJsonPath('data.0.carrier_policy_number', 'UHAI-MOT-DEMO-1001');
    }

    public function test_agent_cannot_intake_policy(): void
    {
        $token = $this->login('agent@demo.wabantu.local');
        $customerId = Customer::query()->value('id');
        $carrierId = Carrier::query()->where('status', 'active')->value('id');

        $this->asToken($token)->postJson('/api/v1/policies/intake', [
            'customer_id' => $customerId,
            'carrier_id' => $carrierId,
            'carrier_policy_number' => 'BLOCKED-1',
            'effective_from' => now()->toDateString(),
            'effective_to' => now()->addYear()->toDateString(),
        ])->assertForbidden();
    }

    public function test_admin_intake_endorsement_cancellation_and_premium_instruction(): void
    {
        $token = $this->login('admin@demo.wabantu.local');
        $customerId = Customer::query()->value('id');
        $carrierId = Carrier::query()->where('trading_name', 'Uhai Cover (demo)')->value('id')
            ?? Carrier::query()->where('status', 'active')->value('id');

        $intake = $this->asToken($token)->postJson('/api/v1/policies/intake', [
            'customer_id' => $customerId,
            'carrier_id' => $carrierId,
            'carrier_policy_number' => 'UHAI-MOT-NEW-2002',
            'effective_from' => now()->toIso8601String(),
            'effective_to' => now()->addYear()->toIso8601String(),
            'premium_amount' => 500000,
            'sum_insured' => 30000000,
            'status' => 'active',
            'verification_status' => 'unavailable',
        ])->assertCreated();

        $policyId = $intake->json('data.id');

        $endorsement = $this->asToken($token)->postJson("/api/v1/policies/{$policyId}/endorsements", [
            'type' => 'change_address',
            'description' => 'Customer moved to Mikocheni',
            'requested_changes' => ['address' => 'Mikocheni'],
        ])->assertCreated();

        $this->asToken($token)->postJson('/api/v1/endorsements/'.$endorsement->json('data.id').'/apply', [
            'insurer_approval_ref' => 'INS-END-55',
        ])->assertOk()
            ->assertJsonPath('data.status', 'applied');

        $cancellation = $this->asToken($token)->postJson("/api/v1/policies/{$policyId}/cancellations", [
            'reason' => 'Customer sold the vehicle',
            'effective_from' => now()->addDay()->toIso8601String(),
        ])->assertCreated();

        $this->asToken($token)->postJson('/api/v1/cancellations/'.$cancellation->json('data.id').'/confirm', [
            'insurer_confirmation_ref' => 'INS-CXL-9',
            'refund_amount' => 10000,
        ])->assertOk();

        $this->assertSame('cancelled', Policy::query()->find($policyId)->status);

        $this->asToken($token)->postJson('/api/v1/premium-instructions', [
            'policy_id' => Policy::query()->where('carrier_policy_number', 'UHAI-MOT-DEMO-1001')->value('id'),
            'carrier_id' => $carrierId,
            'amount' => 450000,
            'beneficiary_name' => 'Uhai Cover Tanzania Limited (Fictional)',
            'beneficiary_account' => '0123456789',
            'routes_to_insurer' => true,
        ])->assertCreated()
            ->assertJsonPath('data.routes_to_insurer', true)
            ->assertJsonPath('data.integration_state', 'NOT_CONFIGURED');
    }

    public function test_blocks_agency_premium_float_by_default(): void
    {
        $token = $this->login('admin@demo.wabantu.local');
        $carrierId = Carrier::query()->where('status', 'active')->value('id');
        $policyId = Policy::query()->value('id');

        $this->asToken($token)->postJson('/api/v1/premium-instructions', [
            'policy_id' => $policyId,
            'carrier_id' => $carrierId,
            'amount' => 1000,
            'beneficiary_name' => 'Demo Brokerage Float Account',
            'routes_to_insurer' => false,
        ])->assertStatus(422);
    }

    public function test_issued_policy_cannot_be_hard_deleted(): void
    {
        $policy = Policy::query()->firstOrFail();
        $this->expectException(\RuntimeException::class);
        $policy->delete();
    }

    private function login(string $email): string
    {
        $this->flushHeaders();
        $this->app['auth']->forgetGuards();

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $email,
            'password' => 'Password1!',
            'device_name' => 'phpunit',
        ]);
        $response->assertOk()->assertJsonPath('user.email', $email);

        return $response->json('token');
    }

    private function asToken(string $token): static
    {
        $this->flushHeaders();
        $this->app['auth']->forgetGuards();

        return $this->withToken($token);
    }
}
