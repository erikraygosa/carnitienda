<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Notas de venta (mostrador) a crédito asignadas a un cobro en ruta,
    // igual que dispatch_ar_assignment_orders lo hace con los pedidos.
    public function up(): void
    {
        Schema::create('dispatch_ar_assignment_sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dispatch_ar_assignment_id')->constrained('dispatch_ar_assignments')->cascadeOnDelete();
            $table->foreignId('sale_id')->constrained('sales')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['dispatch_ar_assignment_id', 'sale_id'], 'dispatch_ar_assign_sale_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dispatch_ar_assignment_sales');
    }
};
