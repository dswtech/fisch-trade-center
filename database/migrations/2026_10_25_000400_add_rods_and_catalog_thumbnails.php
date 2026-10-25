<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rods', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('name')->unique();
            $table->timestamps();
        });

        Schema::table('catalog_items', function (Blueprint $table): void {
            $table->foreignId('rod_id')->nullable()->after('category_id')->constrained('rods')->nullOnDelete();
            $table->string('thumbnail_path')->nullable()->after('image_url');
        });

        DB::table('catalog_categories')->where('key', 'skin')->update(['key' => 'rod_skin', 'name' => 'Rod Skin']);
    }

    public function down(): void
    {
        DB::table('catalog_categories')->where('key', 'rod_skin')->update(['key' => 'skin', 'name' => 'Skins']);
        Schema::table('catalog_items', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('rod_id');
            $table->dropColumn('thumbnail_path');
        });
        Schema::dropIfExists('rods');
    }
};
