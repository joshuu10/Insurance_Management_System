<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('claims', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('reference')->unique();
            $table->foreignUuid('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignUuid('policy_id')->constrained('policies')->restrictOnDelete();
            $table->foreignUuid('customer_id')->constrained('customers')->restrictOnDelete();
            $table->foreignUuid('carrier_id')->constrained('carriers')->restrictOnDelete();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('draft');
            // draft|submitted|acknowledged|documents_pending|insurer_investigating|assessed|
            // insurer_approved|insurer_rejected|partially_approved|payment_pending|insurer_settled|closed
            $table->dateTime('incident_at');
            $table->string('incident_location')->nullable();
            $table->text('description');
            $table->string('police_reference')->nullable();
            $table->decimal('declared_amount', 18, 2)->nullable();
            $table->decimal('insurer_approved_amount', 18, 2)->nullable();
            $table->char('currency', 3)->default('TZS');
            $table->string('urgency')->default('normal'); // normal|high|critical
            $table->boolean('coverage_provisional_notice')->default(true);
            $table->string('fraud_review_flag')->nullable(); // null|possible_duplicate|manual_review — explainable only
            $table->text('staff_notes')->nullable();
            $table->dateTime('submitted_at')->nullable();
            $table->dateTime('closed_at')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'status']);
        });

        Schema::create('claim_timeline_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('claim_id')->constrained('claims')->cascadeOnDelete();
            $table->foreignUuid('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('event_type');
            $table->text('message');
            $table->string('visibility')->default('staff'); // staff|customer|insurer
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('payload')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('commission_schedules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignUuid('carrier_id')->constrained('carriers')->cascadeOnDelete();
            $table->foreignUuid('carrier_product_id')->nullable()->constrained('carrier_products')->nullOnDelete();
            $table->string('name');
            $table->decimal('rate_percent', 8, 4)->nullable();
            $table->decimal('fixed_amount', 18, 2)->nullable();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->string('status')->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('commission_receivables', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignUuid('policy_id')->nullable()->constrained('policies')->nullOnDelete();
            $table->foreignUuid('carrier_id')->constrained('carriers')->restrictOnDelete();
            $table->foreignUuid('commission_schedule_id')->nullable()->constrained('commission_schedules')->nullOnDelete();
            $table->string('status')->default('expected'); // expected|insurer_confirmed|received|reversed|clawback
            $table->decimal('expected_amount', 18, 2);
            $table->decimal('confirmed_amount', 18, 2)->nullable();
            $table->decimal('received_amount', 18, 2)->nullable();
            $table->char('currency', 3)->default('TZS');
            $table->string('basis')->default('calculated'); // calculated|insurer_statement|manual
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('chart_of_accounts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('code');
            $table->string('name');
            $table->string('type'); // asset|liability|equity|income|expense
            $table->boolean('is_system')->default(false);
            $table->timestamps();
            $table->unique(['organization_id', 'code']);
        });

        Schema::create('journal_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('reference')->unique();
            $table->foreignUuid('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('memo');
            $table->date('entry_date');
            $table->string('status')->default('posted'); // draft|posted|voided
            $table->string('source')->default('manual'); // manual|commission|premium_ack|reversal
            $table->uuid('source_id')->nullable();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('posted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('journal_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('journal_entry_id')->constrained('journal_entries')->cascadeOnDelete();
            $table->foreignUuid('account_id')->constrained('chart_of_accounts')->restrictOnDelete();
            $table->decimal('debit', 18, 2)->default(0);
            $table->decimal('credit', 18, 2)->default(0);
            $table->string('memo')->nullable();
            $table->timestamps();
        });

        Schema::create('reconciliation_batches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('name');
            $table->string('source_type'); // insurer_statement|bank_statement|provider
            $table->string('status')->default('open'); // open|in_progress|closed
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('reconciliation_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('batch_id')->constrained('reconciliation_batches')->cascadeOnDelete();
            $table->string('external_reference')->nullable();
            $table->decimal('amount', 18, 2);
            $table->char('currency', 3)->default('TZS');
            $table->string('status')->default('unmatched'); // unmatched|matched|exception|suspense
            $table->uuid('matched_commission_id')->nullable();
            $table->uuid('matched_journal_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reconciliation_lines');
        Schema::dropIfExists('reconciliation_batches');
        Schema::dropIfExists('journal_lines');
        Schema::dropIfExists('journal_entries');
        Schema::dropIfExists('chart_of_accounts');
        Schema::dropIfExists('commission_receivables');
        Schema::dropIfExists('commission_schedules');
        Schema::dropIfExists('claim_timeline_entries');
        Schema::dropIfExists('claims');
    }
};
