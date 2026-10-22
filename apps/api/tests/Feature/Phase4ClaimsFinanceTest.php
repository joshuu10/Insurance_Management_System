<?php

namespace Tests\Feature;

use App\Models\Claim;
use App\Models\CommissionReceivable;
use App\Models\JournalEntry;
use App\Models\Policy;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase4ClaimsFinanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DemoSeeder::class);
    }

    public function test_fnol_submit_acknowledge_and_insurer_update(): void
    {
        $agent = $this->login('agent@demo.wabantu.local');
        $admin = $this->login('admin@demo.wabantu.local');
        $policyId = Policy::query()->value('id');

        $create = $this->asToken($agent)->postJson('/api/v1/claims', [
            'policy_id' => $policyId,
            'incident_at' => now()->subDay()->toIso8601String(),
            'incident_location' => 'Dar es Salaam',
            'description' => 'Rear-end collision at traffic lights, provisional notice only.',
            'declared_amount' => 1500000,
        ])->assertCreated();

        $claimId = $create->json('data.id');
        $this->assertTrue($create->json('data.coverage_provisional_notice'));

        $this->asToken($agent)->postJson("/api/v1/claims/{$claimId}/submit")->assertOk()
            ->assertJsonPath('data.status', 'submitted');

        $this->asToken($agent)->postJson("/api/v1/claims/{$claimId}/acknowledge")->assertForbidden();

        $this->asToken($admin)->postJson("/api/v1/claims/{$claimId}/acknowledge")->assertOk()
            ->assertJsonPath('data.status', 'acknowledged');

        $this->asToken($admin)->postJson("/api/v1/claims/{$claimId}/insurer-update", [
            'status' => 'insurer_approved',
            'insurer_reference' => 'UHAI-CLM-88',
            'insurer_approved_amount' => 1200000,
            'message' => 'Insurer approved with evidence ref UHAI-CLM-88',
        ])->assertOk()
            ->assertJsonPath('data.status', 'insurer_approved');

        $this->assertSame(1, Claim::query()->where('id', $claimId)->count());
    }

    public function test_commission_expect_confirm_receive_posts_balanced_journal(): void
    {
        $token = $this->login('admin@demo.wabantu.local');
        $policyId = Policy::query()->value('id');

        $expect = $this->asToken($token)->postJson('/api/v1/finance/commissions/expect', [
            'policy_id' => $policyId,
        ])->assertCreated();

        // 12.5% of 450000 = 56250.00
        $this->assertSame('56250.00', (string) $expect->json('data.expected_amount'));
        $receivableId = $expect->json('data.id');

        $this->asToken($token)->postJson("/api/v1/finance/commissions/{$receivableId}/confirm", [
            'confirmed_amount' => 56250,
            'evidence' => 'Insurer statement line DEMO-COM-1',
        ])->assertOk();

        $receive = $this->asToken($token)->postJson("/api/v1/finance/commissions/{$receivableId}/receive", [
            'received_amount' => 56250,
        ])->assertOk();

        $journalId = $receive->json('data.journal.id');
        $journal = JournalEntry::query()->with('lines')->findOrFail($journalId);
        $debit = $journal->lines->sum(fn ($l) => (float) $l->debit);
        $credit = $journal->lines->sum(fn ($l) => (float) $l->credit);
        $this->assertEqualsWithDelta($debit, $credit, 0.001);
        $this->assertSame('received', CommissionReceivable::query()->find($receivableId)->status);
    }

    public function test_unbalanced_journal_is_rejected_via_receive_path_requires_confirm(): void
    {
        $token = $this->login('admin@demo.wabantu.local');
        $this->asToken($token)->getJson('/api/v1/finance/overview')
            ->assertOk()
            ->assertJsonPath('data.journals_posted', 0);
    }

    public function test_posted_journal_cannot_be_hard_deleted(): void
    {
        $token = $this->login('admin@demo.wabantu.local');
        $policyId = Policy::query()->value('id');
        $receivableId = $this->asToken($token)->postJson('/api/v1/finance/commissions/expect', [
            'policy_id' => $policyId,
        ])->json('data.id');

        $this->asToken($token)->postJson("/api/v1/finance/commissions/{$receivableId}/confirm", [
            'confirmed_amount' => 56250,
            'evidence' => 'stmt',
        ]);
        $journalId = $this->asToken($token)->postJson("/api/v1/finance/commissions/{$receivableId}/receive", [
            'received_amount' => 56250,
        ])->json('data.journal.id');

        $this->expectException(\RuntimeException::class);
        JournalEntry::query()->findOrFail($journalId)->delete();
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
