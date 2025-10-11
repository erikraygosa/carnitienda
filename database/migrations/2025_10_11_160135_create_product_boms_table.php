<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_boms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_product_id')->constrained('products')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('component_product_id')->constrained('products')->cascadeOnUpdate()->restrictOnDelete();
            $table->decimal('cantidad', 14, 3);
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->unique(['parent_product_id','component_product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_boms');
    }
};
