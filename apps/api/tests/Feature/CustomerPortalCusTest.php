<?php

namespace Tests\Feature;

use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerPortalCusTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DemoSeeder::class);
    }

    private function portalToken(): string
    {
        return $this->postJson('/api/v1/auth/login', [
            'email' => 'customer@demo.wabantu.local',
            'password' => 'Password1!',
        ])->assertOk()->json('token');
    }

    public function test_register_forgot_reset_and_profile_otp(): void
    {
        $reg = $this->postJson('/api/v1/portal/auth/register', [
            'name' => 'Asha New',
            'email' => 'asha.new@example.tz',
            'password' => 'Password1!',
            'password_confirmation' => 'Password1!',
            'phone_e164' => '+255700000099',
        ])->assertCreated();

        $token = $reg->json('token');

        $this->withToken($token)->putJson('/api/v1/portal/profile', [
            'display_name' => 'Asha Updated',
            'national_id_last4' => '1234',
        ])->assertOk()->assertJsonPath('data.display_name', 'Asha Updated');

        $otp = $this->withToken($token)->postJson('/api/v1/portal/auth/otp/request')
            ->assertOk()
            ->json('sandbox_otp');

        $this->withToken($token)->postJson('/api/v1/portal/auth/otp/verify', [
            'code' => $otp,
        ])->assertOk()->assertJsonPath('identity_verification_status', 'verified');

        $forgot = $this->postJson('/api/v1/portal/auth/forgot-password', [
            'email' => 'asha.new@example.tz',
        ])->assertOk();

        $this->postJson('/api/v1/portal/auth/reset-password', [
            'email' => 'asha.new@example.tz',
            'token' => $forgot->json('sandbox_reset_token'),
            'password' => 'Password2!',
            'password_confirmation' => 'Password2!',
        ])->assertOk();

        $this->postJson('/api/v1/auth/login', [
            'email' => 'asha.new@example.tz',
            'password' => 'Password2!',
        ])->assertOk();
    }

    public function test_products_enquiry_documents_payments_renewals_notifications(): void
    {
        $token = $this->portalToken();

        $products = $this->withToken($token)->getJson('/api/v1/portal/products')->assertOk();
        $this->assertNotEmpty($products->json('data'));

        $productId = $products->json('data.0.id');
        $this->withToken($token)->postJson('/api/v1/portal/service-requests', [
            'type' => 'quotation',
            'subject' => 'Motor quote please',
            'body' => 'I need a private motor quotation follow-up.',
            'carrier_product_id' => $productId,
        ])->assertCreated();

        $docs = $this->withToken($token)->getJson('/api/v1/portal/documents')->assertOk();
        $this->assertNotEmpty($docs->json('data'));
        $docId = $docs->json('data.0.id');
        $this->withToken($token)->get("/api/v1/portal/documents/{$docId}/download")->assertOk();

        $policies = $this->withToken($token)->getJson('/api/v1/portal/policies')->assertOk();
        $policyId = $policies->json('data.0.id');

        $pay = $this->withToken($token)->postJson('/api/v1/portal/payments', [
            'policy_id' => $policyId,
        ])->assertCreated();

        $this->withToken($token)
            ->postJson('/api/v1/portal/payments/'.$pay->json('data.id').'/sandbox-confirm')
            ->assertOk()
            ->assertJsonPath('data.status', 'sandbox_confirmed');

        $this->withToken($token)->getJson('/api/v1/portal/renewals')->assertOk();
        $this->withToken($token)->getJson('/api/v1/portal/notifications')->assertOk();
        $this->withToken($token)->getJson('/api/v1/portal/communications')->assertOk();
    }

    public function test_claim_evidence_and_timeline(): void
    {
        $token = $this->portalToken();
        $policyId = $this->withToken($token)->getJson('/api/v1/portal/policies')->json('data.0.id');

        $claim = $this->withToken($token)->postJson('/api/v1/portal/claims', [
            'policy_id' => $policyId,
            'incident_at' => now()->subHours(2)->toIso8601String(),
            'incident_location' => 'Dar es Salaam',
            'description' => 'Windscreen cracked after stone impact on highway.',
        ])->assertCreated();

        $id = $claim->json('data.id');

        $this->withToken($token)->postJson("/api/v1/portal/claims/{$id}/evidence", [
            'original_filename' => 'photo1.jpg',
            'mime_type' => 'image/jpeg',
        ])->assertCreated();

        $this->withToken($token)->postJson("/api/v1/portal/claims/{$id}/submit")->assertOk();

        $detail = $this->withToken($token)->getJson("/api/v1/portal/claims/{$id}")->assertOk();
        $this->assertNotEmpty($detail->json('data.timeline'));
        $this->assertNotEmpty($detail->json('evidence'));
    }
}
