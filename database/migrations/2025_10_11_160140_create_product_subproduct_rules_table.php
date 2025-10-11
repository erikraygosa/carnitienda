<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_subproduct_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('main_product_id')->constrained('products')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('sub_product_id')->constrained('products')->cascadeOnUpdate()->restrictOnDelete();
            $table->decimal('ratio', 14, 6)->default(1);
            $table->decimal('merma_porcent', 8, 4)->default(0);
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->unique(['main_product_id','sub_product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_subproduct_rules');
    }
};
