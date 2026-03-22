<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stamp_counters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();

            $table->unsignedBigInteger('timbres_contratados')->default(0);
            $table->unsignedBigInteger('timbres_usados')->default(0);
            $table->unsignedBigInteger('timbres_cancelados')->default(0);

            // Período de vigencia del paquete
            $table->date('vigencia_inicio')->nullable();
            $table->date('vigencia_fin')->nullable();

            $table->boolean('activo')->default(true);
            $table->text('notas')->nullable();

            $table->timestamps();

            $table->index('company_id');
            $table->index('vigencia_fin');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stamp_counters');
    }
};