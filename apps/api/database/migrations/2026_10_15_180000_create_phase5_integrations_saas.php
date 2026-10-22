<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integrations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->nullable()->constrained('organizations')->cascadeOnDelete();
            $table->string('provider_key'); // tira_mis|insurer_api|premium_payment|sms|email|whatsapp|efd|esign
            $table->string('display_name');
            $table->string('status')->default('NOT_CONFIGURED');
            // NOT_CONFIGURED|SANDBOX|PENDING_APPROVAL|ACTIVE|DEGRADED|DISABLED
            $table->boolean('is_platform_level')->default(false);
            $table->json('config')->nullable(); // non-secret settings only
            $table->string('credentials_ref')->nullable(); // vault/secret manager reference — never raw secrets
            $table->timestamp('last_health_check_at')->nullable();
            $table->string('last_health_status')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['organization_id', 'provider_key']);
        });

        Schema::create('integration_sync_runs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('integration_id')->constrained('integrations')->cascadeOnDelete();
            $table->string('direction')->default('outbound'); // outbound|inbound
            $table->string('status')->default('queued'); // queued|running|succeeded|failed|dead_letter
            $table->json('request_meta')->nullable(); // redacted
            $table->json('response_meta')->nullable(); // redacted
            $table->text('error_message')->nullable();
            $table->unsignedSmallInteger('attempt')->default(1);
            $table->timestamps();
        });

        Schema::create('saas_tenants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('slug')->unique();
            $table->string('legal_name');
            $table->string('status')->default('pending'); // pending|active|suspended|deprovisioning|closed
            $table->foreignUuid('organization_id')->nullable()->constrained('organizations')->nullOnDelete();
            $table->string('primary_domain')->nullable()->unique();
            $table->string('region')->default('tz-dar');
            $table->string('db_connection_name')->nullable(); // future dedicated DB key
            $table->json('branding')->nullable(); // logo_url, primary_color, etc.
            $table->json('feature_flags')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->text('suspension_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('tenant_domains', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('saas_tenant_id')->constrained('saas_tenants')->cascadeOnDelete();
            $table->string('hostname')->unique();
            $table->boolean('is_primary')->default(false);
            $table->boolean('verified')->default(false);
            $table->string('verification_token')->nullable();
            $table->timestamps();
        });

        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('price_monthly', 18, 2)->default(0);
            $table->char('currency', 3)->default('TZS');
            $table->unsignedInteger('seat_limit')->nullable();
            $table->json('entitlements')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('tenant_subscriptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('saas_tenant_id')->constrained('saas_tenants')->cascadeOnDelete();
            $table->foreignUuid('subscription_plan_id')->constrained('subscription_plans')->restrictOnDelete();
            $table->string('status')->default('trialing'); // trialing|active|past_due|cancelled|suspended
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->date('trial_ends_on')->nullable();
            $table->unsignedInteger('seats')->default(5);
            $table->timestamps();
        });

        Schema::create('platform_invoices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('reference')->unique();
            $table->foreignUuid('saas_tenant_id')->constrained('saas_tenants')->cascadeOnDelete();
            $table->foreignUuid('tenant_subscription_id')->nullable()->constrained('tenant_subscriptions')->nullOnDelete();
            $table->decimal('amount', 18, 2);
            $table->char('currency', 3)->default('TZS');
            $table->string('status')->default('draft'); // draft|issued|paid|void|past_due
            $table->string('ledger')->default('platform_software'); // NEVER insurance_premium
            $table->date('due_on')->nullable();
            $table->dateTime('paid_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('platform_users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('role')->default('platform_ops'); // platform_owner|platform_ops|platform_billing|platform_security|support
            $table->boolean('is_active')->default(true);
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::table('organizations', function (Blueprint $table) {
            $table->foreignUuid('saas_tenant_id')->nullable()->after('id')->constrained('saas_tenants')->nullOnDelete();
            $table->json('branding')->nullable()->after('settings');
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('saas_tenant_id');
            $table->dropColumn('branding');
        });

        Schema::dropIfExists('platform_users');
        Schema::dropIfExists('platform_invoices');
        Schema::dropIfExists('tenant_subscriptions');
        Schema::dropIfExists('subscription_plans');
        Schema::dropIfExists('tenant_domains');
        Schema::dropIfExists('saas_tenants');
        Schema::dropIfExists('integration_sync_runs');
        Schema::dropIfExists('integrations');
    }
};
