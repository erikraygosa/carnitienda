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
        Schema::create('ar_payment_items', function (Blueprint $table) {
        $table->id();
        $table->foreignId('ar_payment_id')
              ->constrained('ar_payments')
              ->cascadeOnDelete();
        $table->foreignId('sales_order_id')
              ->constrained('sales_orders')
              ->cascadeOnDelete();
        $table->decimal('monto_aplicado', 12, 2);
        $table->timestamps();
    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ar_payment_items');
    }
};
