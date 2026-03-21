<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_fiscal_data', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();

            // Dirección fiscal (tal como aparece en Constancia SAT)
            $table->string('calle', 200)->nullable();
            $table->string('numero_exterior', 20)->nullable();
            $table->string('numero_interior', 20)->nullable();
            $table->string('colonia', 150)->nullable();
            $table->string('localidad', 150)->nullable();
            $table->string('municipio', 150)->nullable();
            $table->string('estado', 100)->nullable();
            $table->string('codigo_postal', 5);
            $table->string('pais', 3)->default('MEX');

            // Régimen fiscal SAT (catálogo c_RegimenFiscal)
            // Ejemplos: 601 = General de Ley SA, 612 = Personas Físicas con Actividades Empresariales
            $table->string('regimen_fiscal', 3);
            $table->string('regimen_fiscal_descripcion', 200)->nullable();

            // Actividad económica (opcional, para declaraciones)
            $table->string('actividad_economica', 300)->nullable();

            // Datos adicionales persona moral
            $table->string('acta_constitutiva', 100)->nullable();
            $table->date('fecha_constitucion')->nullable();
            $table->string('notario', 100)->nullable();

            // Datos adicionales persona física
            $table->string('curp', 18)->nullable();
            $table->date('fecha_nacimiento')->nullable();

            $table->timestamps();

            $table->unique('company_id'); // Solo un registro fiscal por empresa
            $table->index('codigo_postal');
            $table->index('regimen_fiscal');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_fiscal_data');
    }
};