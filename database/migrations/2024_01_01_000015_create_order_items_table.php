<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add order_items table for multi-product cart checkout
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->string('order_code')->index();
            $table->unsignedBigInteger('product_id')->index();
            $table->string('product_name');
            $table->integer('quantity');
            $table->decimal('price', 12, 2);
            $table->decimal('subtotal', 12, 2);
            $table->timestamps();
        });

        // Make product_id & product_name nullable on orders table since an order can contain multiple items
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedBigInteger('product_id')->nullable()->change();
            $table->string('product_name')->nullable()->change();
            $table->integer('quantity')->nullable()->change();
            $table->decimal('price', 12, 2)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};

