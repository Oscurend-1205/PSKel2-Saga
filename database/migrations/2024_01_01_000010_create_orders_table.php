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
            $table->string('order_code')->unique();
            $table->unsignedBigInteger('product_id'); // Simulated microservice ID (no FK constraint)
            $table->string('product_name');
            $table->integer('quantity');
            $table->decimal('price', 12, 2);
            $table->decimal('total_amount', 12, 2);
            $table->string('customer_name');
            $table->string('customer_email');
            $table->text('shipping_address');
            $table->string('status')->default('PENDING'); // PENDING, COMPLETED, CANCELED
            $table->text('fail_reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};

