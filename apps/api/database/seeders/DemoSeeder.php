<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Carrier;
use App\Models\CarrierAppointment;
use App\Models\CarrierProduct;
use App\Models\CommissionSchedule;
use App\Models\Customer;
use App\Models\Document;
use App\Models\Lead;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\PlatformUser;
use App\Models\Policy;
use App\Models\PremiumInstruction;
use App\Models\ProductClass;
use App\Models\ProductVersion;
use App\Models\Role;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\FinanceService;
use App\Services\Integrations\IntegrationHub;
use App\Services\Portal\CustomerNotifier;
use App\Support\Uuid;
use Illuminate\Database\Seeder;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $org = Organization::query()->create([
            'id' => Uuid::v7(),
            'legal_name' => 'Demo Brokerage Limited (Fictional)',
            'trading_name' => 'Demo Brokerage TZ',
            'intermediary_type' => 'brokerage',
            'licence_number' => null,
            'regulatory_status' => 'pending_review',
            'timezone' => 'Africa/Dar_es_Salaam',
            'currency' => 'TZS',
            'locale' => 'en-TZ',
            'onboarding_completed' => false,
            'settings' => [
                'premium_collection_mode' => 'insurer_directed',
                'allow_agent_premium_collection' => false,
            ],
        ]);

        $branch = Branch::query()->create([
            'id' => Uuid::v7(),
            'organization_id' => $org->id,
            'name' => 'Dar es Salaam Head Office',
            'code' => 'DSM-HO',
            'city' => 'Dar es Salaam',
            'region' => 'Dar es Salaam',
            'is_head_office' => true,
        ]);

        $permissionDefs = [
            ['name' => 'View customers', 'slug' => 'customer.view', 'group' => 'crm'],
            ['name' => 'Export customer PII', 'slug' => 'customer.pii.export', 'group' => 'crm'],
            ['name' => 'Create quote', 'slug' => 'quote.create', 'group' => 'quote'],
            ['name' => 'Approve quote', 'slug' => 'quote.approve', 'group' => 'quote'],
            ['name' => 'View policies', 'slug' => 'policy.view', 'group' => 'policy'],
            ['name' => 'Intake insurer-issued policy', 'slug' => 'policy.issue.authorized', 'group' => 'policy'],
            ['name' => 'Endorse / cancel request', 'slug' => 'policy.endorse', 'group' => 'policy'],
            ['name' => 'Submit claims FNOL', 'slug' => 'claim.submit', 'group' => 'claims'],
            ['name' => 'Review claims', 'slug' => 'claim.review', 'group' => 'claims'],
            ['name' => 'Approve commissions', 'slug' => 'commission.approve', 'group' => 'finance'],
            ['name' => 'Configure tenant', 'slug' => 'tenant.configure', 'group' => 'admin'],
            ['name' => 'View audit', 'slug' => 'audit.view', 'group' => 'compliance'],
            ['name' => 'Manage integrations', 'slug' => 'integration.manage', 'group' => 'admin'],
            ['name' => 'Reconcile finance', 'slug' => 'finance.reconcile', 'group' => 'finance'],
            ['name' => 'Customer portal access', 'slug' => 'portal.access', 'group' => 'portal'],
            ['name' => 'Portal view own policies', 'slug' => 'portal.policy.view', 'group' => 'portal'],
            ['name' => 'Portal submit FNOL', 'slug' => 'portal.claim.submit', 'group' => 'portal'],
        ];

        $permissions = collect($permissionDefs)->mapWithKeys(function (array $def) {
            $permission = Permission::query()->create([
                'id' => Uuid::v7(),
                ...$def,
            ]);

            return [$def['slug'] => $permission];
        });

        $adminRole = Role::query()->create([
            'id' => Uuid::v7(),
            'organization_id' => $org->id,
            'name' => 'Managing Director',
            'slug' => 'managing_director',
            'is_system' => true,
        ]);
        $adminRole->permissions()->sync($permissions->pluck('id'));

        $agentRole = Role::query()->create([
            'id' => Uuid::v7(),
            'organization_id' => $org->id,
            'name' => 'Agent / Producer',
            'slug' => 'agent',
            'is_system' => true,
        ]);
        $agentRole->permissions()->sync([
            $permissions['customer.view']->id,
            $permissions['quote.create']->id,
            $permissions['policy.view']->id,
            $permissions['claim.submit']->id,
        ]);

        $portalRole = Role::query()->create([
            'id' => Uuid::v7(),
            'organization_id' => $org->id,
            'name' => 'Customer portal',
            'slug' => 'customer_portal',
            'is_system' => true,
        ]);
        $portalRole->permissions()->sync([
            $permissions['portal.access']->id,
            $permissions['portal.policy.view']->id,
            $permissions['portal.claim.submit']->id,
        ]);

        $admin = User::query()->create([
            'name' => 'Amina Demo Admin',
            'email' => 'admin@demo.wabantu.local',
            'password' => 'Password1!',
            'organization_id' => $org->id,
            'branch_id' => $branch->id,
            'phone_e164' => '+255712000001',
            'preferred_locale' => 'en-TZ',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $admin->roles()->attach($adminRole->id);

        $agent = User::query()->create([
            'name' => 'Juma Demo Agent',
            'email' => 'agent@demo.wabantu.local',
            'password' => 'Password1!',
            'organization_id' => $org->id,
            'branch_id' => $branch->id,
            'phone_e164' => '+255712000002',
            'preferred_locale' => 'sw-TZ',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $agent->roles()->attach($agentRole->id);

        Carrier::query()->create([
            'id' => Uuid::v7(),
            'legal_name' => 'Alliance Insurance Corporation Limited (Illustrative Example)',
            'trading_name' => 'Alliance (example only)',
            'country_code' => 'TZ',
            'status' => 'inactive',
            'is_illustrative' => true,
            'disclaimer' => 'ILLUSTRATIVE / INACTIVE seed entry. Not a partnership, appointment, or authorization. Do not present as an official partner.',
            'data_source' => 'fictional_seed',
        ]);

        // Appointed fictional carrier (not a real insurer brand) — used for quoting demos.
        $appointedCarrier = Carrier::query()->create([
            'id' => Uuid::v7(),
            'legal_name' => 'Uhai Cover Tanzania Limited (Fictional)',
            'trading_name' => 'Uhai Cover (demo)',
            'country_code' => 'TZ',
            'status' => 'active',
            'is_illustrative' => true,
            'disclaimer' => 'Fictional seed carrier for sandbox workflows only. Not a real insurer.',
            'data_source' => 'fictional_seed',
        ]);

        CarrierAppointment::query()->create([
            'id' => Uuid::v7(),
            'organization_id' => $org->id,
            'carrier_id' => $appointedCarrier->id,
            'status' => 'active',
            'appointed_on' => now()->subYear()->toDateString(),
            'expires_on' => now()->addYears(2)->toDateString(),
            'authority_scope' => 'motor_private_commercial',
            'notes' => 'Demo appointment — verify real appointments before production.',
            'allows_quoting' => true,
        ]);

        $motorClass = ProductClass::query()->create([
            'id' => Uuid::v7(),
            'code' => 'MOTOR',
            'name_en' => 'Motor',
            'name_sw' => 'Magari',
        ]);

        $product = CarrierProduct::query()->create([
            'id' => Uuid::v7(),
            'carrier_id' => $appointedCarrier->id,
            'product_class_id' => $motorClass->id,
            'name' => 'Private Motor Comprehensive (sandbox)',
            'code' => 'MOT-COMP-PRIV',
            'status' => 'active',
            'requires_insurer_rating' => true,
        ]);

        ProductVersion::query()->create([
            'id' => Uuid::v7(),
            'carrier_product_id' => $product->id,
            'version_label' => '2026.1',
            'effective_from' => now()->startOfYear()->toDateString(),
            'questionnaire_schema' => [
                'fields' => [
                    ['key' => 'vehicle_make', 'type' => 'string', 'label_en' => 'Make', 'required' => true],
                    ['key' => 'vehicle_model', 'type' => 'string', 'label_en' => 'Model', 'required' => true],
                    ['key' => 'year_of_manufacture', 'type' => 'integer', 'label_en' => 'Year', 'required' => true],
                    ['key' => 'sum_insured', 'type' => 'decimal', 'label_en' => 'Sum insured (TZS)', 'required' => true],
                ],
            ],
            'required_documents' => ['vehicle_registration', 'national_id'],
            'disclosures' => 'Cover is only bound by the authorized insurer. Agency quotes are not certificates of insurance.',
            'is_current' => true,
        ]);

        $customer = Customer::query()->create([
            'id' => Uuid::v7(),
            'organization_id' => $org->id,
            'branch_id' => $branch->id,
            'type' => 'individual',
            'display_name' => 'Neema Mwanga (Demo)',
            'email' => 'customer@demo.wabantu.local',
            'phone_e164' => '+255713000010',
            'preferred_locale' => 'sw-TZ',
            'kyc_status' => 'pending',
            'owner_user_id' => $agent->id,
        ]);

        $portalUser = User::query()->create([
            'name' => 'Neema Mwanga',
            'email' => 'customer@demo.wabantu.local',
            'password' => 'Password1!',
            'organization_id' => $org->id,
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'phone_e164' => '+255713000010',
            'preferred_locale' => 'sw-TZ',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $portalUser->roles()->attach($portalRole->id);

        Lead::query()->create([
            'id' => Uuid::v7(),
            'organization_id' => $org->id,
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'owner_user_id' => $agent->id,
            'title' => 'Private motor enquiry — Neema',
            'source' => 'manual',
            'stage' => 'qualified',
            'expected_premium_estimate' => null,
            'expected_premium_note' => 'Estimate not set — awaiting insurer.',
            'currency' => 'TZS',
        ]);

        $policy = Policy::query()->create([
            'id' => Uuid::v7(),
            'organization_id' => $org->id,
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'carrier_id' => $appointedCarrier->id,
            'carrier_product_id' => $product->id,
            'owner_user_id' => $admin->id,
            'carrier_policy_number' => 'UHAI-MOT-DEMO-1001',
            'cover_note_number' => 'CN-DEMO-1001',
            'status' => 'active',
            'verification_status' => 'not_checked',
            'effective_from' => now()->subMonths(2),
            'effective_to' => now()->addDays(45),
            'sum_insured' => 25000000,
            'premium_amount' => 450000,
            'currency' => 'TZS',
            'insured_risks' => ['motor_comprehensive'],
            'provenance' => 'manual_staff_entry',
            'intake_notes' => 'Demo insurer-issued policy record. Verification not checked with TIRA.',
            'source_document_ref' => 'DEMO-SCHEDULE-PDF',
            'version' => 1,
        ]);

        Document::query()->create([
            'id' => Uuid::v7(),
            'organization_id' => $org->id,
            'subject_type' => 'policy',
            'subject_id' => $policy->id,
            'purpose' => 'policy_schedule',
            'issuer' => 'insurer',
            'original_filename' => 'UHAI-MOT-DEMO-1001-schedule.pdf',
            'mime_type' => 'application/pdf',
            'classification' => 'customer_visible',
            'status' => 'metadata_only',
            'uploaded_by' => $admin->id,
        ]);

        PremiumInstruction::query()->create([
            'id' => Uuid::v7(),
            'organization_id' => $org->id,
            'policy_id' => $policy->id,
            'carrier_id' => $appointedCarrier->id,
            'status' => 'pending_payer',
            'amount' => 450000,
            'currency' => 'TZS',
            'beneficiary_name' => 'Uhai Assurance (sandbox) — insurer directed',
            'beneficiary_channel' => 'mobile_money',
            'payment_reference' => 'UHAI-PREM-DEMO-1001',
            'payer_instructions' => 'Pay insurer-directed premium via sandbox portal flow. Not a live collection.',
            'routes_to_insurer' => true,
            'integration_state' => 'SANDBOX',
            'created_by' => $admin->id,
        ]);

        app(CustomerNotifier::class)->notify(
            $customer,
            'renewal',
            'Policy renews soon',
            'UHAI-MOT-DEMO-1001 expires in about 45 days. Open Renewals to request staff follow-up.',
            ['policy_id' => $policy->id]
        );

        CommissionSchedule::query()->create([
            'id' => Uuid::v7(),
            'organization_id' => $org->id,
            'carrier_id' => $appointedCarrier->id,
            'carrier_product_id' => $product->id,
            'name' => 'Uhai motor demo schedule 12.5%',
            'rate_percent' => 12.5000,
            'effective_from' => now()->subYear()->toDateString(),
            'status' => 'active',
            'notes' => 'Fictional schedule for demos — not a real insurer tariff.',
        ]);

        app(FinanceService::class)->ensureDefaultChart($org);
        app(IntegrationHub::class)->ensureDefaults($org);

        SubscriptionPlan::query()->create([
            'id' => Uuid::v7(),
            'code' => 'starter',
            'name' => 'Starter',
            'description' => 'Small agency SaaS plan (software only — not insurance).',
            'price_monthly' => 150000,
            'currency' => 'TZS',
            'seat_limit' => 10,
            'entitlements' => ['quotes' => true, 'claims' => true, 'finance' => true],
            'is_active' => true,
        ]);

        SubscriptionPlan::query()->create([
            'id' => Uuid::v7(),
            'code' => 'growth',
            'name' => 'Growth',
            'description' => 'Multi-branch SaaS plan.',
            'price_monthly' => 450000,
            'currency' => 'TZS',
            'seat_limit' => 50,
            'entitlements' => ['quotes' => true, 'claims' => true, 'finance' => true, 'api_sandbox' => true],
            'is_active' => true,
        ]);

        PlatformUser::query()->create([
            'name' => 'Platform Owner',
            'email' => 'platform@wabantu.local',
            'password' => 'Password1!',
            'role' => 'platform_owner',
            'is_active' => true,
        ]);
    }
}
