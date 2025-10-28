<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::create('pos_sale_items', function (Blueprint $t) {
      $t->id();
      $t->foreignId('pos_sale_id')->constrained('pos_sales')->cascadeOnDelete();
      $t->foreignId('product_id')->constrained('products');
      $t->decimal('cantidad',12,3);
      $t->decimal('precio_unitario',12,2);
      $t->decimal('descuento',12,2)->default(0);
      $t->decimal('impuestos',12,2)->default(0);
      $t->decimal('importe',12,2);
      $t->timestamps();
    });
  }
  public function down(): void { Schema::dropIfExists('pos_sale_items'); }
};
