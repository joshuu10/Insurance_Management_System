<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('policies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignUuid('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignUuid('customer_id')->constrained('customers')->restrictOnDelete();
            $table->foreignUuid('carrier_id')->constrained('carriers')->restrictOnDelete();
            $table->foreignUuid('carrier_product_id')->nullable()->constrained('carrier_products')->nullOnDelete();
            $table->foreignUuid('quote_id')->nullable()->constrained('quotes')->nullOnDelete();
            $table->foreignUuid('application_id')->nullable()->constrained('insurance_applications')->nullOnDelete();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('carrier_policy_number');
            $table->string('cover_note_number')->nullable();
            $table->string('status')->default('pending_issuance');
            // pending_insurer|pending_premium_confirmation|pending_issuance|active|grace|expired|lapsed|cancelled
            $table->string('verification_status')->default('not_checked');
            // not_checked|submitted|pending|confirmed|mismatch|rejected|unavailable
            $table->string('verification_reference')->nullable();

            $table->dateTime('effective_from');
            $table->dateTime('effective_to');
            $table->decimal('sum_insured', 18, 2)->nullable();
            $table->decimal('premium_amount', 18, 2)->nullable();
            $table->char('currency', 3)->default('TZS');
            $table->json('insured_risks')->nullable();
            $table->json('exclusions')->nullable();
            $table->json('schedule')->nullable();
            $table->string('source_document_ref')->nullable();
            $table->string('provenance')->default('insurer_import'); // insurer_import|manual_staff_entry
            $table->text('intake_notes')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->unique(['carrier_id', 'carrier_policy_number']);
            $table->index(['organization_id', 'status']);
            $table->index(['organization_id', 'effective_to']);
        });

        Schema::create('endorsements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignUuid('policy_id')->constrained('policies')->cascadeOnDelete();
            $table->string('reference')->unique();
            $table->string('type'); // add_asset|remove_asset|change_address|change_sum_insured|change_beneficiary|other
            $table->string('status')->default('draft');
            // draft|pending_insurer|insurer_approved|insurer_rejected|applied|cancelled
            $table->text('description');
            $table->json('requested_changes')->nullable();
            $table->decimal('adjustment_premium', 18, 2)->nullable();
            $table->string('insurer_approval_ref')->nullable();
            $table->dateTime('effective_from')->nullable();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('cancellations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignUuid('policy_id')->constrained('policies')->cascadeOnDelete();
            $table->string('status')->default('requested');
            // requested|pending_insurer|confirmed|rejected
            $table->string('reason');
            $table->dateTime('effective_from');
            $table->string('insurer_confirmation_ref')->nullable();
            $table->decimal('refund_amount', 18, 2)->nullable();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('renewal_cases', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignUuid('policy_id')->constrained('policies')->cascadeOnDelete();
            $table->string('status')->default('open');
            // open|notified|quote_requested|offer_ready|accepted|lost|closed
            $table->unsignedSmallInteger('days_to_expiry')->nullable();
            $table->timestamp('notified_at')->nullable();
            $table->foreignUuid('renewal_quote_id')->nullable()->constrained('quotes')->nullOnDelete();
            $table->foreignUuid('renewed_policy_id')->nullable()->constrained('policies')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('subject_type'); // customer|quote|policy|claim|endorsement
            $table->uuid('subject_id');
            $table->string('purpose'); // kyc|proposal|policy_schedule|cover_note|invoice|other
            $table->string('issuer'); // agency|insurer|customer|third_party
            $table->string('original_filename');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->string('storage_disk')->default('local');
            $table->string('storage_path')->nullable();
            $table->string('content_hash')->nullable();
            $table->string('classification')->default('internal');
            $table->string('status')->default('available');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['subject_type', 'subject_id']);
        });

        Schema::create('premium_instructions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignUuid('policy_id')->nullable()->constrained('policies')->nullOnDelete();
            $table->foreignUuid('quote_id')->nullable()->constrained('quotes')->nullOnDelete();
            $table->foreignUuid('carrier_id')->constrained('carriers')->restrictOnDelete();
            $table->string('status')->default('created');
            // created|pending_payer|provider_processing|verified_paid|failed|expired|refunded|disputed
            $table->decimal('amount', 18, 2);
            $table->char('currency', 3)->default('TZS');
            $table->string('beneficiary_name');
            $table->string('beneficiary_account')->nullable();
            $table->string('beneficiary_channel')->default('bank_transfer'); // bank_transfer|mobile_money|card_link
            $table->string('payment_reference')->nullable();
            $table->text('payer_instructions')->nullable();
            $table->boolean('routes_to_insurer')->default(true);
            $table->string('integration_state')->default('NOT_CONFIGURED');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('premium_instructions');
        Schema::dropIfExists('documents');
        Schema::dropIfExists('renewal_cases');
        Schema::dropIfExists('cancellations');
        Schema::dropIfExists('endorsements');
        Schema::dropIfExists('policies');
    }
};
