<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            // Identificación
            $table->string('razon_social', 300);
            $table->string('nombre_comercial', 300)->nullable();
            $table->string('rfc', 13)->unique();

            // Tipo de persona
            $table->enum('tipo_persona', ['fisica', 'moral'])->default('moral');

            // Contacto general
            $table->string('telefono', 20)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('sitio_web', 200)->nullable();

            // Logo
            $table->string('logo_path', 500)->nullable();

            // Moneda y configuración regional
            $table->string('moneda', 3)->default('MXN');
            $table->string('pais', 3)->default('MEX');
            $table->string('timezone', 50)->default('America/Mexico_City');

            // Estado del tenant
            $table->boolean('activo')->default(true);
            $table->date('fecha_inicio')->nullable();
            $table->date('fecha_vencimiento')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('rfc');
            $table->index('activo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};