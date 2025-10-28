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
         Schema::create('cash_movements', function (Blueprint $t) {
      $t->id();
      $t->foreignId('cash_register_id')->constrained('cash_registers')->cascadeOnDelete();
      $t->enum('tipo',['INGRESO','EGRESO']);
      $t->decimal('monto',12,2);
      $t->string('concepto')->nullable();
      $t->nullableMorphs('source');
      $t->foreignId('created_by')->nullable()->constrained('users');
      $t->timestamps();
    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cash_movements');
    }
};
