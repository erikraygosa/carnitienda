<?php
 use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('dispatch_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dispatch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sales_order_id')->constrained('sales_orders')->cascadeOnDelete();
            $table->string('referencia')->nullable(); // folio del pedido, guía, etc (denormalizado útil)
            $table->decimal('volumen', 12, 3)->nullable(); // opcional
            $table->decimal('peso', 12, 3)->nullable();    // opcional
            $table->string('status', 20)->default('ASIGNADO'); // ASIGNADO, CARGADO, ENTREGADO, NO_ENTREGADO
            $table->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('dispatch_items');
    }
};
