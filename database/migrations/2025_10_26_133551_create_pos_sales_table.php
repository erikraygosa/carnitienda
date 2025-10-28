<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::create('pos_sales', function (Blueprint $t) {
      $t->id();
      $t->foreignId('cash_register_id')->constrained('cash_registers')->cascadeOnDelete();
      $t->foreignId('warehouse_id')->constrained('warehouses');
      $t->foreignId('user_id')->constrained('users');
      $t->foreignId('client_id')->nullable()->constrained('clients');
      $t->dateTime('fecha');
      $t->decimal('subtotal',12,2)->default(0);
      $t->decimal('descuento',12,2)->default(0);
      $t->decimal('impuestos',12,2)->default(0);
      $t->decimal('total',12,2)->default(0);
      $t->enum('metodo_pago',['EFECTIVO','TARJETA','TRANSFERENCIA','MIXTO','OTRO'])->default('EFECTIVO');
      $t->decimal('efectivo',12,2)->default(0);
      $t->decimal('cambio',12,2)->default(0);
      $t->string('referencia')->nullable();
      $t->timestamps();
    });
  }
  public function down(): void { Schema::dropIfExists('pos_sales'); }
};
