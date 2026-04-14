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
         Schema::create('dispatch_item_lines', function (Blueprint $table) {
        $table->id();
        $table->foreignId('dispatch_item_id')
              ->constrained('dispatch_items')
              ->cascadeOnDelete();
        $table->foreignId('sales_order_item_id')
              ->constrained('sales_order_items')
              ->cascadeOnDelete();
        $table->decimal('qty_solicitada', 12, 3);
        $table->decimal('qty_despachada', 12, 3)->nullable(); // null = pendiente
        $table->text('nota')->nullable();
        $table->timestamps();
     });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dispatch_item_lines');
    }
};
