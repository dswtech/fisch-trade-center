<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_events', function (Blueprint $table): void {
            $table->uuid('correlation_id')->nullable()->after('public_id')->index();
            $table->string('reason_code', 50)->nullable()->after('subject_public_id');
            $table->json('before_state')->nullable()->after('reason_code');
            $table->json('after_state')->nullable()->after('before_state');
        });

        Schema::create('reports', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('reporter_id')->constrained('users');
            $table->string('target_type', 30);
            $table->uuid('target_public_id');
            $table->string('reason_code', 40);
            $table->string('status', 20)->default('open')->index();
            $table->foreignId('assigned_operator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('resolved_at')->nullable();
            $table->timestamps();
            $table->unique(['reporter_id', 'target_type', 'target_public_id', 'reason_code'], 'reports_unique_reason');
            $table->index(['target_type', 'target_public_id']);
        });

        Schema::create('suspensions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('user_id')->constrained('users');
            $table->string('reason_code', 50);
            $table->timestampTz('starts_at');
            $table->timestampTz('ends_at')->nullable();
            $table->timestampTz('lifted_at')->nullable()->index();
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('lifted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['user_id', 'starts_at', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suspensions');
        Schema::dropIfExists('reports');
        Schema::table('audit_events', function (Blueprint $table): void {
            $table->dropIndex(['correlation_id']);
            $table->dropColumn(['correlation_id', 'reason_code', 'before_state', 'after_state']);
        });
    }
};
