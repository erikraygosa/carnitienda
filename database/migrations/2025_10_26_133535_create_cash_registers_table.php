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
         Schema::create('cash_registers', function (Blueprint $t) {
      $t->id();
      $t->foreignId('warehouse_id')->constrained('warehouses');
      $t->foreignId('user_id')->constrained('users');
      $t->date('fecha');
      $t->decimal('monto_apertura',12,2)->default(0);
      $t->decimal('ingresos',12,2)->default(0);
      $t->decimal('egresos',12,2)->default(0);
      $t->decimal('ventas_efectivo',12,2)->default(0);
      $t->decimal('monto_cierre',12,2)->default(0);
      $t->enum('estatus',['ABIERTO','CERRADO'])->default('ABIERTO');
      $t->timestamp('opened_at')->nullable();
      $t->timestamp('closed_at')->nullable();
      $t->foreignId('closed_by')->nullable()->constrained('users');
      $t->text('notas')->nullable();
      $t->timestamps();
      $t->unique(['warehouse_id','user_id','fecha']);
    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cash_registers');
    }
};
