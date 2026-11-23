<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saga_logs', function (Blueprint $table) {
            $table->id();
            $table->string('order_code')->index();
            $table->string('saga_step');      // OrderStep, PaymentStep, InventoryStep, ShippingStep
            $table->string('action_type');   // FORWARD, COMPENSATING
            $table->string('status');        // SUCCESS, FAILED
            $table->json('details')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saga_logs');
    }
};

