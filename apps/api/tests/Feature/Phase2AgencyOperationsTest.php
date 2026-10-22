<?php

namespace Tests\Feature;

use App\Models\Carrier;
use App\Models\CarrierProduct;
use App\Models\Customer;
use App\Models\Quote;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase2AgencyOperationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DemoSeeder::class);
    }

    public function test_agent_can_list_customers_and_create_lead(): void
    {
        $token = $this->login('agent@demo.wabantu.local');

        $this->withToken($token)->getJson('/api/v1/customers')
            ->assertOk()
            ->assertJsonPath('data.0.display_name', 'Neema Mwanga (Demo)');

        $customerId = Customer::query()->first()->id;

        $this->withToken($token)->postJson('/api/v1/leads', [
            'title' => 'Fleet follow-up',
            'customer_id' => $customerId,
            'stage' => 'new',
        ])->assertCreated()
            ->assertJsonPath('data.title', 'Fleet follow-up');
    }

    public function test_quote_blocked_without_appointment(): void
    {
        $token = $this->login('admin@demo.wabantu.local');
        $customerId = Customer::query()->first()->id;

        $orphanCarrier = Carrier::query()->create([
            'legal_name' => 'Unaappointed Carrier (Fictional)',
            'status' => 'active',
            'is_illustrative' => true,
            'disclaimer' => 'No appointment',
            'data_source' => 'test',
        ]);

        $classId = \App\Models\ProductClass::query()->value('id');
        $product = CarrierProduct::query()->create([
            'carrier_id' => $orphanCarrier->id,
            'product_class_id' => $classId,
            'name' => 'Blocked product',
            'status' => 'active',
            'requires_insurer_rating' => true,
        ]);

        $this->withToken($token)->postJson('/api/v1/quotes', [
            'customer_id' => $customerId,
            'carrier_product_id' => $product->id,
        ])->assertStatus(422);
    }

    public function test_quote_sandbox_submit_does_not_invent_premium(): void
    {
        $token = $this->login('admin@demo.wabantu.local');
        $customerId = Customer::query()->first()->id;
        $productId = CarrierProduct::query()->where('status', 'active')->value('id');

        $create = $this->withToken($token)->postJson('/api/v1/quotes', [
            'customer_id' => $customerId,
            'carrier_product_id' => $productId,
            'risk_summary' => ['vehicle_make' => 'Toyota', 'vehicle_model' => 'Corolla'],
        ])->assertCreated();

        $quoteId = $create->json('data.id');
        $this->assertNull($create->json('data.premium_amount'));
        $this->assertSame('awaiting_insurer', $create->json('data.premium_classification'));

        $submit = $this->withToken($token)->postJson("/api/v1/quotes/{$quoteId}/submit", [
            'channel' => 'sandbox',
        ])->assertOk();

        $this->assertSame('awaiting_insurer', $submit->json('data.quote.status'));
        $this->assertNull($submit->json('data.quote.premium_amount'));
        $this->assertSame('SANDBOX', $submit->json('data.submission.integration_state'));
    }

    public function test_insurer_confirmation_requires_evidence_and_permission(): void
    {
        $adminToken = $this->login('admin@demo.wabantu.local');
        $customerId = Customer::query()->first()->id;
        $productId = CarrierProduct::query()->where('status', 'active')->value('id');

        $quoteId = $this->withToken($adminToken)->postJson('/api/v1/quotes', [
            'customer_id' => $customerId,
            'carrier_product_id' => $productId,
        ])->json('data.id');

        $this->withToken($adminToken)->postJson("/api/v1/quotes/{$quoteId}/submit", [
            'channel' => 'sandbox',
        ])->assertOk();

        $agentToken = $this->login('agent@demo.wabantu.local');

        $this->asToken($agentToken)->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.email', 'agent@demo.wabantu.local');

        $this->asToken($agentToken)->postJson("/api/v1/quotes/{$quoteId}/insurer-confirmation", [
            'premium_amount' => 450000,
            'evidence_note' => 'Insurer email REF-DEMO-99 dated today',
        ])->assertForbidden();

        $this->asToken($adminToken)->postJson("/api/v1/quotes/{$quoteId}/insurer-confirmation", [
            'premium_amount' => 450000,
            'evidence_note' => 'Insurer email REF-DEMO-99 dated today',
        ])->assertOk()
            ->assertJsonPath('data.premium_classification', 'insurer_confirmed')
            ->assertJsonPath('data.status', 'insurer_confirmed');

        $this->assertSame('450000.00', (string) Quote::query()->find($quoteId)->premium_amount);
    }

    public function test_application_requires_insurer_confirmed_quote(): void
    {
        $token = $this->login('admin@demo.wabantu.local');
        $customerId = Customer::query()->first()->id;
        $productId = CarrierProduct::query()->where('status', 'active')->value('id');

        $quoteId = $this->withToken($token)->postJson('/api/v1/quotes', [
            'customer_id' => $customerId,
            'carrier_product_id' => $productId,
        ])->json('data.id');

        $this->withToken($token)->postJson('/api/v1/applications', [
            'quote_id' => $quoteId,
            'disclosure_acknowledged' => true,
        ])->assertStatus(422);
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
