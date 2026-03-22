<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pac_configurations', function (Blueprint $table) {
            $table->id();

            // Identificador del driver: 'factuapi', 'generic', etc.
            $table->string('driver', 50)->unique();
            $table->string('nombre', 100); // Nombre display: "Factuapi", "PAC2"

            // Credenciales cifradas
            $table->text('api_key_encrypted')->nullable();
            $table->text('api_secret_encrypted')->nullable();
            $table->text('config_extra_encrypted')->nullable(); // JSON con configs adicionales

            // Ambiente
            $table->enum('ambiente', ['sandbox', 'produccion'])->default('sandbox');

            // Estado
            $table->boolean('activo')->default(false);    // PAC actualmente en uso
            $table->boolean('habilitado')->default(true); // PAC disponible para usar

            // Metadatos
            $table->string('version_cfdi', 5)->default('4.0');
            $table->text('notas')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pac_configurations');
    }
};