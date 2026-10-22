<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('identity_verification_status')->default('pending')->after('kyc_status');
            // pending|otp_sent|verified|failed|manual_review
            $table->timestamp('identity_verified_at')->nullable()->after('identity_verification_status');
            $table->string('national_id_last4')->nullable()->after('identity_verified_at');
        });

        Schema::create('portal_otp_challenges', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('channel')->default('email'); // email|sms_sandbox
            $table->string('code_hash');
            $table->string('purpose')->default('identity_verify');
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamps();
        });

        Schema::create('portal_service_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignUuid('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type'); // quotation|product_enquiry|renewal|support|general
            $table->string('status')->default('open'); // open|in_progress|awaiting_customer|resolved|closed
            $table->string('subject');
            $table->text('body');
            $table->foreignUuid('carrier_product_id')->nullable()->constrained('carrier_products')->nullOnDelete();
            $table->foreignUuid('policy_id')->nullable()->constrained('policies')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->text('staff_response')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->index(['customer_id', 'status']);
        });

        Schema::create('portal_payment_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignUuid('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignUuid('policy_id')->nullable()->constrained('policies')->nullOnDelete();
            $table->foreignUuid('premium_instruction_id')->nullable()->constrained('premium_instructions')->nullOnDelete();
            $table->string('reference');
            $table->string('gateway')->default('sandbox');
            $table->string('status')->default('pending'); // pending|processing|sandbox_confirmed|failed|cancelled
            $table->decimal('amount', 18, 2);
            $table->char('currency', 3)->default('TZS');
            $table->string('receipt_number')->nullable();
            $table->json('gateway_payload')->nullable();
            $table->boolean('fabricated_live_confirmation')->default(false);
            $table->text('notice')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
            $table->unique(['organization_id', 'reference']);
        });

        Schema::create('customer_notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignUuid('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->string('category'); // account|policy|payment|claim|renewal|service
            $table->string('title');
            $table->text('body');
            $table->string('channel')->default('in_app'); // in_app|email_sandbox|sms_sandbox
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['customer_id', 'is_read']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_notifications');
        Schema::dropIfExists('portal_payment_transactions');
        Schema::dropIfExists('portal_service_requests');
        Schema::dropIfExists('portal_otp_challenges');
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['identity_verification_status', 'identity_verified_at', 'national_id_last4']);
        });
    }
};
