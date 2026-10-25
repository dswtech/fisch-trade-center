<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('negotiations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('listing_id')->constrained()->restrictOnDelete();
            $table->foreignId('initiator_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('owner_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('value_snapshot_id')->nullable()->constrained('value_snapshots')->restrictOnDelete();
            $table->string('status', 24)->default('open')->index();
            $table->unsignedInteger('active_proposal_version')->nullable();
            $table->string('initiator_alias', 80);
            $table->string('owner_alias', 80);
            $table->timestampTz('last_activity_at')->index();
            $table->timestampTz('expires_at')->index();
            $table->timestamps();
            $table->index(['initiator_id', 'status']);
            $table->index(['owner_id', 'status']);
        });

        Schema::create('proposal_versions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('negotiation_id')->constrained()->restrictOnDelete();
            $table->foreignId('proposer_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('value_snapshot_id')->nullable()->constrained('value_snapshots')->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->string('status', 24)->default('active')->index();
            $table->decimal('initiator_value', 14, 2)->nullable();
            $table->decimal('owner_value', 14, 2)->nullable();
            $table->timestampTz('expires_at')->index();
            $table->timestamps();
            $table->unique(['negotiation_id', 'version']);
        });

        Schema::create('proposal_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('proposal_version_id')->constrained('proposal_versions')->restrictOnDelete();
            $table->foreignId('owner_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('inventory_entry_id')->constrained('inventory_entries')->restrictOnDelete();
            $table->foreignId('catalog_item_id')->constrained('catalog_items')->restrictOnDelete();
            $table->string('side', 16);
            $table->unsignedInteger('quantity');
            $table->decimal('unit_value', 14, 2)->nullable();
            $table->unique(['proposal_version_id', 'inventory_entry_id']);
            $table->index(['inventory_entry_id', 'proposal_version_id']);
        });

        Schema::create('proposal_acceptances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('proposal_version_id')->constrained('proposal_versions')->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->timestampTz('accepted_at');
            $table->unique(['proposal_version_id', 'user_id']);
        });

        Schema::create('pre_deal_versions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('negotiation_id')->constrained()->restrictOnDelete();
            $table->foreignId('proposal_version_id')->constrained('proposal_versions')->restrictOnDelete();
            $table->foreignId('proposer_id')->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->string('server_type', 20);
            $table->string('status', 24)->default('active')->index();
            $table->decimal('fee_quote', 14, 2)->default(0);
            $table->timestampTz('expires_at')->index();
            $table->timestamps();
            $table->unique(['negotiation_id', 'version']);
        });

        Schema::create('pre_deal_acceptances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pre_deal_version_id')->constrained('pre_deal_versions')->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->timestampTz('accepted_at');
            $table->unique(['pre_deal_version_id', 'user_id']);
        });

        Schema::create('deals', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('negotiation_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('proposal_version_id')->constrained('proposal_versions')->restrictOnDelete();
            $table->foreignId('pre_deal_version_id')->constrained('pre_deal_versions')->restrictOnDelete();
            $table->string('status', 32)->default('awaiting_final_acceptance')->index();
            $table->decimal('fee_quote', 14, 2)->default(0);
            $table->json('snapshot')->nullable();
            $table->timestampTz('expires_at')->index();
            $table->timestampTz('revealed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('deal_acceptances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('deal_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('binding_hash', 64);
            $table->timestampTz('accepted_at');
            $table->unique(['deal_id', 'user_id']);
        });

        Schema::create('deal_identity_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('deal_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->text('usn');
            $table->text('ign');
            $table->unique(['deal_id', 'user_id']);
        });

        Schema::create('reveal_entitlements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('deal_id')->constrained()->restrictOnDelete();
            $table->foreignId('viewer_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('subject_user_id')->constrained('users')->restrictOnDelete();
            $table->timestampTz('expires_at')->index();
            $table->unique(['deal_id', 'viewer_user_id', 'subject_user_id'], 'reveal_viewer_subject_unique');
        });
    }

    public function down(): void
    {
        foreach (['reveal_entitlements', 'deal_identity_snapshots', 'deal_acceptances', 'deals', 'pre_deal_acceptances', 'pre_deal_versions', 'proposal_acceptances', 'proposal_items', 'proposal_versions', 'negotiations'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
