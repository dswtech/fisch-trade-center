<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('value_imports', function (Blueprint $table): void {
            $table->json('payload')->nullable()->after('validation_errors');
        });

        Schema::table('listing_versions', function (Blueprint $table): void {
            $table->foreignId('value_snapshot_id')->nullable()->after('version')->constrained('value_snapshots');
            $table->decimal('offered_value', 14, 2)->unsigned()->nullable()->after('wanted_items');
            $table->decimal('wanted_value', 14, 2)->unsigned()->nullable()->after('offered_value');
            $table->index('offered_value');
        });

        Schema::create('listing_version_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('listing_version_id')->constrained('listing_versions')->cascadeOnDelete();
            $table->foreignId('catalog_item_id')->constrained('catalog_items');
            $table->string('side', 10);
            $table->unsignedInteger('quantity');
            $table->decimal('unit_value', 14, 2)->unsigned()->nullable();
            $table->index(['side', 'catalog_item_id', 'listing_version_id'], 'listing_items_side_catalog_version_idx');
            $table->index(['listing_version_id', 'side']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('listing_version_items');
        Schema::table('listing_versions', function (Blueprint $table): void {
            $table->dropIndex(['offered_value']);
            $table->dropConstrainedForeignId('value_snapshot_id');
            $table->dropColumn(['offered_value', 'wanted_value']);
        });
        Schema::table('value_imports', fn (Blueprint $table) => $table->dropColumn('payload'));
    }
};
