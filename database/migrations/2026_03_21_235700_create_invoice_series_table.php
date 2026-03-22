<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_series', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();

            $table->string('serie', 10);                          // Ej: A, B, FAC
            $table->enum('tipo_comprobante', ['I', 'E', 'P', 'N'])->default('I');
            $table->unsignedBigInteger('folio_actual')->default(0);
            $table->unsignedBigInteger('folio_inicio')->default(1);

            $table->boolean('activa')->default(true);
            $table->boolean('es_default')->default(false);        // Serie por defecto para este tipo

            $table->string('descripcion', 200)->nullable();

            $table->timestamps();

            $table->unique(['company_id', 'serie', 'tipo_comprobante']);
            $table->index(['company_id', 'activa']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_series');
    }
};