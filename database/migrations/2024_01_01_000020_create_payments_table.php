<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('order_code')->index(); // Loose link to order
            $table->string('transaction_id')->nullable()->unique();
            $table->string('payment_type')->nullable(); // qris, credit_card, bank_transfer, etc.
            $table->decimal('amount', 12, 2);
            $table->string('status')->default('PENDING'); // PENDING, PAID, REFUNDED, FAILED
            $table->string('snap_token')->nullable();
            $table->json('payment_payload')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};

