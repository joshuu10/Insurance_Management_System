<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('legal_name');
            $table->string('trading_name')->nullable();
            $table->string('intermediary_type')->default('brokerage'); // agent|brokerage|agent_for_broker|other
            $table->string('licence_number')->nullable();
            $table->string('licence_category')->nullable();
            $table->date('licence_issued_on')->nullable();
            $table->date('licence_expires_on')->nullable();
            $table->string('regulatory_status')->default('pending_review');
            $table->string('timezone')->default('Africa/Dar_es_Salaam');
            $table->char('currency', 3)->default('TZS');
            $table->string('locale')->default('en-TZ');
            $table->boolean('onboarding_completed')->default(false);
            $table->json('settings')->nullable();
            $table->timestamps();
        });

        Schema::create('branches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('name');
            $table->string('code')->nullable();
            $table->string('city')->nullable();
            $table->string('region')->nullable();
            $table->boolean('is_head_office')->default(false);
            $table->timestamps();
            $table->unique(['organization_id', 'code']);
        });

        Schema::create('roles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->nullable()->constrained('organizations')->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('guard_name')->default('web');
            $table->boolean('is_system')->default(false);
            $table->timestamps();
            $table->unique(['organization_id', 'slug']);
        });

        Schema::create('permissions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('group')->nullable();
            $table->timestamps();
        });

        Schema::create('permission_role', function (Blueprint $table) {
            $table->foreignUuid('permission_id')->constrained('permissions')->cascadeOnDelete();
            $table->foreignUuid('role_id')->constrained('roles')->cascadeOnDelete();
            $table->primary(['permission_id', 'role_id']);
        });

        Schema::create('role_user', function (Blueprint $table) {
            $table->foreignUuid('role_id')->constrained('roles')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->primary(['role_id', 'user_id']);
        });

        Schema::create('audit_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->nullable()->constrained('organizations')->nullOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action');
            $table->string('subject_type')->nullable();
            $table->string('subject_id')->nullable();
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->string('correlation_id')->nullable();
            $table->text('reason')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('carriers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('legal_name');
            $table->string('trading_name')->nullable();
            $table->string('country_code', 2)->default('TZ');
            $table->string('status')->default('inactive'); // inactive|pending|verified|active
            $table->boolean('is_illustrative')->default(false);
            $table->text('disclaimer')->nullable();
            $table->string('data_source')->default('seed');
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignUuid('organization_id')->nullable()->after('id')->constrained('organizations')->nullOnDelete();
            $table->foreignUuid('branch_id')->nullable()->after('organization_id')->constrained('branches')->nullOnDelete();
            $table->string('phone_e164')->nullable()->after('email');
            $table->string('preferred_locale')->default('en-TZ')->after('phone_e164');
            $table->boolean('is_active')->default(true)->after('preferred_locale');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('branch_id');
            $table->dropConstrainedForeignId('organization_id');
            $table->dropColumn(['phone_e164', 'preferred_locale', 'is_active']);
        });

        Schema::dropIfExists('carriers');
        Schema::dropIfExists('audit_events');
        Schema::dropIfExists('role_user');
        Schema::dropIfExists('permission_role');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('branches');
        Schema::dropIfExists('organizations');
    }
};
