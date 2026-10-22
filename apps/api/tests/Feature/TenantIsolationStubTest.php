<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use App\Support\Uuid;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * SaaS multi-DB isolation comes later. This stub proves we never trust a client-supplied
 * organization override on /me and that users only see their own organization payload.
 */
class TenantIsolationStubTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_cannot_see_another_organization_via_me(): void
    {
        $this->seed(DemoSeeder::class);

        $otherOrg = Organization::query()->create([
            'id' => Uuid::v7(),
            'legal_name' => 'Other Tenant Brokerage (Fictional)',
            'intermediary_type' => 'brokerage',
            'regulatory_status' => 'pending_review',
        ]);

        $intruder = User::query()->create([
            'name' => 'Intruder',
            'email' => 'intruder@other.local',
            'password' => Hash::make('Password1!'),
            'organization_id' => $otherOrg->id,
            'is_active' => true,
        ]);

        $token = $intruder->createToken('phpunit')->plainTextToken;

        $response = $this->withToken($token)
            ->withHeader('X-Tenant-ID', Organization::query()->where('legal_name', 'like', 'Demo%')->value('id'))
            ->getJson('/api/v1/me');

        $response->assertOk();
        $this->assertSame($otherOrg->id, $response->json('data.organization.id'));
        $this->assertNotSame(
            Organization::query()->where('legal_name', 'like', 'Demo%')->value('id'),
            $response->json('data.organization.id')
        );
    }
}
