<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('carrier_appointments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignUuid('carrier_id')->constrained('carriers')->cascadeOnDelete();
            $table->string('status')->default('pending'); // pending|active|suspended|expired
            $table->date('appointed_on')->nullable();
            $table->date('expires_on')->nullable();
            $table->string('authority_scope')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('allows_quoting')->default(true);
            $table->timestamps();
            $table->unique(['organization_id', 'carrier_id']);
        });

        Schema::create('product_classes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code')->unique();
            $table->string('name_en');
            $table->string('name_sw')->nullable();
            $table->timestamps();
        });

        Schema::create('carrier_products', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('carrier_id')->constrained('carriers')->cascadeOnDelete();
            $table->foreignUuid('product_class_id')->constrained('product_classes')->cascadeOnDelete();
            $table->string('name');
            $table->string('code')->nullable();
            $table->string('status')->default('draft'); // draft|active|retired
            $table->boolean('requires_insurer_rating')->default(true);
            $table->timestamps();
        });

        Schema::create('product_versions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('carrier_product_id')->constrained('carrier_products')->cascadeOnDelete();
            $table->string('version_label');
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->json('questionnaire_schema')->nullable();
            $table->json('required_documents')->nullable();
            $table->text('disclosures')->nullable();
            $table->boolean('is_current')->default(true);
            $table->timestamps();
        });

        Schema::create('customers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignUuid('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->string('type'); // individual|corporate
            $table->string('display_name');
            $table->string('email')->nullable();
            $table->string('phone_e164')->nullable();
            $table->string('preferred_locale')->default('en-TZ');
            $table->string('kyc_status')->default('not_started');
            $table->json('metadata')->nullable();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['organization_id', 'display_name']);
        });

        Schema::create('leads', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignUuid('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignUuid('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->string('source')->default('manual');
            $table->string('stage')->default('new'); // new|contacted|qualified|assessment|quote_requested|insurer_response|negotiation|won|lost
            $table->string('expected_premium_note')->nullable(); // estimate clearly marked — no fake amounts as official
            $table->decimal('expected_premium_estimate', 18, 2)->nullable();
            $table->char('currency', 3)->default('TZS');
            $table->text('blockers')->nullable();
            $table->timestamp('next_follow_up_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('quotes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('reference')->unique();
            $table->foreignUuid('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignUuid('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignUuid('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignUuid('lead_id')->nullable()->constrained('leads')->nullOnDelete();
            $table->foreignUuid('carrier_id')->constrained('carriers')->restrictOnDelete();
            $table->foreignUuid('carrier_product_id')->constrained('carrier_products')->restrictOnDelete();
            $table->foreignUuid('product_version_id')->nullable()->constrained('product_versions')->nullOnDelete();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('draft');
            // draft|validated|submitted_to_insurer|awaiting_insurer|insurer_confirmed|declined|expired|accepted|customer_declined
            $table->string('premium_classification')->default('awaiting_insurer');
            // awaiting_insurer|indicative_internal|insurer_confirmed — never invent official rates
            $table->decimal('premium_amount', 18, 2)->nullable();
            $table->char('currency', 3)->default('TZS');
            $table->json('risk_summary')->nullable();
            $table->json('answers')->nullable();
            $table->text('caveats')->nullable();
            $table->timestamp('valid_until')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('insurance_applications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('reference')->unique();
            $table->foreignUuid('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignUuid('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignUuid('quote_id')->nullable()->constrained('quotes')->nullOnDelete();
            $table->foreignUuid('carrier_id')->constrained('carriers')->restrictOnDelete();
            $table->foreignUuid('carrier_product_id')->constrained('carrier_products')->restrictOnDelete();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('draft');
            // draft|submitted|awaiting_insurer|info_requested|accepted|declined|withdrawn
            $table->json('payload')->nullable();
            $table->boolean('disclosure_acknowledged')->default(false);
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('insurer_submissions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('subject_type'); // quote|application
            $table->uuid('subject_id');
            $table->foreignUuid('carrier_id')->constrained('carriers')->restrictOnDelete();
            $table->string('channel')->default('manual'); // manual|sandbox|api
            $table->string('status')->default('queued');
            // queued|exported|submitted_manual|sandbox_ack|failed|cancelled
            $table->string('integration_state')->default('NOT_CONFIGURED');
            $table->json('packet')->nullable();
            $table->text('staff_notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamps();
            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('insurer_submissions');
        Schema::dropIfExists('insurance_applications');
        Schema::dropIfExists('quotes');
        Schema::dropIfExists('leads');
        Schema::dropIfExists('customers');
        Schema::dropIfExists('product_versions');
        Schema::dropIfExists('carrier_products');
        Schema::dropIfExists('product_classes');
        Schema::dropIfExists('carrier_appointments');
    }
};
