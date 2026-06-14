<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('vendor_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            $table->string('seller_sku')->nullable()->unique();
            $table->unsignedInteger('inventory_count')->default(0);
            $table->boolean('is_active')->default(true)->index();
        });

        Schema::table('search_logs', function (Blueprint $table) {
            $table->foreignId('vendor_id')->nullable()->constrained()->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('search_logs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('vendor_id');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('vendor_id');
            $table->dropColumn(['seller_sku', 'inventory_count', 'is_active']);
        });
    }
};
