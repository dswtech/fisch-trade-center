<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('listing_version_items', function (Blueprint $table): void {
            $table->foreignId('inventory_entry_id')->nullable()->after('catalog_item_id')->constrained('inventory_entries')->restrictOnDelete();
            $table->index(['inventory_entry_id', 'listing_version_id']);
        });
    }

    public function down(): void
    {
        Schema::table('listing_version_items', function (Blueprint $table): void {
            $table->dropIndex(['inventory_entry_id', 'listing_version_id']);
            $table->dropConstrainedForeignId('inventory_entry_id');
        });
    }
};
