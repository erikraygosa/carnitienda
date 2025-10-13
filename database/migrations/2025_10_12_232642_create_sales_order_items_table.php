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
        Schema::create('sales_order_items', function (Blueprint $table) {
        $table->id();
        $table->foreignId('sales_order_id')->constrained('sales_orders')->cascadeOnDelete();
        $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();

        $table->string('descripcion', 255);
        $table->decimal('cantidad', 14, 3);
        $table->decimal('precio', 14, 4);
        $table->decimal('descuento', 14, 2)->default(0);
        $table->decimal('impuesto', 14, 2)->default(0);
        $table->decimal('total', 14, 2)->default(0);

        $table->timestamps();
    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales_order_items');
    }
};
