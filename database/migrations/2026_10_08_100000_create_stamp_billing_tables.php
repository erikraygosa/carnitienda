<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Configuración de cobro de timbres por empresa (solo la ve el super admin).
        Schema::create('stamp_billing_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->unique()->constrained('companies')->cascadeOnDelete();
            $table->unsignedInteger('cortesia_mensual')->default(100);   // timbres gratis que se renuevan cada periodo
            $table->decimal('precio_timbre', 10, 2)->default(0);          // precio por timbre excedente
            $table->decimal('mensualidad', 10, 2)->default(0);            // cuota fija por periodo
            $table->unsignedTinyInteger('dia_corte')->default(1);         // 1-28; 1 = mes calendario
            $table->date('inicio_cobro')->nullable();                     // el primer periodo es el que contiene esta fecha
            $table->boolean('activo')->default(true);
            $table->text('notas')->nullable();
            $table->timestamps();
        });

        // Cortes generados (foto del consumo de un periodo + ticket).
        Schema::create('stamp_cuts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('folio', 40)->unique();
            $table->date('periodo_inicio');
            $table->date('periodo_fin');
            $table->unsignedInteger('cortesia')->default(0);
            $table->unsignedInteger('timbres_usados')->default(0);
            $table->unsignedInteger('cancelados')->default(0);
            $table->unsignedInteger('excedente')->default(0);
            $table->decimal('precio_timbre', 10, 2)->default(0);
            $table->decimal('mensualidad', 10, 2)->default(0);
            $table->decimal('importe_excedente', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->string('estado', 12)->default('pendiente');           // pendiente | pagado
            $table->date('fecha_pago')->nullable();
            $table->text('notas')->nullable();
            $table->foreignId('generado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'periodo_inicio']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stamp_cuts');
        Schema::dropIfExists('stamp_billing_configs');
    }
};
