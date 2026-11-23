<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->string('order_code')->index();
            $table->string('tracking_number')->unique();
            $table->string('courier_name')->default('SAGA-EXPRESS');
            $table->string('status')->default('SCHEDULED'); // SCHEDULED, CANCELED, DELIVERED
            $table->text('destination_address');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipments');
    }
};

