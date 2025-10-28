<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::create('inventory_movements', function (Blueprint $t) {
      $t->id();
      $t->foreignId('warehouse_id')->constrained('warehouses');
      $t->foreignId('product_id')->constrained('products');
      $t->enum('tipo',['IN','OUT']);
      $t->decimal('cantidad',12,3);
      $t->string('motivo')->nullable();
      $t->nullableMorphs('source');
      $t->foreignId('created_by')->nullable()->constrained('users');
      $t->timestamps();
    });
  }
  public function down(): void { Schema::dropIfExists('inventory_movements'); }
};
