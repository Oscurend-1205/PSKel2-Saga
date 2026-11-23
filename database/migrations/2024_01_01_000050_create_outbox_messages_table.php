<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outbox_messages', function (Blueprint $table) {
            $table->id();
            $table->string('aggregate_type'); // Order, Payment, Inventory, Shipping
            $table->string('aggregate_id');   // e.g. order_code or ID
            $table->string('event_type');     // OrderCreated, PaymentCompleted, StockReserved, ShipmentCreated, etc.
            $table->json('payload');
            $table->string('status')->default('PENDING'); // PENDING, PROCESSED, FAILED
            $table->text('error_message')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outbox_messages');
    }
};

