<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->string('customer_name');
            $table->string('customer_email');
            $table->string('customer_phone', 30);
            $table->text('delivery_address');
            $table->string('delivery_city');
            $table->string('delivery_state');
            $table->string('payment_method');
            $table->string('payment_status')->default('pending');
            $table->string('status')->default('placed');
            $table->unsignedBigInteger('subtotal_kobo');
            $table->unsignedBigInteger('delivery_fee_kobo');
            $table->unsignedBigInteger('total_kobo');
            $table->timestamps();
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('vendor_id')->nullable()->constrained()->nullOnDelete();
            $table->string('product_name');
            $table->string('vendor_name');
            $table->string('seller_sku')->nullable();
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('unit_price_kobo');
            $table->unsignedBigInteger('line_total_kobo');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};
