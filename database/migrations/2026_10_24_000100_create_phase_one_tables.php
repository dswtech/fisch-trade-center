<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->uuid('public_id')->unique();
            $table->string('role', 20)->default('player')->index();
            $table->string('locale', 5)->nullable();
            $table->string('theme', 10)->default('dark');
        });
        Schema::create('player_profiles', function (Blueprint $table): void {
            $table->id(); $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('usn'); $table->string('ign'); $table->timestamps();
        });
        Schema::create('catalog_categories', function (Blueprint $table): void {
            $table->id(); $table->uuid('public_id')->unique(); $table->string('key')->unique();
            $table->string('name'); $table->boolean('is_active')->default(true)->index(); $table->timestamps();
        });
        Schema::create('catalog_items', function (Blueprint $table): void {
            $table->id(); $table->uuid('public_id')->unique();
            $table->foreignId('category_id')->constrained('catalog_categories');
            $table->string('canonical_name'); $table->json('aliases')->nullable();
            $table->string('image_url')->nullable(); $table->string('source_url')->nullable();
            $table->json('provenance')->nullable(); $table->boolean('is_active')->default(true)->index();
            $table->timestamps(); $table->index(['category_id', 'canonical_name']);
        });
        Schema::create('value_imports', function (Blueprint $table): void {
            $table->id(); $table->uuid('public_id')->unique(); $table->string('source_key');
            $table->string('status', 20)->index(); $table->string('checksum', 64)->index();
            $table->timestampTz('source_timestamp')->nullable(); $table->json('validation_errors')->nullable();
            $table->timestampTz('imported_at'); $table->timestamps();
        });
        Schema::create('value_snapshots', function (Blueprint $table): void {
            $table->id(); $table->uuid('public_id')->unique();
            $table->foreignId('value_import_id')->unique()->constrained('value_imports');
            $table->string('version')->unique(); $table->string('checksum', 64)->unique();
            $table->timestampTz('source_timestamp'); $table->timestampTz('published_at')->nullable()->index();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete(); $table->timestamps();
        });
        Schema::create('catalog_item_values', function (Blueprint $table): void {
            $table->id(); $table->foreignId('snapshot_id')->constrained('value_snapshots')->cascadeOnDelete();
            $table->foreignId('catalog_item_id')->constrained('catalog_items'); $table->unsignedBigInteger('unit_value');
            $table->string('offer_range')->nullable(); $table->unique(['snapshot_id', 'catalog_item_id']);
        });
        Schema::create('inventory_entries', function (Blueprint $table): void {
            $table->id(); $table->uuid('public_id')->unique(); $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('catalog_item_id')->constrained('catalog_items'); $table->unsignedInteger('quantity')->default(1);
            $table->json('attributes')->nullable(); $table->timestampTz('archived_at')->nullable()->index(); $table->timestamps();
            $table->unique(['user_id', 'catalog_item_id']);
        });
        Schema::create('listings', function (Blueprint $table): void {
            $table->id(); $table->uuid('public_id')->unique(); $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('value_snapshot_id')->nullable()->constrained('value_snapshots');
            $table->string('status', 20)->default('draft')->index(); $table->unsignedInteger('current_version')->default(1);
            $table->timestampTz('published_at')->nullable(); $table->timestampTz('expires_at')->nullable()->index(); $table->timestamps();
            $table->index(['status', 'expires_at', 'id']); $table->index(['user_id', 'status']);
        });
        Schema::create('listing_versions', function (Blueprint $table): void {
            $table->id(); $table->foreignId('listing_id')->constrained()->cascadeOnDelete(); $table->unsignedInteger('version');
            $table->json('offered_items'); $table->json('wanted_items'); $table->timestamps();
            $table->unique(['listing_id', 'version']);
        });
        Schema::create('system_settings', function (Blueprint $table): void {
            $table->string('key')->primary(); $table->json('value');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete(); $table->timestamps();
        });
        Schema::create('audit_events', function (Blueprint $table): void {
            $table->id(); $table->uuid('public_id')->unique(); $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event_type')->index(); $table->string('subject_type'); $table->string('subject_public_id')->nullable();
            $table->json('metadata')->nullable(); $table->timestampTz('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        foreach (['audit_events', 'system_settings', 'listing_versions', 'listings', 'inventory_entries', 'catalog_item_values', 'value_snapshots', 'value_imports', 'catalog_items', 'catalog_categories', 'player_profiles'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['public_id', 'role', 'locale', 'theme']));
    }
};
