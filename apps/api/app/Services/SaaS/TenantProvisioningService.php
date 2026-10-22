<?php

namespace App\Services\SaaS;

use App\Models\Branch;
use App\Models\Organization;
use App\Models\PlatformInvoice;
use App\Models\SaasTenant;
use App\Models\SubscriptionPlan;
use App\Models\TenantDomain;
use App\Models\TenantSubscription;
use App\Services\AuditLogger;
use App\Services\Integrations\IntegrationHub;
use App\Support\Uuid;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class TenantProvisioningService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly IntegrationHub $integrations,
    ) {
    }

    public function assertSaaSEdition(): void
    {
        if (config('wabantu.edition') !== 'saas') {
            throw new RuntimeException('SaaS control plane is disabled in standalone edition.');
        }
    }

    public function provision(array $data): SaasTenant
    {
        $this->assertSaaSEdition();

        return DB::transaction(function () use ($data) {
            $slug = $data['slug'];
            if (SaasTenant::query()->where('slug', $slug)->exists()) {
                throw new RuntimeException('Tenant slug already reserved.');
            }

            $org = Organization::query()->create([
                'id' => Uuid::v7(),
                'legal_name' => $data['legal_name'],
                'trading_name' => $data['trading_name'] ?? $data['legal_name'],
                'intermediary_type' => $data['intermediary_type'] ?? 'brokerage',
                'regulatory_status' => 'pending_review',
                'timezone' => 'Africa/Dar_es_Salaam',
                'currency' => 'TZS',
                'locale' => 'en-TZ',
                'onboarding_completed' => false,
                'settings' => [
                    'premium_collection_mode' => 'insurer_directed',
                    'allow_agent_premium_collection' => false,
                ],
                'branding' => $data['branding'] ?? [
                    'primary_color' => '#0B1F3B',
                    'accent_color' => '#2563EB',
                ],
            ]);

            Branch::query()->create([
                'id' => Uuid::v7(),
                'organization_id' => $org->id,
                'name' => 'Head Office',
                'code' => 'HO',
                'is_head_office' => true,
            ]);

            $tenant = SaasTenant::query()->create([
                'id' => Uuid::v7(),
                'slug' => $slug,
                'legal_name' => $data['legal_name'],
                'status' => 'active',
                'organization_id' => $org->id,
                'primary_domain' => $data['primary_domain'] ?? $slug.'.wabantu.local',
                'region' => $data['region'] ?? 'tz-dar',
                'db_connection_name' => null, // dedicated DB wiring later; same DB with org isolation for Phase 5 local
                'branding' => $org->branding,
                'feature_flags' => $data['feature_flags'] ?? ['claims' => true, 'finance' => true],
                'activated_at' => now(),
            ]);

            $org->saas_tenant_id = $tenant->id;
            $org->save();

            TenantDomain::query()->create([
                'id' => Uuid::v7(),
                'saas_tenant_id' => $tenant->id,
                'hostname' => $tenant->primary_domain,
                'is_primary' => true,
                'verified' => true,
            ]);

            $plan = SubscriptionPlan::query()->where('code', $data['plan_code'] ?? 'starter')->first()
                ?? SubscriptionPlan::query()->where('is_active', true)->first();

            if ($plan) {
                TenantSubscription::query()->create([
                    'id' => Uuid::v7(),
                    'saas_tenant_id' => $tenant->id,
                    'subscription_plan_id' => $plan->id,
                    'status' => 'trialing',
                    'starts_on' => now()->toDateString(),
                    'trial_ends_on' => now()->addDays(14)->toDateString(),
                    'seats' => $plan->seat_limit ?? 5,
                ]);
            }

            $this->integrations->ensureDefaults($org);

            $this->audit->log(
                action: 'saas.tenant.provisioned',
                subjectType: SaasTenant::class,
                subjectId: $tenant->id,
                after: ['slug' => $tenant->slug, 'organization_id' => $org->id],
            );

            return $tenant->load(['organization', 'domains', 'subscriptions.plan']);
        });
    }

    public function suspend(SaasTenant $tenant, string $reason): SaasTenant
    {
        $this->assertSaaSEdition();

        $tenant->status = 'suspended';
        $tenant->suspended_at = now();
        $tenant->suspension_reason = $reason;
        $tenant->save();

        $this->audit->log(
            action: 'saas.tenant.suspended',
            subjectType: SaasTenant::class,
            subjectId: $tenant->id,
            reason: $reason,
            after: ['status' => 'suspended'],
        );

        return $tenant;
    }

    public function issueSoftwareInvoice(SaasTenant $tenant, float $amount, string $notes = ''): PlatformInvoice
    {
        $this->assertSaaSEdition();

        $subscription = $tenant->subscriptions()->latest()->first();

        $invoice = PlatformInvoice::query()->create([
            'id' => Uuid::v7(),
            'reference' => 'PLAT-'.strtoupper(substr(Uuid::v7(), 0, 8)),
            'saas_tenant_id' => $tenant->id,
            'tenant_subscription_id' => $subscription?->id,
            'amount' => $amount,
            'currency' => 'TZS',
            'status' => 'issued',
            'ledger' => 'platform_software',
            'due_on' => now()->addDays(14)->toDateString(),
            'notes' => $notes ?: 'Software subscription invoice — not an insurance premium.',
        ]);

        $this->audit->log(
            action: 'saas.platform_invoice.issued',
            subjectType: PlatformInvoice::class,
            subjectId: $invoice->id,
            after: ['ledger' => 'platform_software', 'amount' => $amount],
        );

        return $invoice;
    }
}
