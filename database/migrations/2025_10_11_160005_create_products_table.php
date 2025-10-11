<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('sku', 60)->unique()->nullable();
            $table->string('nombre', 180);
            $table->string('unidad', 20)->default('PZA');
            $table->boolean('es_compuesto')->default(false);
            $table->boolean('es_subproducto')->default(false);
            $table->decimal('precio_base', 14, 4)->default(0);
            $table->decimal('stock_min', 14, 3)->default(0);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
